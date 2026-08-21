<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use core\setting\Setting;
use equal\email\Email;
use contractika\SALine;
use contractika\SALineType;
use contractika\ServiceAccount;
use contractika\hr\employee\Employee;
use contractika\hr\employee\Role;
use contractika\Report;

list($params, $providers) = eQual::announce([
    'description'   => 'Synchronizes service accounts (lines) with time entries from AutoTask.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'constants'         => ['EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT', 'EMAIL_SA_ALERTS_BCC'],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [
    'warnings'      => 0,
    'errors'        => 0,
    'created'       => 0,
    'updated'       => 0,
    'ignored'       => 0,
    'logs'          => [],
    'alerts'        => [],
    'created_ids'   => '',
    'updated_ids'   => '',
    'ignored_ids'   => ''
];

$now = time();
$last_run = Setting::get_value('contractika', 'sync', 'at_sync_timeentries.last_run', strtotime("-4 weeks"));

// start by updating new last_run value (to prevent ignoring time entries that would have been posted during execution of the script)
Setting::set_value('contractika', 'sync', 'at_sync_timeentries.last_run', $now);

try {
    // remove all pending Reports (this will reset has_report and report_id for attached SA Lines)
    // #memo - updating SA Lines values for lines that are attached to a Report would invalidate the Report and is therefore prohibited
    Report::search(['status', '=', 'pending'])->delete(true);

    // map for object dependencies
    $map_tickets_ids = [];
    $map_tasks_ids = [];
    $map_contacts_ids = [];
    // workaround for invalid time entries, with missing contractID
    $map_missing_contract_lines = [];

    /**
     * Array storing all involved SA Lines (used after the process, for resetting computed fields)
     */
    $sa_lines_ids = [];
    $result['logs'][] = "OK  - fetching time entries created or updated since ".date('Y-m-d', $last_run);
    $time_entries = eQual::run('get', 'contractika_at_timeentries', ['date_from' => $last_run]);
    $result['logs'][] = "OK  - fetched: ".count($time_entries)." time entries.";

    /**
     * Pass-1: check lines from time entries and create new ones (+ mark dependencies for differed requests)
     */
    foreach($time_entries as $time_entry) {
        // discard time entries that are marked as non billable

        if($time_entry['isNonBillable']) {
            continue;
        }

        // ignore contract used for internal management
        /*
        // #memo - we need this for Bonus reports
        if(in_array($time_entry['contractID'], [29683360])) {
            ++$result['ignored'];
            $result['ignored_ids'] .= "{$time_entry['id']},";
            continue;
        }
        */

        // #memo - ignore deprecated code for Travels (now included in SALines)
        if(in_array((string) $time_entry['billingCodeID'], ['29683328'])) {
            ++$result['ignored'];
            $result['ignored_ids'] .= "{$time_entry['id']},";
            continue;
        }

        try {

            // 1) build the values map based on received time entry

            // #important - ignore everything before 2023-01-01 (transition from EuroJob to Contractika)
            if(strtotime($time_entry['dateWorked']) < 1672531200) {
                ++$result['ignored'];
                $result['ignored_ids'] .= "{$time_entry['id']},";
                continue;
            }

            $start_time = strtotime($time_entry['startDateTime']);
            $end_time = strtotime($time_entry['endDateTime']);

            $time_worked = $time_entry['hoursWorked'] * 3600;
            // #memo - we adjust the offset in order to consider the chrono stops, if any (which is not the case in AT as of 2023-02-15)
            if($time_worked != ($end_time-$start_time)) {
                $time_entry['offsetHours'] -= ($end_time - $start_time - $time_worked) / 3600;
            }

            // map input data to values to be assigned (used either to create or update related SA Line)
            $values = [
                    'timeEntryID'       => $time_entry['id'],
                    'resourceID'        => $time_entry['resourceID'],
                    'description'       => $time_entry['summaryNotes'],
                    'date'              => strtotime($time_entry['dateWorked']),
                    'start'             => $start_time,
                    'end'               => $end_time,
                    'pause'             => round((float) $time_entry['offsetHours'], 2),
                    'createDateTime'    => strtotime($time_entry['createDateTime'])
                ];

            // #memo - $time_entry['timeEntryType']) should also reflect the kind of entry (2=>ticket, 6=>task)
            if(!is_null($time_entry['ticketID'])) {
                $values['ticketID'] = $time_entry['ticketID'];
                $values['has_ticket'] = true;
                $values['sa_line_class_id'] = 2;
            }
            elseif(!is_null($time_entry['taskID'])) {
                $values['taskID'] = $time_entry['taskID'];
                $values['has_task'] = true;
                $values['sa_line_class_id'] = 1;
            }
            else {
                // error: unexpected structure
                throw new Exception("Invalid time entry: no ticketID nor taskID for TimeEntry {$time_entry['id']}", QN_ERROR_INVALID_PARAM);
            }

            // find related resource
            $employee = Employee::search(['extref_at_id', '=', $time_entry['resourceID']])->read(['id'])->first();
            if(!$employee) {
                throw new Exception("Employee not found for Resource {$time_entry['resourceID']} (sync?)", QN_ERROR_UNKNOWN_OBJECT);
            }
            $values['employee_id'] = $employee['id'];

            // find related role
            $role = Role::search(['extref_at_id', '=', $time_entry['roleID']])->read(['id'])->first();
            if(!$role) {
                throw new Exception("Role not found for Role {$time_entry['roleID']} (sync?)", QN_ERROR_UNKNOWN_OBJECT);
            }
            $values['role_id'] = $role['id'];

            // find related SALineType
            if(is_null($time_entry['billingCodeID'])) {
                /*
                ++$result['warnings'];
                $result['logs'][] = "WARN- BillingCode is missing (null) for time entry {$time_entry['id']}";
                */
                $reporter->warning("BillingCode is missing (null) for time entry {$time_entry['id']}");
            }
            else {
                $type = SALineType::search(['extref_at_id', '=', $time_entry['billingCodeID']])->read(['id'])->first();
                if(!$type) {
                    throw new Exception("SALineType not found for BillingCode {$time_entry['billingCodeID']} (sync?)", QN_ERROR_UNKNOWN_OBJECT);
                }
                $values['sa_line_type_id'] = $type['id'];
            }

            // 2) create or update the line

            $line = SALine::search(['timeEntryID', '=', $time_entry['id']])->read(['id', 'is_locked', 'is_async'])->first();

            // line does not exist yet: try to create it
            if(!$line) {
                $line = SALine::create($values)->read(['id'])->first();
                if(!$line) {
                    throw new Exception("Unexpected error: unable to create new line for timeEntry {$time_entry['id']}", QN_ERROR_UNKNOWN_OBJECT);
                }
                ++$result['created'];
                $result['created_ids'] .= "{$time_entry['id']}[{$line['id']}],";
                $sa_lines_ids[] = $line['id'];
            }
            // line already exists: update it
            else {
                // ignore time entries that are locked in CT and that has already raised a notification
                if($line['is_async']) {
                    continue;
                }
                if(!$line['is_locked']) {
                    SALine::id($line['id'])->update($values);
                    ++$result['updated'];
                    $result['updated_ids'] .= "{$time_entry['id']}[{$line['id']}],";
                    $sa_lines_ids[] = $line['id'];
                }
                else {
                    // #memo - code below will raise an exception since the line is locked (prevent further notifications)
                    SALine::id($line['id'])->update(['is_async' => true]);
                }
            }

            // find related service account, if any
            $account = ServiceAccount::search(['contractId', '=', $time_entry['contractID']])->read(['id'])->first();
            if(!$account) {
                // queue time entry for contractID retrieval through related Ticket or Task
                // throw new Exception("Service account not found for Contract {$time_entry['contractID']} (sync?)", QN_ERROR_UNKNOWN_OBJECT);
                $map_missing_contract_lines[$line['id']] = true;
            }
            else {
                // #memo - we need to this this after creation/update (not to conflict with onupdateServiceAccountId)
                SALine::id($line['id'])->update(['service_account_id' => $account['id']]);
            }

            // 3) check for relations with Tickets/Tasks

            // if entry is linked to a ticket, queue it for deferred loading
            if(!is_null($time_entry['ticketID'])) {
                $map_tickets_ids[$time_entry['ticketID']][] = true;
            }

            // if entry is linked to a task, queue it for deferred loading
            if(!is_null($time_entry['taskID'])) {
                $map_tasks_ids[$time_entry['taskID']][] = true;
            }

        }
        catch(Exception $e) {
            /*
                something went wrong for that line: enqueue a notification
            */
            // try to retrieve line details if existing
            $time_entry_details = 'timeEntryID '.$time_entry['id']. ' - '.substr($time_entry['dateWorked'], 0, 10);
            $line = SALine::search(['timeEntryID', '=', $time_entry['id']])->read(['id', 'has_ticket', 'ticketNumber', 'has_task', 'taskNumber'])->first();
            if($line) {
                $time_entry_details .= ' - SALine '.$line['id'];
                if($line['has_ticket']) {
                    $time_entry_details .= ' - ticketNumber '.$line['ticketNumber'];
                }
                elseif($line['has_task']) {
                    $time_entry_details .= ' - taskNumber '.$line['taskNumber'];
                }
            }
            else {
                $time_entry_details .= ' - (no SALine)';
            }
            if(isset($time_entry['taskID'])) {
                $time_entry_details .= ' - taskID '.$time_entry['taskID'];
            }
            elseif(isset($time_entry['ticketID'])) {
                $time_entry_details .= ' - ticketID '.$time_entry['ticketID'];
            }
            // this is not a warning, the entry could not be imported : we need to try re-synch later
            ++$result['errors'];
            $result['logs'][] = "ERR - something went wrong for time entry [$time_entry_details]: ".$e->getMessage();
        }
    }

    // dependencies: pass-1 - Tickets

    // retrieve all tickets that have changed since last sync
    // #memo - we need to do this because the AT API do not fetch time entries that have been indirectly updated (i.e. through their parent, ticket or task)
    $tickets = eQual::run('get', 'contractika_at_tickets', ['date_from' => $last_run]);
    foreach($tickets as $ticket) {
        $map_tickets_ids[$ticket['id']] = true;
    }
    $tickets_ids = array_keys($map_tickets_ids);
    if(count($tickets_ids)) {
        foreach(array_chunk($tickets_ids, 100) as $chunk_ids) {
            $tickets = eQual::run('get', 'contractika_at_tickets', ['ids' => $chunk_ids]);
            $result['logs'][] = "OK  - fetched: ".count($tickets)." tickets.";
            foreach($tickets as $ticket) {
                // #memo - lines should have already been updated (only edge case: existing line but not returned on lastActivityDateTime filter)
                // fetch lines relating to ticket
                $lines = SALine::search(['ticketID', '=', $ticket['id']])->read(['id', 'is_locked', 'is_async', 'priority', 'ticketNumber', 'ticketDescription', 'ticketCategory', 'timeEntryID', 'sa_line_type_id' => ['extref_at_id']]);

                // update lines according to ticket
                foreach($lines as $line_id => $line) {
                    if($line['is_locked']) {
                        continue;
                    }
                    // ignore lines marked as modified in AT but locked in CT
                    if($line['is_async']) {
                        continue;
                    }
                    $is_updated = false;
                    try {
                        // update contact
                        if(!is_null($ticket['contactID'])) {
                            if(!isset($map_contacts_ids[$ticket['contactID']])) {
                                $map_contacts_ids[$ticket['contactID']] = [];
                            }
                            $map_contacts_ids[$ticket['contactID']][] = $line_id;
                        }
                        // update contract (Service Account)
                        if(!is_null($ticket['contractID'])) {
                            if(isset($map_missing_contract_lines[$line_id])) {
                                // find related service account
                                $account = ServiceAccount::search(['contractId', '=', $ticket['contractID']])->read(['id'])->first();
                                if($account) {
                                    SALine::id($line_id)->update(['service_account_id' => $account['id']]);
                                    $is_updated = true;
                                }
                                else {
                                    ++$result['warnings'];
                                    $result['logs'][] = "WARN- Service account not present in time entry nor ticket for line {$line_id}";
                                }
                            }
                        }
                        // update 'last change' according to ticket (some entries might not have been fetched)
                        if(isset($ticket['lastActivityDate'])) {
                            // #memo - we ignore this in order to prevent raising an error on locked SA Lines
                            // SALine::id($line_id)->update(['ticketLastActivityDate' => strtotime($ticket['lastActivityDate'])]);
                        }
                        // update ticket number
                        if(isset($ticket['ticketNumber'])) {
                            if($line['ticketNumber'] != $ticket['ticketNumber']) {
                                SALine::id($line_id)->update(['ticketNumber' => $ticket['ticketNumber']]);
                                $is_updated = true;
                            }
                        }
                        else {
                            // make sure ticketNumber property is set (needed in logs)
                            $ticket['ticketNumber'] = null;
                        }
                        // update ticket category
                        /*
                            // #memo - ticketCategory is a picklist in AutoTask with following values
                            1: Standard (non-editable)
                            2: RMM Datto
                            3: Remote
                            4: Datto
                            5: RMA
                            100: New Employee On-boarding
                            102: Onboarding
                            104: Support (OLD to remove)
                            105: Atelier
                            106: Preload
                            107: RMM
                            108: Onsite
                            109: Sales
                            110: Renewals
                            111: Admin
                            112: Standby
                        */
                        if(isset($ticket['ticketCategory'])) {
                            if($line['ticketCategory'] != $ticket['ticketCategory']) {
                                // if ticket marked as Onsite, check consistency with line type
                                if($ticket['ticketCategory'] == 108) {
                                    if(in_array((string) $line['sa_line_type_id']['extref_at_id'], ['29683500', '29683517'])) {
                                        /*
                                        ++$result['warnings'];
                                        $result['logs'][] = "WARN- Line marked both as Helpdesk and Onsite for time entry {$line['timeEntryID']} [{$line_id}] (ticket={$ticket['ticketNumber']}, type={$line['sa_line_type_id']['extref_at_id']}, category={$ticket['ticketCategory']})";
                                        */
                                        // don't store the ticket category (since it is irrelevant)
                                        $reporter->warning("Line marked both as Helpdesk and Onsite for time entry {$line['timeEntryID']} [{$line_id}] (ticket={$ticket['ticketNumber']}, type={$line['sa_line_type_id']['extref_at_id']}, category={$ticket['ticketCategory']})");
                                    }
                                    else {
                                        // #memo - this triggers `SALine::onupdateTicketCategory()`
                                        SALine::id($line_id)->update(['ticketCategory' => $ticket['ticketCategory']]);
                                        $is_updated = true;
                                    }
                                }
                                else {
                                    // #memo - this triggers `SALine::onupdateTicketCategory()`
                                    SALine::id($line_id)->update(['ticketCategory' => $ticket['ticketCategory']]);
                                    $is_updated = true;
                                }
                            }
                        }
                        // update ticket description
                        if(isset($ticket['title']) && strlen($ticket['title'])) {
                            $title = substr($ticket['title'], 0, 255);
                            if($title != $line['ticketDescription']) {
                                SALine::id($line_id)->update(['ticketDescription' => $title]);
                                $is_updated = true;
                            }
                        }
                        else {
                            // if there's no title, fallback to ticket description
                            if(isset($ticket['description']) && strlen($ticket['description'])) {
                                $description = substr($ticket['description'], 0, 255);
                                if($description != $line['ticketDescription']) {
                                    SALine::id($line_id)->update(['ticketDescription' => $description]);
                                    $is_updated = true;
                                }
                            }
                        }
                        // update priority
                        /*
                            // priority is set at the Ticket or Task level and selected as a picklist with following values
                            // #memo - despite its presence in the Task schema, priority seems to be always set to 0 for Tasks
                            1:	High        => 3
                            2:	Medium      => 2
                            3:	Low         => 1
                            4:	Critical    => 4
                        */
                        $map_priority = [
                            1 => 3,
                            2 => 2,
                            3 => 1,
                            4 => 4
                        ];
                        if(isset($ticket['priority']) && $ticket['priority'] > 0) {
                            if(isset($map_priority[$ticket['priority']]) && $map_priority[$ticket['priority']] != $line['priority']) {
                                SALine::id($line_id)->update(['priority' => $map_priority[$ticket['priority']]]);
                                $is_updated = true;
                            }
                        }

                        if($is_updated) {
                            // remember involved SALines (for refreshing computed fields)
                            $sa_lines_ids[] = $line_id;
                        }
                    }
                    catch(Exception $e) {
                        // error: unexpected timeentry update (locked SA line ?)
                        // #memo - this seems to be triggered by a change on TicketLastActivityDate and leads to false positive warnings - disabled
                        // ++$result['warnings'];
                        // $result['logs'][] = "WARN- Error while updating from Ticket[{$ticket['ticketNumber']} - {$ticket['id']}]: SA Line[{$line_id}] could not be updated; ".$e->getMessage();
                    }
                }
            }
        }
    }

    // dependencies: pass-2 - Tasks

    // retrieve all tasks that have changed since last sync
    // #memo - we need to do this because the AT API do not fetch time entries that have been indirectly updated (i.e. through their parent, ticket or task)
    $tasks = eQual::run('get', 'contractika_at_tasks', ['date_from' => $last_run]);
    foreach($tasks as $task) {
        $map_tasks_ids[$task['id']] = true;
    }
    $tasks_ids = array_keys($map_tasks_ids);
    if(count($tasks_ids)) {
        foreach(array_chunk($tasks_ids, 100) as $chunk_ids) {
            $tasks = eQual::run('get', 'contractika_at_tasks', ['ids' => $chunk_ids]);
            $result['logs'][] = "OK  - fetched: ".count($tickets)." tasks.";
            foreach($tasks as $task) {
                // #memo - lines should already have been updated (only edge case: existing line but not returned on lastActivityDateTime filter)
                // fetch lines relating to ticket
                $lines = SALine::search(['taskID', '=', $task['id']])->read(['id', 'is_locked', 'is_async'])->get();
                // update lines according to task
                foreach($lines as $line_id => $line) {
                    if($line['is_locked']) {
                        // error: unexpected timeentry (locked SA line)

                        // #memo - this seems to be triggered by a change on TicketLastActivityDate and leads to false positive warnings - disabled
                        /*
                        ++$result['warnings'];
                        $result['logs'][] = "WARN- Non-writable SA Line: Task[{$task['taskNumber']} - {$task['id']}] relates to SA Line[{$line_id}] that is locked and cannot be updated.";
                        */
                        continue;
                    }
                    // ignore lines marked as modified in AT but locked in CT
                    if($line['is_async']) {
                        continue;
                    }
                    // remember involved SALines (for refreshing computed fields)
                    $sa_lines_ids[] = $line_id;

                    // #memo - priority is set to 0 for tasks
                    // #memo - there is no contact associated with tasks

                    // TaskCategoryID seems to always be set to 2
                    if(isset($task['taskCategoryID'])) {
                        if($task['taskCategoryID'] == 2) {
                            // taskCategory is not stored (so far)
                        }
                    }

                    if(isset($task['taskNumber'])) {
                        SALine::id($line_id)->update(['taskNumber' => $task['taskNumber']]);
                    }

                    if(isset($task['description']) && $task['description']) {
                        SALine::id($line_id)->update(['taskDescription' => substr($task['description'], 0, 255)]);
                    }

                    // if projectID is present, request related entity from AT
                    if(isset($task['projectID']) && $task['projectID'] > 0) {
                        $projects = eQual::run('get', 'contractika_at_projects', ['ids' => (array) $task['projectID']]);
                        $project = (count($projects) > 0) ? current($projects) : null;
                        if($project) {
                            if(empty($project['contractID'])) {
                                ++$result['warnings'];
                                $result['logs'][] = "WARN- No contractID for Project [{$task['projectID']}] referenced in task [{$task['id']} - {$task['taskNumber']}] (sync?)";

                                $result['alerts'][] = [
                                    "Projet sans contrat associé" => "{$project['projectNumber']} {$project['projectName']}",
                                    "Tâche concernée"             => "{$task['taskNumber']}",
                                    "Action attendue"             => "Vérifier ou créer le Contrat/Service Account associé au projet."
                                ];
                            }
                            else {
                                // find related service account
                                $account = ServiceAccount::search(['contractId', '=', $project['contractID']])->read(['id'])->first();
                                if($account) {
                                    // use Project ContractID for lines relating to the Task that don't have a contractID yet
                                    if(isset($map_missing_contract_lines[$line_id])) {
                                        SALine::id($line_id)->update(['service_account_id' => $account['id']]);
                                    }
                                }
                                else {
                                    ++$result['warnings'];
                                    $result['logs'][] = "WARN- Service account referenced in [{$task['projectID']}] not present for task [{$task['id']} - {$task['taskNumber']}] (sync?)";
                                    $result['alerts'][] = [
                                        "Projet sans contrat associé"   => "{$project['projectNumber']} {$project['projectName']}",
                                        "Tâche concernée"               => "{$task['taskNumber']}",
                                        "Action attendue"               => "Vérifier ou créer le Contrat/Service Account associé au projet."
                                    ];

                                }
                            }
                        }
                        else {
                            ++$result['warnings'];
                            $result['logs'][] = "WARN- Unable to retrieve project associated with Task for task {$task['id']} - {$task['taskNumber']}";
                        }
                    }
                }
            }
        }
    }

    // dependencies: pass-3 - Contacts

    if(count($map_contacts_ids)) {
        $contacts_ids = array_keys($map_contacts_ids);
        foreach(array_chunk($contacts_ids, 100) as $chunk_ids) {
            $contacts = eQual::run('get', 'contractika_at_contacts', ['ids' => $chunk_ids]);
            $result['logs'][] = "OK  - fetched: ".count($contacts)." contacts.";
            foreach($contacts as $contact) {
                if(isset($map_contacts_ids[$contact['id']])) {
                    $lines = SALine::ids($map_contacts_ids[$contact['id']])->read(['id', 'is_locked', 'is_async', 'timeEntryID']);
                    foreach($lines as $id => $line) {
                        if($line['is_locked']) {
                            // error: unexpected timeentry (locked SA line)
                            // #memo - this seems to be triggered by a change on TicketLastActivityDate and leads to false positive warnings - disabled
                            // ++$result['warnings'];
                            // $result['logs'][] = "WARN- Non-writable SA Line for updated AT contact[{$contact['id']}]: CT SA Line[{$line['id']}] (TimeEntry[{$line['timeEntryID']}]) is locked and cannot be updated.";
                            continue;
                        }
                        // ignore lines marked as modified in AT but locked in CT
                        if($line['is_async']) {
                            continue;
                        }
                        SALine::id($id)->update(['contact' => $contact['firstName'].' '.$contact['lastName']]);
                    }
                }
            }
        }
    }

    /**
     * Pass-2: all passed time entries should have been created and present in DB : perform updates based on billing items
     */

    // retrieve new billing items
    $billing_items = eQual::run('get', 'contractika_at_billingitems', ['date_from' => $last_run]);
    $result['logs'][] = "OK  - fetched: ".count($billing_items)." billing items.";

    // map billing items on timeEntryID
    $map_billing_items_timeentries = [];
    foreach($billing_items as $billing_item) {
        // discard billing items marked as non billable
        if($billing_item['nonBillable']) {
            continue;
        }
        if(!is_null($billing_item['timeEntryID'])) {
            $map_billing_items_timeentries[$billing_item['timeEntryID']] = $billing_item;
        }
    }

    // update all SA lines (time entries) targeted by fetched billing items
    // #memo - once marked as posted, a timeentry is not expected to be reverted
    $billing_updated_count = 0;
    foreach($map_billing_items_timeentries as $time_entry_id => $billing_item) {
        $line = SALine::search(['timeEntryID', '=', $time_entry_id])->read(['id', 'is_locked', 'is_posted', 'is_async'])->first();
        if($line) {
            if($line['is_posted']) {
                // ignore lines already posted (prevents to raise an irrelevant warning)
                continue;
            }
            if($line['is_async']) {
                // ignore lines already marked as modified in AT but locked in CT
                continue;
            }
            if($line['is_locked']) {
                // error: unexpected timeentry (relates to a locked SA line)
                ++$result['warnings'];
                $result['logs'][] = "WARN- Error while updating 'is_posted' for time entry[{$time_entry_id}] from BillingItem [{$billing_item['id']}]: SA Line[{$line['id']}] is locked and cannot be updated.";
                SALine::id($line['id'])->update(['is_async' => true]);
                continue;
            }
            // mark the line as posted (will trigger update of parent report `has_non_posted`)
            // #memo - line should be a CC line
            SALine::id($line['id'])->update([
                'is_posted'     => true,
                'postedOnTime'  => strtotime($billing_item['postedOnTime'])
            ]);
            ++$billing_updated_count;
        }
    }

    $result['logs'][] = "OK  - updated $billing_updated_count SA lines from billing items.";

    /**
     * void all computed fields relating to points calculation
     */

    SALine::ids($sa_lines_ids)
        ->update(['pause_time' => null, 'duration' => null, 'travel_time' => null])
        ->update(['points' => null]);
}
catch(Exception $e) {
    /**
     * An unexpected error occurred.
     */

    // revert last_run value (to force retrying all involved time entries at next run)
    Setting::set_value('contractika', 'sync', 'at_sync_timeentries.last_run', $last_run);

    // send an email alert
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('ERROR Contractika')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Erreur inattendue lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i')." :</p>
                    <pre>".qn_error_name($e->getCode()).' : '.$e->getMessage()."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
    // relay exception
    throw new Exception($e->getMessage(), $e->getCode());
}

if($result['errors'] > 0) {
    // revert last_run value (to force retrying involved time entries at next run)
    Setting::set_value('contractika', 'sync', 'at_sync_timeentries.last_run', $last_run);
}

/**
 * Send email report.
 */

if($result['warnings'] > 0 || $result['errors'] > 0) {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    $body = "<html><body><p>Alertes lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>";

    $body .= "<pre>".htmlspecialchars($report, ENT_QUOTES, 'UTF-8')."</pre>";

    $body .= "</body></html>";

    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('WARNING Contractika')
            ->setContentType("text/html")
            ->setBody($body);

    if(count($result['alerts']) > 0) {
        $message->addCc(constant('EMAIL_SA_ALERTS_BCC'));
    }

    // queue message
    Mail::queue($message);
}
else {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->setSubject('SUCCESS Contractika')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Exécution réussie du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
                    <pre>".$report."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
