#!/bin/bash
# Γενικός έλεγχος: ψάχνει τις ΚΑΤΗΓΟΡΙΕΣ λαθών που εμφανίστηκαν στην πράξη.
DEV="$(cd "$(dirname "$0")" && pwd)"   # πριν το cd, αλλιώς χάνεται η διαδρομή
cd /var/www/vhosts/cloudon.gr/my.cloudon.gr || exit 1
PM=projectmanagement
MOD=modules/addons/cloudonprojects
PHP=/opt/plesk/php/8.3/bin/php
NODE=/opt/plesk/node/22/bin/node

echo "══ 1. Σύνταξη PHP (δικά μας) ══"
bad=0
for f in $(find $MOD $PM -name '*.php' 2>/dev/null); do
  $PHP -l "$f" >/dev/null 2>&1 || { echo "  ✗ $f"; bad=1; }
done
[ $bad -eq 0 ] && echo "  ✓ όλα καθαρά ($(find $MOD $PM -name '*.php' | wc -l) αρχεία)"

echo
echo "══ 2. Σύνταξη JavaScript ══"
bad=0
for f in $PM/*.js $MOD/lib/*.js; do
  [ -f "$f" ] || continue
  $NODE --check "$f" >/dev/null 2>&1 || { echo "  ✗ $f"; bad=1; }
done
[ $bad -eq 0 ] && echo "  ✓ όλα καθαρά"

echo
echo "══ 3. Νεκρές αναφορές: window.CNP.X που δεν ορίζεται πουθενά ══"
# ΠΡΟΣΟΧΗ: το views3.js περιέχει σκόπιμα NUL (διαχωριστικό μπλοκ κώδικα) — χωρίς -a
# το grep το θεωρεί binary και ΚΡΥΒΕΙ τους ορισμούς, βγάζοντας ψευδείς «νεκρές» αναφορές.
USED=$(grep -ahoE 'window\.CNP\.[a-zA-Z_][a-zA-Z0-9_]*' $PM/*.js | sed 's/.*CNP\.//' | sort -u)
EXPORTED=$($PHP $DEV/cnp_exports.php)
miss=0
for u in $USED; do
  echo "$EXPORTED" | grep -qx "$u" || { echo "  ✗ window.CNP.$u — χρησιμοποιείται αλλά δεν ορίζεται"; miss=1; }
done
[ $miss -eq 0 ] && echo "  ✓ καμία νεκρή αναφορά ($(echo "$USED" | wc -l) σε χρήση)"

echo
echo "══ 3β. Ονόματα που αποδομούνται από το window.CNP αλλά δεν εξάγονται ══"
$PHP $DEV/cnp_destructure.php

echo
echo "══ 3γ. Συναρτήσεις PHP που καλούνται αλλά δεν ορίζονται ══"
$PHP $DEV/php_undef.php

echo
echo "══ 3δ. Στατικές κλήσεις δικών μας κλάσεων (Db::, Pharmacy::…) ══"
$PHP $DEV/php_static.php

echo
echo "══ 4. Σιωπηλά κοψίματα κειμένου χρήστη (mb_substr σε πεδία περιεχομένου) ══"
grep -nE "mb_substr\(\\\$(title|body|descr|note|text|value|name)[^,]*, *0, *([0-9]{1,3})\)" \
  $MOD/lib/*.php $PM/api.php 2>/dev/null | head -12 || echo "  ✓ κανένα ύποπτο"

echo
echo "══ 5. Το API: κλήσεις, endpoints και πύλη δικαιωμάτων ══"
$PHP $DEV/api_calls.php
$PHP $DEV/api_gates.php

echo "══ 6. Πίνακες/στήλες που ζητά ο κώδικας αλλά λείπουν ══"
$PHP -r '
require "init.php";
use WHMCS\Database\Capsule;
$need = [
  "mod_cpm_tasks" => ["product_id","dept_id","module_id","billing_ok"],
  "mod_cpm_projects" => ["product_id","deptid","clientid","offer_id"],
  "mod_cpm_offers" => ["product_id","kind","config"],
  "mod_cpm_checklist" => ["title"],
  "mod_cpm_products" => ["name","dept_id"],
  "mod_cpm_client_products" => ["clientid","product_id"],
  "mod_cpm_client_contacts" => ["clientid","kind","value"],
  "mod_cpm_client_branches" => ["clientid","name"],
  "mod_cpm_ticket_class" => ["product_id","area_id"],
];
$bad = 0;
foreach ($need as $t => $cols) {
  if (!Capsule::schema()->hasTable($t)) { echo "  ✗ λείπει πίνακας $t\n"; $bad++; continue; }
  foreach ($cols as $c) {
    if (!Capsule::schema()->hasColumn($t, $c)) { echo "  ✗ $t.$c λείπει\n"; $bad++; }
  }
}
if (!$bad) { echo "  ✓ όλα τα πεδία που χρειάζεται ο κώδικας υπάρχουν\n"; }
' 2>&1 | grep -v "^$"

echo
echo "══ 6β. Ορφανές εγγραφές / ακεραιότητα ιεράρχησης ══"
$PHP $DEV/integrity.php 2>&1 | grep -E "^  [✓⚠?ℹ]"

echo
echo "══ 7. Σφάλματα PHP σήμερα στο my.cloudon.gr ══"
L=/var/www/vhosts/system/my.cloudon.gr/logs/error_log
if [ -f "$L" ]; then
  n=$(grep "$(date '+%b %d')" "$L" 2>/dev/null | grep -icE "fatal|uncaught|parse error" || true)
  echo "  fatal/uncaught σήμερα: ${n:-0}"
  grep "$(date '+%b %d')" "$L" 2>/dev/null | grep -iE "fatal|uncaught|parse error" | tail -3 | cut -c1-200
else
  echo "  (δεν βρέθηκε log)"
fi

echo
echo "══ 8. Τα cron μπορούν να γράψουν το log τους; ══"
# Αν η ανακατεύθυνση «>> log» αποτύχει, το cron ΔΕΝ εκτελεί καθόλου την εντολή.
# Έτσι έμειναν νεκρά 7 εβδομάδες τα pulse/daily (φάκελος crons/ ανήκει στον root).
CU=$(awk '!/^#/ && NF {print $6; exit}' /etc/cron.d/cloudonprojects 2>/dev/null)
bad=0
for L in $(grep -oE '>> [^ ]+\.log' /etc/cron.d/cloudonprojects 2>/dev/null | awk '{print $2}' | sort -u); do
  su -s /bin/bash "$CU" -c "touch '$L'" 2>/dev/null || { echo "  ✗ ο χρήστης $CU δεν μπορεί να γράψει το $L — το cron ΔΕΝ θα τρέξει"; bad=1; }
done
[ $bad -eq 0 ] && echo "  ✓ κάθε cron log είναι εγγράψιμο από τον $CU"
