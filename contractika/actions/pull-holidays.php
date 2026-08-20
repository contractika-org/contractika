<?php
use contractika\hr\employee\Employee;
use contractika\hr\holiday\Holiday;
use contractika\hr\absence\Absence;
use hr\absence\AbsenceCode;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Updates the list of Holiday objects (legal days-off) based on list of Holidays from AT, and create related Absence objects that do not exist yet for each employee.',
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
    'created' => 0,
    'updated' => 0,
    'unknown' => 0,
    'logs'    => []
];

// retrieve legal absence_code for holiday
$legal_holiday_absence_code = Setting::get_value('contractika', 'sync', 'holiday.absence_code', 'T350');

// build absence codes map (prefetch all absence codes)
$absence_code_map = [];
$absenceCodes = AbsenceCode::search()->read(['id', 'code']);
if(!count($absenceCodes)) {
    throw new Exception('Missing mandatory setting: absence codes listing.', QN_ERROR_INVALID_CONFIG);
}
foreach($absenceCodes as $oid => $code) {
    $absence_code_map[$code['code']] = $code['id'];
}

if(!isset($absence_code_map[$legal_holiday_absence_code])) {
    throw new Exception('Missing mandatory setting: legal holiday absence code.', QN_ERROR_INVALID_CONFIG);
}

$legal_holiday_absence_code_id = $absence_code_map[$legal_holiday_absence_code];

// fetch the latest listing of holidays from AutoTask (using API)
$data = eQual::run('get', 'contractika_at_holidays');

// fetch all active employees
$employees = Employee::search(['is_active', '=', true])->read(['id', 'extref_at_id']);
$resources_map = [];

$today = time();

foreach($data as $at_holiday) {
    // parse date
    $at_holiday_date = strtotime($at_holiday['holidayDate']);
    // search for local entity
    $holiday = Holiday::search(['extref_at_id', '=', $at_holiday['id']])->read(['id', 'date'])->first();
    if(!$holiday) {
        ++$result['created'];
        // entity does not exist yet: create it
        $holiday = Holiday::create([
                'date'          => $at_holiday_date,
                'name'          => $at_holiday['holidayName'],
                'extref_at_id'  => $at_holiday['id']
            ])
            ->read(['id', 'date'])
            ->first();
    }
    if($holiday['date'] < $today) {
        // holiday is in the past: skip
        $result['logs'][] = "ignored passed holiday {$at_holiday['holidayName']} for date {$at_holiday['holidayDate']}";
        ++$result['ignored'];
    }
    else {
        // create an absence for all active employees
        foreach($employees as $employee) {
            // search for existing equivalent Absence
            $absences = Absence::search([
                    ['employee_id', '=', $employee['id']],
                    ['date', '=', $at_holiday_date],
                    ['day_part', '=', 'fullday'],
                    ['code_id', '=', $legal_holiday_absence_code_id]
                ]);
            if(count($absences)) {
                // make sure absence is handled as an holiday
                // #memo - holiday can be manually requested by employee (employees dont have to, but can)
                $absences->update([
                    'is_holiday'    => true,
                    'holiday_id'    => $holiday['id']
                ]);
                ++$result['updated'];
            }
            else {
                // extref_sd_id is left to null (irrelevant for holidays)
                // extref_at_id is left to null (yet to be created)
                Absence::create([
                    'employee_id'   => $employee['id'],
                    'date'          => $holiday['date'],
                    'day_part'      => 'fullday',
                    'measure_unit'  => 'fullday',
                    'qty'           => 1,
                    'layer'         => 'planning',
                    'status'        => 'planned',
                    'code_id'       => $legal_holiday_absence_code_id,
                    'is_holiday'    => true,
                    'holiday_id'    => $holiday['id']
                ]);
            }
        }
    }
}


$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
