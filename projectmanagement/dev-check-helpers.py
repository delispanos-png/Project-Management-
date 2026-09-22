#!/usr/bin/env python3
"""Ελέγχει ότι κάθε view module ΕΙΣΑΓΕΙ ό,τι κοινό helper ΧΡΗΣΙΜΟΠΟΙΕΙ.

ΓΙΑΤΙ ΥΠΑΡΧΕΙ: τα view modules μοιράζονται helpers μόνο μέσω `window.CNP`, με
destructuring στην κορυφή. Αν γράψεις `fChip(...)` χωρίς να το έχεις βάλει εκεί,
το `node --check` περνάει καθαρό — το λάθος σκάει ΜΟΝΟ όταν ανοίξει η οθόνη,
ως «fChip is not a function». Έχει συμβεί δύο φορές.

    python3 dev-check-helpers.py        → 0 αν όλα καλά, 1 αν λείπει κάτι
"""
import io, re, sys, glob

def exported_helpers():
    """
    Τα ονόματα ΑΠΟ ΤΗΝ ΙΔΙΑ ΤΗΝ ΕΞΑΓΩΓΗ του app.js.

    Ήταν σκληρή λίστα είκοσι ονομάτων — και γι' αυτό δεν έπιασε το `cnpHolder`
    που χρησιμοποιήθηκε οκτώ φορές χωρίς import. Ένας έλεγχος που μένει πίσω
    από τον κώδικα είναι χειρότερος από κανέναν: δίνει σιγουριά που δεν ισχύει.
    """
    src = io.open('app.js', encoding='utf-8', errors='surrogateescape').read()
    m = re.search(r'window\.CNP\s*=\s*\{(.*?)\};', src, re.S)
    if not m:
        return []
    out = []
    for part in m.group(1).split(','):
        part = part.strip()
        if not part:
            continue
        # «palette: cnpPalette» → ο καταναλωτής γράφει «palette»
        name = part.split(':')[0].strip()
        if re.match(r'^[A-Za-z_$][\w$]*$', name):
            out.append(name)
    return out


HELPERS = exported_helpers()
if not HELPERS:
    print('ΠΡΟΣΟΧΗ: δεν βρέθηκε το window.CNP στο app.js — ο έλεγχος δεν έτρεξε')
    raise SystemExit(1)
bad = 0
for f in sorted(glob.glob('views*.js') + ['help.js']):
    s = io.open(f, encoding='utf-8', errors='surrogateescape').read()
    # ΟΛΕΣ οι αποδομήσεις του αρχείου, όχι μόνο η πρώτη: πολλά view modules
    # παίρνουν helpers και μέσα σε συνάρτηση. Κοιτώντας μόνο την κορυφή, ο
    # έλεγχος έβγαζε εννιά ψευδείς συναγερμούς — και έναν ψευδή συναγερμό τον
    # πίστεψα κιόλας, «διορθώνοντας» κώδικα που δούλευε.
    ms = list(re.finditer(r'const \{([^}]*)\} = window\.CNP;', s))
    if not ms:
        continue
    have = set()
    for m in ms:
        have |= {n.strip() for n in m.group(1).replace('\n', ' ').split(',')}
    # και η άλλη νόμιμη μορφή: const suStat = window.CNP.suStat;
    have |= set(re.findall(r'const\s+([\w$]+)\s*=\s*window\.CNP\.', s))
    body = s[ms[0].end():]
    for h in HELPERS:
        if h in have:
            continue
        # κλήση συνάρτησης, όχι απλή αναφορά μέσα σε συμβολοσειρά
        if re.search(r'(?<![\w.$])' + h + r'\s*\(', body):
            print(f'  ✘ {f}: χρησιμοποιεί «{h}» χωρίς να το εισάγει από το window.CNP')
            bad += 1
print('Έλεγχος helpers: ' + ('ΟΛΑ ΚΑΛΑ' if not bad else f'{bad} προβλήματα'))
sys.exit(1 if bad else 0)
