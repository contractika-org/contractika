<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches the Projects from Datto AutoTask API and returns the list as a JSON array.',
    'params'        => [
        'ids' => [
            'type'              => 'array',
            'description'       => 'Array of projects identifiers to request (mandatory since there can be a hughe amount of projects).',
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
    # AT Messages description

    ## Request

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Projects


    ## Response (JSON)

    {
       "items": [
            {
                "id": 36,
                "actualBilledHours": 0.0,
                "actualHours": 155.6999,
                "changeOrdersBudget": null,
                "changeOrdersRevenue": 0.0,
                "companyID": 0,
                "companyOwnerResourceID": null,
                "completedDateTime": null,
                "completedPercentage": 100,
                "contractID": 29683527,
                "createDateTime": "2022-04-19T00:00:00Z",
                "creatorResourceID": 29682886,
                "department": 29683481,
                "description": "Mise en oeuvre approche MSP pour les outils RMM",
                "duration": 66,
                "endDateTime": "2022-06-23T00:00:00Z",
                "estimatedSalesCost": 0.0,
                "estimatedTime": 0.0,
                "extProjectNumber": "",
                "extProjectType": null,
                "impersonatorCreatorResourceID": null,
                "laborEstimatedCosts": 0.0,
                "laborEstimatedMarginPercentage": 0.0,
                "laborEstimatedRevenue": 0.0,
                "lastActivityDateTime": "2023-01-06T11:07:03.303Z",
                "lastActivityPersonType": 1,
                "lastActivityResourceID": 29682886,
                "opportunityID": null,
                "organizationalLevelAssociationID": 19,
                "originalEstimatedRevenue": 0.0,
                "projectCostEstimatedMarginPercentage": 0.0,
                "projectCostsBudget": 0.0,
                "projectCostsRevenue": 0.0,
                "projectLeadResourceID": 29682886,
                "projectName": "MSP Integration RMM WG VEEAM",
                "projectNumber": "P20220419.0001",
                "projectType": 4,
                "purchaseOrderNumber": "",
                "sgda": 0.0,
                "startDateTime": "2022-04-19T00:00:00Z",
                "status": 1,
                "statusDateTime": "2022-04-19T07:51:00Z",
                "statusDetail": "",
                "userDefinedFields": []
            },
            {
                [...]
            }
        ]
    }

*/

$result = [];

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}


// create a template request holding API credentials
$query = '{"filter":[{"op":"in","field":"id","value":['.implode(',', $params['ids']).']}]}';

$request = new HttpRequest("GET $entrypoint_url".'Projects/query?search='.$query);
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

$result = $data['items'];

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
