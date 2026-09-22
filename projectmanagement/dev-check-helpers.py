#!/usr/bin/env python3
"""Ελέγχει ότι κάθε view module ΕΙΣΑΓΕΙ ό,τι κοινό helper ΧΡΗΣΙΜΟΠΟΙΕΙ.

ΓΙΑΤΙ ΥΠΑΡΧΕΙ: τα view modules μοιράζονται helpers μόνο μέσω `window.CNP`, με
destructuring στην κορυφή. Αν γράψεις `fChip(...)` χωρίς να το έχεις βάλει εκεί,
το `node --check` περνάει καθαρό — το λάθος σκάει ΜΟΝΟ όταν ανοίξει η οθόνη,
ως «fChip is not a function». Έχει συμβεί δύο φορές.

    python3 dev-check-helpers.py        → 0 αν όλα καλά, 1 αν λείπει κάτι
"""
import io, re, sys, glob

HELPERS = ['fChip', 'fSel', 'fBool', 'fOne', 'fAdd', 'fWire', 'cnpSearch', 'cnpSkel',
           'cnpConfirm', 'cnpDialog', 'cnpDenied', 'cnpCan', 'setTop', 'toast', 'esc',
           'api', 'miniMenu', 'stPill', 'openTask', 'go']
bad = 0
for f in sorted(glob.glob('views*.js') + ['help.js']):
    s = io.open(f, encoding='utf-8', errors='surrogateescape').read()
    m = re.search(r'const \{([^}]*)\} = window\.CNP;', s)
    if not m:
        continue
    have = {n.strip() for n in m.group(1).replace('\n', ' ').split(',')}
    body = s[m.end():]
    for h in HELPERS:
        if h in have:
            continue
        # κλήση συνάρτησης, όχι απλή αναφορά μέσα σε συμβολοσειρά
        if re.search(r'(?<![\w.$])' + h + r'\s*\(', body):
            print(f'  ✘ {f}: χρησιμοποιεί «{h}» χωρίς να το εισάγει από το window.CNP')
            bad += 1
print('Έλεγχος helpers: ' + ('ΟΛΑ ΚΑΛΑ' if not bad else f'{bad} προβλήματα'))
sys.exit(1 if bad else 0)
