<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches the Tickets from Datto AutoTask API and returns the list as a JSON array.',
    'params'        => [
        'ids' => [
            'type'              => 'array',
            'description'       => 'Array of tickets identifiers to request (mandatory since there can be a large amount of tickets).',
            'default'           => []
        ],
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date for filtering tickets that have been changed since.',
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

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Tickets


    ## Response (JSON)

    {
        "items": [
            {
                "id": 29296,
                "apiVendorID": null,
                "assignedResourceID": 29682899,
                "assignedResourceRoleID": 29683355,
                "billingCodeID": 29683502,
                "changeApprovalBoard": null,
                "changeApprovalStatus": null,
                "changeApprovalType": null,
                "changeInfoField1": "",
                "changeInfoField2": "",
                "changeInfoField3": "",
                "changeInfoField4": "",
                "changeInfoField5": "",
                "companyID": 629,
                "companyLocationID": 457,
                "completedByResourceID": 29682899,
                "completedDate": "2022-12-07T09:15:28.147Z",
                "configurationItemID": null,
                "contactID": 30684718,
                "contractID": 29683361,
                "contractServiceBundleID": null,
                "contractServiceID": null,
                "createDate": "2020-09-24T09:40:14.113Z",
                "createdByContactID": null,
                "creatorResourceID": 29682885,
                "creatorType": 1,
                "currentServiceThermometerRating": null,
                "description": null,
                "dueDateTime": "2022-12-06T13:00:00Z",
                "estimatedHours": 3.5000,
                "externalID": "",
                "firstResponseAssignedResourceID": null,
                "firstResponseDateTime": "2020-09-24T09:40:16.073Z",
                "firstResponseDueDateTime": null,
                "firstResponseInitiatingResourceID": null,
                "hoursToBeScheduled": 0.0000,
                "impersonatorCreatorResourceID": null,
                "isAssignedToComanaged": false,
                "issueType": 6,
                "isVisibleToComanaged": false,
                "lastActivityDate": "2022-12-07T09:15:52.407Z",
                "lastActivityPersonType": 1,
                "lastActivityResourceID": 4,
                "lastCustomerNotificationDateTime": "2022-12-07T09:15:45.2Z",
                "lastCustomerVisibleActivityDateTime": "2022-12-07T09:15:28.133Z",
                "lastTrackedModificationDateTime": "2020-09-24T09:40:14.113Z",
                "monitorID": null,
                "monitorTypeID": null,
                "opportunityID": null,
                "organizationalLevelAssociationID": null,
                "previousServiceThermometerRating": null,
                "priority": 2,
                "problemTicketId": null,
                "projectID": null,
                "purchaseOrderNumber": "",
                "queueID": 29683354,
                "resolution": "",
                "resolutionPlanDateTime": "2020-09-24T09:40:16.073Z",
                "resolutionPlanDueDateTime": null,
                "resolvedDateTime": "2022-12-06T16:10:00Z",
                "resolvedDueDateTime": null,
                "rmaStatus": null,
                "rmaType": null,
                "rmmAlertID": null,
                "serviceLevelAgreementHasBeenMet": null,
                "serviceLevelAgreementID": null,
                "serviceLevelAgreementPausedNextEventHours": null,
                "serviceThermometerTemperature": null,
                "source": 13,
                "status": 5,
                "subIssueType": null,
                "ticketCategory": 3,
                "ticketNumber": "T20200924.0046.051",
                "ticketType": 1,
                "title": "ICT Coaching",
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


// #memo - GET request URL is limited to 2048 bytes, remember not to request too many items (100 at a time seems a good limit).

// create a template request holding API credentials
if(isset($params['ids']) && count($params['ids'])) {
    $query = '{"filter":[{"op":"in","field":"id","value":['.implode(',', $params['ids']).']}]}';
}
elseif($params['date_from'] > 0) {
    $query = '{"filter":[{"op":"gte","field":"lastActivityDate","value":"'.date('Y-m-d\TH:i:s\Z', $params['date_from']).'"}]}';
}
else {
    // default query (filtering is mandatory when sending GET requests)
    $query = '{"filter":[{"op":"gte","field":"id","value":"1"}]}';
}


// loop while there is a next page URL or we reach 10.000 items
$max_loop = 20;
$i = 0;
while($i < $max_loop) {
    $request = new HttpRequest("GET $entrypoint_url".'Tickets/query?search='.$query);
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
        throw new Exception("request to AT (Tickets) rejected with code $status", QN_ERROR_INVALID_PARAM);
    }

    // we should have received an application/json response, if so HttpMessage::body() contains a decoded version of the JSON data
    $data = $response->body();

    if(!is_array($data) || !isset($data['items'])) {
        throw new Exception("response is empty", QN_ERROR_UNKNOWN);
    }

    $result = array_merge($result, $data['items']);

    if(isset($data['pageDetails']['nextPageUrl']) && !is_null($data['pageDetails']['nextPageUrl'])) {
        // there are more items: keep on fetching by using the next page URL
        $url = $data['pageDetails']['nextPageUrl'];
    }
    else {
        break;
    }
    ++$i;
}

$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
