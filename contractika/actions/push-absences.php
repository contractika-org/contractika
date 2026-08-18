<?php
use contractika\hr\absence\Absence;
use core\setting\Setting;

list($params, $providers) = eQual::announce([
    'description'   => "Makes sure that planned absences are linked to AT Appointments. \n
                        Reviews all absences that have been updated since last synch, and creates, updates or deletes AT Appointments accordingly.",
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
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
    'errors'  => 0,
    'warnings'=> 0,
    'ignored' => 0,
    'updated' => 0,
    'created' => 0,
    'deleted' => 0,
    'logs'    => []
];

// retrieve last_run from settings (defaults to 'all times')
$last_run = Setting::get_value('contractika', 'sync', 'at_sync_absences.last_run', 0);

// TEST
// $last_run = time() - 60 * 24 * 60 * 60;
$result['logs'][] = "INFO- Fetching all non-holiday Absences modified since ".date("c", $last_run);
// fetch all absences that have been modified since last run
$absences = Absence::search([ ['is_holiday', '=', false], ['modified', '>=', $last_run] ])
    ->read([
        'id',
        'status',
        'date',
        'day_part',
        'extref_sd_id',
        'extref_at_id',
        'code_id'       => ['name'],
        'employee_id'   => ['id', 'code', 'is_active', 'extref_at_id']
    ]);

//#memo - hardcoded times are relative to Western Europe Time (Europe/Brussels), when sending to AT we need to convert to ISO UTC
$time_zone = Setting::get_value('core', 'locale', 'time_zone', 'Europe/Brussels');
$timeZone = new DateTimeZone($time_zone);

$map_ignored_employees_ids = [];

foreach($absences as $absence) {
    if(!isset($absence['employee_id']) || !$absence['employee_id']['is_active'] || !isset($absence['employee_id']['extref_at_id'])) {
        // skip irrelevant absences
        ++$result['ignored'];
        $result['logs'][] = 'INFO- ignoring unknown or inactive employee '.$absence['employee_id']['id'];
        continue;
    }

    if(isset($map_ignored_employees_ids[$absence['employee_id']['id']])) {
        ++$result['ignored'];
        $result['logs'][] = "INFO- ignoring inactive/faulty employee {$absence['employee_id']['code']} {$absence['employee_id']['extref_at_id']} [{$absence['employee_id']['id']}]";
        continue;
    }

    $title = "SDworx ~ ".$absence['code_id']['name']." ~ ".$absence['status'];
    $description = $absence['extref_sd_id'];
    $datetime_from = $absence['date'];
    $datetime_to = $absence['date'];
    $tz_offset = $timeZone->getOffset(new DateTime('@'.$absence['date']));

    switch($absence['day_part']) {
        case 'forenoon':
            $datetime_from += intval( 9.0 * 3600) - $tz_offset;
            $datetime_to   += intval(12.5 * 3600) - $tz_offset;
            break;
        case 'afternoon':
            $datetime_from += intval(13.5 * 3600) - $tz_offset;
            $datetime_to   += intval(17.5 * 3600) - $tz_offset;
            break;
        case 'fullday':
            $datetime_from += intval( 9.0 * 3600) - $tz_offset;
            $datetime_to   += intval(17.5 * 3600) - $tz_offset;
            break;
        default:
            ++$result['ignored'];
            continue 2;
    }

    // before attempting to request an update on AT side, we check if the employee (Resource) exists and is active
    try {
        $at_resource = eQual::run('get', 'contractika_at_resource', ['id' => $absence['employee_id']['extref_at_id']]);
        if(!$at_resource || !isset($at_resource['isActive'])) {
            throw new Exception('invalid_at_response', EQ_ERROR_UNKNOWN);
        }
        if(!$at_resource['isActive']) {
            ++$result['ignored'];
            $result['logs'][] = "INFO- ignoring employee marked as inactive in AT {$absence['employee_id']['extref_at_id']} [{$absence['employee_id']['code']} - {$absence['employee_id']['id']}]";
            // ignore next attempts for same employee is the same thread
            $map_ignored_employees_ids[$absence['employee_id']['id']] = true;
            continue;
        }
    }
    catch(Exception $e) {
        // unexpected error during AT API call
        ++$result['errors'];
        $result['logs'][] = "ERR - error while fetching Resource AT `{$absence['employee_id']['extref_at_id']}` [{$absence['employee_id']['code']} - {$absence['employee_id']['id']}]: ".$e->getMessage();
        continue;
    }

    try {
        switch($absence['status']) {
            // absence is pending or approved
            case 'requested':
            case 'planned':
            case 'approved':
                // if appointment does not exist yet, create it
                if(!is_numeric($absence['extref_at_id']) || $absence['extref_at_id'] <= 0) {
                    $values = [
                            'resource_id'   => $absence['employee_id']['extref_at_id'],
                            'datetime_from' => $datetime_from,
                            'datetime_to'   => $datetime_to,
                            'title'         => $title,
                            'description'   => $description
                        ];
                    // create a new appointment
                    $data = eQual::run('do', 'contractika_at_create-appointment', $values);
                    // check the response (make sure it is valid JSON object)
                    if(!isset($data['itemId'])) {
                        throw new Exception("Invalid response received from AT Appointment creation", QN_ERROR_UNKNOWN);
                    }
                    // update absence with at ID
                    Absence::id($absence['id'])->update(['extref_at_id' => $data['itemId']]);
                    ++$result['created'];
                    $result['logs'][] = 'OK  - synced (created) appointment AT '.$data['itemId'].' : ['.implode(',', array_map(function ($a, $b) {return "$a:$b";}, array_keys($values), array_values($values))).']';
                }
                // appointment already exists: update it
                else {
                    $values = [
                            'id'            => $absence['extref_at_id'],
                            'resource_id'   => $absence['employee_id']['extref_at_id'],
                            'datetime_from' => $datetime_from,
                            'datetime_to'   => $datetime_to,
                            'title'         => $title,
                            'description'   => $description
                        ];
                    // update existing appointment
                    $data = eQual::run('do', 'contractika_at_update-appointment', $values);
                    ++$result['updated'];
                    $result['logs'][] = 'OK  - synced (updated) appointment AT'.$absence['extref_at_id'].' : ['.implode(',', array_map(function ($a, $b) {return "$a:$b";}, array_keys($values), array_values($values))).']';
                }
                break;
            // absence has been denied or deleted
            case 'refused':
            case 'requesteddeleted':
            case 'approveddeleted':
                // an appointment already exist, remove it
                if(!is_null($absence['extref_at_id'])) {
                    // delete existing appointment
                    $data = eQual::run('do', 'contractika_at_delete-appointment', [
                            'id' => $absence['extref_at_id']
                        ]);
                    Absence::id($absence['id'])->update(['extref_at_id' => null]);
                    ++$result['deleted'];
                    $result['logs'][] = 'OK  - synced (deleted) appointment AT'.$absence['extref_at_id'];
                }
                break;
            default:
                // unknown status: ignore
                ++$result['errors'];
                $result['logs'][] = 'ERR - unknown absence status (' . $absence['status'] . ') for absence SD' .  $absence['extref_sd_id'];
                continue 2;
        }
    }
    catch(Exception $e) {
        // unexpected error during AT API call
        ++$result['errors'];
        $result['logs'][] = "ERR - error updating Appointment AT `{$absence['extref_at_id']}` [{$absence['id']}] ({$absence['status']}) for employee `{$absence['employee_id']['extref_at_id']}` ({$absence['employee_id']['code']}): ".$e->getMessage();

        // ignore next attempts for same employee is the same thread
        $map_ignored_employees_ids[$absence['employee_id']['id']] = true;
    }

}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
