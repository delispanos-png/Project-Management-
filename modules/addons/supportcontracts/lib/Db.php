<?php
/**
 * Support Contracts & SLA — data layer (schema + CRUD + prepaid-time ledger).
 *
 * Tables (all owned by this module):
 *   mod_supportcontracts_clients  — one contract row per client (balance, SLA, priority, business hours)
 *   mod_supportcontracts_ledger   — every prepaid-time movement (top-up / usage / adjust) with running balance
 *   mod_supportcontracts_tickets  — per-ticket billable flag, logged minutes, SLA due/first-response tracking
 *
 * @package WHMCS\Module\Addon\SupportContracts
 */

namespace WHMCS\Module\Addon\SupportContracts;

use WHMCS\Database\Capsule;

class Db
{
    /* ------------------------------------------------------------------ */
    /* Schema                                                             */
    /* ------------------------------------------------------------------ */

    public static function install()
    {
        $s = Capsule::schema();

        if (!$s->hasTable('mod_supportcontracts_clients')) {
            $s->create('mod_supportcontracts_clients', function ($t) {
                $t->increments('id');
                $t->integer('userid')->unsigned()->unique();
                $t->tinyInteger('enabled')->default(1);
                $t->tinyInteger('priority')->default(0);           // 0 normal, 1 high, 2 critical
                $t->integer('balance_minutes')->default(0);        // current prepaid time balance (minutes)
                $t->integer('sla_response_value')->default(8);     // response time number
                $t->string('sla_response_unit', 10)->default('hours'); // hours | days
                $t->string('biz_days', 20)->default('1,2,3,4,5');  // ISO day nums 1=Mon..7=Sun
                $t->string('biz_start', 5)->default('09:00');
                $t->string('biz_end', 5)->default('17:00');
                $t->string('biz_tz', 40)->default('Europe/Athens');
                $t->string('label', 100)->nullable();
                $t->string('report_email', 255)->nullable();  // weekly-report recipients (comma-sep); empty = account email
                $t->text('notes')->nullable();                // free notes about the client / agreements / quirks
                $t->text('ticket_notes')->nullable();         // details shown to ticket handlers on the admin ticket page
                $t->text('covered')->nullable();              // which products/services this contract covers
                $t->string('contract_file', 255)->nullable(); // stored filename of the attached contract
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        // migrations: add new columns to an existing clients table
        foreach (['report_email' => 'string', 'notes' => 'text', 'ticket_notes' => 'text', 'covered' => 'text', 'contract_file' => 'string'] as $col => $kind) {
            if ($s->hasTable('mod_supportcontracts_clients') && !$s->hasColumn('mod_supportcontracts_clients', $col)) {
                $s->table('mod_supportcontracts_clients', function ($t) use ($col, $kind) {
                    $kind === 'text' ? $t->text($col)->nullable() : $t->string($col, 255)->nullable();
                });
            }
        }

        if (!$s->hasTable('mod_supportcontracts_ledger')) {
            $s->create('mod_supportcontracts_ledger', function ($t) {
                $t->increments('id');
                $t->integer('userid')->unsigned()->index();
                $t->integer('ticketid')->unsigned()->nullable()->index();
                $t->string('type', 12)->default('usage');          // topup | usage | adjust
                $t->integer('minutes');                            // signed: + top-up, - usage
                $t->integer('balance_after');
                $t->string('note', 255)->nullable();
                $t->integer('admin_id')->unsigned()->nullable();
                $t->string('ref', 64)->nullable()->index(); // idempotency key (e.g. inv:123:item:45)
                $t->timestamp('created_at')->nullable();
            });
        }
        // migration: add ref column to an existing ledger table
        if ($s->hasTable('mod_supportcontracts_ledger') && !$s->hasColumn('mod_supportcontracts_ledger', 'ref')) {
            $s->table('mod_supportcontracts_ledger', function ($t) {
                $t->string('ref', 64)->nullable()->index();
            });
        }

        if (!$s->hasTable('mod_supportcontracts_worklog')) {
            $s->create('mod_supportcontracts_worklog', function ($t) {
                $t->increments('id');
                $t->integer('userid')->unsigned()->index();
                $t->integer('ticketid')->unsigned()->nullable()->index();
                $t->integer('worked_minutes')->default(0);   // actual time worked
                $t->integer('charged_minutes')->default(0);  // what was deducted (0 for non-billable)
                $t->tinyInteger('billable')->default(1);
                $t->string('note', 255)->nullable();
                $t->integer('admin_id')->unsigned()->nullable();
                $t->string('gd_time_report_id', 32)->nullable()->index(); // GoodDay time-report ingestion (idempotency)
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!$s->hasTable('mod_supportcontracts_tickets')) {
            $s->create('mod_supportcontracts_tickets', function ($t) {
                $t->increments('id');
                $t->integer('ticketid')->unsigned()->unique();
                $t->integer('userid')->unsigned()->index();
                $t->tinyInteger('billable')->default(0);
                $t->integer('minutes_logged')->default(0);
                $t->dateTime('sla_due')->nullable();
                $t->dateTime('first_response_at')->nullable();
                $t->tinyInteger('sla_met')->nullable();            // null unknown, 1 met, 0 breached
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
    }

    public static function uninstall()
    {
        // Intentionally NON-destructive: keep tables/data on deactivate.
    }

    /* ------------------------------------------------------------------ */
    /* Contract (client) CRUD                                             */
    /* ------------------------------------------------------------------ */

    public static function contract($userid)
    {
        return Capsule::table('mod_supportcontracts_clients')->where('userid', (int) $userid)->first();
    }

    public static function allContracts()
    {
        return Capsule::table('mod_supportcontracts_clients')->orderBy('userid')->get();
    }

    public static function saveContract($userid, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $exists = self::contract($userid);
        $data['userid'] = (int) $userid;
        $data['updated_at'] = $now;
        if ($exists) {
            Capsule::table('mod_supportcontracts_clients')->where('userid', (int) $userid)->update($data);
        } else {
            $data['created_at'] = $now;
            if (!isset($data['balance_minutes'])) {
                $data['balance_minutes'] = 0;
            }
            Capsule::table('mod_supportcontracts_clients')->insert($data);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Prepaid-time ledger + balance                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Apply a signed minutes movement to a client's balance and record it.
     * $minutes: positive = top-up, negative = usage.
     * Returns the new balance (minutes), or null if no contract exists.
     */
    public static function applyMovement($userid, $minutes, $type, $note = '', $ticketid = null, $adminId = null, $ref = null)
    {
        $c = self::contract($userid);
        if (!$c) {
            return null;
        }
        $new = (int) $c->balance_minutes + (int) $minutes;
        Capsule::table('mod_supportcontracts_clients')->where('userid', (int) $userid)
            ->update(['balance_minutes' => $new, 'updated_at' => date('Y-m-d H:i:s')]);
        $row = [
            'userid'        => (int) $userid,
            'ticketid'      => $ticketid ? (int) $ticketid : null,
            'type'          => $type,
            'minutes'       => (int) $minutes,
            'balance_after' => $new,
            'note'          => mb_substr((string) $note, 0, 255),
            'admin_id'      => $adminId ? (int) $adminId : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ];
        if (Capsule::schema()->hasColumn('mod_supportcontracts_ledger', 'ref')) {
            $row['ref'] = $ref ? mb_substr((string) $ref, 0, 64) : null;
        }
        Capsule::table('mod_supportcontracts_ledger')->insert($row);
        return $new;
    }

    /** True if a ledger movement with this idempotency ref already exists. */
    public static function refExists($ref)
    {
        if (!$ref || !Capsule::schema()->hasColumn('mod_supportcontracts_ledger', 'ref')) {
            return false;
        }
        return Capsule::table('mod_supportcontracts_ledger')->where('ref', $ref)->exists();
    }

    /** Return the client's contract, creating one from $defaults if none exists. */
    public static function ensureContract($userid, array $defaults = [])
    {
        $c = self::contract($userid);
        if ($c) {
            return $c;
        }
        self::saveContract($userid, $defaults + ['enabled' => 1, 'balance_minutes' => 0]);
        return self::contract($userid);
    }

    public static function ledger($userid, $limit = 200)
    {
        return Capsule::table('mod_supportcontracts_ledger')
            ->where('userid', (int) $userid)->orderBy('id', 'desc')->limit($limit)->get();
    }

    /* ------------------------------------------------------------------ */
    /* Worklog (billable + non-billable time entries)                     */
    /* ------------------------------------------------------------------ */

    public static function addWork($userid, $ticketid, $workedMin, $chargedMin, $billable, $note = '', $adminId = null, $gdTimeReportId = null)
    {
        if ($gdTimeReportId && Capsule::table('mod_supportcontracts_worklog')
            ->where('gd_time_report_id', (string) $gdTimeReportId)->exists()) {
            return false; // already ingested from GoodDay
        }
        // returns the worklog id (truthy — backward compatible with boolean callers)
        return (int) Capsule::table('mod_supportcontracts_worklog')->insertGetId([
            'userid'            => (int) $userid,
            'ticketid'          => $ticketid ? (int) $ticketid : null,
            'worked_minutes'    => (int) $workedMin,
            'charged_minutes'   => (int) $chargedMin,
            'billable'          => $billable ? 1 : 0,
            'note'              => mb_substr((string) $note, 0, 255),
            'admin_id'          => $adminId ? (int) $adminId : null,
            'gd_time_report_id' => $gdTimeReportId ? (string) $gdTimeReportId : null,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public static function worklogSince($userid, $since)
    {
        return Capsule::table('mod_supportcontracts_worklog')
            ->where('userid', (int) $userid)->where('created_at', '>=', $since)
            ->orderBy('id')->get();
    }

    /* ------------------------------------------------------------------ */
    /* Per-ticket state                                                   */
    /* ------------------------------------------------------------------ */

    public static function ticket($ticketid)
    {
        return Capsule::table('mod_supportcontracts_tickets')->where('ticketid', (int) $ticketid)->first();
    }

    public static function saveTicket($ticketid, array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (self::ticket($ticketid)) {
            Capsule::table('mod_supportcontracts_tickets')->where('ticketid', (int) $ticketid)->update($data);
        } else {
            $data['ticketid'] = (int) $ticketid;
            $data['created_at'] = $now;
            Capsule::table('mod_supportcontracts_tickets')->insert($data);
        }
    }

    /** Tickets flagged billable but with no time logged yet (admin worklist). */
    public static function pendingBillable($limit = 100)
    {
        return Capsule::table('mod_supportcontracts_tickets as st')
            ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
            ->where('st.billable', 1)->where('st.minutes_logged', 0)
            ->orderBy('st.updated_at', 'desc')->limit($limit)
            ->get(['st.ticketid', 'st.userid', 't.tid', 't.title', 't.status']);
    }
}
