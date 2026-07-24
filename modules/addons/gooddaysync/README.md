# GoodDay Sync — native WHMCS ↔ GoodDay integration

Replaces the external Python `whmcs-goodday-sync` Docker middleware with an
event-driven WHMCS addon. WHMCS→GoodDay is instant (hooks); GoodDay→WHMCS and
WHMCS reply edit/delete detection run in a 1-minute cron reconciler.

**Golden rules**
- Only **one** system may write to the shared GoodDay workspace at a time.
- Ships in **DRY_RUN** (no writes). Go live only after the old Python container is **stopped**.
- **Full-ticket-delete is never implemented** — there is no `DeleteTicket` code path.

---

## Files
```
gooddaysync.php        config() / activate() / deactivate() / output() dashboard + settings loader
hooks.php              TicketOpen, TicketUserReply, TicketAdminReply, TicketStatusChange
crons/reconcile.php    1-minute reconciler (GoodDay→WHMCS + WHMCS edit/delete→GoodDay)
lib/Db.php             schema (mod_gooddaysync_tickets/_replies/_tombstones/_log) + state.json import
lib/Formatter.php      exact string formats / signatures / !public parsing / phone / html→text (app.py §9)
lib/GoodDayClient.php  official v2 API + private web API (behind flag); DRY_RUN gate on every write
lib/SyncState.php      sync engine using localAPI (never remote WHMCS API)
```

## DB tables (replace state.json)
- `mod_gooddaysync_tickets` — one row per mapped ticket (task_id, project_id, baseline, last_reply_id, status dedupe, backoff…).
- `mod_gooddaysync_replies` — rid ↔ gd_message_id ↔ signature ↔ **origin** (`whmcs`|`goodday_public`|`mirror`) — origin drives loop-prevention.
- `mod_gooddaysync_tombstones` — delete-recreate guard.
- `mod_gooddaysync_log` — sync log (shown in the dashboard).

---

## 1. Activation
1. Copy the `gooddaysync/` folder into `modules/addons/`.
2. **Setup → Addon Modules → GoodDay Sync → Activate.** Tables are created; `DRY_RUN` stays **on**.
3. Grant admin role access when prompted.

## 2. Configuration (Setup → Addon Modules → GoodDay Sync → Configure)
Non-secret defaults are pre-filled from the current `.env`:
- Admin username `support`; statuses `Open,Customer-Reply,Answered,In Progress`; status-on-public `Answered`.
- Default project `2wCY2n`; department map JSON; bot user `BtPcCg`; task type `hB9A7F`.
- Custom fields: ticket=`uojOkJ`, created=`SQiqbD`, subject=`ZvSBHp`, dept=`mkKgtU`, name=`VpSUEj`, email=`TCJX4l`, phone=`E4fpXg`; phone prefix `+357`.
- Company id `sNs5TG`; web origin; login email `pdelis@cloudon.gr`.

**Secrets** (enter here, stored encrypted): `GoodDay API Token`, and — only if the web API is enabled —
`Login Password` or a long-lived `Access Token`. Transfer them from the old `.env` securely (scp / copy-paste
into these fields), never through logs.

Then click **Test GoodDay Connection** — it does a read-only `GET /project/{id}/tasks` and reports success.

## 3. Cron (reconciler — every 1 minute)
WHMCS's own cron runs every 5 minutes, which is too slow for the reconciler. Add a dedicated system-cron line:
```
* * * * * /opt/plesk/php/8.3/bin/php -q /var/www/vhosts/cloudon.gr/my.cloudon.gr/modules/addons/gooddaysync/crons/reconcile.php >/dev/null 2>&1
```
It is safe to run while DRY_RUN is on (it only computes + logs). A file lock prevents overlapping runs.

## 4. Import the historical mappings (state.json)
The 24 existing ticket↔task mappings must be imported so existing tickets are **not** re-created as duplicates.
1. Copy the old `state.json` to `modules/addons/gooddaysync/state.json.import` on this server.
2. Dashboard → **Import state.json**. It reports imported tickets/replies/tombstones counts.
3. Take the state.json **at cutover time** (after stopping the container) so mappings are fresh.

**Fallback rebuild (if state.json is lost):** for each project (`GET /project/{id}/tasks`), task titles contain
`[WHMCS #<internal ticketid>]`, so ticket↔task can be rebuilt from the API using only the token. Reply↔message
maps are then re-learned gradually by the reconciler from the `Reply ID:` headers in GoodDay messages.

---

## 5. SAFE CUTOVER (critical — never two writers)
The old Python container is **actively syncing** the same GoodDay workspace. Follow in order:

1. **Shadow phase (DRY_RUN):** with `DRY_RUN=on`, enable hooks + cron on this server. Watch the dashboard log —
   it shows what *would* be sent. Compare against the Python's behaviour. No writes happen.
2. **Pick a low-traffic window.**
3. **Stop the old writer:** `docker compose stop whmcs-goodday-sync` on the old host. Now nobody writes to GoodDay.
4. **Fresh state import:** copy the just-frozen `state.json` → `state.json.import` → dashboard **Import**.
5. **Go live:** set `DRY_RUN=off` in the module config. Hooks + reconciler now write for real.
6. **Verify (acceptance tests, §6) + monitor 15–30 min** via the dashboard log.

## 6. Acceptance tests (run live, right after cutover)
- New ticket → GoodDay task appears with `[WHMCS #id]` title + custom fields.
- Admin reply / client reply → GoodDay comment; `mod_gooddaysync_replies` gets rid↔mid.
- Edit a reply in WHMCS → within ~1 min the GoodDay message updates.
- Delete a reply in WHMCS → GoodDay message deleted.
- Status change → GoodDay custom field updates (if status CF configured).
- GoodDay `!public …` message → new WHMCS reply as `support`; edit it → WHMCS reply updates; delete it → WHMCS reply removed.
- **Confirm full-ticket-delete stays OFF:** delete a GoodDay task → WHMCS ticket is untouched (only a `gd.taskMissing` warning is logged).

## 7. Rollback
If anything misbehaves:
1. Set `DRY_RUN=on` (or deactivate the module) — this server stops writing immediately.
2. `docker compose start whmcs-goodday-sync` on the old host — its `state.json` is still the source of truth.
3. Keep the Python container **stopped-not-deleted for ≥ 1 week** before decommissioning.

---

## Notes / limitations
- **No WHMCS hooks exist for reply edit/delete** → handled by the 1-minute reconciler (signature diff on mapped
  open tickets only — cheap).
- **Web API (edit/delete/attachments)** is behind `Enable Web API` (off by default). It uses a scraped JWT and is
  fragile; prefer a long-lived Access Token over the login password. Message *create/read* and custom fields use the
  stable official v2 API.
- Loop-prevention: replies whose `origin` is `goodday_public`/`mirror` are never pushed back; while the reconciler
  injects an inbound reply, a same-process guard suppresses the reply hook.
- All WHMCS access is via `localAPI()` — no remote WHMCS API round-trips.
