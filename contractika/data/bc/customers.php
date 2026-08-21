<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;

[$params, $providers] = announce([
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
    'constants'     => [ 'PROVIDERS_BC_TENANT_ID' ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [];

$environment = 'Production';
$company = 'NETiKA IT Services';

$tenant_id = constant('PROVIDERS_BC_TENANT_ID');
$entrypoint_url = "https://api.businesscentral.dynamics.com/v2.0";

$data = eQual::run('get', 'contractika_bc_token', []);

$token = $data['token'];

$today = time();

$default_price = null;

$map_customer_prices = [];

$starting_date = date('Y-01-01');
$ending_date = date('Y-12-31');


// 1) fetch default Price (applicable in case no price is set for a Customer)
$request = new HttpRequest(
    "GET {$entrypoint_url}/{$tenant_id}/{$environment}/ODataV4/Company('" . rawurlencode($company) . "')/SalesPricesLines"
);

$response = $request
    ->body([
        '$filter' =>
            "Asset_No eq 'NTK-SERVICEPACKAGE'
             and AssignToNo eq ''
             and Unit_of_Measure_Code eq 'PNT'
             and Asset_Type eq 'Item'
             and minimum_Quantity eq 0
             and StartingDate le " . date('Y-m-d'),
        '$select' => 'Unit_Price,StartingDate,EndingDate'
    ])
    ->header('Authorization', "Bearer $token")
    ->send();

// check response status
$status = $response->getStatusCode();

if($status != 200) {
    ob_start();
    print_r($response->body());
    $out = ob_get_clean();
    $reporter->error('Error fetching SalePricesLines ' . $out);
    // upon request rejection, we stop the whole job
    throw new Exception("Error fetching SalePricesLines - Request to BC rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();

foreach($data['value'] as $price) {
    if(strtotime($price['StartingDate']) > $today) {
        continue;
    }
    if($price['EndingDate'] !== '0001-01-01' && strtotime($price['EndingDate']) < $today) {
        continue;
    }
    $default_price = (float) $price['Unit_Price'];
    break;
}

// raise an Exception if no default Price was found
if(!$default_price) {
    throw new Exception("Error fetching SalePricesLines - No default Price found - stopping process", QN_ERROR_INVALID_PARAM);
}


// 2) fetch Payment Terms / Sale Prices Lines (all customers)
$request = new HttpRequest("GET {$entrypoint_url}/{$tenant_id}/{$environment}/ODataV4/Company('" . rawurlencode($company) . "')/SalesPricesLines");

$response = $request
    ->body([
        '$count'    => 'true',
        // #memo #fix - fix for BC OData filter issue, by removing condition on EndingDate (if not set, falls back to 0001-01-01)
        // '$filter'   => 'Asset_No eq \'NTK-SERVICEPACKAGE\' and AssignToNo ne \'\' and Unit_of_Measure_Code eq \'PNT\' and Asset_Type eq \'Item\' and StartingDate ge ' . $starting_date . ' and EndingDate ge ' . $ending_date,
        '$filter'   =>
            'Asset_No eq \'NTK-SERVICEPACKAGE\'
            and AssignToNo ne \'\'
            and Unit_of_Measure_Code eq \'PNT\'
            and Asset_Type eq \'Item\'
            and StartingDate ge ' . $starting_date,
        '$select'   => 'AssignToNo,Description,Unit_Price,StartingDate,EndingDate'
    ])
    ->header('Authorization', "Bearer $token")
    ->send();

// check response status
$status = $response->getStatusCode();

if($status != 200) {
    ob_start();
    print_r($response->body());
    $out = ob_get_clean();
    $reporter->error('Error fetching Customers ' . $out);
    // upon request rejection, we stop the whole job
    throw new Exception("Error fetching Customers SalePricesLines - Request to BC rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();



// pass-1 build map_customer_prices and ignore prices with outdated time range (we might have fetched more than current year)
foreach($data['value'] as $price) {
    // #memo - we assume StartingDate is always set (and never '0001-01-01')
    if(strtotime($price['StartingDate']) > $today) {
        continue;
    }
    if($price['EndingDate'] !== '0001-01-01' && strtotime($price['EndingDate']) < $today) {
        continue;
    }
    $map_customer_prices[$price['AssignToNo']] = $price['Unit_Price'];
}


// 3) fetch Customers
$request = new HttpRequest("GET {$entrypoint_url}/{$tenant_id}/{$environment}/api/v2.0/customers");

$response = $request
    ->body([
        '$count'    => 'true',
        'company'   => $company,
        '$select'   => 'number,displayName,blocked,taxRegistrationNumber,lastModifiedDateTime',
        '$expand'   => 'paymentTerm($select=code,displayName,discountPercent)'
    ])
    ->header('Authorization', "Bearer $token")
    ->send();


// check response status
$status = $response->getStatusCode();

if($status != 200) {
    ob_start();
    print_r($response->body());
    $out = ob_get_clean();
    $reporter->error('Error fetching SalePricesLines ' . $out);
    // upon request rejection, we stop the whole job
    throw new Exception("Error fetching Customers - Request to BC rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();

// pass-2 build result set
foreach($data['value'] as $customer) {
    $last_update = strtotime($customer['lastModifiedDateTime']);

    if($last_update < $params['date_from']) {
        continue;
    }

    $customer_bc_id = $customer['number'];

    $service_price = floatval($map_customer_prices[$customer_bc_id] ?? $default_price);

    if($service_price <= 0) {
        $reporter->error("Active customer without ServicePrice [BC {$customer_bc_id}]");
    }

    $result[] = [
        'Id'                        => $customer_bc_id,
        'Name'                      => $customer['displayName'],
        'Blocked'                   => ($customer['blocked'] === 'All'),
        'Vat'                       => $customer['taxRegistrationNumber'],
        'Discount'                  => floatval($customer['paymentTerm']['discountPercent'] ?? 0),
        'ServicePrice'              => $service_price,
        'PaymentTermsCode'          => $customer['paymentTerm']['code'] ?? '',
        'PaymentTermsDescription'   => $customer['paymentTerm']['displayName'] ?? ''
    ];
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
