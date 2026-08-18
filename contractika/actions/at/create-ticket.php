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
    'description'   => 'Creates a Ticket on AT using Datto AutoTask API.',
    'help'          => 'This script is limited to creating ServiceAccount balance renewal tickets.',
    'params'        => [
        'title'   => [
            'description'       => 'Title of the ticket to create.',
            'type'              => 'string',
            'required'          => true
        ],
        'description'   => [
            'description'       => 'Short description for the ticket.',
            'type'              => 'string',
            'required'          => true
        ],
        'company_id'   => [
            'description'       => 'Identifier of the AT Company the Ticket relates to.',
            'type'              => 'integer',
            'required'          => true
        ],
        'contract_id'   => [
            'description'       => 'Identifier of the AT Contract the Ticket relates to.',
            'type'              => 'integer',
            'required'          => true
        ],
        'external_id'   => [
            'description'       => 'Arbitrary identifier (Contractika) to be stored with the AT Ticket.',
            'type'              => 'integer',
            'required'          => true
        ],
        'due_datetime'   => [
            'description'       => 'Limit before which Ticket should be handled.',
            'type'              => 'datetime',
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

    POST https://webservices19.autotask.net/atservicesrest/v1.0/Tickets/

    ### body

    {
        "Title": "Test 20201116.01",
        "Status": 1,
        "Priority": 2,
        "CompanyID": 712,
        "DueDateTime": "2020-11-17T12:13:14.00",
        "AssignedResourceID": 29682885,
        "AssignedResourceRoleID": 29682834,
        "QueueID": 6,
        "ContactID": 30684854,
        "Description": "This is the test 20201116.01 description",
        "BillingCodeID": 29682860,
        "PurchaseOrderNumber": "123456789",
        "TicketCategory": 109,
        "OpportunityID": 323,
        "TicketType": 1
    }

    ## Response
    {
    }

*/

$result = [];

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing mandatory setting: AT API entrypoint', QN_ERROR_INVALID_CONFIG);
}

$body = [
        "title"                     => $params['title'],
        "description"               => $params['description'],
        "companyID"                 => $params['company_id'],
        "contractID"                => $params['contract_id'],
        "externalID"                => $params['external_id'],
        "dueDateTime"               => date('c', $params['due_datetime']),
        // #memo - values below are constant (provided by Netika)
        "billingCodeID"             => 29682860,
        "issueType"                 => 39,
        "priority"                  => 2,
        "queueID"                   => 29683493,
        "serviceLevelAgreementID"   => 1,
        "source"                    => 22,
        "status"                    => 1,
        "ticketCategory"            => 109,
        "ticketType"                => 1
    ];

// create a template request holding API credentials
$request = new HttpRequest("POST $entrypoint_url".'Tickets');
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
/** @var HttpResponse */
/*
$reporter->debug("sending create request: ".json_encode($body, JSON_PRETTY_PRINT));
$response = new HttpResponse('');
$response->setStatus(200);
$response->setBody([ 'itemId' => 29999999 ]);
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
