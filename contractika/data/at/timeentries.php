<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches the Time Entries from Datto AutoTask API and returns the list as a JSON array.',
    'params'        => [
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date since which we request the created and modified time entries.'
        ],
        'date_to' => [
            'type'              => 'datetime',
            'description'       => 'Last date for which we request the created and modified time entries.'
        ],
        'date_field' => [
            'type'              => 'string',
            'description'       => '(optional) Forces the name of the date field to use for dates comparisons.'
        ],
        'ids' => [
            'type'              => 'array',
            'description'       => 'Specific list of TimeEntry ids that are requested.'
        ],
        'fields' => [
            'type'              => 'array',
            'description'       => 'Specific list of fields that are requested (defaults to all).',
            'default'           => []
        ],
        'ticket_id' => [
            'type'              => 'integer',
            'description'       => '(optional) Forces the ticket for which entries are requested based on AT ticketID.',
            'default'           => 0
        ],
        'task_id' => [
            'type'              => 'integer',
            'description'       => '(optional) Forces the task for which entries are requested based on AT taskID.',
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


// #todo - add params to limit results to a specific ticket/task

/*
    # AT Messages description

    ## Request

    GET https://webservices19.autotask.net/atservicesrest/v1.0/TimeEntries


    ## Response (JSON)

    {
        "items": [
           {
                "id": 236,
                "billingApprovalDateTime": null,
                "billingApprovalLevelMostRecent": 0,
                "billingApprovalResourceID": null,
                "billingCodeID": 29683502,
                "contractID": 29683374,
                "contractServiceBundleID": null,
                "contractServiceID": null,
                "createDateTime": "2020-01-29T16:19:07.613Z",
                "creatorUserID": 29682899,
                "dateWorked": "2020-01-29T00:00:00Z",
                "endDateTime": "2020-01-29T16:20:00Z",
                "hoursToBill": 3.5000,
                "hoursWorked": 3.5000,
                "impersonatorCreatorResourceID": null,
                "impersonatorUpdaterResourceID": null,
                "internalBillingCodeID": 29683502,
                "internalNotes": null,
                "isInternalNotesVisibleToComanaged": false,
                "isNonBillable": false,
                "lastModifiedDateTime": "2020-01-29T16:19:07.613Z",
                "lastModifiedUserID": 29682899,
                "offsetHours": 0.0000,
                "resourceID": 29682899,
                "roleID": 29683355,
                "showOnInvoice": true,
                "startDateTime": "2020-01-29T12:50:00Z",
                "summaryNotes": "Fini",
                "taskID": null,
                "ticketID": 8998,
                "timeEntryType": 2
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

if(isset($params['ids']) && is_array($params['ids'])) {
    $args = '"filter":[{"op":"in","field":"id","value":['.implode(',', $params['ids']).']}]';
}
elseif(isset($params['date_from']) && strlen($params['date_from'])) {
    $date_from = date('Y-m-d', $params['date_from']);

    if(isset($params['date_to']) && strlen($params['date_to'])) {
        $date_to = date('Y-m-d', $params['date_to']);
        $args = '"filter":[{"op":"or","items":[{"op":"and","items":[{"op":"gte","field":"createDateTime","value":"'.$date_from.'"},{"op":"lte","field":"createDateTime","value":"'.$date_to.'"}]},{"op":"and","items":[{"op":"gte","field":"lastModifiedDateTime","value":"'.$date_from.'"},{"op":"lte","field":"lastModifiedDateTime","value":"'.$date_to.'"}]}]}]';
    }
    else {
        $args = '"filter":[{"op":"or","items":[{"op":"gte","field":"createDateTime","value":"'.$date_from.'"},{"op":"gte","field":"lastModifiedDateTime","value":"'.$date_from.'"}]}]';
    }
}
elseif(isset($params['ticket_id']) && $params['ticket_id'] > 0) {
    $args = '"filter":[{"op":"eq","field":"ticketID","value":"'.$params['ticket_id'].'"}]';
}
elseif(isset($params['task_id']) && $params['task_id'] > 0) {
    $args = '"filter":[{"op":"eq","field":"taskID","value":"'.$params['task_id'].'"}]';
}
else {
    throw new Exception('invalid or missing mandatory parameter', QN_ERROR_MISSING_PARAM);
}

if($params['fields'] && count($params['fields'])) {
    $args .= ',"includeFields":["'.implode('","', $params['fields'] ).'"]';
}

// #todo - quick workaround : for some queries we need to use distinct date field. This should be improved.
if(isset($params['date_field']) && strlen($params['date_field']) > 0) {
    $args = str_replace(['createDateTime', 'lastModifiedDateTime'], $params['date_field'], $args);
}

// loop while there is a next page URL or we reach 10.000 items
$max_loop = 20;
$i = 0;
$url = $entrypoint_url.'TimeEntries/query?search={'.$args.'}';
while($i < $max_loop) {
    // create a template request holding API credentials
    $request = new HttpRequest("GET $url");
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
        throw new Exception("request to AT (TimeEntries) rejected with code $status", QN_ERROR_INVALID_PARAM);
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
