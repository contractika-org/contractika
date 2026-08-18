<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => 'Fetches the Resources (employees) Roles from Datto AutoTask API and returns it as a JSON array.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'constants'     => [ 'PROVIDERS_AT_API_APPKEY', 'PROVIDERS_AT_API_USERNAME', 'PROVIDERS_AT_API_PASSWORD'],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

/*
    The list of employees is maintained using SDworx API
*/

/*
    # AT Messages description

    ## Request

    GET https://webservices19.autotask.net/atservicesrest/v1.0/ResourceRoles


    ## Response (JSON)

    {
        "items": [
            {
                "id": 29683468,
                "name": "System Engineer",
                "description": "",
                "hourlyFactor": 1.2000,
                "hourlyRate": 110.4700,
                "isActive": true,
                "isExcludedFromNewContracts": false,
                "isSystemRole": false,
                "quoteItemDefaultTaxCategoryId": 1,
                "roleType": 0
            },
            {
                [...]
            },
            [...]
        }
    ]

*/

$result = [];

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}

// create a template request holding API credentials
$request = new HttpRequest("GET $entrypoint_url".'Roles/query?search={"filter":[{"op":"eq","field":"isActive","value":"true"}]}');
$request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json");


// TEST - manually feed response with stored data
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$test_data = file_get_contents('at_roles_response.json');
$response
    ->setHeader('Content-Type', 'application/json')
    ->setStatus(200)
    ->setBody($test_data);
*/

// PROD - request the provider
/** @var HttpResponse */
$response = $request->send();


// check response status
$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception("request to AT (Roles) rejected with code $status", QN_ERROR_INVALID_PARAM);
}

// we should have received an application/json response, if so HttpMessage::body() contains a decoded version of the JSON data
$data = $response->body();

if(!is_array($data) || !isset($data['items'])) {
    throw new Exception("response is empty", QN_ERROR_UNKNOWN);
}

$result = $data['items'];

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
