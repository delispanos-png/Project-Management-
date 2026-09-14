<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Ορφανές εγγραφές: δείχνουν σε γονιό που δεν υπάρχει πια. Σιωπηλές — η οθόνη απλώς
   δείχνει κενό ή σκάει. Τα «έργα χωρίς προϊόν/τμήμα» είναι η ΜΕΤΑΒΑΤΙΚΗ περίοδος
   της ιεράρχησης (παλιά έργα που περιμένουν ανάθεση), όχι σφάλμα. */
require '/var/www/vhosts/cloudon.gr/my.cloudon.gr/init.php';
use WHMCS\Database\Capsule;

$T = [
  ['έργα χωρίς πελάτη', 'SELECT COUNT(*) n FROM mod_cpm_projects WHERE (clientid IS NULL OR clientid=0)', 0],
  ['έργα πελάτη χωρίς προϊόν', 'SELECT COUNT(*) n FROM mod_cpm_projects WHERE clientid>0 AND (product_id IS NULL OR product_id=0)', 1],
  ['έργα πελάτη χωρίς τμήμα', 'SELECT COUNT(*) n FROM mod_cpm_projects WHERE clientid>0 AND (deptid IS NULL OR deptid=0)', 1],
  ['έργα με πελάτη που δεν υπάρχει', 'SELECT COUNT(*) n FROM mod_cpm_projects p LEFT JOIN tblclients c ON c.id=p.clientid WHERE p.clientid>0 AND c.id IS NULL', 0],
  ['tasks σε έργο που δεν υπάρχει', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN mod_cpm_projects p ON p.id=t.project_id WHERE t.project_id>0 AND p.id IS NULL', 0],
  ['checklist σε task που δεν υπάρχει', 'SELECT COUNT(*) n FROM mod_cpm_checklist c LEFT JOIN mod_cpm_tasks t ON t.id=c.task_id WHERE t.id IS NULL', 0],
  ['χρόνος σε task που δεν υπάρχει', 'SELECT COUNT(*) n FROM mod_cpm_timelogs l LEFT JOIN mod_cpm_tasks t ON t.id=l.task_id WHERE l.task_id>0 AND t.id IS NULL', 0],
  ['tasks με ανάθεση σε ανύπαρκτο admin', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN tbladmins a ON a.id=t.assignee WHERE t.assignee>0 AND a.id IS NULL', 0],
  ['tasks με action_user ανύπαρκτο', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN tbladmins a ON a.id=t.action_user WHERE t.action_user>0 AND a.id IS NULL', 0],
  ['tasks με άγνωστο ticket', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN tbltickets k ON k.id=t.ticketid WHERE t.ticketid>0 AND k.id IS NULL', 0],
  ['tasks με άγνωστο status', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN mod_cpm_statuses s ON s.id=t.status_id WHERE t.status_id>0 AND s.id IS NULL', 0],
  ['tasks με άγνωστο product_id', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN mod_cpm_products p ON p.id=t.product_id WHERE t.product_id>0 AND p.id IS NULL', 0],
  ['tasks με άγνωστο dept_id', 'SELECT COUNT(*) n FROM mod_cpm_tasks t LEFT JOIN tblticketdepartments d ON d.id=t.dept_id WHERE t.dept_id>0 AND d.id IS NULL', 0],
  ['έργα με άγνωστο deptid', 'SELECT COUNT(*) n FROM mod_cpm_projects p LEFT JOIN tblticketdepartments d ON d.id=p.deptid WHERE p.deptid>0 AND d.id IS NULL', 0],
  ['προϊόντα με άγνωστο dept_id', 'SELECT COUNT(*) n FROM mod_cpm_products p LEFT JOIN tblticketdepartments d ON d.id=p.dept_id WHERE p.dept_id>0 AND d.id IS NULL', 0],
  ['προϊόντα πελάτη με άγνωστο προϊόν', 'SELECT COUNT(*) n FROM mod_cpm_client_products cp LEFT JOIN mod_cpm_products p ON p.id=cp.product_id WHERE p.id IS NULL', 0],
  ['προϊόντα πελάτη με άγνωστο πελάτη', 'SELECT COUNT(*) n FROM mod_cpm_client_products cp LEFT JOIN tblclients c ON c.id=cp.clientid WHERE c.id IS NULL', 0],
  ['προσφορές με άγνωστο πελάτη', 'SELECT COUNT(*) n FROM mod_cpm_offers o LEFT JOIN tblclients c ON c.id=o.clientid WHERE o.clientid>0 AND c.id IS NULL', 0],
  ['ticket_class με άγνωστο ticket', 'SELECT COUNT(*) n FROM mod_cpm_ticket_class k LEFT JOIN tbltickets t ON t.id=k.ticketid WHERE t.id IS NULL', 0],
  ['εξαρτήσεις σε ανύπαρκτο task', 'SELECT COUNT(*) n FROM mod_cpm_deps x LEFT JOIN mod_cpm_tasks t ON t.id=x.task_id WHERE t.id IS NULL', 0],
  ['εξαρτήσεις προς ανύπαρκτο task', 'SELECT COUNT(*) n FROM mod_cpm_deps x LEFT JOIN mod_cpm_tasks t ON t.id=x.depends_on WHERE t.id IS NULL', 0],
];
$bad = 0;
foreach ($T as $row) {
    list($lab, $sql, $transitional) = $row;
    try { $n = (int) Capsule::select($sql)[0]->n; } catch (\Throwable $e) {
        printf("  ? %-38s %s\n", $lab, substr($e->getMessage(), 0, 60)); $bad++; continue;
    }
    if ($n && $transitional) { printf("  ℹ %-38s %d (μεταβατική περίοδος ιεράρχησης)\n", $lab, $n); continue; }
    if ($n) { $bad++; }
    printf("  %s %-38s %d\n", $n ? '⚠' : '✓', $lab, $n);
}
echo $bad ? "" : "  ✓ καμία ορφανή εγγραφή\n";
