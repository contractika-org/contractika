<?php
use contractika\hr\employee\Employee;
use contractika\hr\holiday\Holiday;
use contractika\hr\absence\Absence;

[$params, $providers] = eQual::announce([
    'description'   => 'Makes sure that all upcoming holiday absences (AT Holidays x Employees) are linked to AT Appointments, and creates new Appointments in AT when necessary.',
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
    'ignored' => 0,
    'updated' => 0,
    'failed'  => 0,
    'unknown' => 0,
    'logs'    => []
];

$absences = Absence::search([ ['is_holiday', '=', true], ['date', '>=', strtotime('today')], ['extref_at_id', 'is', null] ])->read(['id', 'holiday_id', 'employee_id']);

foreach($absences as $absence) {
    if(!$absence['holiday_id']) {
        ++$result['unknown'];
        // inconsistency : skip
        continue;
    }

    $holiday = Holiday::id($absence['holiday_id'])->read(['id', 'name', 'date'])->first();

    if(!$holiday) {
        continue;
    }

    $employee = Employee::id($absence['employee_id'])->read(['id', 'relationship', 'partner_identity_id' => ['name'], 'code', 'extref_at_id', 'is_active'])->first();

    if(!$employee) {
        $result['logs'][] = 'skipping not found employee ['.$employee['partner_identity_id']['name'].' - '.$absence['employee_id'].']';
        continue;
    }

    if($employee['relationship'] != 'employee') {
        // #memo - Employee still uses identity_partner table (and is therefore mixed with contacts and other identities)
        continue;
    }

    if(!$employee['extref_at_id']) {
        $result['logs'][] = 'skipping incomplete employee ['.$employee['partner_identity_id']['name'].' - '.$absence['employee_id'].']';
        continue;
    }

    if(!$employee['code'] || strlen($employee['code']) <= 0) {
        $result['logs'][] = 'skipping object with no employee code [' . $employee['partner_identity_id']['name'] . ' - ' . $absence['employee_id'] . ' - ' . $employee['extref_at_id'] . ']';
        // #memo - some Employee objects relate to API-only accounts: these have a AT ID but no SD ID nor code assigned
        continue;
    }

    if(!$employee['is_active']) {
        $result['logs'][] = 'skipping inactive employee ['.$employee['partner_identity_id']['name'].' - '.$absence['employee_id'].']';
        continue;
    }

    /*
    // #memo - this is not mandatory and prevents syncing employees when manual assignment has not been done yet in Contractika
    if(strlen($employee['code']) <= 0) {
        $result['logs'][] = 'skipping employee with no code ['.$employee['partner_identity_id']['name'].' - '.$absence['employee_id'].']';
        continue;
    }
    */

    $payload = [
            'resource_id'   => $employee['extref_at_id'],
            'datetime_from' => $holiday['date'] + 9 * 3600,
            'datetime_to'   => $holiday['date'] + intval(17.5 * 3600),
            'title'         => "AT ~ ".$holiday['name'],
            'description'   => "Holiday sync from AutoTask Holidays set"
        ];

    try {
        // absence does not yet relate to an AutoTask appointment: create one
        $data = eQual::run('do', 'contractika_at_create-appointment', $payload);
        // check the response (make sure it is valid JSON object)
        if(!isset($data['itemId'])) {
            throw new Exception("Invalid response received from AT Appointment creation", QN_ERROR_UNKNOWN);
        }
        // update absence with newly created Appointment
        Absence::id($absence['id'])->update(['extref_at_id' => $data['itemId']]);
        ++$result['created'];
        $result['logs'][] = 'synced (created) holiday appointment AT'.$data['itemId'];
    }
    catch(Exception $e) {
        // unable to create Appointment
        ob_start();
        print_r($payload);
        $str_payload = ob_get_clean();
        $result['logs'][] = 'Unable to create appointment in AT: '.$e->getMessage(). ': '.$str_payload;
        ++$result['failed'];
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
