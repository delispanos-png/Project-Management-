#!/usr/bin/env python3
"""
Spamhaus DROP list -> Plesk permanent-ban jail (fail2ban), kept in sync.

Only manages the CIDRs it added itself (tracked in spamhaus-block.state) —
never touches bans added by hand (e.g. plesk bin ip_ban run directly by an
admin) or by anything else. Safe to re-run; idempotent; add-only (keeps
existing bans) on a failed or suspiciously-small fetch.

Added 2026-09-25 after a large distributed SMTP-AUTH brute force against
Postfix (~2000 source IPs in one day) on this box. See memory
'hetzner-multiproject' for the incident context.

Run manually:  python3 scripts/spamhaus-block-sync.py
Cron (daily):  see /etc/cron.d/spamhaus-block
"""
import ipaddress
import subprocess
import sys
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

DIR = Path(__file__).parent
STATE_FILE = DIR / "spamhaus-block.state"
LOG_FILE = DIR / "spamhaus-block.log"
SOURCE_URL = "https://www.spamhaus.org/drop/drop.txt"
JAIL = "plesk-permanent-ban"
BATCH = 250

# Never ban a range that would contain any of these — office/admin/own-infra
# IPs seen across the Hetzner firewalls + this server's own addresses.
PROTECT = [
    "194.154.34.84", "46.227.56.50", "135.181.19.235", "135.181.19.234",
    "193.92.171.138", "46.227.56.9", "157.180.26.98", "91.140.28.145",
    "46.246.163.47", "46.62.173.143", "188.4.234.5", "182.74.233.130",
    "91.98.174.23", "94.67.92.90", "49.13.241.237",
    "49.12.117.202", "167.233.181.136", "23.88.57.251",
]
PROTECT_IPS = [ipaddress.ip_address(ip) for ip in PROTECT]


def log(msg):
    line = f"{datetime.now(timezone.utc).isoformat()} {msg}"
    print(line)
    with open(LOG_FILE, "a") as f:
        f.write(line + "\n")


def fetch_cidrs():
    req = urllib.request.Request(SOURCE_URL, headers={"User-Agent": "cloudon-spamhaus-sync/1.0"})
    with urllib.request.urlopen(req, timeout=30) as r:
        body = r.read().decode("utf-8", "replace")
    cidrs = set()
    for line in body.splitlines():
        line = line.strip()
        if not line or line.startswith(";"):
            continue
        cidr = line.split(";")[0].strip()
        try:
            net = ipaddress.ip_network(cidr, strict=False)
        except ValueError:
            continue
        cidrs.add(str(net))
    return cidrs


def is_protected(cidr):
    net = ipaddress.ip_network(cidr, strict=False)
    return any(ip in net for ip in PROTECT_IPS)


def load_state():
    if not STATE_FILE.exists():
        return set()
    return {l.strip() for l in STATE_FILE.read_text().splitlines() if l.strip()}


def save_state(cidrs):
    STATE_FILE.write_text("\n".join(sorted(cidrs)) + "\n")


def run_ip_ban(action, cidrs):
    """action: 'ban' or 'unban'. Runs in batches, returns count applied."""
    cidrs = sorted(cidrs)
    applied = 0
    for i in range(0, len(cidrs), BATCH):
        chunk = cidrs[i:i + BATCH]
        arg = ";".join(f"{c},{JAIL}" for c in chunk)
        r = subprocess.run(
            ["plesk", "bin", "ip_ban", f"--{action}", arg],
            capture_output=True, text=True,
        )
        if r.returncode != 0 or "SUCCESS" not in (r.stdout or ""):
            log(f"ERROR {action} batch {i}-{i+len(chunk)}: rc={r.returncode} "
                f"stdout={r.stdout.strip()[:300]} stderr={r.stderr.strip()[:300]}")
        else:
            applied += len(chunk)
    return applied


def main():
    try:
        fresh = fetch_cidrs()
    except Exception as e:
        log(f"FETCH FAILED ({e}) — keeping existing bans, no changes made")
        return 1

    if len(fresh) < 500:
        # Sanity guard: Spamhaus DROP is normally 1000+ entries. A tiny
        # response means a broken/blocked fetch, not a real shrink of the
        # list — never let that unban everything we already applied.
        log(f"FETCH SUSPICIOUS (only {len(fresh)} entries) — keeping existing bans, no changes made")
        return 1

    protected_skipped = {c for c in fresh if is_protected(c)}
    fresh -= protected_skipped

    state = load_state()
    to_add = fresh - state
    to_remove = state - fresh

    added = run_ip_ban("ban", to_add) if to_add else 0
    removed = run_ip_ban("unban", to_remove) if to_remove else 0

    save_state((state - to_remove) | to_add)

    log(f"sync ok: source={len(fresh)+len(protected_skipped)} "
        f"protected_skipped={len(protected_skipped)} "
        f"added={added}/{len(to_add)} removed={removed}/{len(to_remove)} "
        f"total_managed={len(load_state())}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
