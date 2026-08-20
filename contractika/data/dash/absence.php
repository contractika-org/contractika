<?php
use equal\db\DBManipulatorSqlSrv;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a specific absence, identified by its SDworx ID, from ITs Dashboard database and returns it as a JSON array (wich might empty if absence is not found).',
    'params'        => [
        'id' => [
            'type'          => 'string',
            'description'   => 'SDworx Identifier',
            'required'      => true
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

// try to fetch the absence from DB
$query = 'SELECT * '.
'FROM [SD_Absence] '.
'WHERE [Id] LIKE \''.$params['id'].'%\' ';

$res = $dbConnection->sendQuery($query);
while($row = $dbConnection->fetchArray($res) ) {
    $result[] = $row;
}

$dbConnection->disconnect();

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
