<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;

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


// fetch Payment Terms

$request = new HttpRequest("GET {$entrypoint_url}/{$tenant_id}/{$environment}/api/v2.0/paymentTerms");
$response = $request
    ->body([
        'company'   => $company,
        '$select'   => 'code,displayName,discountPercent',

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

foreach($data['value'] as $term) {
    $result[] = [
        'Code'                   => $term['code'],
        'Description'            => $term['displayName'],
        'Discount'               => $term['discountPercent'],
    ];
}

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
