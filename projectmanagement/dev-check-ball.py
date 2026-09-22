#!/usr/bin/env python3
"""
Ο ΚΑΝΟΝΑΣ ΤΗΣ ΜΠΑΛΑΣ, ως έλεγχος.

Μια εργασία ανήκει σε ΕΝΑΝ άνθρωπο κάθε φορά: σε αυτόν που κρατά την μπάλα.
Η ανάθεση μετράει ΜΟΝΟ όταν δεν την κρατά κανείς.

Στις 22/09/2026 έξι οθόνες φιλτράριζαν μόνο με `assignee` και έδειχναν εργασίες
που τις έτρεχε άλλος. Ο έλεγχος αυτός υπάρχει ώστε να μην ξαναγίνει: βρίσκει
κάθε ερώτημα που φιλτράρει εργασίες σε έναν άνθρωπο χωρίς να περάσει από τους
δύο μοναδικούς ορισμούς — cnp_scope_mine() και cnp_scope_team().

Τρέξε το πριν από κάθε commit που αγγίζει λίστες εργασιών.
"""
import io, re, os, sys, glob

ROOT = os.path.dirname(os.path.abspath(__file__))
FILES = [os.path.join(ROOT, 'api.php')]
LIB = os.path.normpath(os.path.join(ROOT, '..', 'modules', 'addons', 'cloudonprojects', 'lib'))
if os.path.isdir(LIB):
    FILES += [os.path.join(LIB, f) for f in sorted(os.listdir(LIB)) if f.endswith('.php')]

# φίλτρο σε ανάθεση με μεταβλητή χειριστή — εκεί κρύβεται το λάθος
SUSPECT = re.compile(r"""(?:where|whereIn)\(\s*'(?:t\.)?assignee'\s*,\s*\$(\w+)""")
# οι μεταβλητές που σημαίνουν «άνθρωπος»
PERSON = re.compile(r"^(adminId|aid|uid|whoP|memM|members|ids|me\w*)$")
# γραμμές που ΔΕΝ είναι φιλτράρισμα προβολής (γράψιμο, ειδοποίηση)
SAFE_CTX = ('Notify::', 'update(', 'insert(', '->value(', 'saveTask')
# ο κανόνας γραμμένος inline: ανάθεση ΚΑΙ «δεν την κρατά κανείς»
INLINE = re.compile(r"(whereNull\(\s*'(?:t\.)?action_user|action_user'\s*,\s*0)")
# ρητή εξαίρεση, όταν το φίλτρο ΔΕΝ σημαίνει «δικά μου»
OPTOUT = 'ball-rule: ok'

# όπου ο ορισμός ΓΡΑΦΕΤΑΙ — δεν ελέγχεται ο εαυτός του
DEFS = ('function cnp_scope_mine', 'function cnp_scope_team')

bad = []
for path in FILES:
    src = io.open(path, encoding='utf-8', errors='surrogateescape').read().split('\n')
    case = func = ''
    in_def = 0
    for i, line in enumerate(src, 1):
        m = re.match(r"\s*case '([a-z_0-9]+)'", line)
        if m:
            case = m.group(1)
        m2 = re.search(r"function ([a-zA-Z_][\w]*)\s*\(", line)
        if m2:
            func = m2.group(1)
            in_def = 8 if any(d in line for d in DEFS) else 0
        if in_def:
            in_def -= 1
            continue
        m3 = SUSPECT.search(line)
        if not m3 or not PERSON.match(m3.group(1)):
            continue
        if any(s in line for s in SAFE_CTX):
            continue
        # το παράθυρο γύρω από τη γραμμή: εκεί φαίνεται αν ο κανόνας τηρείται
        near = ' '.join(src[max(0, i - 4):i + 4])
        if 'cnp_scope_mine' in near or 'cnp_scope_team' in near:
            continue
        if INLINE.search(near):      # ο κανόνας γραμμένος με το χέρι — δεκτό
            continue
        if OPTOUT in near:           # ρητή, τεκμηριωμένη εξαίρεση
            continue
        if 'mod_cpm_tasks' not in near:   # άλλος πίνακας (leads, tickets…)
            continue
        bad.append((os.path.basename(path), i, case or func, line.strip()[:72]))

# ── ΚΑΙ Η ΟΘΟΝΗ ──────────────────────────────────────────────────────────────
# Ο server μπορεί να φιλτράρει σωστά και η οθόνη να δείχνει λάθος όνομα. Έτσι
# ξέφυγε η «Λίστα tasks»: το API έστελνε και μπάλα και ανάθεση, και η οθόνη
# ομαδοποιούσε με την ανάθεση.
JS = re.compile(r"""(?:adminName|adminIni)\(\s*t\.assignee""")
JS_SAFE = 'ball-rule: ok'

for path in sorted(glob.glob(os.path.join(ROOT, 'views*.js')) + [os.path.join(ROOT, 'app.js')]):
    src = io.open(path, encoding='utf-8', errors='surrogateescape').read().split('\n')
    for i, line in enumerate(src, 1):
        if not JS.search(line):
            continue
        near = ' '.join(src[max(0, i - 4):i + 3])
        if 'cnpHolder' in near or JS_SAFE in near:
            continue
        # το χειριστήριο ΑΝΑΘΕΣΗΣ δείχνει σωστά την ανάθεση — αυτό επεξεργάζεται
        if 'ανάθεση' in near and ('data-pasg' in near or 'κλικ για' in near):
            continue
        bad.append((os.path.basename(path), i, 'οθόνη', line.strip()[:72]))

if bad:
    print('Κανόνας της μπάλας: %d σημεία φιλτράρουν με ΑΝΑΘΕΣΗ αντί για μπάλα\n' % len(bad))
    for f, i, ctx, line in bad:
        print('  ✘ %-12s %-6s %-20s %s' % (f, i, ctx, line))
    print('\n  PHP: cnp_scope_mine($q, $id[, "t."]) ή cnp_scope_team($q, $ids[, "t."]).')
    print('  JS:  cnpHolder(t) αντί για t.assignee — ή σχόλιο «ball-rule: ok» αν είναι σκόπιμο.')
    sys.exit(1)
print('Κανόνας της μπάλας: ΟΛΑ ΚΑΛΑ')
