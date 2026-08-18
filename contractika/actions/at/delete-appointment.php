<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;


list($params, $providers) = announce([
    'description'   => 'Deletes an Appointment on AT given its ID, using Datto AutoTask API.',
    'params'        => [
        'id'   => [
            'description'       => 'Identifier of the AT Appointment to delete.',
            'type'              => 'integer',
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

    DELETE https://webservices19.autotask.net/atservicesrest/v1.0/Appointments/:id

    ## Response
    {
        "itemId": 4
    }

*/

$result = [];

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}

// create a template request holding API credentials
$request = new HttpRequest("DELETE $entrypoint_url".'Appointments/'.$params['id']);
$request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json");

// PROD - request the provider
/** @var HttpResponse */
$response = $request->send();

// TEST
$reporter->debug("sending delete request for Appointment {$params['id']}");
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
    ->status(204)
    ->send();
