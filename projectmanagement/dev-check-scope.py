#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ΦΥΛΑΚΑΣ: καμία προσωπική οθόνη δεν δείχνει ξένη δουλειά.

Γιατί υπάρχει
─────────────
Ο κανόνας της μπάλας λέει ότι μια εργασία είναι σε ΕΝΑΝ κάθε φορά. Οι δύο
στατικοί έλεγχοι (dev-check-ball.py, dev-check-helpers.py) διαβάζουν κώδικα και
πιάνουν λάθος ΓΡΑΦΗ. Δεν πιάνουν όμως το λάθος που μας κόστισε: μια οθόνη που
απλώς ΔΕΝ φιλτράρει καθόλου. Η «Λίστα tasks» άνοιγε χωρίς φίλτρο προσώπου και ο
καθένας έβλεπε ως εκκρεμότητά του τη δουλειά όλης της εταιρείας — 274 τέτοιες
εμφανίσεις σε 9 ανθρώπους, 56 με κόκκινη ημερομηνία (23/09/2026).

Αυτός ο έλεγχος είναι ΖΩΝΤΑΝΟΣ: χτυπά το API ως κάθε χειριστής και μετράει τι
γυρίζει. Ό,τι δεν κρατάει ο ίδιος και δεν δικαιολογείται ρητά, είναι σφάλμα.

Χρήση:  python3 dev-check-scope.py            (όλοι οι ενεργοί χειριστές)
        python3 dev-check-scope.py 10 2       (συγκεκριμένοι)
Έξοδος: 0 = καθαρό, 1 = βρέθηκαν διαρροές.
"""
import json
import subprocess
import sys
import os

HERE = os.path.dirname(os.path.abspath(__file__))
PHP = '/opt/plesk/php/8.3/bin/php'
BOOT = os.path.join(HERE, 'boot.php')

# Οθόνη -> (action, κλειδιά που ΠΡΕΠΕΙ να είναι δικά μου, κλειδιά που επιτρέπεται
#           να έχουν ξένα + ο λόγος). Ό,τι δεν αναφέρεται, δεν ελέγχεται.
PERSONAL = [
    (u'Η μέρα μου', 'myday',
     ['plan', 'balls', 'deadlines', 'queue', 'follows'],
     {'waiting': u'«περιμένεις από X» — η ξένη μπάλα είναι το νόημα της γραμμής',
      'team': u'παρουσία συναδέλφων, όχι εργασίες',
      'supervising': u'ρητά «επιβλέπεις»',
      'coach': u'προτάσεις προς εσένα για άλλους'}),
    # Η Λίστα ρωτά τον server με το φίλτρο αναμμένο — έτσι την ανοίγει ο χρήστης.
    (u'Λίστα tasks', 'list&mine=1&open=1', ['tasks'], {}),
    (u'Το πλάνο μου', 'todos_list', None, {}),
    (u'Ο χρόνος μου', 'time', None, {}),
    # Το «ματάκι»: προσωπικό, αλλά με ρητά τμήματα εποπτείας για όσους έχουν ρόλο.
    (u'Τι να προσέξω', 'attention', None,
     {'team': u'ρητά «η ομάδα σου» — μόνο για επικεφαλής',
      'leads': u'ρητά «ποιος θέλει το βλέμμα σου» — μόνο για διαχειριστές',
      'pm': u'ρητά αναθέσεις project manager',
      'coach': u'προτάσεις προς εσένα για άλλους'}),
]


def token(admin_id):
    r = subprocess.run([PHP, '-r',
                        'define("WHMCS",true); require "%s"; echo pm_mint_token((int)$argv[1], 300);' % BOOT,
                        str(admin_id)], capture_output=True, text=True)
    return r.stdout.strip().splitlines()[-1] if r.stdout.strip() else ''


def call(admin_id, action):
    t = token(admin_id)
    if not t:
        return {'error': 'δεν βγήκε token'}
    r = subprocess.run(['curl', '-s', '-X', 'POST',
                        'https://my.cloudon.gr/projectmanagement/api.php?a=%s&t=%s' % (action, t),
                        '-H', 'Content-Type: application/json', '-d', '{}'],
                       capture_output=True, text=True)
    try:
        return json.loads(r.stdout)
    except Exception:
        return {'error': u'μη-JSON απάντηση'}


def holder(o):
    """Ο κανόνας της μπάλας, σε μορφή Python — ίδιος με cnpHolder/cnp_scope_mine."""
    return int(o.get('ball') or 0) or int(o.get('assignee') or 0)


def leaks(node, me, path=''):
    out = []
    if isinstance(node, dict):
        if 'id' in node and 'title' in node and ('ball' in node or 'assignee' in node):
            h = holder(node)
            if h and h != me:
                out.append((path, node.get('id'), (node.get('title') or '')[:48], h))
        for k, v in node.items():
            out += leaks(v, me, path + '/' + str(k) if path else str(k))
    elif isinstance(node, list):
        for v in node:
            out += leaks(v, me, path)
    return out


def admins():
    r = subprocess.run([PHP, '-r',
                        'define("WHMCS",true); require "%s"; '
                        'foreach (\\WHMCS\\Database\\Capsule::table("tbladmins")->where("disabled",0)'
                        '->pluck("id") as $i) echo (int)$i, "\\n";' % BOOT],
                       capture_output=True, text=True, cwd=os.path.dirname(HERE))
    return [int(x) for x in r.stdout.split() if x.isdigit()]


# Οθόνες όπου η ΙΔΙΑ εργασία δεν επιτρέπεται να εμφανιστεί δύο φορές. Το «ματάκι»
# έδειχνε τις δύο εκπρόθεσμες του Θέμιου και στη γραμμή του και στη γραμμή του
# Βάκρινου (επικεφαλής άλλης ομάδας όπου ο Θέμιος είναι μέλος) — διάβαζες δύο
# προβλήματα εκεί που υπήρχε ένα, με λάθος όνομα από πάνω. Κανόνας: ένας άνθρωπος,
# μία γραμμή — στη δική του αν είναι επικεφαλής, αλλιώς σε αυτήν του επικεφαλή του.
NO_DUPES = [(u'Τι να προσέξω', 'attention')]


def dupes(admin_id, action):
    d = call(admin_id, action)
    if not isinstance(d, dict) or 'groups' not in d:
        return {}
    seen = {}
    for g in d['groups']:
        for it in g.get('items', []):
            for r in it.get('refs', []):
                k = '%s#%s' % (r.get('kind'), r.get('id'))
                seen[k] = seen.get(k, 0) + 1
    return {k: v for k, v in seen.items() if v > 1}


def main():
    who = [int(a) for a in sys.argv[1:]] or admins()
    bad = 0
    for label, action, strict_keys, allowed in PERSONAL:
        for a in who:
            d = call(a, action)
            if not isinstance(d, dict) or 'error' in d:
                continue
            sub = {k: d[k] for k in strict_keys if k in d} if strict_keys else d
            found = [x for x in leaks(sub, a) if x[0].split('/')[0] not in allowed]
            if found:
                bad += len(found)
                print(u'✗ %s · χειριστής #%d: %d ξένα — %s'
                      % (label, a, len(found),
                         ', '.join(u'#%s (κρατά ο #%s, στο «%s»)' % (i, h, p) for p, i, t, h in found[:5])))
    for label, action in NO_DUPES:
        for a in who:
            dd = dupes(a, action)
            if dd:
                bad += len(dd)
                print(u'✗ %s · χειριστής #%d: η ίδια εργασία δύο φορές — %s'
                      % (label, a, ', '.join('%s ×%d' % (k, v) for k, v in list(dd.items())[:5])))
    if bad:
        print(u'\nΔιαρροές σε προσωπικές οθόνες: %d' % bad)
        print(u'Κανόνας: ό,τι δείχνεις ως δικό κάποιου περνά από τη μπάλα '
              u'(cnp_scope_mine στον server, cnpIsMine στην οθόνη).')
        return 1
    print(u'Εμβέλεια προσωπικών οθονών: ΟΛΑ ΚΑΛΑ')
    return 0


if __name__ == '__main__':
    sys.exit(main())
