<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use contractika\identity\Identity;
use core\setting\Setting;


list($params, $providers) = announce([
    'description'   => 'Updates an Appointment on AT given its ID, using Datto AutoTask API.',
    'params'        => [
        'id'   => [
            'description'       => 'Identifier of the AT Appointment to update.',
            'type'              => 'integer',
            'required'          => true
        ],
        'resource_id'   => [
            'description'       => 'Identifier of the AT Resources the Appointment relates to.',
            'type'              => 'integer',
            'required'          => true
        ],
        'datetime_from'   => [
            'description'       => 'Moment at which the appointment should start.',
            'type'              => 'datetime',
            'required'          => true
        ],
        'datetime_to'   => [
            'description'       => 'Moment at which the appointment should end.',
            'type'              => 'datetime',
            'required'          => true
        ],
        'title'   => [
            'description'       => 'Label for the appointment.',
            'type'              => 'string',
            'required'          => true
        ],
        'description'   => [
            'description'       => 'Short description for the appointment.',
            'type'              => 'string',
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

    PATCH https://webservices19.autotask.net/atservicesrest/v1.0/Appointments

    ### body

    {
        "id": 33329,
        "resourceID": 29682927,
        "startDateTime": "2022-09-05T09:00:00Z",
        "endDateTime": "2022-09-05T17:00:00Z",
        "title": "",
        "description": ""
    }

    ## Response
    {
        "itemId": 33329
    }

*/

$result = [];

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}

$organisation = Identity::id(1)->read(['name', 'extref_sd_id'])->first();
$body = [
        "id"            => $params['id'],
        "resourceID"    => $params['resource_id'],
        "startDateTime" => date('c', $params['datetime_from']),
        "endDateTime"   => date('c', $params['datetime_to']),
        "title"         => $params['title'],
        "description"   => $params['description']
    ];

// create a template request holding API credentials
$request = new HttpRequest("PATCH $entrypoint_url".'Appointments');
$request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json")
    ->body($body);

// PROD - request the provider
/** @var HttpResponse */
$response = $request->send();

// TEST
$reporter->debug("sending update request for Appointment {$params['id']}: ".json_encode($body, JSON_PRETTY_PRINT));
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$response->setStatus(200);
*/

// check response status
$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception("request to AT rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$context
    ->httpResponse()
    ->body($response->body())
    ->status(200)
    ->send();
