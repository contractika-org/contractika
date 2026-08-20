<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;

[$params, $providers] = eQual::announce([
    'description'   => 'Requests a new token for accessing Business Central API (validity 60 min).',
    'params'        => [
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'examples' => [
        'CLI'           => './equal.run --get=contractika_bc_token'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'constants'     => [ 'PROVIDERS_BC_TENANT_ID', 'PROVIDERS_BC_CLIENT_ID', 'PROVIDERS_BC_CLIENT_SECRET' ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];


$tenant_id = constant('PROVIDERS_BC_TENANT_ID');
$entrypoint_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";

// build a request to endpoint holding API credentials
$request = new HttpRequest("POST {$entrypoint_url}");
$request
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->body([
        'grant_type' 	=> 'client_credentials',
        'scope' 		=> 'https://api.businesscentral.dynamics.com/.default',
        'client_id' 	=> constant('PROVIDERS_BC_CLIENT_ID'),
        'client_secret' => constant('PROVIDERS_BC_CLIENT_SECRET'),
    ]);

/** @var HttpResponse */
$response = $request->send();

// check response status
$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception("Request to BC rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();
$result = [
    'token' => $data['access_token']
];

$context
    ->httpResponse()
    ->body($result)
    ->status(200)
    ->send();
