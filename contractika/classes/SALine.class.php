<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;
use core\setting\Setting;
use contractika\hr\holiday\Holiday;

class SALine extends \equal\orm\Model {

    public static function getLink() {
        return "/contractika/#/serviceaccountline/object.id";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'alias',
                'alias'             => 'description'
            ],

            'service_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\ServiceAccount',
                'description'       => 'The service account the line belongs to.',
                'onupdate'          => 'onupdateServiceAccountId'
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'description'       => 'Customer the line relates to (depending on service account).'
            ],

            'contact' => [
                'type'              => 'string',
                'description'       => 'Name of the contact person at the company the contract refers to.'
            ],

            'internal_notes' => [
                'type'              => 'text'
            ],

            'description' => [
                'type'              => 'text'
            ],

            'date' => [
                'type'              => 'datetime',
                'description'       => 'Date of the time entry (at which the service was performed).',
                'default'           => time()
            ],

            'start' => [
                'type'              => 'datetime',
                'description'       => 'Start time of the time entry (should be the same as date).',
                'dependencies'      => ['duration']
            ],

            'end' => [
                'type'              => 'datetime',
                'description'       => 'End time of the time entry.',
                'dependencies'      => ['duration', 'procrastin']
            ],

            // #memo - not stored: used in views only
            'delta_time' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'description'       => 'Total time: end time minus start time.',
                'function'          => 'calcDeltaTime'
            ],

            'pause' => [
                'type'              => 'float',
                'description'       => 'Offset to apply on time entry duration (can be negative or positive).',
                'help'              => 'Pause is received from AT as `offsetHours`: a float offset given as a float value (i.e. `-0.5` means a pause of 30 min; `0.25` means 15min of extra time).',
                'default'           => 0.0,
                'dependencies'      => ['pause_time', 'duration']
            ],

            'pause_time' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'description'       => 'Absolute time based on offset (to subtract or add to the duration).',
                'function'          => 'calcPauseTime',
                'store'             => true
            ],

            'duration' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'description'       => 'Computed duration: end time minus start time minus pause time.',
                'function'          => 'calcDuration',
                'store'             => true
            ],

            'procrastin' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Computed delay between the time of recording of the entry and the actual time the work took place.',
                'function'          => 'calcProcrastin',
                'store'             => true
            ],

            'travel_time' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'description'       => 'Computed travel duration: based on settings and customer.',
                'function'          => 'calcTravelTime',
                'store'             => true
            ],

            'on_site' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Does the entry imply some travel? Value retrieved from the related Ticket.'
            ],

            'helpdesk' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Does the entry relates to an helpdesk intervention (remote)?'
            ],

            'standby' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Value retrieved from the related Ticket.'
            ],

            'priority' => [
                'type'              => 'integer',
                'selection'         => [
                    1       => 'Low',
                    2       => 'Medium',
                    3       => 'High',
                    4       => 'Critical'
                ],
                'default'           => 1,
                'description'       => 'Priority level retrieved from the related Ticket (1 = low, 4 = critical).'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\hr\employee\Employee',
                'description'       => 'The employee the time entry originates from.'
            ],

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\hr\employee\Role',
                'description'       => 'Role assigned to the employee.',
                'help'              => 'This value is used as default role, i.e. the value to fallback to in case a time entry does not mention the role the resource endorsed in order to perform it.'
            ],

            'resourceID' => [
                'type'              => 'integer',
                'description'       => 'Code used by PSA software for the employee (AutoTask Resource).',
                'help'              => 'This value is meant to be mapped with field `extref_at_id` from `contractika\identity\Identity` for situations where employee_id has been compromised.',
            ],

            'sa_line_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SALineType',
                'description'       => 'Type the line is assigned to.',
                'help'              => 'Indicates the billing mode.',
                'onupdate'          => 'onupdateSALineTypeId'
            ],

            'sa_line_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SALineClass',
                'description'       => 'Class the line is assigned to.',
                'help'              => 'Indicates the nature of the line (1=task, 2=ticket, 3=credit, 4=correction).',
                // #memo - set default to 'correction' line, so the points are not computed
                'default'           => 4
            ],

            'postedOnTime' => [
                'type'              => 'datetime',
                'description'       => 'Date at which the line as been approved in external PSA software.'
            ],

            'is_posted' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the line as posted (has been approved in AT).',
                'help'              => "This field should only be updated through a dedicated process based on Billing Items from AutoTask.",
                'default'           => false,
                'onupdate'          => 'onupdateIsPosted'
            ],

            'has_report' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the line as attached to a report.',
                'default'           => false
            ],

            'report_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\Report',
                'ondelete'          => 'null',
                'onupdate'          => 'onupdateReportId',
                'description'       => 'Report to which the line is assigned, if any.',
                'visible'           => ['has_report', '=', true]
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => 'Marks the line as locked/invoiced (equivalent to has_report with a report in `released` status).',
                'default'           => false,
                'onupdate'          => 'onupdateIsLocked'
            ],

            'locked_date' => [
                'type'              => 'datetime',
                'description'       => 'Date-time at which the line has been locked / marked as invoiced.'
            ],

            'is_orphan' => [
                'type'              => 'boolean',
                'description'       => 'Flag raised if the line is present in CT but has been deleted in AT.',
                'help'              => "Deleting a posted entry is supposed to be forbidden. This flag is used to prevent recurring error messages.",
                'default'           => false
            ],

            'is_async' => [
                'type'              => 'boolean',
                'description'       => 'Flag raised if  the line is locked in CT but has been modified in AT.',
                'help'              => "Modifying a locked entry is supposed to be forbidden. This flag is used to prevent recurring error messages.",
                'default'           => false
            ],

            'has_ticket' => [
                'type'              => 'boolean',
                'description'       => 'Marks the line as originating from an AutoTask Time Entry.',
                'default'           => false
            ],

            'ticketID' => [
                'type'              => 'integer',
                'description'       => 'Code used by AutoTask PSA software for the ticket the line (time entry) relates to.',
                'visible'           => ['has_ticket', '=', true]
            ],

            'ticketNumber' => [
                'type'              => 'string',
                'description'       => 'Ticket number (string) generated by AutoTask PSA software.',
                'visible'           => ['has_ticket', '=', true]
            ],

            'ticketDescription' => [
                'type'              => 'string',
                'description'       => "Short description of the parent ticket (from AT).",
                'visible'           => ['has_ticket', '=', true]
            ],

            'ticketLink' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'function'          => 'calcTicketLink',
                'description'       => 'Direct link to AutoTask ticket edition URL.'
            ],

            'ticketCategory' => [
                'type'              => 'integer',
                'selection'         => [
                        2 => 'RMM Datto',
                        3 => 'Remote',
                        4 => 'Datto',
                        5 => 'RMA',
                        100 => 'New Employee On-boarding',
                        102 => 'Onboarding',
                        104 => 'Support (OLD to remove)',
                        105 => 'Atelier',
                        106 => 'Preload',
                        107 => 'RMM',
                        108 => 'Onsite',
                        109 => 'Sales',
                        110 => 'Renewals',
                        111 => 'Admin',
                        112 => 'Standby'
                    ],
                'description'       => 'Code of the category of the ticket.',
                'visible'           => ['has_ticket', '=', true],
                'onupdate'          => 'onupdateTicketCategory'
            ],

            'ticketLastActivityDate' => [
                'type'              => 'datetime',
                'description'       => 'Moment the ticket was last updated.',
                'visible'           => ['has_ticket', '=', true]
            ],

            'has_task' => [
                'type'              => 'boolean',
                'description'       => 'Flag for marking the line as originating from an AutoTask Task.',
                'default'           => false
            ],

            'taskID' => [
                'type'              => 'integer',
                'description'       => 'Code used by AutoTask PSA software for the task the line relates to.',
                'visible'           => ['has_task', '=', true]
            ],

            'taskNumber' => [
                'type'              => 'string',
                'description'       => 'Task number (string) generated by AutoTask PSA software.',
                'visible'           => ['has_task', '=', true]
            ],

            'taskDescription' => [
                'type'              => 'string',
                'description'       => "Short description of the parent task (from AT).",
                'visible'           => ['has_task', '=', true]
            ],

            'taskLink' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'function'          => 'calcTaskLink',
                'description'       => 'Direct link to AutoTask task edition URL.'
            ],

            'taskLastActivityDate' => [
                'type'              => 'datetime',
                'description'       => 'Moment the task was last updated.',
                'visible'           => ['has_task', '=', true]
            ],

            'timeEntryID' => [
                'type'              => 'integer',
                'description'       => 'Code used by AutoTask PSA software for the time entry the line relates to.',
                'unique'            => true
            ],

            'createDateTime' => [
                'type'              => 'datetime',
                'description'       => 'Provided by AutoTask PSA software as the original creation time of the entry.',
                'dependencies'      => ['procrastin']
            ],

            'points' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Number of points the line corresponds to (can be computed or set manually).',
                'help'              => 'Points count is always a positive number. Points from Ticket and Task lines are used to decrement the related Report balance, and Credit and Correction lines are used to increment it.',
                'store'             => true,
                'function'          => 'calcPoints',
                'onupdate'          => 'onupdatePoints'
            ],

            'calculation_log' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Detailed log of the points calculations.'
            ],

            'calculation_time' => [
                'type'              => 'datetime',
                'description'       => 'Moment of the points calculations.'
            ]

        ];
    }

    /**
     * Synchronize customer_id with the customer_id from parent Service Account.
     * SALine CC are created from Service Account, so we need to sync with related customer.
     * #memo - this can be triggered at the line creation, so we take care of the state.
     */
    public static function onupdateServiceAccountId($om, $self, $values) {
        $self->read(['state', 'service_account_id' => ['id', 'customer_id']]);
        foreach($self as $id => $line) {
            // #memo - mind not impacting state (Collection::update() DOES change the state)
            $om->update(self::getType(), $id, ['customer_id' => $line['service_account_id']['customer_id']]);
            ServiceAccount::id($line['service_account_id']['id'])->update(['balance_current' => null, 'has_balance_changed' => true]);
        }
    }

    /**
     * Changes the locked_date value according to the is_locked status.
     */
    public static function onupdateIsLocked($self, $values) {
        if(isset($values['is_locked']) && $values['is_locked']) {
            $self->update(['locked_date' => time()]);
        }
    }

    public static function onupdateReportId($self, $values) {
        if(isset($values['report_id']) && $values['report_id'] > 0) {
            $self->update(['has_report' => true]);
        }
        else {
            $self->update(['has_report' => false]);
        }
    }

    public static function onupdateTicketCategory($om, $ids, $values, $lang) {
        if(isset($values['ticketCategory']) && $values['ticketCategory'] > 0) {
            if($values['ticketCategory'] == 108) {
                SALine::ids($ids)->update(['on_site' => true]);
            }
            else {
                SALine::ids($ids)->update(['on_site' => false]);
            }
            if($values['ticketCategory'] == 112) {
                SALine::ids($ids)->update(['standby' => true]);
            }
            else {
                SALine::ids($ids)->update(['standby' => false]);
            }
        }
    }

    public static function onupdateSALineTypeId($om, $ids, $values, $lang) {
        $lines = self::ids($ids)->read(['sa_line_type_id' => ['extref_at_id']]);
        foreach($lines as $id => $line) {
            if(isset($line['sa_line_type_id'])) {
                // #memo - the names of the Helpdesk services are subjects to change, but not their AT ID
                /*
                    29683517 Helpdesk
                    29683500 Helpdesk [/Coaching]
                */
                if(in_array($line['sa_line_type_id']['extref_at_id'], ['29683500', '29683517'])) {
                    self::id($id)->update(['helpdesk' => true, 'on_site' => false]);
                }
                /*
                    29682808 Internal Administration
                    29683504 PreLoad
                    29683507 Remote Service
                */
                elseif(in_array($line['sa_line_type_id']['extref_at_id'], ['29683504', '29683507', '29682808'])) {
                    self::id($id)->update(['on_site' => false]);
                }
            }
        }
    }

    public static function onupdateIsPosted($self) {
        $self->read(['has_report', 'report_id']);
        $map_reports_ids= [];
        foreach($self as $line) {
            if($line['has_report']) {
                $map_reports_ids[$line['report_id']] = true;
            }
        }
        Report::ids(array_keys($map_reports_ids))
            ->update(['has_non_posted' => null])
            ->read(['has_non_posted']);
    }

    public static function calcProcrastin($self) {
        $result = [];
        $self->read(['end', 'createDateTime']);
        foreach($self as $id => $entry) {
            if(!$entry['createDateTime'] || !$entry['end']) {
                continue;
            }
            $result[$id] = $entry['createDateTime'] - $entry['end'];
        }
        return $result;
    }

    /**
     * Converts pause to an amount of seconds.
     * Sign is inverted (original offset is negative). By default, pause is subtracted from time entry (but the other way around is possible).
     */
    public static function calcPauseTime($om, $oids, $lang) {
        $result = [];
        $lines = self::ids($oids)->read(['pause']);
        foreach($lines as $oid => $line) {
            $result[$oid] = round(abs($line['pause']) * 60 * 60);
        }
        return $result;
    }

    /**
     * Computes the difference between end time and start time.
     */
    public static function calcDeltaTime($om, $oids, $lang) {
        $result = [];
        $lines = self::ids($oids)->read(['start', 'end']);
        foreach($lines as $oid => $line) {
            $result[$oid] = $line['end'] - $line['start'];
        }
        return $result;
    }

    /**
     * Computes the duration of the time entry.
     * Duration is the time spent working, minus the pause, rounded to the quarter and expressed in seconds.
     */
    public static function calcDuration($om, $oids, $lang) {
        $result = [];
        $lines = self::ids($oids)->read(['start', 'end', 'pause', 'pause_time']);
        foreach($lines as $oid => $line) {
            // #memo - pause_time is always positive; pause is negative if time has to be subtracted, and positive if time has to be added
            $sign = ($line['pause'] > 0)?-1:1;
            $result[$oid] = ceil(($line['end'] - $line['start'] - ($sign * $line['pause_time']) ) /  (15 * 60)) * (15*60);
        }
        return $result;
    }

    /**
     * Computes the duration of the travel based on settings and Customer.
     * Returns the duration in seconds.
     *
     */
    public static function calcTravelTime($om, $oids, $lang) {
        $result = [];
        // config relating to travel
        $default_travel_duration = Setting::get_value('contractika', 'travel', 'd_travel');
        // travel duration is in hours: convert in seconds
        $default_travel_duration *= 3600;

        $lines = self::ids($oids)->read(['on_site', 'service_account_id' => ['customer_id' => ['has_d_travel', 'd_travel']]]);
        foreach($lines as $oid => $line) {
            $result[$oid] = 0;
            if($line['on_site']) {
                $result[$oid] = $default_travel_duration;
                // override with customer setting if applicable (whatever the value)
                if($line['service_account_id']['customer_id']['has_d_travel']) {
                    $travel_duration = intval($line['service_account_id']['customer_id']['d_travel'] * 3600);
                    $result[$oid] = $travel_duration;
                }
            }
        }
        return $result;
    }


    public static function calcTicketLink($om, $oids, $lang) {
        $result = [];
        $link = 'https://ww19.autotask.net/Autotask/AutotaskExtend/ExecuteCommand.aspx?Code=OpenTicketDetail&TicketID=';

        $lines = self::ids($oids)->read(['ticketID']);
        foreach($lines as $oid => $line) {
            if(isset($line['ticketID']) && $line['ticketID'] > 0) {
                $result[$oid] = $link.$line['ticketID'];
            }
            else {
                $result[$oid] = null;
            }
        }
        return $result;
    }

    public static function calcTaskLink($om, $oids, $lang) {
        $result = [];
        $link = 'https://ww19.autotask.net/Mvc/Projects/TaskDetail.mvc?taskId=';

        $lines = self::ids($oids)->read(['taskID']);
        foreach($lines as $oid => $line) {
            if(isset($line['taskID']) && $line['taskID'] > 0) {
                $result[$oid] = $link.$line['taskID'];
            }
            else {
                $result[$oid] = null;
            }
        }
        return $result;
    }

    /**
     * Compute the points of the line, according to configuration and line specifics.
     *
     */
    public static function calcPoints($om, $oids, $lang) {
        $result = [];

        $lines = self::ids($oids)->read([
                'is_locked',
                'date',
                'start',
                'end',
                'pause',
                'pause_time',
                'travel_time',
                'duration',
                'on_site',
                'helpdesk',
                'standby',
                'sa_line_class_id',
                'priority',
                'role_id'               => ['name', 'hourly_factor'],
                'sa_line_type_id'       => ['extref_at_id', 'externalNumber'],
                'service_account_id'    => [
                    'id',
                    'customer_id' => [
                        'f_hfd_discount',
                        'c_halfday',
                        'c_fullday',
                        'c_saturday',
                        'c_sunday',
                        'c_dayoff',
                        'c_helpdesk',
                        'c_priority_critical',
                        'c_priority_high',
                        'c_priority_normal',
                        'c_priority_low',
                        'c_limit'
                    ]
                ]
            ], $lang);

        foreach($lines as $oid => $line) {

            // prevent processing invoiced lines
            if($line['is_locked']) {
                continue;
            }

            /**
             * 0) retrieve default coefficients from global settings
             *
             * #memo - some values might be reassigned by customer specific values, so we need to reload them at each loop
             */

            // cap limit coefficient
            $coef_limit = Setting::get_value('contractika', 'limit', 'c_limit');
            // priority coefficients
            $priority_normal = Setting::get_value('contractika', 'priority', 'c_priority_normal');
            $priority_low = Setting::get_value('contractika', 'priority', 'c_priority_low');
            $priority_high = Setting::get_value('contractika', 'priority', 'c_priority_high');
            $priority_critical = Setting::get_value('contractika', 'priority', 'c_priority_critical');
            // type coefficients
            $type_helpdesk = Setting::get_value('contractika', 'type', 'c_helpdesk');
            $type_standby = Setting::get_value('contractika', 'type', 'c_standby');
            // halday/fullday coefficients
            $hfd_discount = Setting::get_value('contractika', 'halfday_fullday', 'f_hfd_discount');
            $hfd_halfday = Setting::get_value('contractika', 'halfday_fullday', 'c_halfday');
            $hfd_fullday = Setting::get_value('contractika', 'halfday_fullday', 'c_fullday');
            // time in minutes (convert to seconds)
            $hfd_halfday_min = self::_getTimeFromString(Setting::get_value('contractika', 'halfday_fullday', 'd_halfday_min')) * 60;
            $hfd_halfday_max = self::_getTimeFromString(Setting::get_value('contractika', 'halfday_fullday', 'd_halfday_max')) * 60;
            $hfd_fullday_min = self::_getTimeFromString(Setting::get_value('contractika', 'halfday_fullday', 'd_fullday_min')) * 60;
            // time in hours (convert to seconds)
            $hfd_morning_stop = Setting::get_value('contractika', 'halfday_fullday', 't_morning_stop', 14) * 3600;
            $hfd_afternoon_start = Setting::get_value('contractika', 'halfday_fullday', 't_afternoon_start', 12) * 3600;
            // specific days coefficients
            $day_saturday = Setting::get_value('contractika', 'days', 'c_saturday');
            $day_sunday = Setting::get_value('contractika', 'days', 'c_sunday');
            $day_dayoff = Setting::get_value('contractika', 'days', 'c_dayoff');

            // keep track of the operations (will be stored in the `calculation_log` field)
            $logs = [];

            // ignore computing for non ticket or task lines
            // #memo - for credit and corrections, the points are set manually
            if(!in_array($line['sa_line_class_id'], [1, 2])) {
                continue;
            }

            // retrieve offset between local timezone and UTC (times in settings use local time)
            $tz = new \DateTimeZone("Europe/Brussels");

            // timezone offset in seconds to apply, depending on the date of the time entry
            $tz_offset = $tz->getOffset(new \DateTime('@'.$line['date']));

            /**
             * 1) retrieve the coefficients for global settings or customer specific parameters
             */

            // override settings with customer specifics, if any
            $customer = $line['service_account_id']['customer_id'];
            // #memo - we don't know if the flag is disabled or set to false (so we override only if global setting is set to false)
            if(!is_null($customer['f_hfd_discount']) && !$hfd_discount && $customer['f_hfd_discount']) {
                $hfd_discount = $customer['f_hfd_discount'];
                $logs[] = "Retrieved customer specific half-day flag (".intval($hfd_discount).")";
            }
            if(!is_null($customer['c_halfday']) && $customer['c_halfday']  > 0) {
                $hfd_halfday = $customer['c_halfday'];
                $logs[] = "Retrieved customer specific half-day coefficient (".self::_getStringFromCoeff($hfd_halfday).")";
            }
            if(!is_null($customer['c_fullday']) && $customer['c_fullday'] > 0) {
                $hfd_fullday = $customer['c_fullday'];
                $logs[] = "Retrieved customer specific full-day coefficient (".self::_getStringFromCoeff($hfd_fullday).")";
            }
            if(!is_null($customer['c_saturday']) && $customer['c_saturday'] > 0) {
                $day_saturday = $customer['c_saturday'];
                $logs[] = "Retrieved customer specific saturday coefficient (".self::_getStringFromCoeff($day_saturday).")";
            }
            if(!is_null($customer['c_sunday']) && $customer['c_sunday'] > 0) {
                $day_sunday = $customer['c_sunday'];
                $logs[] = "Retrieved customer specific sunday coefficient (".self::_getStringFromCoeff($day_sunday).")";
            }
            if(!is_null($customer['c_dayoff']) && $customer['c_dayoff'] > 0) {
                $day_dayoff = $customer['c_dayoff'];
                $logs[] = "Retrieved customer specific dayoff coefficient (".self::_getStringFromCoeff($day_dayoff).")";
            }
            if(!is_null($customer['c_helpdesk']) && $customer['c_helpdesk'] > 0) {
                $type_helpdesk = $customer['c_helpdesk'];
                $logs[] = "Retrieved customer specific helpdesk coefficient (".self::_getStringFromCoeff($type_helpdesk).")";
            }
            if(!is_null($customer['c_priority_low']) && $customer['c_priority_low'] > 0) {
                $priority_low = $customer['c_priority_low'];
                $logs[] = "Retrieved customer specific priority_low coefficient (".self::_getStringFromCoeff($priority_low).")";
            }
            if(!is_null($customer['c_priority_normal']) && $customer['c_priority_normal'] > 0) {
                $priority_normal = $customer['c_priority_normal'];
                $logs[] = "Retrieved customer specific priority_normal coefficient (".self::_getStringFromCoeff($priority_normal).")";
            }
            if(!is_null($customer['c_priority_high']) && $customer['c_priority_high'] > 0) {
                $priority_high = $customer['c_priority_high'];
                $logs[] = "Retrieved customer specific priority_high coefficient (".self::_getStringFromCoeff($priority_high).")";
            }
            if(!is_null($customer['c_priority_critical']) && $customer['c_priority_critical'] > 0) {
                $priority_critical = $customer['c_priority_critical'];
                $logs[] = "Retrieved customer specific priority_critical coefficient (".self::_getStringFromCoeff($priority_critical).")";
            }
            if(!is_null($customer['c_limit']) && $customer['c_limit'] > 0) {
                $coef_limit = $customer['c_limit'];
                $logs[] = "Retrieved customer specific limit coefficient (".self::_getStringFromCoeff($coef_limit).")";
            }

            // start and end are datetimes : convert to seconds (remove the date part of the timestamp)
            $start = $line['start'] - strtotime('midnight', $line['start']);
            $end = $line['end'] - strtotime('midnight', $line['end']);

            $logs[] = "Retrieved start time: ".self::_getStringFromTime($start + $tz_offset);
            $logs[] = "Retrieved end time: ".self::_getStringFromTime($end + $tz_offset);

            // get the pause as a positive amount of seconds
            $pause = $line['pause_time'];
            // #memo - pause time can be negative (positive offset)
            if($line['pause'] > 0) {
                $pause = -$pause;
            }
            if($pause != 0) {
                if($pause < 0) {
                    $logs[] = "Positive offset: added extra time (".self::_getStringFromTime(-$pause).")";
                }
                else {
                    $logs[] = "Pause included: removed time off (".self::_getStringFromTime($pause).")";
                }
            }

            // duration is the time spent working (end-start), minus the pause, rounded to the quarter (@see calcDuration), given in seconds
            $duration = $line['duration'];
            $logs[] = "Retrieved rounded duration: ".self::_getStringFromTime($duration);

            // retrieve the day of the week (ISO 8601)
            $weekday = date('N', $line['date']);

            $coef = 1.0;

            /**
             * 2) Half / Full day reduction
             */

            // if half/full day is applicable and time entry is during working days
            if($hfd_discount && $weekday < 6) {
                $logs[] = "Qualified for Halfday-Fullday discount";
                if($duration > $hfd_fullday_min) {
                    $coef = $hfd_fullday;
                    $logs[] = "Assigned full-day coefficient (".self::_getStringFromCoeff($hfd_fullday).")";
                }
                elseif($duration > $hfd_halfday_min) {
                    // discard entries than span over morning and afternoon (by default morning strop is 2PM and afternoon start is 12AM)
                    if($end <= $hfd_morning_stop || $start >= $hfd_afternoon_start) {
                        $coef = $hfd_halfday;
                        $logs[] = "Assigned half-day coefficient (".self::_getStringFromCoeff($hfd_halfday).")";
                    }
                    else {
                        $logs[] = "(off-limit : no hdfd coefficient applied)";
                    }
                }
                else {
                    $logs[] = "(off-limit : no hdfd coefficient applied)";
                }
            }

            /**
             * 3) Hours coefficients (adapt the coefficient based on entry specifics)
             */

            // build the map (we slice a day into 6 parts holding UTC times)
            $map = [
                [
                    'from'  => self::_getTimeFromString('00:00'),
                    'to'    => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_morning')) - $tz_offset,
                    'coeff' => Setting::get_value('contractika', 'hours', 'c_night')
                ],
                [
                    'from'  => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_morning')) - $tz_offset,
                    'to'    => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_workinghours_start')) - $tz_offset,
                    'coeff' => Setting::get_value('contractika', 'hours', 'c_morning')
                ],
                [
                    'from'  => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_workinghours_start')) - $tz_offset,
                    'to'    => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_workinghours_end')) - $tz_offset,
                    'coeff' => 1.0
                ],
                [
                    'from'  => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_workinghours_end')) - $tz_offset,
                    'to'    => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_evening_1')) - $tz_offset,
                    'coeff' => Setting::get_value('contractika', 'hours', 'c_evening_1')
                ],
                [
                    'from'  => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_evening_1')) - $tz_offset,
                    'to'    => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_evening_2')) - $tz_offset,
                    'coeff' => Setting::get_value('contractika', 'hours', 'c_evening_2')
                ],
                [
                    'from'  => self::_getTimeFromString(Setting::get_value('contractika', 'hours', 't_evening_2')) - $tz_offset,
                    'to'    => self::_getTimeFromString(24),
                    'coeff' => Setting::get_value('contractika', 'hours', 'c_night')
                ]
            ];

            // compute virtual duration (in seconds)

            /**
             * @var float $time Holds the total of time slices, in seconds (= end-start-pause)
             * @var float $q    Holds the sum of the times with their related coefficient applied
             * #
             */
            list($time, $q) = [0.0, 0.0];

            // by default, there is one period : from start time to end time
            $periods = [
                [
                    'start' => $start,
                    'end'   => $end
                ]
            ];

            // in case a pause is present and positive, split the entry in 2 periods, with the pause in the middle
            if($pause > 0) {
                $periods = [
                    [
                        'start'   => $start,
                        'end'     => $start + ( ($end-$start)/2 ) - ceil($pause/2),
                    ],
                    [
                        'start'   => $start + ( ($end-$start)/2 ) + floor($pause/2),
                        'end'     => $end
                    ]
                ];
            }
            elseif($pause < 0) {
                // no change - offset has already been added to computed duration (@see `calcDuration()`)
            }

            for($i = 0, $n = count($periods); $i < $n; ++$i) {
                $period = $periods[$i];
                foreach($map as $set) {
                    $applicable_time = self::_getApplicableTime($period['start'], $period['end'], $set['from'], $set['to']);
                    // #memo - we do not round the times within periods
                    if($applicable_time) {
                        $time += $applicable_time;
                        $q += $set['coeff'] * $applicable_time;
                        $logs[] = "Counting ".self::_getStringFromTime($applicable_time)." applied on range [".self::_getStringFromTime($set['from'] + $tz_offset)." - ".self::_getStringFromTime($set['to'] + $tz_offset)."] (".self::_getStringFromCoeff($set['coeff']).")";
                    }
                }
            }

            // discard fractions of seconds, if any, and round to the upper second
            $q = ceil($q);

            $coef_hours = ($time > 0)?($q / $time):1.0;
            // apply the resulting Hours coefficient
            $coef *= $coef_hours;
            $logs[] = "Retrieved base coefficient: ".round($coef, 4);

            // #memo - points are counted in quarters
            $logs[] = "Retrieved base quarters: ".round($duration / (15*60), 2);


            /**
             * 4) Weekday coefficient
             */

            if($weekday == 6) {
                $coef *= $day_saturday;
                $logs[] = "Job performed on Saturday: applying c_saturday (".self::_getStringFromCoeff($day_saturday).")";
            }
            elseif($weekday == 7) {
                $coef *= $day_sunday;
                $logs[] = "Job performed on Saturday: applying c_sunday (".self::_getStringFromCoeff($day_sunday).")";
            }
            else {
                // check if date matches a day-off entry
                $holiday = Holiday::search(['date', '=', $line['start']])->read(['id', 'name'])->first();
                if(!is_null($holiday)) {
                    $coef *= $day_dayoff;
                    $logs[] = "Day-off ({$holiday['name']}): applying c_dayoff (".self::_getStringFromCoeff($day_dayoff).")";
                }
            }


            /**
             * 5) Worktype coefficient (helpdesk)
             */

            // helpdesk
            if($line['helpdesk']) {
                $coef *= $type_helpdesk;
                $logs[] = "Helpdesk: applying c_helpdesk (".self::_getStringFromCoeff($type_helpdesk).")";
            }


            /**
             * 6) Category coefficient
             */

            // standby
            if($line['standby']) {
                $coef *= $type_standby;
                $logs[] = "Standby: applying c_standby (".self::_getStringFromCoeff($type_standby).")";
            }


            /**
             * 7) Priority coefficient
             */

            // apply the Priority coefficient
            switch($line['priority']) {
                // low
                case 1:
                    $coef *= $priority_low;
                    $logs[] = "Priority: applying 'low' (".self::_getStringFromCoeff($priority_low).")";
                    break;
                // normal (medium)
                case 2:
                    $coef *= $priority_normal;
                    $logs[] = "Priority: applying 'normal' (".self::_getStringFromCoeff($priority_normal).")";
                    break;
                // high
                case 3:
                    $coef *= $priority_high;
                    $logs[] = "Priority: applying 'high' (".self::_getStringFromCoeff($priority_high).")";
                    break;
                // critical
                case 4:
                    $coef *= $priority_critical;
                    $logs[] = "Priority: applying 'critical' (".self::_getStringFromCoeff($priority_critical).")";
                    break;
                default:
                    break;
            }


            /**
             * 8) Coefficient limit
             */

            if($coef > $coef_limit) {
                // cap limit
                $coef = $coef_limit;
                $logs[] = "Max reached: caping to c_limit (".self::_getStringFromCoeff($coef_limit).")";
            }


            /**
             * 9) Coefficient application
             */

            $time = $duration * $coef;
            $logs[] = "Resulting final coefficient: ".self::_getStringFromCoeff($coef);

            /**
             * 10) Travel increment
             */

            if($line['on_site']) {
                // #memo - travel_time is in seconds
                $travel_time = $line['travel_time'];
                $time += $travel_time;
                $logs[] = "On-site job: adding travel time (".self::_getStringFromTime($travel_time).")";
            }

            /**
             * 11) Role coefficient
             */

            if(isset($line['role_id']['hourly_factor'])) {
                $coef_role = $line['role_id']['hourly_factor'];
                $time = $time * $coef_role;
                $logs[] = "Role: applying {$line['role_id']['name']} (".self::_getStringFromCoeff($coef_role).")";
            }

            /**
             * 12) Points calculation
             */

            // compute final result
            $points = round($time / (15 * 60), 2);

            if(!is_numeric($points) || is_nan($points)) {
                // should not occur
                $logs[] = "ERROR - result is not a number";
            }
            else {
                $result[$oid] = $points;
                $logs[] = "Resulting final points: ".$result[$oid];
            }

            // store logs
            $om->update(self::getType(), $oid, ['calculation_time' => time(),'calculation_log' => implode('<br />', $logs)]);
            // reset current balance of parent Service Account
            $om->update(ServiceAccount::getType(), $line['service_account_id']['id'], ['balance_current' => null, 'has_balance_changed' => true]);
        }
        return $result;
    }

    /**
     * There is no sync from AT for Credit & Correction lines, so for those lines (classes 3 and 4), when `points` is updated, `is_posted` is set to true.
     */
    public static function onupdatePoints($self) {
        $self->read(['sa_line_class_id', 'service_account_id']);
        foreach($self as $id => $line) {
            if(in_array($line['sa_line_class_id'], [3, 4])) {
                // #memo - will trigger update of parent report `has_non_posted`, if any
                self::id($id)->update(['is_posted' => true]);
                // if line is a credit, reset the `alert_sent` flag of the related service account
                if($line['sa_line_class_id'] == 3) {
                    ServiceAccount::id($line['service_account_id'])->update(['has_renew_alert_sent' => false]);
                }
            }
            ServiceAccount::id($line['service_account_id'])->update(['balance_current' => null, 'has_balance_changed' => true]);
        }
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\Collection    $self   Collection of objects of current class.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($self, $values) {
        $providers = \eQual::inject(['dispatch']);
        /** @var \equal\dispatch\Dispatcher $dispatch */
        $dispatch = $providers['dispatch'];

        $self->read(['is_locked', 'has_report', 'report_id' => ['id', 'status']]);
        foreach($self as $id => $line) {
            if($line['is_locked']) {
                // allow updating special flags
                if(!isset($values['is_orphan']) && !isset($values['is_async']) && !isset($values['createDateTime'])) {
                    return ['is_locked' => ['non_editable' => "Locked SA line [$id] cannot be updated (linked to released Report)."]];
                }
            }
            else {
                // #memo - allow arbitrary change of report-related fields for non locked lines
                $allowed = ['report_id', 'has_report', 'is_posted', 'postedOnTime', 'is_locked', 'locked_date'];
                // #memo - at this stage a linked pending report might have been removed resulting in a NULL report_id
                if($line['report_id']) {
                    if(isset($values['report_id']) && $values['report_id'] > 0 && $line['report_id'] != $values['report_id']) {
                        // prevent change unless made only on is_locked
                        if(count($values) > 1 || array_keys($values)[0] != 'is_locked') {
                            $dispatch->dispatch('contractika.sa_line.already_sent', self::getType(), $id, 'warning');
                            return ['has_report' => ['non_editable' => "SA line [$id] cannot be linked to a new Report while already linked to a Report."]];
                        }
                    }
                    elseif(count(array_diff(array_keys($values), $allowed)) > 0 ) {
                        return ['has_report' => ['non_editable' => "SA line [$id] is linked to a pending Report and cannot be updated."]];
                    }
                }
            }
        }
        return parent::canupdate($self);
    }

    public static function candelete($self) {
        $self->read(['is_locked']);
        foreach($self as $id => $line) {
            if($line['is_locked']) {
                return ['is_locked' => ['not_allowed' => "Locked SA line [$id] cannot be deleted (linked to released Report)."]];
            }
        }
        return parent::candelete($self);
    }

    /**
     * Returns the value of a moment (time as a string or as an integer) expressed as an integer amount of seconds.
     *
     * @return int  The amount of seconds elapsed since 00:00 (from 0 to 86400).
     */
    private static function _getTimeFromString($value) {
        $value = (string) $value;
        list($hour, $minute, $second) = [0,0,0];
        $count = substr_count($value, ':');
        if($count == 2) {
            list($hour, $minute, $second) = sscanf($value, "%d:%d:%d");
        }
        else if($count == 1) {
            list($hour, $minute) = sscanf($value, "%d:%d");
        }
        else if($count == 0) {
            if(intval($value) > 24) {
                // time in minutes
                $hour = intval($value) / 60;
            }
            else {
                $hour = intval($value);
            }
        }
        return ($hour * 3600) + ($minute * 60) + $second;
    }

    private static function _getStringFromCoeff($value) {
        return number_format((float) round($value, 2), 2, '.', '');
    }

    private static function _getStringFromTime($value) {
        $hours = floor($value / 3600);
        $minutes = floor(($value % 3600) / 60);
        return sprintf("%02d:%02d", $hours, $minutes);
    }

    /**
     * Returns the time, in seconds, that must be accounted for a given range, within a specific set of limits.
     * Meant to receive and return values in seconds.
     * We compare a range (A, B) to a pair of limits (from, to).
     * This method is meant to be called in loop with an invariable range and a series of limit.
     *
     *
     *        There are 4 possible matches: 2, 3, 4, 6
     *
     *                   from             to
     *                   |----------------|
     *           1        2       3       4        5
     *        |-----|  |-----|  |---|  |-----|  |-----|
     *                            6
     *               |---------------------------|
     *
     * @param int $a        Left edge of the range as a time relative to 00:00.
     * @param int $b        Right edge of the range as a time relative to 00:00.
     * @param int $from     Left limit to compare the range with.
     * @param int $to       Right limit to compare the range with.
     * @return int          Returns the time, in seconds, that the limits cover within the range.
     */
    private static function _getApplicableTime($a, $b, $from, $to) {
        $qty = 0;
        if($a >= $from && $a <= $to) {
            if($b >= $from && $b <= $to) {
                $qty = ($b-$a);
            }
            else {
                $qty = ($to-$a);
            }
        }
        else {
            if($b >= $from && $b <= $to) {
                $qty = ($b-$from);
            }
            else {
                if($a < $from && $b > $to) {
                    $qty = ($to-$from);
                }
            }
        }
        return $qty;
    }


}
