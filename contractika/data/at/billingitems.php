<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches the Billing Items from Datto AutoTask API and returns the list as a JSON array.',
    'params'        => [
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date since which we request the billing items based on creation time (postedOnTime).',
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

    GET https://webservices19.autotask.net/atservicesrest/v1.0/BillingItems


    ## Response (JSON)

    {
        "items": [
            {
                "id": 26039,
                "accountManagerWhenApprovedID": 29682885,
                "billingCodeID": 29683502,
                "billingItemType": 1,
                "companyID": 239,
                "configurationItemID": null,
                "contractBlockID": 75,
                "contractChargeID": null,
                "contractID": 29683363,
                "contractServiceAdjustmentID": null,
                "contractServiceBundleAdjustmentID": null,
                "contractServiceBundleID": null,
                "contractServiceBundlePeriodID": null,
                "contractServiceID": null,
                "contractServicePeriodID": null,
                "description": "Remote coaching",
                "expenseItemID": null,
                "extendedPrice": 386.6450,
                "internalCurrencyExtendedPrice": 386.6450,
                "internalCurrencyRate": 100.0000,
                "internalCurrencyTaxDollars": 0.0000,
                "internalCurrencyTotalAmount": 0.0000,
                "invoiceID": 0,
                "itemApproverID": 29682897,
                "itemDate": "2021-12-01T00:00:00Z",     // correspond au dateWorked de la timeEntry
                "itemName": "ICT Coaching",
                "lineItemFullDescription": null,
                "lineItemGroupDescription": null,
                "milestoneID": null,
                "nonBillable": 0,
                "organizationalLevelAssociationID": null,
                "ourCost": 205.9980,
                "postedDate": "2022-12-02T00:00:00Z",
                "postedOnTime": "2022-12-02T08:52:54.023Z",
                "projectChargeID": null,
                "projectID": null,
                "purchaseOrderNumber": "",
                "quantity": 3.1750,
                "rate": 100.0000,
                "roleID": 29683468,
                "serviceBundleID": null,
                "serviceID": null,
                "sortOrderID": 0,
                "subType": 1,
                "taskID": null,
                "taxDollars": 0.0000,
                "ticketChargeID": null,
                "ticketID": 29799,
                "timeEntryID": 28673,
                "totalAmount": 0.0000,
                "vendorID": null,
                "webServiceDate": null
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

// retrieve last_run from settings (defaults to 'all times'), i.e. the last time the absences were imported from SDworx
$date_from = date('Y-m-d', $params['date_from']);

// #memo - we filter on postedDate/postedOnTime but we igore billingItems targeting timeEntries created before 2023-01-01 (through itemDate field)
$url = $entrypoint_url.'BillingItems/query?search={"filter":[{"op":"and","items":[{"op":"gte","field":"itemDate","value":"2023-01-01"},{"op":"gte","field":"postedOnTime","value":"'.$date_from.'"}]}],"IncludeFields":["id","timeEntryID","postedOnTime"]}';

// loop while there is a next page URL or we reach 10.000 items
$max_loop = 20;
$i = 0;
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
        throw new Exception("request to AT (BillingItems) rejected with code $status", QN_ERROR_INVALID_PARAM);
    }

    // we should have received an application/json response, if so HttpMessage::body() contains a decoded version of the JSON data
    $data = $response->body();

    if(!is_array($data) || !isset($data['items'])) {
        throw new Exception("response is empty", QN_ERROR_UNKNOWN);
    }

    $result = array_merge($result, $data['items']);

    if(isset($data['pageDetails']['nextPageUrl']) && !is_null($data['pageDetails']['nextPageUrl'])) {
        // there are more items: keep fetch by using the next page URL
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
