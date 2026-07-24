<?php
/**
 * CloudOn Project Manager — data layer.
 *
 * Tables:
 *   mod_cpm_projects  — projects (linked to WHMCS client and/or support department)
 *   mod_cpm_statuses  — task statuses = Kanban columns (manageable, ordered)
 *   mod_cpm_tasks     — tasks (optionally linked to a WHMCS ticket)
 *   mod_cpm_comments  — discussion / analysis on a task
 *   mod_cpm_activity  — audit stream (who did what, when) per task
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Db
{
    /* ------------------------------------------------------------------ */
    /* Schema (1.1)                                                       */
    /* ------------------------------------------------------------------ */

    public static function install()
    {
        $s = Capsule::schema();

        if (!$s->hasTable('mod_cpm_projects')) {
            $s->create('mod_cpm_projects', function ($t) {
                $t->increments('id');
                $t->string('kind', 10)->default('dept');       // dept=λειτουργικό | client=έργο πελάτη
                $t->decimal('budget', 10, 2)->nullable();      // προϋπολογισμός έργου €
                $t->decimal('est_hours', 7, 1)->nullable();    // εκτίμηση ωρών
                $t->date('start_date')->nullable();
                $t->date('due_date')->nullable();
                $t->integer('offer_id')->unsigned()->nullable(); // γέννηση από προσφορά
                $t->string('name', 120);
                $t->integer('clientid')->unsigned()->nullable()->index(); // WHMCS client
                $t->integer('deptid')->unsigned()->nullable()->index();   // support department
                $t->string('color', 7)->default('#0097e4');
                $t->text('descr')->nullable();
                $t->string('status', 12)->default('active');              // active | archived
                $t->tinyInteger('client_visible')->default(1);            // ορατό στο client portal (5.2)
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        if (!$s->hasColumn('mod_cpm_projects', 'client_visible')) {
            $s->table('mod_cpm_projects', function ($t) {
                $t->tinyInteger('client_visible')->default(1);
            });
        }
        if (!$s->hasColumn('mod_cpm_projects', 'parent_id')) {
            $s->table('mod_cpm_projects', function ($t) {
                $t->integer('parent_id')->unsigned()->nullable()->index(); // ιεραρχία (υπο-project)
                $t->string('pstatus', 20)->nullable();                     // Νέο|Σε εξέλιξη|Σε αναμονή|Ολοκληρωμένο
                $t->string('health', 10)->nullable();                      // green|yellow|red
            });
        }

        if (!$s->hasTable('mod_cpm_statuses')) {
            $s->create('mod_cpm_statuses', function ($t) {
                $t->increments('id');
                $t->string('title', 60);
                $t->string('color', 7)->default('#8291a9');
                $t->integer('sort')->default(0);
                $t->tinyInteger('is_done')->default(0);
            });
        }
        // seed default board columns once
        if (Capsule::table('mod_cpm_statuses')->count() === 0) {
            Capsule::table('mod_cpm_statuses')->insert([
                ['title' => 'Backlog',       'color' => '#8291a9', 'sort' => 1, 'is_done' => 0],
                ['title' => 'Σε εξέλιξη',    'color' => '#0097e4', 'sort' => 2, 'is_done' => 0],
                ['title' => 'Έλεγχος',       'color' => '#e0a020', 'sort' => 3, 'is_done' => 0],
                ['title' => 'Ολοκληρώθηκε',  'color' => '#1f9d57', 'sort' => 4, 'is_done' => 1],
            ]);
        }

        if (!$s->hasTable('mod_cpm_tasks')) {
            $s->create('mod_cpm_tasks', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->string('title', 200);
                $t->text('descr')->nullable();
                $t->integer('status_id')->unsigned()->index();
                $t->tinyInteger('priority')->default(0);                 // 0 normal, 1 high, 2 critical
                $t->integer('assignee')->unsigned()->nullable()->index(); // tbladmins.id
                $t->integer('ticketid')->unsigned()->nullable()->index(); // tbltickets.id
                $t->date('due_date')->nullable();
                $t->integer('sort')->default(0);
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->timestamp('completed_at')->nullable();
            });
        }

        if (!$s->hasColumn('mod_cpm_tasks', 'action_user')) {
            $s->table('mod_cpm_tasks', function ($t) {
                $t->integer('action_user')->unsigned()->nullable()->index(); // «η μπάλα»: ποιος πρέπει να κινηθεί
                $t->date('schedule_date')->nullable();                       // προγραμματισμός ημέρας (≠ deadline)
                $t->integer('type_id')->unsigned()->nullable();              // τύπος task
                $t->integer('estimate_minutes')->unsigned()->nullable();     // εκτίμηση χρόνου
            });
        }

        if (!$s->hasColumn('mod_cpm_tasks', 'start_date')) {
            $s->table('mod_cpm_tasks', function ($t) {
                $t->date('start_date')->nullable(); // Gantt: έναρξη εργασίας (μπάρα start→due)
            });
        }

        if (!$s->hasTable('mod_cpm_task_types')) {
            $s->create('mod_cpm_task_types', function ($t) {
                $t->increments('id');
                $t->string('name', 60);
                $t->string('icon', 40)->default('fa-tasks');
                $t->string('color', 7)->default('#8291a9');
                $t->tinyInteger('req_assignee')->default(0);
                $t->tinyInteger('req_due')->default(0);
                $t->tinyInteger('req_estimate')->default(0);
                $t->integer('sort')->default(0);
            });
        }
        if (Capsule::table('mod_cpm_task_types')->count() === 0) {
            Capsule::table('mod_cpm_task_types')->insert([
                ['name' => 'Εργασία',           'icon' => 'fa-tasks',        'color' => '#8291a9', 'req_assignee' => 0, 'req_due' => 0, 'req_estimate' => 0, 'sort' => 1],
                ['name' => 'Bug',               'icon' => 'fa-bug',          'color' => '#d92d3a', 'req_assignee' => 0, 'req_due' => 0, 'req_estimate' => 0, 'sort' => 2],
                ['name' => 'Επίσκεψη πελάτη',   'icon' => 'fa-briefcase',    'color' => '#0097e4', 'req_assignee' => 1, 'req_due' => 1, 'req_estimate' => 1, 'sort' => 3],
                ['name' => 'Εργασία onsite',    'icon' => 'fa-truck',        'color' => '#e0a020', 'req_assignee' => 1, 'req_due' => 1, 'req_estimate' => 0, 'sort' => 4],
                ['name' => 'Νέα δυνατότητα',    'icon' => 'fa-bolt',         'color' => '#7b5cd6', 'req_assignee' => 0, 'req_due' => 0, 'req_estimate' => 0, 'sort' => 5],
                ['name' => 'Ιδέα',              'icon' => 'fa-lightbulb',    'color' => '#1f9d57', 'req_assignee' => 0, 'req_due' => 0, 'req_estimate' => 0, 'sort' => 6],
                ['name' => 'Support ticket',    'icon' => 'fa-life-ring',    'color' => '#16a2a2', 'req_assignee' => 0, 'req_due' => 0, 'req_estimate' => 0, 'sort' => 7],
            ]);
        }

        if (!$s->hasTable('mod_cpm_comments')) {
            $s->create('mod_cpm_comments', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->integer('admin_id')->unsigned()->nullable();
                $t->text('comment');
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasColumn('mod_cpm_comments', 'to_admin')) {
            $s->table('mod_cpm_comments', function ($t) {
                $t->integer('to_admin')->nullable(); // «προς»: null=όλοι, -1=διαχειριστές, id=άτομο (SIGNED)
            });
        }

        if (!$s->hasTable('mod_cpm_watchers')) {
            $s->create('mod_cpm_watchers', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->integer('admin_id')->unsigned()->index();
                $t->unique(['task_id', 'admin_id']);
            });
        }

        if (!$s->hasTable('mod_cpm_reminders')) {
            $s->create('mod_cpm_reminders', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->nullable()->index();
                $t->integer('admin_id')->unsigned()->index();
                $t->dateTime('remind_at');
                $t->string('note', 200)->nullable();
                $t->tinyInteger('sent')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_expenses')) {
            $s->create('mod_cpm_expenses', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->string('descr', 200);
                $t->decimal('amount', 12, 2);
                $t->date('spent_at');
                $t->integer('admin_id')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_snapshots')) {
            $s->create('mod_cpm_snapshots', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->date('snap_date');
                $t->integer('open_n')->default(0);
                $t->integer('done_n')->default(0);
                $t->unique(['project_id', 'snap_date']);
            });
        }

        if (!$s->hasTable('mod_cpm_timelogs')) {
            $s->create('mod_cpm_timelogs', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->integer('admin_id')->unsigned()->index();
                $t->integer('minutes')->default(0);          // πραγματικός χρόνος εργασίας
                $t->integer('charged_minutes')->default(0);  // τι αφαιρέθηκε από προαγορά (0 = μη χρεώσιμο)
                $t->tinyInteger('billable')->default(0);
                $t->integer('sc_userid')->unsigned()->nullable();     // πελάτης που χρεώθηκε (supportcontracts)
                $t->integer('sc_worklog_id')->unsigned()->nullable(); // αντίστοιχη εγγραφή στο SC worklog (για αναίρεση)
                $t->string('note', 255)->nullable();
                $t->tinyInteger('running')->default(0);
                $t->dateTime('started_at')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_checklist')) {
            $s->create('mod_cpm_checklist', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->string('title', 200);
                $t->tinyInteger('done')->default(0);
                $t->integer('sort')->default(0);
            });
        }

        if (!$s->hasTable('mod_cpm_recurring')) {
            $s->create('mod_cpm_recurring', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->string('title', 200);
                $t->text('descr')->nullable();
                $t->tinyInteger('priority')->default(0);
                $t->integer('assignee')->unsigned()->nullable();
                $t->string('freq', 10)->default('monthly');   // daily | weekly | monthly | yearly
                $t->integer('every')->default(1);             // κάθε N περιόδους
                $t->date('next_run');
                $t->integer('due_days')->default(0);          // due_date = δημιουργία + N ημέρες (0 = χωρίς)
                $t->tinyInteger('active')->default(1);
                $t->date('last_run')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_fields')) {
            $s->create('mod_cpm_fields', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->string('label', 60);
                $t->string('type', 10)->default('text');      // text | select | date
                $t->text('options')->nullable();              // για select: μία επιλογή ανά γραμμή
                $t->integer('sort')->default(0);
            });
        }

        if (!$s->hasTable('mod_cpm_field_values')) {
            $s->create('mod_cpm_field_values', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->integer('field_id')->unsigned()->index();
                $t->text('value')->nullable();
                $t->unique(['task_id', 'field_id']);
            });
        }

        if (!$s->hasTable('mod_cpm_offers')) {
            $s->create('mod_cpm_offers', function ($t) {
                $t->increments('id');
                $t->string('title', 200);
                $t->integer('clientid')->unsigned()->nullable()->index();
                $t->integer('quoteid')->unsigned()->nullable()->index();  // tblquotes.id
                $t->decimal('amount', 12, 2)->nullable();                  // χειροκίνητο· αν υπάρχει quote υπερισχύει το total του
                $t->string('stage', 12)->default('new');                   // new|draft|sent|accepted|lost
                $t->integer('assignee')->unsigned()->nullable();
                $t->date('expected_close')->nullable();
                $t->text('descr')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->timestamp('closed_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_leads')) {
            $s->create('mod_cpm_leads', function ($t) {
                $t->increments('id');
                $t->string('company', 120)->nullable();
                $t->string('contact', 120)->nullable();       // πρόσωπο επικοινωνίας
                $t->string('email', 120)->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('source', 60)->nullable();          // πηγή (σύσταση, site, κλήση…)
                $t->string('stage', 12)->default('target');    // target|contacted|interested|offer|won|lost
                $t->decimal('value', 10, 2)->nullable();       // εκτιμώμενη αξία deal (pipeline)
                $t->string('lost_reason', 190)->nullable();    // αιτία απώλειας (στάδιο lost)
                $t->integer('assignee')->unsigned()->nullable();
                $t->integer('clientid')->unsigned()->nullable(); // WHMCS client μετά τη μετατροπή
                $t->date('next_action')->nullable();           // επόμενο follow-up
                $t->string('next_note', 200)->nullable();
                $t->text('descr')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->timestamp('closed_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_teams')) {
            $s->create('mod_cpm_teams', function ($t) {
                $t->increments('id');
                $t->string('name', 80);
                $t->string('color', 7)->default('#0097e4');
                $t->text('descr')->nullable();
                $t->integer('sort')->default(0);
            });
        }
        if (!$s->hasTable('mod_cpm_team_members')) {
            $s->create('mod_cpm_team_members', function ($t) {
                $t->increments('id');
                $t->integer('team_id')->unsigned()->index();
                $t->integer('admin_id')->unsigned()->index();
                $t->string('role_title', 60)->nullable();  // π.χ. «Τεχνικός», «Υπεύθυνος πωλήσεων»
                $t->tinyInteger('is_leader')->default(0);
                $t->unique(['team_id', 'admin_id']);
            });
        }
        if (!$s->hasTable('mod_cpm_project_teams')) {
            $s->create('mod_cpm_project_teams', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->integer('team_id')->unsigned()->index();
                $t->unique(['project_id', 'team_id']);
            });
        }

        if (!$s->hasTable('mod_cpm_product_targets')) {
            $s->create('mod_cpm_product_targets', function ($t) {
                $t->increments('id');
                $t->integer('product_id')->unsigned(); // tblproducts.id
                $t->integer('admin_id')->unsigned()->default(0);
                $t->integer('target_units')->default(0);         // στόχος τεμαχίων / μήνα
                $t->decimal('target_value', 12, 2)->default(0);  // στόχος € / μήνα (0 = χωρίς)
                $t->timestamp('created_at')->nullable();
                            $t->unique(['product_id', 'admin_id']);
            });
        }

        if (!$s->hasTable('mod_cpm_people')) {
            $s->create('mod_cpm_people', function ($t) {
                $t->increments('id');
                $t->string('name', 120);
                $t->string('email', 120)->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('title', 80)->nullable();          // ρόλος: «Ιδιοκτήτης», «Λογιστήριο»…
                $t->integer('lead_id')->unsigned()->nullable()->index();
                $t->integer('clientid')->unsigned()->nullable()->index();
                $t->text('notes')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_lead_fields')) {
            $s->create('mod_cpm_lead_fields', function ($t) {
                $t->increments('id');
                $t->string('label', 60);
                $t->string('type', 10)->default('text');       // text | select | date
                $t->text('options')->nullable();
                $t->integer('sort')->default(0);
            });
        }
        if (!$s->hasTable('mod_cpm_lead_values')) {
            $s->create('mod_cpm_lead_values', function ($t) {
                $t->increments('id');
                $t->integer('lead_id')->unsigned()->index();
                $t->integer('field_id')->unsigned()->index();
                $t->text('value')->nullable();
                $t->unique(['lead_id', 'field_id']);
            });
        }

        if (!$s->hasTable('mod_cpm_chat')) {
            $s->create('mod_cpm_chat', function ($t) {
                $t->increments('id');
                $t->string('channel', 20)->index();              // 'team' ή 'd<min>-<max>' (DM)
                $t->integer('admin_id')->unsigned();
                $t->text('body')->nullable();
                $t->string('filename', 190)->nullable();         // συνημμένο (προαιρετικό)
                $t->string('stored', 60)->nullable();
                $t->integer('size')->unsigned()->nullable();
                $t->dateTime('created_at');
            });
        }
        if (!$s->hasTable('mod_cpm_chat_reads')) {
            $s->create('mod_cpm_chat_reads', function ($t) {
                $t->integer('admin_id')->unsigned();
                $t->string('channel', 20);
                $t->integer('last_id')->unsigned()->default(0);
                $t->primary(['admin_id', 'channel']);
            });
        }
        if (!$s->hasTable('mod_cpm_ticket_cats')) {
            $s->create('mod_cpm_ticket_cats', function ($t) {
                $t->increments('id');
                $t->string('kind', 6);                      // area (περιοχή/προϊόν) | cause (ρίζα/επίλυση)
                $t->string('name', 80);
                $t->string('color', 9)->default('#0090dd');
                $t->integer('sort')->default(0);
                $t->index(['kind', 'sort']);
            });
        }
        if (!$s->hasTable('mod_cpm_ticket_class')) {
            $s->create('mod_cpm_ticket_class', function ($t) {
                $t->integer('ticketid')->unsigned()->primary();
                $t->integer('area_id')->unsigned()->nullable()->index();
                $t->integer('cause_id')->unsigned()->nullable()->index();
                $t->text('note')->nullable();                    // περιγραφή λύσης (rich HTML)
                $t->integer('classified_by')->unsigned()->nullable();
                $t->dateTime('classified_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_events')) {
            $s->create('mod_cpm_events', function ($t) {
                $t->increments('id');
                $t->string('kind', 14)->default('meeting');      // meeting|appointment|leave|other
                $t->string('title', 255);
                $t->dateTime('start_dt')->index();
                $t->dateTime('end_dt');
                $t->boolean('all_day')->default(false);
                $t->string('attendees', 190)->default('');       // ',2,9,' — συμμετέχοντες admins
                $t->integer('clientid')->unsigned()->nullable(); // ραντεβού με πελάτη
                $t->string('location', 190)->nullable();
                $t->text('notes')->nullable();
                $t->integer('created_by')->unsigned();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_kb')) {
            $s->create('mod_cpm_kb', function ($t) {
                $t->increments('id');
                $t->string('title', 255);                        // βάση γνώσης: συχνά προβλήματα & λύσεις
                $t->string('keywords', 500)->default('');
                $t->mediumText('solution');
                $t->string('tags', 190)->default('');
                $t->integer('uses')->unsigned()->default(0);
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_project_todos')) {
            $s->create('mod_cpm_project_todos', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();  // παραδοτέα/TODO του έργου
                $t->string('title', 255);
                $t->integer('sort')->default(0);
                $t->integer('done_by')->unsigned()->nullable();
                $t->timestamp('done_at')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_project_shares')) {
            $s->create('mod_cpm_project_shares', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->unique();  // δημόσιο link πελάτη (ένα ανά έργο)
                $t->string('token', 48)->unique();
                $t->timestamp('expires_at')->nullable();          // null = μέχρι το κλείσιμο του έργου
                $t->boolean('can_comment')->default(0);
                $t->boolean('revoked')->default(0);
                $t->integer('views')->unsigned()->default(0);
                $t->timestamp('last_view')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_share_comments')) {
            $s->create('mod_cpm_share_comments', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();   // μηνύματα πελάτη από το δημόσιο link
                $t->string('author', 90)->default('');
                $t->text('body');
                $t->boolean('from_team')->default(0);             // 0=πελάτης, 1=ομάδα (απάντηση)
                $t->integer('admin_id')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_client_remote')) {
            $s->create('mod_cpm_client_remote', function ($t) {
                $t->integer('clientid')->unsigned()->primary();  // 📇 αποθηκευμένο RustDesk ID ανά πελάτη
                $t->string('rustdesk_id', 20);
                $t->string('label', 120)->default('');
                $t->integer('updated_by')->unsigned()->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_lead_tasks')) {
            $s->create('mod_cpm_lead_tasks', function ($t) {
                $t->increments('id');
                $t->integer('lead_id')->unsigned()->index();     // CRM εργασίες/δραστηριότητες ανά lead
                $t->string('title', 200);
                $t->string('kind', 12)->default('todo');
                $t->date('due_date')->nullable()->index();
                $t->integer('assignee')->unsigned()->nullable()->index();
                $t->boolean('done')->default(0);
                $t->timestamp('done_at')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_prefs')) {
            $s->create('mod_cpm_prefs', function ($t) {
                $t->increments('id');
                $t->integer('admin_id')->unsigned();
                $t->string('pref', 40);                          // προσωπικές προτιμήσεις χρήστη
                $t->string('value', 190)->default('');
                $t->unique(['admin_id', 'pref']);
            });
        }
        if (!$s->hasTable('mod_cpm_deps')) {
            $s->create('mod_cpm_deps', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();     // αυτό το task…
                $t->integer('depends_on')->unsigned()->index();  // …μπλοκάρεται από αυτό
                $t->unique(['task_id', 'depends_on']);
            });
        }
        if (!$s->hasTable('mod_cpm_files')) {
            $s->create('mod_cpm_files', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->string('filename', 190);
                $t->string('stored', 60);
                $t->integer('size')->unsigned()->default(0);
                $t->integer('admin_id')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_canned')) {
            $s->create('mod_cpm_canned', function ($t) {
                $t->increments('id');
                $t->string('title', 80);
                $t->text('body');
                $t->integer('sort')->default(0);
            });
        }

        if (!$s->hasTable('mod_cpm_automations')) {
            $s->create('mod_cpm_automations', function ($t) {
                $t->increments('id');
                $t->string('name', 120);
                $t->string('trigger', 20);            // task_status | ticket_status | lead_stage | sla_breach
                $t->string('tvalue', 60)->nullable(); // π.χ. status id / status name / stage key
                $t->string('action', 20);             // assign_task|ball|set_prio|notify|assign_ticket|escalate
                $t->string('avalue', 60)->nullable(); // π.χ. admin id / prio
                $t->tinyInteger('active')->default(1);
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_auto_log')) {
            $s->create('mod_cpm_auto_log', function ($t) {
                $t->increments('id');
                $t->integer('auto_id')->unsigned()->index();
                $t->string('ref', 64)->index();       // dedupe: π.χ. sla:ticketid
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_notifications')) {
            $s->create('mod_cpm_notifications', function ($t) {
                $t->increments('id');
                $t->integer('admin_id')->unsigned()->index();  // παραλήπτης
                $t->string('type', 20)->default('info');       // assign|comment|done|due|recurring|info
                $t->string('title', 255);
                $t->string('url', 255)->nullable();
                $t->tinyInteger('is_read')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_cpm_project_members')) {
            $s->create('mod_cpm_project_members', function ($t) {
                $t->increments('id');
                $t->integer('project_id')->unsigned()->index();
                $t->integer('admin_id')->unsigned()->index();
                $t->unique(['project_id', 'admin_id']);
            });
        }

        if (!$s->hasTable('mod_cpm_interactions')) {
            $s->create('mod_cpm_interactions', function ($t) {
                $t->increments('id');
                $t->integer('lead_id')->unsigned()->nullable()->index();
                $t->integer('clientid')->unsigned()->nullable()->index();
                $t->string('kind', 10)->default('call');       // call|email|meeting|note
                $t->string('summary', 255);
                $t->text('detail')->nullable();
                $t->integer('admin_id')->unsigned()->nullable();
                $t->dateTime('happened_at');
                $t->date('followup_date')->nullable();          // εκκρεμής επόμενη ενέργεια
                $t->string('followup_note', 200)->nullable();
                $t->tinyInteger('followup_done')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasColumn('mod_cpm_offers', 'lead_id')) {
            $s->table('mod_cpm_offers', function ($t) {
                $t->integer('lead_id')->unsigned()->nullable()->index();
            });
        }

        if (!$s->hasTable('mod_cpm_activity')) {
            $s->create('mod_cpm_activity', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->integer('admin_id')->unsigned()->nullable();
                $t->string('action', 40);
                $t->string('detail', 255)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Projects (1.3)                                                     */
    /* ------------------------------------------------------------------ */

    public static function projects($includeArchived = false)
    {
        $q = Capsule::table('mod_cpm_projects')->orderBy('name');
        if (!$includeArchived) {
            $q->where('status', 'active');
        }
        return $q->get();
    }

    public static function project($id)
    {
        return Capsule::table('mod_cpm_projects')->where('id', (int) $id)->first();
    }

    public static function saveProject($id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if ($id) {
            Capsule::table('mod_cpm_projects')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        $data['created_at'] = $now;
        return (int) Capsule::table('mod_cpm_projects')->insertGetId($data);
    }

    /** Project for a support department (for auto-tasks). */
    public static function projectForDept($deptid)
    {
        return Capsule::table('mod_cpm_projects')
            ->where('deptid', (int) $deptid)->where('status', 'active')
            ->orderBy('id')->first();
    }

    /* ------------------------------------------------------------------ */
    /* Statuses (1.4)                                                     */
    /* ------------------------------------------------------------------ */

    public static function statuses()
    {
        return Capsule::table('mod_cpm_statuses')->orderBy('sort')->get();
    }

    public static function firstStatusId()
    {
        return (int) Capsule::table('mod_cpm_statuses')->orderBy('sort')->value('id');
    }

    public static function status($id)
    {
        return Capsule::table('mod_cpm_statuses')->where('id', (int) $id)->first();
    }

    /* ------------------------------------------------------------------ */
    /* Tasks (1.5)                                                        */
    /* ------------------------------------------------------------------ */

    public static function task($id)
    {
        return Capsule::table('mod_cpm_tasks')->where('id', (int) $id)->first();
    }

    public static function taskForTicket($ticketid)
    {
        return Capsule::table('mod_cpm_tasks')->where('ticketid', (int) $ticketid)->first();
    }

    public static function saveTask($id, array $data, $adminId = null)
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if ($id) {
            Capsule::table('mod_cpm_tasks')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        $data['created_at'] = $now;
        $data['created_by'] = $adminId ? (int) $adminId : null;
        if (empty($data['sort'])) {
            $data['sort'] = 1 + (int) Capsule::table('mod_cpm_tasks')
                ->where('project_id', (int) ($data['project_id'] ?? 0))->max('sort');
        }
        return (int) Capsule::table('mod_cpm_tasks')->insertGetId($data);
    }

    /** Tasks of a project grouped by status_id (for the board). */
    public static function board($projectId)
    {
        $out = [];
        $rows = Capsule::table('mod_cpm_tasks')->where('project_id', (int) $projectId)
            ->orderBy('sort')->orderBy('id')->get();
        foreach ($rows as $t) {
            $out[(int) $t->status_id][] = $t;
        }
        return $out;
    }

    /** Filtered task list (1.7 / 1.8). */
    public static function tasksFiltered(array $f, $limit = 300)
    {
        $q = Capsule::table('mod_cpm_tasks as t')
            ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->select('t.*', 'p.name as project_name', 'p.color as project_color');
        if (!empty($f['project_id'])) { $q->where('t.project_id', (int) $f['project_id']); }
        if (!empty($f['status_id']))  { $q->where('t.status_id', (int) $f['status_id']); }
        if (!empty($f['assignee']))   { $q->where('t.assignee', (int) $f['assignee']); }
        if (isset($f['priority']) && $f['priority'] !== '') { $q->where('t.priority', (int) $f['priority']); }
        if (!empty($f['q'])) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('t.title', 'like', $like)->orWhere('t.descr', 'like', $like);
            });
        }
        if (!empty($f['open_only'])) {
            $done = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all();
            if ($done) { $q->whereNotIn('t.status_id', $done); }
        }
        // περιορισμένος agent: μόνο μέλος-projects Ή δικές του αναθέσεις
        if (!empty($f['restrict_admin'])) {
            $aid = (int) $f['restrict_admin'];
            $vis = self::visibleProjectIds($aid);
            if ($vis !== null) {
                $q->where(function ($w) use ($vis, $aid) {
                    if ($vis) {
                        $w->whereIn('t.project_id', $vis)->orWhere('t.assignee', $aid);
                    } else {
                        $w->where('t.assignee', $aid);
                    }
                });
            }
        }
        return $q->orderByRaw('t.priority DESC')->orderBy('t.due_date')->orderBy('t.id', 'desc')
            ->limit($limit)->get();
    }

    public static function moveTask($id, $statusId, $adminId = null)
    {
        $t = self::task($id);
        if (!$t) { return false; }
        $new = self::status($statusId);
        if (!$new) { return false; }
        $upd = ['status_id' => (int) $statusId, 'updated_at' => date('Y-m-d H:i:s')];
        $upd['completed_at'] = $new->is_done ? date('Y-m-d H:i:s') : null;
        Capsule::table('mod_cpm_tasks')->where('id', (int) $id)->update($upd);
        $old = self::status($t->status_id);
        self::logActivity($id, $adminId, 'status', ($old->title ?? '?') . ' → ' . $new->title);
        // automations: task μπήκε σε status
        if (!class_exists(Auto::class)) {
            require_once __DIR__ . '/Auto.php';
        }
        Auto::run('task_status', ['taskId' => (int) $id, 'statusId' => (int) $statusId]);
        return true;
    }

    public static function deleteTask($id)
    {
        Capsule::table('mod_cpm_comments')->where('task_id', (int) $id)->delete();
        Capsule::table('mod_cpm_activity')->where('task_id', (int) $id)->delete();
        // Τα timelogs σβήνονται μόνο από το CPM — ό,τι έχει ήδη περαστεί/χρεωθεί
        // στο supportcontracts ΜΕΝΕΙ (η εργασία έγινε· διαγραφή task ≠ επιστροφή χρέωσης).
        Capsule::table('mod_cpm_timelogs')->where('task_id', (int) $id)->delete();
        Capsule::table('mod_cpm_checklist')->where('task_id', (int) $id)->delete();
        Capsule::table('mod_cpm_field_values')->where('task_id', (int) $id)->delete();
        Capsule::table('mod_cpm_tasks')->where('id', (int) $id)->delete();
    }

    /* ------------------------------------------------------------------ */
    /* Time logs (2.1) — timer + manual                                   */
    /* ------------------------------------------------------------------ */

    public static function timelog($id)
    {
        return Capsule::table('mod_cpm_timelogs')->where('id', (int) $id)->first();
    }

    /** Ο τρέχων (running) timer του admin — max ένας ανά admin. */
    public static function runningTimer($adminId)
    {
        return Capsule::table('mod_cpm_timelogs')
            ->where('admin_id', (int) $adminId)->where('running', 1)->first();
    }

    /**
     * Start timer. Σταματά αυτόματα όποιον άλλον timer τρέχει για τον admin
     * (GoodDay-style). Returns ['id' => new timer id, 'stopped' => [entry ids]].
     */
    public static function startTimer($taskId, $adminId)
    {
        $stopped = [];
        $other = self::runningTimer($adminId);
        if ($other) {
            self::stopTimer($other->id);
            self::updateTimelog($other->id, ['note' => trim(($other->note ? $other->note . ' ' : '') . '(auto-stop)')]);
            $stopped[] = (int) $other->id;
        }
        $id = (int) Capsule::table('mod_cpm_timelogs')->insertGetId([
            'task_id'    => (int) $taskId,
            'admin_id'   => (int) $adminId,
            'running'    => 1,
            'billable'   => 0,
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['id' => $id, 'stopped' => $stopped];
    }

    /** Stop timer: υπολογίζει λεπτά (min 1), κλείνει την εγγραφή. Returns row ή null. */
    public static function stopTimer($logId)
    {
        $l = self::timelog($logId);
        if (!$l || !$l->running) {
            return null;
        }
        $mins = max(1, (int) round((time() - strtotime($l->started_at)) / 60));
        Capsule::table('mod_cpm_timelogs')->where('id', (int) $logId)
            ->update(['running' => 0, 'minutes' => $mins, 'created_at' => date('Y-m-d H:i:s')]);
        return self::timelog($logId);
    }

    /** Χειροκίνητη καταχώρηση χρόνου. Returns entry id. */
    public static function addTime($taskId, $adminId, $minutes, $billable, $note = '')
    {
        return (int) Capsule::table('mod_cpm_timelogs')->insertGetId([
            'task_id'    => (int) $taskId,
            'admin_id'   => (int) $adminId,
            'minutes'    => max(1, (int) $minutes),
            'billable'   => $billable ? 1 : 0,
            'note'       => mb_substr((string) $note, 0, 255),
            'running'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateTimelog($id, array $data)
    {
        Capsule::table('mod_cpm_timelogs')->where('id', (int) $id)->update($data);
    }

    public static function deleteTimelog($id)
    {
        Capsule::table('mod_cpm_timelogs')->where('id', (int) $id)->delete();
    }

    public static function timelogsForTask($taskId)
    {
        return Capsule::table('mod_cpm_timelogs')
            ->where('task_id', (int) $taskId)->orderBy('id', 'desc')->get();
    }

    /** Σύνολο λεπτών εργασίας ενός task (χωρίς running). */
    public static function taskMinutes($taskId)
    {
        return (int) Capsule::table('mod_cpm_timelogs')
            ->where('task_id', (int) $taskId)->where('running', 0)->sum('minutes');
    }

    /** Map task_id → λεπτά, για όλα τα tasks ενός project (board chips, 1 query). */
    public static function minutesByTask($projectId)
    {
        $rows = Capsule::table('mod_cpm_timelogs as l')
            ->join('mod_cpm_tasks as t', 't.id', '=', 'l.task_id')
            ->where('t.project_id', (int) $projectId)->where('l.running', 0)
            ->groupBy('l.task_id')
            ->selectRaw('l.task_id, SUM(l.minutes) as m')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->task_id] = (int) $r->m;
        }
        return $out;
    }

    /** Map task_id → λεπτά για συγκεκριμένα task ids (list view). */
    public static function minutesForTasks(array $ids)
    {
        if (!$ids) {
            return [];
        }
        $rows = Capsule::table('mod_cpm_timelogs')->whereIn('task_id', $ids)
            ->where('running', 0)->groupBy('task_id')
            ->selectRaw('task_id, SUM(minutes) as m')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->task_id] = (int) $r->m;
        }
        return $out;
    }

    /** Καταχωρήσεις περιόδου με task/project info (tab «Χρόνος», 2.4). */
    public static function timeReport($from, $to, array $f = [])
    {
        $q = Capsule::table('mod_cpm_timelogs as l')
            ->join('mod_cpm_tasks as t', 't.id', '=', 'l.task_id')
            ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->select('l.*', 't.title as task_title', 't.ticketid', 'p.id as project_id',
                'p.name as project_name', 'p.color as project_color', 'p.clientid')
            ->where('l.running', 0)
            ->whereBetween('l.created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        if (!empty($f['project_id'])) { $q->where('t.project_id', (int) $f['project_id']); }
        if (!empty($f['admin_id']))   { $q->where('l.admin_id', (int) $f['admin_id']); }
        return $q->orderBy('l.id', 'desc')->get();
    }

    /* ------------------------------------------------------------------ */
    /* Checklist (3.2)                                                    */
    /* ------------------------------------------------------------------ */

    public static function checklist($taskId)
    {
        return Capsule::table('mod_cpm_checklist')->where('task_id', (int) $taskId)
            ->orderBy('sort')->orderBy('id')->get();
    }

    public static function addCheckItem($taskId, $title)
    {
        $sort = 1 + (int) Capsule::table('mod_cpm_checklist')->where('task_id', (int) $taskId)->max('sort');
        return (int) Capsule::table('mod_cpm_checklist')->insertGetId([
            'task_id' => (int) $taskId, 'title' => mb_substr($title, 0, 200), 'done' => 0, 'sort' => $sort,
        ]);
    }

    public static function toggleCheckItem($id)
    {
        $it = Capsule::table('mod_cpm_checklist')->where('id', (int) $id)->first();
        if (!$it) {
            return null;
        }
        Capsule::table('mod_cpm_checklist')->where('id', (int) $id)->update(['done' => $it->done ? 0 : 1]);
        return $it;
    }

    public static function deleteCheckItem($id)
    {
        Capsule::table('mod_cpm_checklist')->where('id', (int) $id)->delete();
    }

    /** Map task_id → [done, total] για όλα τα tasks ενός project (board chips). */
    public static function checklistProgress($projectId)
    {
        $rows = Capsule::table('mod_cpm_checklist as c')
            ->join('mod_cpm_tasks as t', 't.id', '=', 'c.task_id')
            ->where('t.project_id', (int) $projectId)
            ->groupBy('c.task_id')
            ->selectRaw('c.task_id, SUM(c.done) as d, COUNT(*) as n')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->task_id] = [(int) $r->d, (int) $r->n];
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Recurring tasks (3.3)                                              */
    /* ------------------------------------------------------------------ */

    public static function recurring($id)
    {
        return Capsule::table('mod_cpm_recurring')->where('id', (int) $id)->first();
    }

    public static function recurringAll($onlyActive = false)
    {
        $q = Capsule::table('mod_cpm_recurring as r')
            ->join('mod_cpm_projects as p', 'p.id', '=', 'r.project_id')
            ->select('r.*', 'p.name as project_name', 'p.color as project_color')
            ->orderBy('r.next_run');
        if ($onlyActive) {
            $q->where('r.active', 1);
        }
        return $q->get();
    }

    public static function saveRecurring($id, array $data)
    {
        if ($id) {
            Capsule::table('mod_cpm_recurring')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        return (int) Capsule::table('mod_cpm_recurring')->insertGetId($data);
    }

    public static function deleteRecurring($id)
    {
        Capsule::table('mod_cpm_recurring')->where('id', (int) $id)->delete();
    }

    /** Ενεργά recurring που πρέπει να τρέξουν (next_run <= σήμερα). */
    public static function dueRecurring($today)
    {
        return Capsule::table('mod_cpm_recurring')->where('active', 1)
            ->where('next_run', '<=', $today)->get();
    }

    /** Επόμενη ημερομηνία εκτέλεσης από freq + every (μήνες/έτη: clamp στην τελευταία μέρα, όχι overflow 31/1→3/3). */
    public static function nextRun($fromDate, $freq, $every)
    {
        $every = max(1, (int) $every);
        if ($freq === 'daily' || $freq === 'weekly') {
            return date('Y-m-d', strtotime($fromDate . ' +' . $every . ' ' . ($freq === 'daily' ? 'day' : 'week')));
        }
        $d = new \DateTime($fromDate);
        $day = (int) $d->format('j');
        $d->modify('first day of this month');
        $d->modify('+' . ($freq === 'yearly' ? $every * 12 : $every) . ' month');
        $d->setDate((int) $d->format('Y'), (int) $d->format('n'), min($day, (int) $d->format('t')));
        return $d->format('Y-m-d');
    }

    /* ------------------------------------------------------------------ */
    /* Custom fields ανά project (3.5)                                    */
    /* ------------------------------------------------------------------ */

    public static function fieldsForProject($projectId)
    {
        return Capsule::table('mod_cpm_fields')->where('project_id', (int) $projectId)
            ->orderBy('sort')->orderBy('id')->get();
    }

    public static function saveField($id, array $data)
    {
        if ($id) {
            Capsule::table('mod_cpm_fields')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        return (int) Capsule::table('mod_cpm_fields')->insertGetId($data);
    }

    public static function deleteField($id)
    {
        Capsule::table('mod_cpm_field_values')->where('field_id', (int) $id)->delete();
        Capsule::table('mod_cpm_fields')->where('id', (int) $id)->delete();
    }

    /** Map field_id → value για ένα task. */
    public static function fieldValues($taskId)
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_field_values')->where('task_id', (int) $taskId)->get() as $r) {
            $out[(int) $r->field_id] = $r->value;
        }
        return $out;
    }

    public static function saveFieldValue($taskId, $fieldId, $value)
    {
        $value = (string) $value;
        $exists = Capsule::table('mod_cpm_field_values')
            ->where('task_id', (int) $taskId)->where('field_id', (int) $fieldId)->exists();
        if ($exists) {
            Capsule::table('mod_cpm_field_values')
                ->where('task_id', (int) $taskId)->where('field_id', (int) $fieldId)
                ->update(['value' => $value]);
        } else {
            Capsule::table('mod_cpm_field_values')->insert([
                'task_id' => (int) $taskId, 'field_id' => (int) $fieldId, 'value' => $value,
            ]);
        }
    }

    /** Tasks με due_date μέσα σε έναν μήνα (calendar, 3.6). */
    public static function tasksForMonth($ym)
    {
        return Capsule::table('mod_cpm_tasks as t')
            ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->select('t.id', 't.title', 't.due_date', 't.priority', 't.completed_at', 't.assignee',
                't.project_id', 'p.name as project_name', 'p.color as project_color')
            ->whereNotNull('t.due_date')
            ->whereBetween('t.due_date', [$ym . '-01', date('Y-m-t', strtotime($ym . '-01'))])
            ->orderBy('t.due_date')->get();
    }

    /* ------------------------------------------------------------------ */
    /* Προσφορές (4.1-4.3)                                                */
    /* ------------------------------------------------------------------ */

    /** Στάδια pipeline (σταθερά): key => [τίτλος, χρώμα, κλειστό;, κερδισμένο;]. */
    public static function offerStages()
    {
        return [
            'new'      => ['Νέα', '#8291a9', 0, 0],
            'draft'    => ['Σύνταξη προσφοράς', '#0097e4', 0, 0],
            'sent'     => ['Εστάλη — αναμονή', '#e0a020', 0, 0],
            'accepted' => ['Αποδεκτή', '#1f9d57', 1, 1],
            'lost'     => ['Χαμένη', '#d92d3a', 1, 0],
        ];
    }

    /** Αντιστοίχιση σταδίου WHMCS Quote → δικό μας (quote = πηγή αλήθειας όταν είναι δεμένο). */
    public static function stageFromQuote($quoteStage)
    {
        return ['Draft' => 'draft', 'Delivered' => 'sent', 'On Hold' => 'sent',
                'Accepted' => 'accepted', 'Lost' => 'lost', 'Dead' => 'lost'][$quoteStage] ?? null;
    }

    public static function offer($id)
    {
        return Capsule::table('mod_cpm_offers')->where('id', (int) $id)->first();
    }

    public static function saveOffer($id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if ($id) {
            Capsule::table('mod_cpm_offers')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        $data['created_at'] = $now;
        return (int) Capsule::table('mod_cpm_offers')->insertGetId($data);
    }

    public static function moveOffer($id, $stage, $adminId = null)
    {
        $stages = self::offerStages();
        $o = self::offer($id);
        if (!$o || !isset($stages[$stage])) {
            return false;
        }
        self::saveOffer($id, [
            'stage'     => $stage,
            'closed_at' => $stages[$stage][2] ? date('Y-m-d H:i:s') : null,
        ]);
        if (function_exists('logActivity')) {
            logActivity('CPM: προσφορά #' . $id . ' («' . $o->title . '») → ' . $stages[$stage][0]
                . ($adminId ? ' (admin #' . $adminId . ')' : ''));
        }
        return true;
    }

    public static function deleteOffer($id)
    {
        Capsule::table('mod_cpm_offers')->where('id', (int) $id)->delete();
    }

    /**
     * Προσφορές (προαιρετικά φιλτραρισμένες ανά πελάτη), με quote info,
     * αφού πρώτα συγχρονιστεί το στάδιο από τα δεμένα WHMCS Quotes.
     */
    public static function offers($clientid = 0)
    {
        self::syncOfferStages();
        $q = Capsule::table('mod_cpm_offers as o')
            ->leftJoin('tblquotes as q', 'q.id', '=', 'o.quoteid')
            ->select('o.*', 'q.stage as quote_stage', 'q.total as quote_total', 'q.validuntil as quote_validuntil');
        if ($clientid) {
            $q->where('o.clientid', (int) $clientid);
        }
        return $q->orderBy('o.id', 'desc')->get();
    }

    /** Τραβά το στάδιο από τα δεμένα quotes (quote = πηγή αλήθειας). */
    public static function syncOfferStages()
    {
        $rows = Capsule::table('mod_cpm_offers as o')
            ->join('tblquotes as q', 'q.id', '=', 'o.quoteid')
            ->select('o.id', 'o.stage', 'q.stage as qstage')->get();
        foreach ($rows as $r) {
            $mapped = self::stageFromQuote($r->qstage);
            if ($mapped && $mapped !== $r->stage) {
                self::moveOffer($r->id, $mapped);
            }
        }
    }

    /** Στατιστικά προσφορών (όλες ή ανά πελάτη): σύνολα, κερδισμένες, ποσοστό, αξία. */
    public static function offerStats($clientid = 0)
    {
        $stages = self::offerStages();
        $st = ['total' => 0, 'open' => 0, 'won' => 0, 'lost' => 0, 'won_value' => 0.0, 'open_value' => 0.0];
        foreach (self::offers($clientid) as $o) {
            $val = $o->quoteid && $o->quote_total !== null ? (float) $o->quote_total : (float) ($o->amount ?? 0);
            $st['total']++;
            $meta = $stages[$o->stage] ?? $stages['new'];
            if (!$meta[2]) {
                $st['open']++;
                $st['open_value'] += $val;
            } elseif ($meta[3]) {
                $st['won']++;
                $st['won_value'] += $val;
            } else {
                $st['lost']++;
            }
        }
        $closed = $st['won'] + $st['lost'];
        $st['win_rate'] = $closed ? (int) round($st['won'] / $closed * 100) : null;
        return $st;
    }

    /* ------------------------------------------------------------------ */
    /* Leads / Λίστα στόχων πωλήσεων (CRM)                                */
    /* ------------------------------------------------------------------ */

    /** Funnel πωλήσεων: key => [τίτλος, χρώμα, κλειστό;, κερδισμένο;]. */
    public static function leadStages()
    {
        return [
            'target'     => ['Στόχος', '#8291a9', 0, 0],           // λίστα στόχων — δεν έχει γίνει επαφή
            'contacted'  => ['Έγινε επαφή', '#0097e4', 0, 0],
            'interested' => ['Ενδιαφέρεται', '#7b5cd6', 0, 0],
            'offer'      => ['Σε προσφορά', '#e0a020', 0, 0],
            'won'        => ['Έγινε πελάτης', '#1f9d57', 1, 1],
            'lost'       => ['Δεν προχώρησε', '#d92d3a', 1, 0],
        ];
    }

    public static function lead($id)
    {
        return Capsule::table('mod_cpm_leads')->where('id', (int) $id)->first();
    }

    public static function leads($stage = '')
    {
        $q = Capsule::table('mod_cpm_leads')->orderBy('next_action')->orderBy('id', 'desc');
        if ($stage !== '') {
            $q->where('stage', $stage);
        }
        return $q->get();
    }

    public static function saveLead($id, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if ($id) {
            Capsule::table('mod_cpm_leads')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        $data['created_at'] = $now;
        return (int) Capsule::table('mod_cpm_leads')->insertGetId($data);
    }

    public static function moveLead($id, $stage, $adminId = null)
    {
        $stages = self::leadStages();
        $l = self::lead($id);
        if (!$l || !isset($stages[$stage])) {
            return false;
        }
        self::saveLead($id, [
            'stage'     => $stage,
            'closed_at' => $stages[$stage][2] ? date('Y-m-d H:i:s') : null,
        ]);
        if (function_exists('logActivity')) {
            logActivity('CPM: lead #' . $id . ' («' . trim(($l->company ?: '') . ' ' . ($l->contact ?: '')) . '») → '
                . $stages[$stage][0] . ($adminId ? ' (admin #' . $adminId . ')' : ''));
        }
        if (!class_exists(Auto::class)) {
            require_once __DIR__ . '/Auto.php';
        }
        Auto::run('lead_stage', ['leadId' => (int) $id, 'stage' => $stage]);
        return true;
    }

    public static function deleteLead($id)
    {
        Capsule::table('mod_cpm_offers')->where('lead_id', (int) $id)->update(['lead_id' => null]);
        Capsule::table('mod_cpm_leads')->where('id', (int) $id)->delete();
    }

    /** Αξία κερδισμένων προσφορών μέσα σε έναν μήνα (στόχος πωλήσεων). */
    public static function wonValueForMonth($ym)
    {
        $from = $ym . '-01 00:00:00';
        $to = date('Y-m-t', strtotime($ym . '-01')) . ' 23:59:59';
        $sum = 0.0;
        $rows = Capsule::table('mod_cpm_offers as o')
            ->leftJoin('tblquotes as q', 'q.id', '=', 'o.quoteid')
            ->where('o.stage', 'accepted')->whereBetween('o.closed_at', [$from, $to])
            ->select('o.amount', 'o.quoteid', 'q.total as quote_total')->get();
        foreach ($rows as $r) {
            $sum += $r->quoteid && $r->quote_total !== null ? (float) $r->quote_total : (float) ($r->amount ?? 0);
        }
        return $sum;
    }

    /* ------------------------------------------------------------------ */
    /* Watchers / Υπενθυμίσεις / Έξοδα / Snapshots                        */
    /* ------------------------------------------------------------------ */

    public static function watcherIds($taskId)
    {
        return array_map('intval', Capsule::table('mod_cpm_watchers')
            ->where('task_id', (int) $taskId)->pluck('admin_id')->all());
    }

    public static function toggleWatcher($taskId, $adminId)
    {
        $q = Capsule::table('mod_cpm_watchers')
            ->where('task_id', (int) $taskId)->where('admin_id', (int) $adminId);
        if ($q->exists()) {
            $q->delete();
            return false;
        }
        Capsule::table('mod_cpm_watchers')->insert(['task_id' => (int) $taskId, 'admin_id' => (int) $adminId]);
        return true;
    }

    public static function addReminder($adminId, $remindAt, $note = '', $taskId = null)
    {
        return (int) Capsule::table('mod_cpm_reminders')->insertGetId([
            'task_id'    => $taskId ? (int) $taskId : null,
            'admin_id'   => (int) $adminId,
            'remind_at'  => $remindAt,
            'note'       => mb_substr((string) $note, 0, 200) ?: null,
            'sent'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function dueReminders($now = null)
    {
        return Capsule::table('mod_cpm_reminders')->where('sent', 0)
            ->where('remind_at', '<=', $now ?: date('Y-m-d H:i:s'))->get();
    }

    public static function markReminderSent($id)
    {
        Capsule::table('mod_cpm_reminders')->where('id', (int) $id)->update(['sent' => 1]);
    }

    public static function remindersForTask($taskId, $adminId)
    {
        return Capsule::table('mod_cpm_reminders')->where('task_id', (int) $taskId)
            ->where('admin_id', (int) $adminId)->where('sent', 0)->orderBy('remind_at')->get();
    }

    public static function addExpense($projectId, $descr, $amount, $spentAt, $adminId)
    {
        return (int) Capsule::table('mod_cpm_expenses')->insertGetId([
            'project_id' => (int) $projectId,
            'descr'      => mb_substr($descr, 0, 200),
            'amount'     => round((float) $amount, 2),
            'spent_at'   => $spentAt,
            'admin_id'   => (int) $adminId ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function deleteExpense($id)
    {
        Capsule::table('mod_cpm_expenses')->where('id', (int) $id)->delete();
    }

    public static function expenses($from, $to, $projectId = 0)
    {
        $q = Capsule::table('mod_cpm_expenses as e')
            ->join('mod_cpm_projects as p', 'p.id', '=', 'e.project_id')
            ->select('e.*', 'p.name as project_name', 'p.clientid')
            ->whereBetween('e.spent_at', [$from, $to])->orderBy('e.spent_at', 'desc');
        if ($projectId) {
            $q->where('e.project_id', (int) $projectId);
        }
        return $q->get();
    }

    /** Ημερήσιο στιγμιότυπο προόδου όλων των ενεργών projects (idempotent ανά ημέρα). */
    public static function snapshotAll()
    {
        $today = date('Y-m-d');
        $n = 0;
        foreach (self::projects() as $p) {
            [$done, $total] = self::projectProgress($p->id);
            $open = $total - $done;
            $exists = Capsule::table('mod_cpm_snapshots')
                ->where('project_id', $p->id)->where('snap_date', $today)->exists();
            if ($exists) {
                Capsule::table('mod_cpm_snapshots')->where('project_id', $p->id)->where('snap_date', $today)
                    ->update(['open_n' => $open, 'done_n' => $done]);
            } else {
                Capsule::table('mod_cpm_snapshots')->insert([
                    'project_id' => $p->id, 'snap_date' => $today, 'open_n' => $open, 'done_n' => $done,
                ]);
            }
            $n++;
        }
        return $n;
    }

    /** Μεταβολή ανοιχτών σε σχέση με N ημέρες πριν: [τότε, τώρα] ή null. */
    public static function snapshotDelta($projectId, $days = 7)
    {
        $past = Capsule::table('mod_cpm_snapshots')->where('project_id', (int) $projectId)
            ->where('snap_date', '<=', date('Y-m-d', strtotime("-$days days")))
            ->orderBy('snap_date', 'desc')->first();
        if (!$past) {
            return null;
        }
        [$done, $total] = self::projectProgress($projectId);
        return [(int) $past->open_n, $total - $done];
    }

    /* ------------------------------------------------------------------ */
    /* Τύποι task + βοηθητικά GoodDay-ιδεών                               */
    /* ------------------------------------------------------------------ */

    public static function taskTypes()
    {
        return Capsule::table('mod_cpm_task_types')->orderBy('sort')->get();
    }

    public static function taskType($id)
    {
        return Capsule::table('mod_cpm_task_types')->where('id', (int) $id)->first();
    }

    /** Καταστάσεις & υγεία project (portfolio). */
    public static function projectStatuses()
    {
        return ['new' => 'Νέο', 'active' => 'Σε εξέλιξη', 'hold' => 'Σε αναμονή', 'done' => 'Ολοκληρωμένο'];
    }

    public static function healthColors()
    {
        return ['green' => '#1f9d57', 'yellow' => '#e0a020', 'red' => '#d92d3a'];
    }

    /** Πρόοδος project από tasks: [done, total, pct]. */
    public static function projectProgress($projectId)
    {
        $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
        $total = Capsule::table('mod_cpm_tasks')->where('project_id', (int) $projectId)->count();
        $done = Capsule::table('mod_cpm_tasks')->where('project_id', (int) $projectId)
            ->whereIn('status_id', $doneIds)->count();
        return [$done, $total, $total ? (int) round($done / $total * 100) : 0];
    }

    /* ------------------------------------------------------------------ */
    /* Ομάδες / Οργανόγραμμα                                              */
    /* ------------------------------------------------------------------ */

    public static function teams()
    {
        return Capsule::table('mod_cpm_teams')->orderBy('sort')->orderBy('name')->get();
    }

    public static function team($id)
    {
        return Capsule::table('mod_cpm_teams')->where('id', (int) $id)->first();
    }

    public static function saveTeam($id, array $data)
    {
        if ($id) {
            Capsule::table('mod_cpm_teams')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        return (int) Capsule::table('mod_cpm_teams')->insertGetId($data);
    }

    public static function deleteTeam($id)
    {
        Capsule::table('mod_cpm_team_members')->where('team_id', (int) $id)->delete();
        Capsule::table('mod_cpm_project_teams')->where('team_id', (int) $id)->delete();
        Capsule::table('mod_cpm_teams')->where('id', (int) $id)->delete();
    }

    public static function teamMembers($teamId)
    {
        return Capsule::table('mod_cpm_team_members')->where('team_id', (int) $teamId)
            ->orderBy('is_leader', 'desc')->orderBy('id')->get();
    }

    public static function addTeamMember($teamId, $adminId, $roleTitle = null, $isLeader = 0)
    {
        $exists = Capsule::table('mod_cpm_team_members')
            ->where('team_id', (int) $teamId)->where('admin_id', (int) $adminId)->exists();
        if ($exists) {
            Capsule::table('mod_cpm_team_members')
                ->where('team_id', (int) $teamId)->where('admin_id', (int) $adminId)
                ->update(['role_title' => $roleTitle, 'is_leader' => $isLeader ? 1 : 0]);
        } else {
            Capsule::table('mod_cpm_team_members')->insert([
                'team_id' => (int) $teamId, 'admin_id' => (int) $adminId,
                'role_title' => $roleTitle, 'is_leader' => $isLeader ? 1 : 0,
            ]);
        }
        if ($isLeader) { // ένας αρχηγός ανά ομάδα
            Capsule::table('mod_cpm_team_members')->where('team_id', (int) $teamId)
                ->where('admin_id', '!=', (int) $adminId)->update(['is_leader' => 0]);
        }
    }

    public static function removeTeamMember($teamId, $adminId)
    {
        Capsule::table('mod_cpm_team_members')
            ->where('team_id', (int) $teamId)->where('admin_id', (int) $adminId)->delete();
    }

    /** Ids ομάδων στις οποίες ανήκει ο admin. */
    public static function teamsForAdmin($adminId)
    {
        return array_map('intval', Capsule::table('mod_cpm_team_members')
            ->where('admin_id', (int) $adminId)->pluck('team_id')->all());
    }

    /** Map admin_id → «Ομάδα Α, Ομάδα Β» (για εμφάνιση δίπλα στο όνομα). */
    public static function adminTeamMap()
    {
        $out = [];
        $rows = Capsule::table('mod_cpm_team_members as m')
            ->join('mod_cpm_teams as t', 't.id', '=', 'm.team_id')
            ->get(['m.admin_id', 't.name']);
        foreach ($rows as $r) {
            $out[(int) $r->admin_id] = isset($out[(int) $r->admin_id])
                ? $out[(int) $r->admin_id] . ', ' . $r->name : $r->name;
        }
        return $out;
    }

    public static function projectTeams($projectId)
    {
        return array_map('intval', Capsule::table('mod_cpm_project_teams')
            ->where('project_id', (int) $projectId)->pluck('team_id')->all());
    }

    public static function saveProjectTeams($projectId, array $teamIds)
    {
        Capsule::table('mod_cpm_project_teams')->where('project_id', (int) $projectId)->delete();
        foreach (array_unique(array_map('intval', $teamIds)) as $tid) {
            if ($tid > 0) {
                Capsule::table('mod_cpm_project_teams')->insert(['project_id' => (int) $projectId, 'team_id' => $tid]);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Ειδοποιήσεις (καμπανάκι)                                           */
    /* ------------------------------------------------------------------ */

    public static function pushNotification($adminId, $type, $title, $url = null)
    {
        if (!(int) $adminId) {
            return;
        }
        Capsule::table('mod_cpm_notifications')->insert([
            'admin_id'   => (int) $adminId,
            'type'       => mb_substr($type, 0, 20),
            'title'      => mb_substr($title, 0, 255),
            'url'        => $url ? mb_substr($url, 0, 255) : null,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // καθάρισμα: κράτα τις 200 τελευταίες ανά χρήστη
        $min = Capsule::table('mod_cpm_notifications')->where('admin_id', (int) $adminId)
            ->orderBy('id', 'desc')->skip(200)->take(1)->value('id');
        if ($min) {
            Capsule::table('mod_cpm_notifications')->where('admin_id', (int) $adminId)
                ->where('id', '<=', $min)->delete();
        }
    }

    public static function notificationsFor($adminId, $limit = 12)
    {
        return Capsule::table('mod_cpm_notifications')->where('admin_id', (int) $adminId)
            ->orderBy('id', 'desc')->limit($limit)->get();
    }

    public static function unreadCount($adminId)
    {
        return (int) Capsule::table('mod_cpm_notifications')
            ->where('admin_id', (int) $adminId)->where('is_read', 0)->count();
    }

    public static function markNotifRead($adminId, $id = 0)
    {
        $q = Capsule::table('mod_cpm_notifications')->where('admin_id', (int) $adminId);
        if ($id) {
            $q->where('id', (int) $id);
        }
        $q->update(['is_read' => 1]);
    }

    public static function notification($id)
    {
        return Capsule::table('mod_cpm_notifications')->where('id', (int) $id)->first();
    }

    /** Όλοι οι ενεργοί admins με πλήρη πρόσβαση (διαχειριστές). */
    public static function fullAccessAdminIds()
    {
        $out = [];
        foreach (self::admins() as $a) {
            if (self::isFullAccess($a->id)) {
                $out[] = (int) $a->id;
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Δικαιώματα ανά agent (access control)                              */
    /* ------------------------------------------------------------------ */

    /**
     * Full access = admin που ανήκει σε ρόλο της ρύθμισης full_access_roles
     * (default ρόλος 1 = Full Administrator). Όλοι οι άλλοι βλέπουν ΜΟΝΟ
     * ό,τι τους έχει ανατεθεί ή projects όπου είναι μέλη.
     */
    public static function isFullAccess($adminId)
    {
        static $cache = [];
        $adminId = (int) $adminId;
        if (isset($cache[$adminId])) {
            return $cache[$adminId];
        }
        $roles = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'full_access_roles')->value('value');
        $roleIds = array_filter(array_map('intval', preg_split('/[,\s]+/', (string) ($roles ?: '1'))));
        if (!$roleIds) {
            $roleIds = [1];
        }
        $roleid = (int) Capsule::table('tbladmins')->where('id', $adminId)->value('roleid');
        return $cache[$adminId] = in_array($roleid, $roleIds, true);
    }

    public static function projectMembers($projectId)
    {
        return Capsule::table('mod_cpm_project_members')->where('project_id', (int) $projectId)
            ->pluck('admin_id')->all();
    }

    public static function saveProjectMembers($projectId, array $adminIds)
    {
        Capsule::table('mod_cpm_project_members')->where('project_id', (int) $projectId)->delete();
        foreach (array_unique(array_map('intval', $adminIds)) as $aid) {
            if ($aid > 0) {
                Capsule::table('mod_cpm_project_members')->insert(['project_id' => (int) $projectId, 'admin_id' => $aid]);
            }
        }
    }

    /** null = βλέπει όλα· array = ΜΟΝΟ αυτά τα project ids (άμεσο μέλος Ή μέσω ομάδας). */
    public static function visibleProjectIds($adminId)
    {
        if (self::isFullAccess($adminId)) {
            return null;
        }
        $direct = array_map('intval', Capsule::table('mod_cpm_project_members')
            ->where('admin_id', (int) $adminId)->pluck('project_id')->all());
        $viaTeams = [];
        $teamIds = self::teamsForAdmin($adminId);
        if ($teamIds) {
            $viaTeams = array_map('intval', Capsule::table('mod_cpm_project_teams')
                ->whereIn('team_id', $teamIds)->pluck('project_id')->all());
        }
        return array_values(array_unique(array_merge($direct, $viaTeams)));
    }

    /** Ορατό project για τον admin; */
    public static function canSeeProject($adminId, $projectId)
    {
        $vis = self::visibleProjectIds($adminId);
        return $vis === null || in_array((int) $projectId, $vis, true);
    }

    /** Ορατό task: ορατό project Ή ανατεθειμένο στον ίδιο. */
    public static function canSeeTask($adminId, $task)
    {
        if (!$task) {
            return false;
        }
        if ((int) $task->assignee === (int) $adminId) {
            return true;
        }
        return self::canSeeProject($adminId, $task->project_id);
    }

    /** Projects φιλτραρισμένα για τον admin. */
    public static function projectsFor($adminId, $includeArchived = false)
    {
        $all = self::projects($includeArchived);
        $vis = self::visibleProjectIds($adminId);
        if ($vis === null) {
            return $all;
        }
        return $all->filter(function ($p) use ($vis) {
            return in_array((int) $p->id, $vis, true);
        })->values();
    }

    /* ------------------------------------------------------------------ */
    /* Εξαρτήσεις tasks (blockers) + αρχεία                               */
    /* ------------------------------------------------------------------ */

    /** Τα tasks που μπλοκάρουν το $taskId (με done κατάσταση). */
    /** Προσωπική προτίμηση χρήστη (mod_cpm_prefs). */
    public static function pref($adminId, $key, $default = '')
    {
        $v = Capsule::table('mod_cpm_prefs')->where('admin_id', (int) $adminId)
            ->where('pref', $key)->value('value');
        return $v === null ? $default : $v;
    }

    public static function setPref($adminId, $key, $value)
    {
        $ex = Capsule::table('mod_cpm_prefs')->where('admin_id', (int) $adminId)->where('pref', $key);
        if ($ex->exists()) {
            $ex->update(['value' => (string) $value]);
        } else {
            Capsule::table('mod_cpm_prefs')->insert(['admin_id' => (int) $adminId,
                'pref' => $key, 'value' => (string) $value]);
        }
    }

    public static function depsOf($taskId)
    {
        return Capsule::table('mod_cpm_deps as d')
            ->join('mod_cpm_tasks as t', 't.id', '=', 'd.depends_on')
            ->where('d.task_id', (int) $taskId)
            ->get(['d.id as dep_id', 't.id', 't.title', 't.completed_at']);
    }

    public static function addDep($taskId, $dependsOn)
    {
        $taskId = (int) $taskId;
        $dependsOn = (int) $dependsOn;
        if ($taskId === $dependsOn || !self::task($dependsOn)) {
            return false;
        }
        // όχι άμεσος κύκλος (Α→Β και Β→Α)
        if (Capsule::table('mod_cpm_deps')->where('task_id', $dependsOn)->where('depends_on', $taskId)->exists()) {
            return false;
        }
        if (Capsule::table('mod_cpm_deps')->where('task_id', $taskId)->where('depends_on', $dependsOn)->exists()) {
            return true;
        }
        Capsule::table('mod_cpm_deps')->insert(['task_id' => $taskId, 'depends_on' => $dependsOn]);
        return true;
    }

    public static function delDep($depId)
    {
        Capsule::table('mod_cpm_deps')->where('id', (int) $depId)->delete();
    }

    /** Map task_id => [τίτλοι ανοιχτών blockers] για σύνολο tasks (1 query). */
    public static function blockedMap(array $taskIds)
    {
        if (!$taskIds) {
            return [];
        }
        $out = [];
        foreach (Capsule::table('mod_cpm_deps as d')
            ->join('mod_cpm_tasks as b', 'b.id', '=', 'd.depends_on')
            ->whereIn('d.task_id', $taskIds)->whereNull('b.completed_at')
            ->get(['d.task_id', 'b.title']) as $r) {
            $out[(int) $r->task_id][] = $r->title;
        }
        return $out;
    }

    public static function filesOf($taskId)
    {
        return Capsule::table('mod_cpm_files')->where('task_id', (int) $taskId)->orderBy('id', 'desc')->get();
    }

    /* ------------------------------------------------------------------ */
    /* Πρόσωπα & custom πεδία CRM (Attio-style)                           */
    /* ------------------------------------------------------------------ */

    public static function peopleFor($leadId = 0, $clientId = 0)
    {
        $q = Capsule::table('mod_cpm_people')->orderBy('id');
        if ($leadId) {
            $q->where('lead_id', (int) $leadId);
        } elseif ($clientId) {
            $q->where('clientid', (int) $clientId);
        }
        return $q->get();
    }

    public static function savePerson($id, array $data)
    {
        if ($id) {
            Capsule::table('mod_cpm_people')->where('id', (int) $id)->update($data);
            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        return (int) Capsule::table('mod_cpm_people')->insertGetId($data);
    }

    public static function delPerson($id)
    {
        Capsule::table('mod_cpm_people')->where('id', (int) $id)->delete();
    }

    public static function leadFields()
    {
        return Capsule::table('mod_cpm_lead_fields')->orderBy('sort')->orderBy('id')->get();
    }

    public static function leadValues($leadId)
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_lead_values')->where('lead_id', (int) $leadId)->get() as $r) {
            $out[(int) $r->field_id] = $r->value;
        }
        return $out;
    }

    public static function saveLeadValue($leadId, $fieldId, $value)
    {
        $ex = Capsule::table('mod_cpm_lead_values')
            ->where('lead_id', (int) $leadId)->where('field_id', (int) $fieldId);
        if ($ex->exists()) {
            $ex->update(['value' => (string) $value]);
        } else {
            Capsule::table('mod_cpm_lead_values')->insert([
                'lead_id' => (int) $leadId, 'field_id' => (int) $fieldId, 'value' => (string) $value,
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Επικοινωνίες CRM (interactions)                                    */
    /* ------------------------------------------------------------------ */

    /** Είδη επικοινωνίας: key => [τίτλος, fa-icon, χρώμα]. */
    public static function interactionKinds()
    {
        return [
            'call'    => ['Τηλεφώνημα', 'fas fa-phone-alt', '#0097e4'],
            'email'   => ['Email', 'fas fa-envelope', '#7b5cd6'],
            'meeting' => ['Συνάντηση', 'fas fa-handshake', '#1f9d57'],
            'note'    => ['Σημείωση', 'fas fa-sticky-note', '#8291a9'],
        ];
    }

    public static function interaction($id)
    {
        return Capsule::table('mod_cpm_interactions')->where('id', (int) $id)->first();
    }

    public static function addInteraction(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = (int) Capsule::table('mod_cpm_interactions')->insertGetId($data);
        // follow-up σε lead ενημερώνει και την «Επόμενη ενέργεια» του lead
        if (!empty($data['lead_id']) && !empty($data['followup_date'])) {
            self::saveLead($data['lead_id'], [
                'next_action' => $data['followup_date'],
                'next_note'   => $data['followup_note'] ?? null,
            ]);
        }
        return $id;
    }

    public static function deleteInteraction($id)
    {
        Capsule::table('mod_cpm_interactions')->where('id', (int) $id)->delete();
    }

    public static function interactionsForLead($leadId, $limit = 100)
    {
        return Capsule::table('mod_cpm_interactions')->where('lead_id', (int) $leadId)
            ->orderBy('happened_at', 'desc')->orderBy('id', 'desc')->limit($limit)->get();
    }

    public static function interactionsForClient($uid, $limit = 100)
    {
        return Capsule::table('mod_cpm_interactions')->where('clientid', (int) $uid)
            ->orderBy('happened_at', 'desc')->orderBy('id', 'desc')->limit($limit)->get();
    }

    public static function recentInteractions($limit = 50)
    {
        return Capsule::table('mod_cpm_interactions as i')
            ->leftJoin('mod_cpm_leads as l', 'l.id', '=', 'i.lead_id')
            ->select('i.*', 'l.company as lead_company', 'l.contact as lead_contact')
            ->orderBy('i.happened_at', 'desc')->orderBy('i.id', 'desc')->limit($limit)->get();
    }

    /** Εκκρεμή follow-ups ΠΕΛΑΤΩΝ από επικοινωνίες (των leads έρχονται από next_action). */
    public static function pendingClientFollowups($until = null)
    {
        $q = Capsule::table('mod_cpm_interactions')
            ->whereNotNull('followup_date')->where('followup_done', 0)
            ->whereNull('lead_id')->whereNotNull('clientid')
            ->orderBy('followup_date');
        if ($until) {
            $q->where('followup_date', '<=', $until);
        }
        return $q->get();
    }

    /** Map «τελευταία επαφή»: ['lead:<id>' => ts, 'client:<id>' => ts]. */
    public static function lastContactMap()
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_interactions')
            ->selectRaw('lead_id, clientid, MAX(happened_at) as ts')
            ->groupBy('lead_id')->groupBy('clientid')->get() as $r) {
            if ($r->lead_id) {
                $key = 'lead:' . $r->lead_id;
                $out[$key] = max($out[$key] ?? '', $r->ts);
            }
            if ($r->clientid) {
                $key = 'client:' . $r->clientid;
                $out[$key] = max($out[$key] ?? '', $r->ts);
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Στόχοι πωλήσεων ανά προϊόν                                         */
    /* ------------------------------------------------------------------ */

    public static function productTargets()
    {
        return Capsule::table('mod_cpm_product_targets as t')
            ->join('tblproducts as p', 'p.id', '=', 't.product_id')
            ->select('t.*', 'p.name as product_name', 'p.gid')
            ->orderBy('p.name')->get();
    }

    public static function saveProductTarget($productId, $units, $value)
    {
        $exists = Capsule::table('mod_cpm_product_targets')->where('product_id', (int) $productId)->exists();
        $data = ['target_units' => max(0, (int) $units), 'target_value' => round((float) $value, 2)];
        if ($exists) {
            Capsule::table('mod_cpm_product_targets')->where('product_id', (int) $productId)->update($data);
        } else {
            $data['product_id'] = (int) $productId;
            $data['created_at'] = date('Y-m-d H:i:s');
            Capsule::table('mod_cpm_product_targets')->insert($data);
        }
    }

    public static function deleteProductTarget($id)
    {
        Capsule::table('mod_cpm_product_targets')->where('id', (int) $id)->delete();
    }

    /**
     * Πραγματικές νέες πωλήσεις μήνα ανά προϊόν (νέες υπηρεσίες tblhosting):
     * map product_id => [units, value€ (άθροισμα recurring amount)].
     */
    public static function productSalesForMonth($ym)
    {
        $from = $ym . '-01';
        $to = date('Y-m-t', strtotime($from));
        $out = [];
        foreach (Capsule::table('tblhosting')
            ->whereBetween('regdate', [$from, $to])
            ->whereNotIn('domainstatus', ['Cancelled', 'Fraud'])
            ->selectRaw('packageid, COUNT(*) n, SUM(amount) v')->groupBy('packageid')->get() as $r) {
            $out[(int) $r->packageid] = [(int) $r->n, (float) $r->v];
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Client timeline (5.1) — ενιαίο ιστορικό πελάτη                     */
    /* ------------------------------------------------------------------ */

    /**
     * Συγχωνευμένο ιστορικό πελάτη: tasks, χρόνος, tickets, κινήσεις
     * προαγοράς (supportcontracts), προσφορές, πληρωμές.
     * Returns array of ['ts','type','title','meta','link'] sorted desc.
     */
    public static function clientTimeline($uid, $since, $limit = 200)
    {
        $uid = (int) $uid;
        $ev = [];
        if (!class_exists(Time::class)) {
            require_once __DIR__ . '/Time.php'; // για Time::fmt όταν φορτώνεται μόνο το Db
        }

        // tasks του πελάτη (μέσω projects)
        $tasks = Capsule::table('mod_cpm_tasks as t')
            ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->where('p.clientid', $uid)
            ->select('t.id', 't.title', 't.created_at', 't.completed_at', 'p.name as pname')->get();
        foreach ($tasks as $t) {
            if ($t->created_at >= $since) {
                $ev[] = ['ts' => $t->created_at, 'type' => 'task', 'title' => 'Νέο task: ' . $t->title,
                         'meta' => $t->pname, 'link' => 'task:' . $t->id];
            }
            if ($t->completed_at && $t->completed_at >= $since) {
                $ev[] = ['ts' => $t->completed_at, 'type' => 'task_done', 'title' => 'Ολοκληρώθηκε: ' . $t->title,
                         'meta' => $t->pname, 'link' => 'task:' . $t->id];
            }
        }

        // χρόνος εργασίας (μέσω projects του πελάτη ή sc_userid)
        $logs = Capsule::table('mod_cpm_timelogs as l')
            ->join('mod_cpm_tasks as t', 't.id', '=', 'l.task_id')
            ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->where('l.running', 0)->where('l.created_at', '>=', $since)
            ->where(function ($w) use ($uid) {
                $w->where('p.clientid', $uid)->orWhere('l.sc_userid', $uid);
            })
            ->select('l.*', 't.title as ttitle')->get();
        foreach ($logs as $l) {
            $ev[] = ['ts' => $l->created_at, 'type' => $l->billable ? 'time_bill' : 'time',
                     'title' => 'Εργασία ' . Time::fmt($l->minutes) . ($l->billable ? ' (χρέωση ' . Time::fmt($l->charged_minutes) . ')' : ' (χωρίς χρέωση)') . ' — ' . $l->ttitle,
                     'meta' => self::adminName($l->admin_id) . ($l->note ? ' · ' . $l->note : ''), 'link' => 'task:' . $l->task_id];
        }

        // tickets
        foreach (Capsule::table('tbltickets')->where('userid', $uid)
            ->where('date', '>=', $since)->get(['id', 'tid', 'title', 'status', 'date']) as $tk) {
            $ev[] = ['ts' => $tk->date, 'type' => 'ticket', 'title' => 'Ticket #' . $tk->tid . ': ' . $tk->title,
                     'meta' => $tk->status, 'link' => 'ticket:' . $tk->id];
        }

        // κινήσεις προαγοράς supportcontracts
        try {
            if (Capsule::schema()->hasTable('mod_supportcontracts_ledger')) {
                $typeL = ['topup' => 'Αγορά χρόνου', 'usage' => 'Ανάλωση χρόνου', 'adjust' => 'Διόρθωση', 'init' => 'Αρχικοποίηση'];
                foreach (Capsule::table('mod_supportcontracts_ledger')->where('userid', $uid)
                    ->where('created_at', '>=', $since)->get() as $lg) {
                    $ev[] = ['ts' => $lg->created_at, 'type' => $lg->minutes >= 0 ? 'sc_plus' : 'sc_minus',
                             'title' => ($typeL[$lg->type] ?? $lg->type) . ': ' . ($lg->minutes >= 0 ? '+' : '') . Time::fmt(abs($lg->minutes)) . ' → υπόλοιπο ' . Time::fmt($lg->balance_after),
                             'meta' => (string) $lg->note, 'link' => ''];
                }
            }
        } catch (\Throwable $e) {
            // supportcontracts απών — παράλειψη
        }

        // προσφορές
        foreach (Capsule::table('mod_cpm_offers')->where('clientid', $uid)->get() as $o) {
            $stages = self::offerStages();
            if ($o->created_at >= $since) {
                $ev[] = ['ts' => $o->created_at, 'type' => 'offer', 'title' => 'Νέα προσφορά: ' . $o->title,
                         'meta' => $stages[$o->stage][0] ?? $o->stage, 'link' => 'offer:' . $o->id];
            }
            if ($o->closed_at && $o->closed_at >= $since) {
                $won = !empty($stages[$o->stage][3]);
                $ev[] = ['ts' => $o->closed_at, 'type' => $won ? 'offer_won' : 'offer_lost',
                         'title' => ($won ? 'Κερδισμένη' : 'Χαμένη') . ' προσφορά: ' . $o->title,
                         'meta' => '', 'link' => 'offer:' . $o->id];
            }
        }

        // επικοινωνίες CRM
        $kinds = self::interactionKinds();
        foreach (Capsule::table('mod_cpm_interactions')->where('clientid', $uid)
            ->where('happened_at', '>=', $since)->get() as $i) {
            $ev[] = ['ts' => $i->happened_at, 'type' => 'contact',
                     'title' => ($kinds[$i->kind][0] ?? $i->kind) . ': ' . $i->summary,
                     'meta' => self::adminName($i->admin_id)
                        . ($i->followup_date && !$i->followup_done ? ' · follow-up ' . date('d/m', strtotime($i->followup_date)) : ''),
                     'link' => ''];
        }

        // πληρωμές
        foreach (Capsule::table('tblaccounts')->where('userid', $uid)
            ->where('amountin', '>', 0)->where('date', '>=', $since)
            ->get(['date', 'amountin', 'gateway', 'invoiceid']) as $p) {
            $ev[] = ['ts' => $p->date, 'type' => 'payment',
                     'title' => 'Πληρωμή ' . number_format((float) $p->amountin, 2, ',', '.') . ' €',
                     'meta' => $p->gateway . ($p->invoiceid ? ' · Invoice #' . $p->invoiceid : ''),
                     'link' => $p->invoiceid ? 'invoice:' . $p->invoiceid : ''];
        }

        usort($ev, function ($a, $b) {
            return strcmp($b['ts'], $a['ts']);
        });
        return array_slice($ev, 0, $limit);
    }

    /* ------------------------------------------------------------------ */
    /* Comments (1.9) + Activity (1.10)                                   */
    /* ------------------------------------------------------------------ */

    public static function addComment($taskId, $adminId, $comment, $toAdmin = null)
    {
        Capsule::table('mod_cpm_comments')->insert([
            'task_id'    => (int) $taskId,
            'admin_id'   => $adminId ? (int) $adminId : null,
            'comment'    => $comment,
            'to_admin'   => $toAdmin !== null ? (int) $toAdmin : null, // -1 = προς διαχειριστές
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        self::logActivity($taskId, $adminId, 'comment', mb_substr($comment, 0, 120));
    }

    public static function comments($taskId)
    {
        return Capsule::table('mod_cpm_comments')->where('task_id', (int) $taskId)->orderBy('id')->get();
    }

    public static function logActivity($taskId, $adminId, $action, $detail = '')
    {
        Capsule::table('mod_cpm_activity')->insert([
            'task_id'    => (int) $taskId,
            'admin_id'   => $adminId ? (int) $adminId : null,
            'action'     => $action,
            'detail'     => mb_substr((string) $detail, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function activity($taskId, $limit = 50)
    {
        return Capsule::table('mod_cpm_activity')->where('task_id', (int) $taskId)
            ->orderBy('id', 'desc')->limit($limit)->get();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    public static function admins()
    {
        return Capsule::table('tbladmins')->where('disabled', 0)
            ->orderBy('firstname')->get(['id', 'firstname', 'lastname']);
    }

    public static function adminName($id)
    {
        static $cache = [];
        $id = (int) $id;
        if (!$id) { return '—'; }
        if (!isset($cache[$id])) {
            $a = Capsule::table('tbladmins')->where('id', $id)->first(['firstname', 'lastname']);
            $cache[$id] = $a ? trim($a->firstname . ' ' . $a->lastname) : ('#' . $id);
        }
        return $cache[$id];
    }
}
