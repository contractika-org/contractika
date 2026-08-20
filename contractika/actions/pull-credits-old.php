<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\db\DBManipulatorSqlSrv;
use contractika\NAVLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Import (create) NAVLine from NAVISION for reconciliation as SALine.',
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

$result = [
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'processed' => 0,
    'logs'      => []
];

$dbConnection = new DBManipulatorSqlSrv(
    constant('DB_HOST'),
    constant('DB_PORT'),
    'NAV_ITS_80',
    constant('PROVIDERS_NAV_SQL_USERNAME'),
    constant('PROVIDERS_NAV_SQL_PASSWORD'),
    constant('PROVIDERS_NAV_DB_CHARSET'),
    constant('PROVIDERS_NAV_DB_COLLATION')
);

// prod
$min_date = strtotime("-2 month");
// init
// $min_date = strtotime("2023-01-01");

$nav_lines_ids = [];


/* step-1 - fetch lines from invoices */

// #memo - lines in target table cannot be changed (invoices cannot be updated)
$query = 'SELECT * FROM [NETiKA IT Services$Sales Invoice Line] '.
'WHERE [Posting Date] >= \''.date('Y-m-d', $min_date).'\' AND [No_] in (\'NTK-PROVISION\', \'NTK-SERVICEPACKAGE\')';

// #memo - for invoices, `Document No_` starts with 'SI{yy}-'

try {
    // fetch raw values
    $rows = [];

    if(!$dbConnection->connect()) {
        throw new Exception('Unable to connect to selected database.', QN_ERROR_SQL);
    }

    $res = $dbConnection->sendQuery($query);
    while($row = $dbConnection->fetchArray($res) ) {
        $rows[] = $row;
    }
    $dbConnection->disconnect();
    // create NAVLine if not yet present
    foreach($rows as $row) {
        $line = NAVLine::search([ ['extref_document_no', '=', $row['Document No_']], ['extref_line_no', '=', $row['Line No_']] ])->read(['id', 'has_error'])->first();
        if($line) {
            // if has_error, update Description2
            if($line['has_error']) {
                // #memo - will trigger reset of service_account_id and has_error
                NAVLine::id($line['id'])
                    ->update(['has_error_service_account' => false])
                    ->update(['extref_description2' => $row['Description 2']]);
                $nav_lines_ids[] = $line['id'];
                ++$result['updated'];
            }
            // otherwise, ignore
            else {
                continue;
            }
        }
        else {
            $line = NAVLine::create([
                    'extref_document_no'    => $row['Document No_'],
                    'extref_line_no'        => $row['Line No_'],
                    'extref_customer'       => $row['Sell-to Customer No_'],
                    'extref_no'             => $row['No_'],
                    'extref_description2'   => $row['Description 2'],
                    'extref_uom_code'       => $row['Unit of Measure Code'],
                    'extref_unit_price'     => (string) floatval($row['Unit Price']),
                    'extref_quantity'       => (string) floatval($row['Quantity']),
                    'extref_amount'         => (string) floatval($row['Amount']),
                    'description'           => $row['Description'],
                    'date'                  => strtotime($row['Posting Date']),
                    'points'                => floatval($row['Quantity'])
                ])
                ->read(['id'])
                ->first();
            $nav_lines_ids[] = $line['id'];
            ++$result['created'];
        }
    }
}
catch(Exception $e) {
    $dbConnection->disconnect();
    throw new Exception($e->getMessage(), $e->getCode());
}


/* step-2 - fetch lines from credit notes */
// init
// $min_date = strtotime("2023-01-01");

// #memo - lines in target table cannot be changed (invoices cannot be updated)
$query = 'SELECT * FROM [NETiKA IT Services$Sales Cr_Memo Line] '.
'WHERE [Posting Date] >= \''.date('Y-m-d', $min_date).'\' AND [No_] in (\'NTK-PROVISION\', \'NTK-SERVICEPACKAGE\')';

// #memo - for invoices, `Document No_` starts with 'SC{yy}-'

try {
    // fetch raw values
    $rows = [];

    if(!$dbConnection->connect()) {
        throw new Exception('Unable to connect to selected database.', QN_ERROR_SQL);
    }

    $res = $dbConnection->sendQuery($query);
    while($row = $dbConnection->fetchArray($res) ) {
        $rows[] = $row;
    }
    $dbConnection->disconnect();
    // create NAVLine if not yet present
    foreach($rows as $row) {
        $line = NAVLine::search([ ['extref_document_no', '=', $row['Document No_']], ['extref_line_no', '=', $row['Line No_']] ])->read(['id', 'has_error'])->first();
        if($line) {
            // if has_error, update Description2
            if($line['has_error']) {
                // #memo - will trigger reset of service_account_id and has_error
                NAVLine::id($line['id'])
                    ->update(['has_error_service_account' => false])
                    ->update(['extref_description2' => $row['Description 2']]);
                $nav_lines_ids[] = $line['id'];
                ++$result['updated'];
            }
            // otherwise, ignore
            else {
                continue;
            }
        }
        else {
            $line = NAVLine::create([
                    'extref_document_no'    => $row['Document No_'],
                    'extref_line_no'        => $row['Line No_'],
                    'extref_customer'       => $row['Sell-to Customer No_'],
                    'extref_no'             => $row['No_'],
                    'extref_description2'   => $row['Description 2'],
                    'extref_uom_code'       => $row['Unit of Measure Code'],
                    'extref_unit_price'     => (string) floatval($row['Unit Price']),
                    'extref_quantity'       => (string) floatval($row['Quantity']),
                    'extref_amount'         => (string) floatval($row['Amount']),
                    'description'           => $row['Description'],
                    'date'                  => strtotime($row['Posting Date']),
                    'points'                => (-1.0) * floatval($row['Quantity'])
                ])
                ->read(['id'])
                ->first();
            $nav_lines_ids[] = $line['id'];
            ++$result['created'];
        }
    }
}
catch(Exception $e) {
    $dbConnection->disconnect();
    throw new Exception($e->getMessage(), $e->getCode());
}


// force generating computed fields
NAVLine::ids($nav_lines_ids)
    ->read(['customer_id', 'service_account_id'])
    ->read(['has_error']);

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
