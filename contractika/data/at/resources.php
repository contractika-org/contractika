<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use equal\text\TextTransformer;
use contractika\hr\employee\Employee;
use contractika\hr\employee\Role;
use contractika\identity\Identity;
use core\setting\Setting;


list($params, $providers) = announce([
    'description'   => 'Retrieve the Employee and Identity objects from AT.',
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

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Resources/query?search={"filter":[{"op":"eq","field":"resourceType","value":"Employee"}]}


    ## Response (JSON)

    [
        {
            "id": 29682927,
            "accountingReferenceID": "RMEERE",
            "dateFormat": "dd.MM.yyyy",
            "defaultServiceDeskRoleID": 29683469,
            "email": "remi.meere@its.netika.com",
            "email2": "remimeere@gmail.com",
            "email3": "",
            "emailTypeCode": "PRIMARY",
            "emailTypeCode2": "SECONDARY",
            "emailTypeCode3": null,
            "firstName": "Rémi",
            "gender": "M",
            "greeting": 4,
            "hireDate": "2022-09-05T00:00:00Z",
            "homePhone": "",
            "initials": "0000019",
            "internalCost": 30.0000,
            "isActive": true,
            "lastName": "MEERE",
            "licenseType": 3,
            "locationID": 90683,
            "middleName": "",
            "mobilePhone": "+32483 74 30 50",
            "numberFormat": "X,XXX.XX",
            "officeExtension": "",
            "officePhone": "",
            "payrollType": 1,
            "resourceType": "Employee",
            "suffix": null,
            "surveyResourceRating": null,
            "timeFormat": "HH:mm",
            "title": "Stagiaire",
            "travelAvailabilityPct": null,
            "userName": "Remi.MEERE",
            "userType": 24
        }
        ,
        {
            [...]
        },
        [...]
    ]

*/

$result = [];

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}

// create a template request holding API credentials
$request = new HttpRequest("GET $entrypoint_url".'Resources/query?search={"filter":[{"op":"eq","field":"resourceType","value":"Employee"}]}');

// another possible way for filtering employees (does not seem to work): {"op":"eq","field":"licenseType","value":"2"}

/** @var HttpResponse */
$response = $request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json");


// TEST - manually feed response with stored data
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$test_data = file_get_contents('at_resources_response.json');
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
    throw new Exception("request to AT (Resources) rejected with code $status", QN_ERROR_INVALID_PARAM);
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
