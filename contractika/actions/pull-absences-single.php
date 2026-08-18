<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\hr\employee\Employee;
use contractika\hr\absence\Absence;
use hr\absence\AbsenceCode;

list($params, $providers) = eQual::announce([
    'description'   => 'Requests the updated list of absences from SDworx since last query (from setting `sd_sync.last_run`), and stores received data as Absence objects.',
    'params'        => [
        'date_from'   => [
            'description'       => 'First date of the range.',
            'type'              => 'date',
            'required'          => true
        ],
        'date_to'   => [
            'description'       => 'Last date of the range.',
            'type'              => 'date',
            'required'          => true
        ],
        'employee_id'   => [
            'description'       => 'Identifier of the Employee for which we want to update the absences.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\hr\employee\Employee',
            'required'          => true
        ]
    ],
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
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'processed' => 0,
    'errors'    => 0,
    'logs'      => []
];


// build absence codes map (prefetch all absence codes)
$absence_code_map = [];
$absenceCodes = AbsenceCode::search()->read(['id', 'code']);
if(!count($absenceCodes)) {
    throw new Exception('Missing mandatory setting: absence codes listing.', QN_ERROR_INVALID_CONFIG);
}
foreach($absenceCodes as $oid => $code) {
    $absence_code_map[$code['code']] = $code['id'];
}

/*
    process employees list
*/

// fetch all employees
// PROD
$employees = Employee::id($params['employee_id'])
    ->read(['id', 'name', 'extref_sd_id', 'extref_at_id']);

$last_run = $params['date_from'];

$now = time();

$nowIndex = ((int) date('Y', $now)) * 12 + ((int) date('n', $now) - 1);
$fromIndex = ((int) date('Y', $params['date_from'])) * 12 + ((int) date('n', $params['date_from']) - 1);
$toIndex   = ((int) date('Y', $params['date_to'])) * 12 + ((int) date('n', $params['date_to']) - 1);

$passed_months = max(0, $nowIndex - $fromIndex);
$future_months = max(0, $toIndex - $nowIndex);

$result['logs'][] = "INFO- reviewing " . $employees->count() . " employees, since " . date('Y-m-d', $last_run) . ", for upcoming ". $future_months . " months";

foreach($employees as $employee) {

    if(!isset($employee['extref_sd_id']) || strlen($employee['extref_sd_id']) <= 0) {
        // ignore employees with missing SDworx id
        $result['logs'][] = "WARN- Missing SD id for employee {$employee['name']} [CT-{$employee['id']}, SD-{$employee['extref_sd_id']}, AT-{$employee['extref_at_id']}].";
        continue;
    }

    // fetch the latest listing of employees from SDWorx (using API)
    try {
        $data = eQual::run('get', 'contractika_sd_absences', [
                'id'            => $employee['id'],
                'future_months' => $future_months,
                'passed_months' => $passed_months,
                'last_run'      => $last_run
            ]);
    }
    catch(Exception $e) {
        // ignore faulty employees
        ++$result['errors'];
        // something went wrong when fetching data of the employee
        $result['logs'][] = "ERR - unable to fetch data for SD employee {$employee['name']} [CT-{$employee['id']}, SD-{$employee['extref_sd_id']}, AT-{$employee['extref_at_id']}]: {$e->getMessage()}";
        continue;
    }

    if(!count($data)) {
        $result['logs'][] = "INFO- No Absence returned for SD employee {$employee['name']} [CT-{$employee['id']}, SD-{$employee['extref_sd_id']}, AT-{$employee['extref_at_id']}]";
    }
    // adapt absences values and update DB
    foreach($data as $line) {

        // SDworx returns data for all Contracts of the identity targeted by Employee (i.e. EmployeeNumber can be different than requested extref_sd_id !)
        if($line['EmployeeNumber'] != $employee['extref_sd_id']) {
            // discard irrelevant data
            ++$result['ignored'];
            $result['logs'][] = "WARN- Mismatch on line {$line['Id']} between employeeNumber and extref_sd_id for employee '{$employee['name']}' [{$employee['id']}]";
            continue;
        }

        $values = [
            'extref_sd_id'  => $line['Id'],
            'employee_id'   => $employee['id'],
            'date'          => strtotime($line['Date']),
            'day_part'      => strtolower($line['DayPart']),
            'measure_unit'  => strtolower($line['InterpretedMeasureUnit']),
            'qty'           => round(floatval($line['InterpretedAmount']), 2),
            'layer'         => strtolower($line['Layer']),
            'status'        => strtolower($line['Status']),
            'code_id'       => $absence_code_map[$line['AbsenceCodeId']]
        ];

        $absences = Absence::search(['extref_sd_id', '=', $line['Id']]);
        // if absence already exists
        if($absences->count()) {
            $fields = ['date', 'day_part', 'measure_unit', 'qty', 'layer', 'status', 'code_id'];
            $absence = $absences->read($fields)->first();
            $has_changes = false;
            // if there are changes, update it
            foreach($fields as $field) {
                if($values[$field] != $absence[$field]) {
                    $absences->update($values);
                    ++$result['updated'];
                    $has_changes = true;
                    $result['logs'][] = "INFO- Updated - Absence {$line['Id']} [{$absence['id']}] : field `$field` changed from '{$absence[$field]}' to '{$values[$field]}'";
                    break;
                }
            }
            if(!$has_changes) {
                $result['logs'][] = "INFO- Skipped - No change for Absence {$line['Id']} [{$absence['id']}]";
            }
        }
        // if absence does not exist yet, create it
        else {
            Absence::create($values);
            $result['logs'][] = "INFO- Created - New Absence {$line['Id']} for SD employee {$employee['name']} [CT-{$employee['id']}, SD-{$employee['extref_sd_id']}, AT-{$employee['extref_at_id']}]";
            ++$result['created'];
        }
        ++$result['processed'];
    }

}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
