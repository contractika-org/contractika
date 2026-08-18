<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\db\DBManipulatorSqlSrv;
use contractika\hr\absence\Absence;
use contractika\identity\Identity;

list($params, $providers) = announce([
    'description'   => 'Fetches data for a specific absence and creates a related record into ITs Dashboard database. Related record is expected to be already present in database.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => Absence::getType(),
            'description'       => 'Absence to store to the database',
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
    'constants'     => [ 'DB_HOST', 'DB_PORT', 'PROVIDERS_DASH_SQL_USERNAME', 'PROVIDERS_DASH_SQL_PASSWORD'],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [];

/*
    # ITs Dashboard.SD_Absence table schema

    Navision is configured to store its data in SQL server database "ITs DashBoard".

    schema [ITs DashBoard].SD_Absence

    column_name             type        length
    Id                      nchar       80      // original SDworx ID
    EmployerNumber          nchar       14
    EmployeeNumber          nchar       14
    FirstName               nchar       100
    LastName                nchar       100
    Date                    date        3
    DayPart                 nchar       40
    OriginalAmount          float       8
    OriginalMeasureUnit     nchar       40
    InterpretedAmount       float       8
    InterpretedMeasureUnit  nchar       40
    AbsenceCodeId           nchar       8
    AbsenceCodeLabel        nchar       100
    Layer                   nchar       40
    Status                  nchar       100
    ShiftCode               nchar       20
    LastModificationDate    datetime    8
    UniqueKey               nchar       160     // legacy composite identifier

*/


$dbConnection = new DBManipulatorSqlSrv(
    constant('DB_HOST'),
    constant('DB_PORT'),
    'ITs DashBoard',
    constant('PROVIDERS_DASH_SQL_USERNAME'),
    constant('PROVIDERS_DASH_SQL_PASSWORD')
);

if(!$dbConnection->connect()) {
    throw new Exception('Unable to connect to selected database.', QN_ERROR_SQL);
}

$organisation = Identity::id(1)->read(['name', 'extref_sd_id'])->first();

// retrieve data for record creation
$absence = Absence::id($params['id'])->read([
        'extref_sd_id',
        'status',
        'date',
        'day_part',
        'measure_unit',
        'qty',
        'duration',
        'layer',
        'code_id' => [
            'code',
            'description'
        ],
        'employee_id' => [
            'extref_sd_id',
            'partner_identity_id' => ['firstname', 'lastname']
        ]
    ])
    ->first();

// #memo - this is not used anymore, maintained as legacy (SDworx absence ID has proven to be unique)
$unique_key = implode('|', [$absence['employee_id']['extref_sd_id'], date('Ymd', $absence['date']), $absence['day_part'], $absence['code_id']['code'], $absence['extref_sd_id']]);

$values = [
    'EmployerNumber'            => $organisation['extref_sd_id'],
    'EmployeeNumber'            => $absence['employee_id']['extref_sd_id'],
    'FirstName'                 => $absence['employee_id']['partner_identity_id']['firstname'],
    'LastName'                  => $absence['employee_id']['partner_identity_id']['lastname'],
    'Date'                      => date('Y-m-d', $absence['date']),
    'DayPart'                   => $absence['day_part'],
    'OriginalAmount'            => (float) $absence['qty'],
    'OriginalMeasureUnit'       => $absence['measure_unit'],
    'InterpretedAmount'         => (float) $absence['duration'],
    'InterpretedMeasureUnit'    => 'hours',
    'AbsenceCodeId'             => $absence['code_id']['code'],
    'AbsenceCodeLabel'          => str_replace("'", "''", $absence['code_id']['description']),
    'Layer'                     => $absence['layer'],
    'Status'                    => $absence['status'],
    'ShiftCode'                 => '',
    'LastModificationDate'      => substr(date('c', time()), 0, -6),
    'UniqueKey'                 => $unique_key
];

$assign = array_map(
        function($k, $v) {
            return '['.$k.'] = \''.$v.'\'';
        },
        array_keys($values),
        array_values($values)
    );

// try to insert the absence into DB
$query = 'UPDATE [SD_Absence] '.
'SET '.implode(',', $assign).' '.
'WHERE Id like \''.$absence['extref_sd_id'].'%\' ';

try {
    $res = $dbConnection->sendQuery($query);
    $dbConnection->disconnect();
}
catch(Exception $e) {
    $dbConnection->disconnect();
    throw new Exception($e->getMessage(), $e->getCode());
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
