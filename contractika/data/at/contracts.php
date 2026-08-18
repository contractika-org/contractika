<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => 'Fetches the Contracts from Datto AutoTask API and returns the list as a JSON array.',
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
    The list of employees is maintained using SDworx API
*/

/*
    # AT Messages description

    ## Request

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Contracts


    ## Response (JSON)

    {
        "items": [
            {
                "id": 29683353,
                "billingPreference": 3,
                "billToCompanyContactID": null,
                "billToCompanyID": null,
                "companyID": 600,
                "contactID": 30684655,
                "contactName": "Blanpain, Myriam",
                "contractCategory": 13,
                "contractExclusionSetID": null,
                "contractName": "Régie",
                "contractNumber": null,
                "contractPeriodType": null,
                "contractType": 1,
                "description": null,
                "endDate": "2020-12-31T00:00:00Z",
                "estimatedCost": 0.0000,
                "estimatedHours": 0.0000,
                "estimatedRevenue": 0.00,
                "exclusionContractID": 29683853,
                "internalCurrencyOverageBillingRate": null,
                "internalCurrencySetupFee": 0.0000,
                "isCompliant": true,
                "isDefaultContract": false,
                "opportunityID": null,
                "organizationalLevelAssociationID": null,
                "overageBillingRate": null,
                "purchaseOrderNumber": "",
                "renewedContractID": null,
                "serviceLevelAgreementID": null,
                "setupFee": 0.0000,
                "setupFeeBillingCodeID": null,
                "startDate": "2019-01-01T00:00:00Z",
                "status": 1,
                "timeReportingRequiresStartAndStopTimes": 0,
                "userDefinedFields": [
                    {
                        "name": Balance:float
                        "value": null
                    },
                    {
                        "name": Balance_LastUpdated,
                        "value": null
                    },
                    {
                        "name": "Bonus",
                        "value": "Yes"
                    },
                    {
                        "name": "EuroJob_Contract_ID",
                        "value": "ADDR/NTK_R/02"
                    },
                    {
                        "name": "EuroJob_Job_ID",
                        "value": "ADDR##00001"
                    },
                    {
                        "name": "CutOff",
                        "value": "Yes"
                    },
                    {
                        "name": "NoWorkReport",
                        "value": null
                    },
                    {
                        "name": "SP_Renew_amount",
                        "value": null
                    },
                    {
                        "name": "SP_Renew_auto",
                        "value": null
                    },
                    {
                        "name": "SP_Renew_floor",
                        "value": null
                    },
                    {
                        "name": "TSreport",
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

// #memo - filter parameter is mandatory so we use a condition that is always true
$url = $entrypoint_url.'Contracts/query?search={"filter":[{"op":"exist","field":"id"}]}';

// loop while there is a next page URL or we reach 2500 items
$max_loop = 5;
$i = 0;
while($i < $max_loop) {
    // create a template request holding API credentials
    // #memo - there is a max of 500 items per result page
    $request = new HttpRequest("GET $url");
    $request
        ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
        ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
        ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
        ->header('Content-Type', "application/json");


    // TEST - manually feed response with stored data
    /** @var HttpResponse */
    /*
    $response = new HttpResponse('');
    $test_data = file_get_contents('at_roles_response.json');
    $response
        ->setHeader('Content-Type', 'application/json')
        ->setStatus(200)
        ->setBody($test_data);
    */

    // PROD - request the provider
    /** @var HttpResponse */
    $response = $request->send();


    // check response status
    $code = $response->getStatusCode();
    $status = $response->getStatus();

    if($code != 200) {
        // upon request rejection, we stop the whole job
        throw new Exception("request to AT (contracts) rejected with code $code ($status)", QN_ERROR_INVALID_PARAM);
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
