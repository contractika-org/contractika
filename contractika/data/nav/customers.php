<?php
use equal\db\DBManipulatorSqlSrv;

list($params, $providers) = announce([
    'description'   => 'Requests the list of customers from Navision database and returns it as a JSON array.',
    'params'        => [
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date for filtering customers that have been changed since.',
            'default'           => 0
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
    'constants'     => [ 'DB_HOST', 'DB_PORT', 'PROVIDERS_NAV_SQL_USERNAME', 'PROVIDERS_NAV_SQL_PASSWORD', 'PROVIDERS_NAV_DB_CHARSET', 'PROVIDERS_NAV_DB_COLLATION'],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [];

/*
    # Navision Customer objects description

    Navision is configured to store its data in SQL server database "NAV_ITS_80".



    Required fields are fetched from the table `NETiKA IT Services$Customer` and returned according to the following map :

        'Id'                        => $row['No_'],
        'Blocked'                   => $row['Blocked'],
        'Vat'                       => $row['VAT Registration No_'],
        'Discount'                  => $row['Discount _'],
        'TargetMargin'              => $row['Target Margin'],
        'ServicePrice'              => (computed)
        'PaymentTermCode'           => $row['Code'],
        'PaymentTermDescription'    => $row['Description']
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


// pass-1: retrieve price list

// read all prices for current year at once including default price ([Sales Code] is AT_NAV_ID)
$today = date('Y-m-d');

$query = 'SELECT [Item No_],[Unit Price],[Sales Type],[Sales Code] '.
'FROM [NETiKA IT Services$Sales Price] '.
'WHERE [Item No_] = \'NTK-SERVICEPACKAGE\' AND [Starting Date]<=\''.$today.'\' AND [Ending Date]>=\''.$today.'\' '.
'AND ( ( [Sales Type] = 0 ) OR ( [Sales Type] = 2 AND [Minimum Quantity] = 0) )';

$customer_prices_map = [];
$default_price = 0.0;
$default_margin = 0.15;

$res = $dbConnection->sendQuery($query);
while($row = $dbConnection->fetchArray($res) ) {
	if($row['Sales Type'] == 2) {
		$default_price = $row['Unit Price'];
	}
	elseif(isset($row['Sales Code']) && strlen($row['Sales Code']) > 0) {
		$customer_prices_map[$row['Sales Code']] = $row['Unit Price'];
	}
}

// pass-2: retrieve customer data

$query = 'SELECT t0.No_, t0.Name, t0.Blocked, t0.[VAT Registration No_], t0.[Target Margin], t1.[Discount _], t1.Code, t1.Description AS [PaymentTerms], t0.[Last Date Modified] '.
'FROM dbo.[NETiKA IT Services$Customer] as t0, dbo.[NETiKA IT Services$Payment Terms] as t1 '.
'WHERE t0.[Payment Terms Code] = t1.Code ';

// #memo - removed because it does not support last time of change for `Unit Price`
/*
if($params['date_from'] > 0) {
    $query .= 'AND t0.[Last Date Modified] >=\''.date('Y-m-d', $params['date_from']).'\' ';
}
*/

$res = $dbConnection->sendQuery($query);
while($row = $dbConnection->fetchArray($res) ) {
    $customer_nav_id = $row['No_'];
    // convert target margin to a [0-1] float (percent)
    $target_margin = floatval($row['Target Margin']);
    if($target_margin >= 1.0) {
        $target_margin = $target_margin / 100.0;
    }
    $result[] = [
        'Id'                        => $customer_nav_id,
        'Name'                      => $row['Name'],
        'Blocked'                   => $row['Blocked'],
        'Vat'                       => $row['VAT Registration No_'],
        'Discount'                  => floatval($row['Discount _']),
        'TargetMargin'              => ($target_margin > 0)?$target_margin:$default_margin,
        'ServicePrice'              => floatval(isset($customer_prices_map[$customer_nav_id])?$customer_prices_map[$customer_nav_id]:$default_price),
        'PaymentTermsCode'          => $row['Code'],
        'PaymentTermsDescription'   => (string) $row['PaymentTerms']
    ];
}

$dbConnection->disconnect();


// TEST
// $result = file_get_contents('nav_customers_response.json');

$context
    ->httpResponse()
    ->body($result)
    ->send();
