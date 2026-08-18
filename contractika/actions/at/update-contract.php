<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;


list($params, $providers) = eQual::announce([
    'description'   => 'Updates a Contract on AT given its ID, using Datto AutoTask API (update is limited to the Balance UDF).',
    'params'        => [
        'id'   => [
            'description'       => 'Identifier of the AT Contract to update.',
            'type'              => 'integer',
            'required'          => true
        ],
        'balance'   => [
            'description'       => 'New value of the contract Balance.',
            'type'              => 'float',
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

    PATCH https://webservices19.autotask.net/atservicesrest/v1.0/Contracts

    ### body
    {
      "Id":29683949,
      "userDefinedFields": [
            {
                "name": "Balance",
                "value": 23.45
            },
            {
                "name": "Balance_LastUpdated",
                "value": "2023-09-01T00:00:00Z"
            }
        ]
    }

    ## Response
    {
        "itemId": 33329
    }

*/


// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('Missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}


$body = [
        "id"                => $params['id'],
        "userDefinedFields" => [
            [
                "name"  => "Balance",
                "value" => round($params['balance'], 2)
            ],
            [
                "name"  => "Balance_LastUpdated",
                "value" => date('c')
            ]
        ]
    ];

// create a template request holding API credentials
$request = new HttpRequest("PATCH $entrypoint_url".'Contracts');
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
$reporter->debug("Sending update request for Contract {$params['id']}: ".json_encode($body, JSON_PRETTY_PRINT));
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$response->setStatus(200);
*/

// check response status
$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception("Request to AT rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$context
    ->httpResponse()
    ->body($response->body())
    ->status(200)
    ->send();
