<?php
/**
 * ΦΥΛΑΚΑΣ ΔΕΔΟΜΕΝΩΝ — καταστάσεις που δεν πρέπει να υπάρχουν.
 *
 * Οι άλλοι τρεις έλεγχοι κοιτούν κώδικα και οθόνες. Αυτός κοιτά τη ΒΑΣΗ: εγγραφές
 * που έχουν γεννηθεί λάθος και δεν σπάνε τίποτα θορυβωδώς — απλώς λένε ψέματα.
 * Π.χ. εργασία με κατάσταση που δεν υπάρχει (βρέθηκαν 2, 23/09/2026): χωρίς
 * πινακίδα, έξω από κάθε φίλτρο, αόρατη στο board.
 *
 * Χρήση:  /opt/plesk/php/8.3/bin/php projectmanagement/dev-check-data.php
 *         (από το root του WHMCS)
 * Έξοδος: 0 = καθαρό, 1 = βρέθηκαν προβλήματα.
 */
define('WHMCS', true);
require __DIR__ . '/../init.php';
use WHMCS\Database\Capsule as C;

$bad = 0;
$say = function ($n, $what, $how = '') use (&$bad) {
    if (!$n) { return; }
    $bad += $n;
    printf("✗ %-58s %d%s\n", mb_substr($what, 0, 58), $n, $how ? "  → $how" : '');
};

$statuses = C::table('mod_cpm_statuses')->pluck('id')->all() ?: [0];
$done     = C::table('mod_cpm_statuses')->whereIn('phase', ['done', 'cancel'])->pluck('id')->all() ?: [0];
$isDone   = C::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
$projects = C::table('mod_cpm_projects')->pluck('id')->all() ?: [0];
$tasks    = C::table('mod_cpm_tasks')->pluck('id')->all() ?: [0];
$admins   = array_map('intval', C::table('tbladmins')->where('disabled', 0)->pluck('id')->all());

$say(C::table('mod_cpm_tasks')->whereNotIn('status_id', $statuses)->count(),
     'Εργασίες σε κατάσταση που δεν υπάρχει', 'Db::saveTask βάζει πλέον προεπιλογή');

$say(C::table('mod_cpm_tasks')->whereNotNull('project_id')->where('project_id', '>', 0)
       ->whereNotIn('project_id', $projects)->count(),
     'Εργασίες σε έργο που δεν υπάρχει');

$say(C::table('mod_cpm_timelogs')->whereNotIn('task_id', $tasks)->count(),
     'Καταγραφές χρόνου σε εργασία που δεν υπάρχει');

$say(C::table('mod_cpm_tasks')->whereIn('status_id', $isDone)->whereNull('completed_at')->count(),
     'Ολοκληρωμένες χωρίς ημερομηνία ολοκλήρωσης');

$say(C::table('mod_cpm_tasks')->whereNotIn('status_id', $done)->whereNotNull('completed_at')->count(),
     'Ανοιχτές που κρατούν παλιά σφραγίδα ολοκλήρωσης');

$say(C::table('mod_cpm_tasks')->whereNotNull('start_date')->whereNotNull('due_date')
       ->whereColumn('due_date', '<', 'start_date')->count(),
     'Εργασίες με λήξη ΠΡΙΝ την έναρξη');

$say(C::table('mod_cpm_events')->whereColumn('end_dt', '<', 'start_dt')->count(),
     'Συμβάντα με λήξη πριν την έναρξη');

/* Σύσκεψη πάνω από 8 ώρες: σχεδόν πάντα ξεχασμένη ώρα λήξης — και κρατά κλειστά
   τα τηλέφωνα όσων συμμετέχουν. */
$long = 0;
foreach (C::table('mod_cpm_events')->whereIn('kind', ['meeting', 'appointment'])->where('all_day', 0)->get() as $e) {
    if ((strtotime($e->end_dt) - strtotime($e->start_dt)) > 8 * 3600) { $long++; }
}
$say($long, 'Συσκέψεις πάνω από 8 ώρες (πιθανή λάθος ώρα λήξης)');

$say(C::table('mod_cpm_timelogs')->where('running', 1)
       ->where('started_at', '<', date('Y-m-d H:i:s', strtotime('-12 hours')))->count(),
     'Χρονόμετρα που τρέχουν πάνω από 12 ώρες');

foreach ([['mod_cpm_tasks', 'assignee'], ['mod_cpm_tasks', 'action_user'],
          ['mod_cpm_cv', 'assignee'], ['mod_cpm_leads', 'assignee']] as [$t, $c]) {
    if (!C::schema()->hasTable($t) || !C::schema()->hasColumn($t, $c)) { continue; }
    $say(C::table($t)->whereNotNull($c)->where($c, '<>', 0)->whereNotIn($c, $admins)->count(),
         "$t.$c σε χειριστή που δεν υπάρχει/απενεργοποιήθηκε");
}

echo $bad ? "\nΣΥΝΟΛΟ: $bad εγγραφές θέλουν χέρι.\n" : "Ακεραιότητα δεδομένων: ΟΛΑ ΚΑΛΑ\n";
exit($bad ? 1 : 0);
