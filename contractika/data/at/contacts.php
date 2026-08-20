<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches the Contacts from Datto AutoTask API and returns the list as a JSON array.',
    'params'        => [
        'ids' => [
            'type'              => 'array',
            'description'       => 'Array of contact identifiers to request.',
            'default'           => []
        ],
        'company_id' => [
            'type'              => 'integer',
            'description'       => 'Specific company id for which we request the contacts.'
        ],
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date for filtering contacts that have been changed since.',
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

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Contacts


    ## Response (JSON)

    {
        "items": [
            {
                "id": 30682885,
                "additionalAddressInformation": "",
                "addressLine": "",
                "addressLine1": "",
                "alternatePhone": "",
                "apiVendorID": null,
                "bulkEmailOptOutTime": null,
                "city": "",
                "companyID": 176,
                "companyLocationID": null,
                "countryID": 23,
                "createDate": "2019-11-25T16:18:17.893Z",
                "emailAddress": "gerard@example.com",
                "emailAddress2": null,
                "emailAddress3": null,
                "extension": "",
                "externalID": "",
                "facebookUrl": "",
                "faxNumber": "",
                "firstName": "Gérard",
                "impersonatorCreatorResourceID": null,
                "isActive": 1,
                "isOptedOutFromBulkEmail": false,
                "lastActivityDate": "2019-11-25T16:18:17.893Z",
                "lastModifiedDate": "2019-11-25T16:18:17.887Z",
                "lastName": "Mentor",
                "linkedInUrl": "",
                "middleInitial": null,
                "mobilePhone": "+32 485 56 44 75",
                "namePrefix": null,
                "nameSuffix": null,
                "note": "",
                "receivesEmailNotifications": true,
                "phone": "",
                "primaryContact": true,
                "roomNumber": "",
                "solicitationOptOut": false,
                "solicitationOptOutTime": null,
                "state": "",
                "surveyOptOut": false,
                "title": "Directeur",
                "twitterUrl": "",
                "zipCode": "",
                "userDefinedFields": [
                    {
                        "name": "Alternate Email",
                        "value": null
                    },
                    {
                        "name": "CRM ID",
                        "value": "DCB71616-857F-E811-A971-000D3AB4CCCD"
                    },
                    {
                        "name": "Customer Contact",
                        "value": null
                    },
                    {
                        "name": "Language",
                        "value": "ENU"
                    },
                    {
                        "name": "TimeSheets Report",
                        "value": null
                    }
                ]
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

// by default, retrieve all contacts
$query = '{"filter":[{"op":"eq","field":"isActive","value":"true"}]}';

if(count($params['ids'])) {
    // retrieve specific contacts which ID is given as param
    $query = '{"filter":[{"op":"in","field":"id","value":['.implode(',', $params['ids']).']}]}';
}
elseif(isset($params['company_id']) && is_numeric($params['company_id'])) {
    // retrieve all contacts from a specific company
    $query = '{"filter":[{"op":"eq","field":"companyID","value":'.$params['company_id'].'}]}';
}
elseif($params['date_from'] > 0) {
    $query = '{"filter":[{"op":"gte","field":"lastActivityDate","value":"'.date('Y-m-d', $params['date_from']).'"}]}';
}

$request = new HttpRequest("GET $entrypoint_url".'Contacts/query?search='.$query);
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
    throw new Exception("request to AT (Contacts) rejected with code $status", QN_ERROR_INVALID_PARAM);
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
