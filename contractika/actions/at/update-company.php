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


[$params, $providers] = eQual::announce([
    'description'   => 'Updates an Appointment on AT given its ID, using Datto AutoTask API.',
    'params'        => [
        'id'   => [
            'description'       => 'Identifier of the AT Company to update.',
            'type'              => 'integer',
            'required'          => true
        ],
        'is_active'   => [
            'description'       => 'Flag for marking the company as being in use.',
            'type'              => 'boolean',
            'required'          => true
        ],
        'vat_id'   => [
            'description'       => 'VAT identification number.',
            'type'              => 'string',
            'required'          => true
        ],
        'discount'   => [
            'description'       => 'Discount to apply on early payment.',
            'type'              => 'float'
        ],
        'payment_terms'   => [
            'description'       => 'Description of the payment terms.',
            'type'              => 'string'
        ],
        'service_price'   => [
            'description'       => 'Sale price of the last point.',
            'type'              => 'float'
        ],
        // #memo - this field had been set in Navision before AutoTask was being used, it is no longer needed (input is made directly under AT)
        /*
        'target_margin'   => [
            'description'       => 'Target margin.',
            'type'              => 'float'
        ]
        */
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


$body = [
        "id"                => $params['id'],
        "taxID"             => $params['vat_id'],
        "isActive"          => $params['is_active'],
        "userDefinedFields" => [
            [
                "name"  => "Discount",
                "value" => $params['discount']
            ],
            [
                "name"  => "Payment Terms",
                "value" => $params['payment_terms']
            ],
            [
                "name"  => "Service Price",
                "value" => $params['service_price']
            ],
            /*
            // #memo - this field had been set in Navision before AutoTask was being used, it is no longer needed (input is made directly under AT)
            [
                "name"  => "Target Margin",
                "value" => $params['target_margin']
            ]
            */
        ]
    ];

// create a template request holding API credentials
$request = new HttpRequest("PATCH $entrypoint_url".'Companies');
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
$reporter->debug("sending update request for Company {$params['id']}: ".json_encode($body, JSON_PRETTY_PRINT));
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
