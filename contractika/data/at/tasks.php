<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches the Tasks from Datto AutoTask API and returns the list as a JSON array.',
    'params'        => [
        'ids' => [
            'type'              => 'array',
            'description'       => 'Array of tasks identifiers to request (mandatory since there can be a large amount of tasks).',
            'default'           => []
        ],
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date for filtering tasks that have been changed since.',
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
    'constants'     => [ 'PROVIDERS_AT_API_APPKEY', 'PROVIDERS_AT_API_USERNAME', 'PROVIDERS_AT_API_PASSWORD'],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

/*
    # AT Messages description

    ## Request

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Tasks


    ## Response (JSON)

    {
        "items": [
            {
                "id": 19254,
                "assignedResourceID": 29682886,
                "assignedResourceRoleID": 29683466,
                "billingCodeID": 29683499,
                "canClientPortalUserCompleteTask": false,
                "companyLocationID": 544,
                "completedByResourceID": null,
                "completedByType": null,
                "completedDateTime": null,
                "createDateTime": "2020-05-01T09:49:57.183Z",
                "creatorResourceID": 29682886,
                "creatorType": 1,
                "departmentID": 29683481,
                "description": "",
                "endDateTime": "2020-09-06T00:00:00Z",
                "estimatedHours": 0.0,
                "externalID": "",
                "hoursToBeScheduled": -168.1166,
                "isTaskBillable": false,
                "isVisibleInClientPortal": true,
                "lastActivityDateTime": "2022-12-09T12:28:49.873Z",
                "lastActivityPersonType": 1,
                "lastActivityResourceID": 29682886,
                "phaseID": null,
                "priority": 0,
                "priorityLabel": null,
                "projectID": 9,
                "purchaseOrderNumber": "",
                "remainingHours": 0.0,
                "startDateTime": "2020-05-01T00:00:00Z",
                "status": 8,
                "taskCategoryID": 2,
                "taskNumber": "T20200501.0049",
                "taskType": 1,
                "title": "Deploiement Clients - coordination",
                "userDefinedFields": []
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
if(isset($params['ids']) && count($params['ids'])) {
    $query = '{"filter":[{"op":"in","field":"id","value":['.implode(',', $params['ids']).']}]}';
}
elseif($params['date_from'] > 0) {
    $query = '{"filter":[{"op":"gte","field":"lastActivityDateTime","value":"'.date('Y-m-d\TH:i:s\Z', $params['date_from']).'"}]}';
}
else {
    // default query (filtering is mandatory when sending GET requests)
    $query = '{"filter":[{"op":"gte","field":"id","value":"1"}]}';
}

$request = new HttpRequest("GET $entrypoint_url".'Tasks/query?search='.$query);
$request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json");


// PROD - request the provider
/** @var HttpResponse */
$response = $request->send();

// check response status
$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception("request to AT (Tasks) rejected with code $status", QN_ERROR_INVALID_PARAM);
}

// we should have received an application/json response, if so HttpMessage::body() contains a decoded version of the JSON data
$data = $response->body();

if(!is_array($data) || !isset($data['items'])) {
    throw new Exception("response is empty", QN_ERROR_UNKNOWN);
}

$result = array_merge($result, $data['items']);

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
