<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use equal\text\TextTransformer;
use contractika\hr\employee\Employee;
use contractika\hr\employee\Role;
use contractika\identity\Identity;
use core\setting\Setting;


[$params, $providers] = eQual::announce([
    'description'   => 'Retrieve a single Employee (Resource) from AT.',
    'params'        => [
        'id' => [
            'type'              => 'integer',
            'description'       => 'Autotask ID of the Resource to fetch.',
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
            "id": 29682895,
            "accountingReferenceID": "NVANSAND",
            "dateFormat": "dd\/MM\/yyyy",
            "defaultServiceDeskRoleID": 29683355,
            "email": "Nicolas.VANSAND@its.netika.com",
            "email2": "nicolas.vansand@example.com",
            "email3": "",
            "emailTypeCode": "PRIMARY",
            "emailTypeCode2": "SECONDARY",
            "emailTypeCode3": null,
            "firstName": "Nicolas",
            "gender": "M",
            "greeting": 4,
            "hireDate": "2015-09-15T00:00:00Z",
            "homePhone": "",
            "initials": "0000012",
            "internalCost": 50,
            "isActive": false,
            "lastName": "VAN SAND",
            "licenseType": 3,
            "locationID": 90683,
            "middleName": "",
            "mobilePhone": "+32 475 021 839",
            "numberFormat": "X,XXX.XX",
            "officeExtension": "",
            "officePhone": "",
            "payrollIdentifier": "0000012",
            "payrollType": 1,
            "resourceType": "Employee",
            "suffix": null,
            "surveyResourceRating": null,
            "timeFormat": "HH:mm",
            "title": "",
            "travelAvailabilityPct": "up to 100%",
            "userName": "Nicolas.VANSAND",
            "userType": 24
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
$url_query = 'search={"filter":[{"op":"eq","field":"id","value":'.$params['id'].'}]}';

$request = new HttpRequest("GET $entrypoint_url".'Resources/query?'.$url_query);

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
$test_data = file_get_contents('at_resource_response.json');
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

$result = current($data['items']);

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
