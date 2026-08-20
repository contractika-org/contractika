<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\db\DBManipulatorSqlSrv;

[$params, $providers] = eQual::announce([
    'description'   => 'Requests the list of payment terms from Navision database and returns it as a JSON array.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'constants'     => [ 'DB_HOST', 'DB_PORT', 'PROVIDERS_NAV_SQL_USERNAME', 'PROVIDERS_NAV_SQL_PASSWORD', 'PROVIDERS_NAV_DB_CHARSET', 'PROVIDERS_NAV_DB_COLLATION' ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [];

/*
    # Navision PaymentTerms objects description

    Navision is configured to store its data in SQL server database "NAV_ITS_80".
*/

// PROD
$dbConnection = new DBManipulatorSqlSrv(
    constant('DB_HOST'),
    constant('DB_PORT'),
    'NAV_ITS_80',
    constant('PROVIDERS_NAV_SQL_USERNAME'),
    constant('PROVIDERS_NAV_SQL_PASSWORD'),
    constant('PROVIDERS_NAV_DB_CHARSET'),
    constant('PROVIDERS_NAV_DB_COLLATION')
);

if(!$dbConnection->connect()) {
    throw new Exception('Unable to connect to selected database.', QN_ERROR_SQL);
}

$query = 'SELECT * from [NETiKA IT Services$Payment Terms]';

$res = $dbConnection->sendQuery($query);
while($row = $dbConnection->fetchArray($res) ) {
    $result[] = [
        'Code'                   => $row['Code'],
        'Description'            => $row['Description'],
        'Discount'               => $row['Discount _'],
    ];
}

$dbConnection->disconnect();

// TEST
// $result = file_get_contents('nav_payment-terms_response.json');

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
