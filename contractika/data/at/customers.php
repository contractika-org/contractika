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
    'description'   => 'Fetches the Customers from Datto AutoTask API, with their user-defined fields, and returns it as a JSON array.',
    'params'        => [
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date for filtering customers that have been changed since.',
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

    GET https://webservices19.autotask.net/atservicesrest/v1.0/Companies/query?search={"IncludeFields":["companyName","ID","NAV ID","taxID","Target Margin","Discount","Payment Terms","Service Price"],"filter":[{"op":"and","items":[{"op":"eq","field":"IsActive","value":1},{"op":"eq","field":"CompanyType","value":1},{"op":"exist","field":"NAV ID","udf":true}]}]}


    ## Response (JSON)

    {
        "items": [
            {
                "id": 486,
                "additionalAddressInformation": "",
                "address1": "Rue Souveraine, 19",
                "address2": null,
                "alternatePhone1": "",
                "alternatePhone2": "",
                "apiVendorID": null,
                "assetValue": null,
                "billToCompanyLocationID": null,
                "billToAdditionalAddressInformation": "",
                "billingAddress1": "Rue Souveraine, 19",
                "billingAddress2": "",
                "billToAddressToUse": 1,
                "billToAttention": "",
                "billToCity": "Bruxelles",
                "billToCountryID": 23,
                "billToState": "",
                "billToZipCode": "1050",
                "city": "Bruxelles",
                "classification": 15,
                "companyCategoryID": 1,
                "companyName": "Mentor Escale",
                "companyNumber": "ME",
                "companyType": 1,
                "competitorID": null,
                "countryID": 23,
                "createDate": "2019-11-25T15:47:59.167Z",
                "createdByResourceID": 29682885,
                "currencyID": 1,
                "fax": "",
                "impersonatorCreatorResourceID": null,
                "invoiceEmailMessageID": 1,
                "invoiceMethod": 2,
                "invoiceNonContractItemsToParentCompany": false,
                "invoiceTemplateID": 102,
                "isActive": true,
                "isClientPortalActive": true,
                "isEnabledForComanaged": false,
                "isTaskFireActive": false,
                "isTaxExempt": false,
                "lastActivityDate": "2023-05-04T08:53:10Z",
                "lastTrackedModifiedDateTime": "2023-03-17T10:32:29.52Z",
                "marketSegmentID": null,
                "ownerResourceID": 29682891,
                "parentCompanyID": null,
                "phone": "32 2 505 32 32",
                "postalCode": "1050",
                "purchaseOrderTemplateID": null,
                "quoteEmailMessageID": 2,
                "quoteTemplateID": 1,
                "sicCode": "",
                "state": "",
                "stockMarket": "",
                "stockSymbol": "",
                "surveyCompanyRating": null,
                "taxID": " ",
                "taxRegionID": 1,
                "territoryID": 29682778,
                "webAddress": "www.mentorescale.be/",
                "userDefinedFields": [
                    {
                        "name": "Acronym",
                        "value": null
                    },
                    {
                        "name": "Coefficient_limit",
                        "value": null
                    },
                    {
                        "name": "CRM ID",
                        "value": "EB25C34C-3BA6-E511-80D5-6C3BE5BEEE2C"
                    },
                    {
                        "name": "DayOff_coef",
                        "value": null
                    },
                    {
                        "name": "Discount",
                        "value": "0.0000"
                    },
                    {
                        "name": "Emergency Update Consent",
                        "value": null
                    },
                    {
                        "name": "Emergency Update Installation",
                        "value": null
                    },
                    {
                        "name": "EuroJob ID",
                        "value": "MENTOR"
                    },
                    {
                        "name": "FD_coef",
                        "value": null
                    },
                    {
                        "name": "HD_coef",
                        "value": null
                    },
                    {
                        "name": "HDFD_reduction",
                        "value": null
                    },
                    {
                        "name": "Helpdesk_coef",
                        "value": "1.0000"
                    },
                    {
                        "name": "Language",
                        "value": "FRA"
                    },
                    {
                        "name": "Lead Source",
                        "value": null
                    },
                    {
                        "name": "NAV ID",
                        "value": "C05252"
                    },
                    {
                        "name": "Number of Employees",
                        "value": null
                    },
                    {
                        "name": "Payment Terms",
                        "value": null
                    },
                    {
                        "name": "PriorityCritical_coef",
                        "value": null
                    },
                    {
                        "name": "PriorityHigh_coef",
                        "value": null
                    },
                    {
                        "name": "PriorityLow_coef",
                        "value": null
                    },
                    {
                        "name": "PriorityNormal_coef",
                        "value": null
                    },
                    {
                        "name": "Saturday_coef",
                        "value": null
                    },
                    {
                        "name": "Service Price",
                        "value": "20.6400"
                    },
                    {
                        "name": "SP_Renew_floor",
                        "value": null
                    },
                    {
                        "name": "Sunday_coef",
                        "value": null
                    },
                    {
                        "name": "Target Margin",
                        "value": "0.1500"
                    },
                    {
                        "name": "Travel",
                        "value": "1.0000"
                    },
                    {
                        "name": "TSreport_frequency",
                        "value": null
                    },
                    {
                        "name": "Work Report",
                        "value": "Yes"
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

// #memo - we fetch all companies (even non-active ones, because they might have been changed manually)
$url_query = 'search={"filter":[{"op":"in","field":"companyType","value":[1,4]}]}';
if($params['date_from'] > 0) {
    $url_query = 'search={"filter":[{"op":"and","items":[{"op":"in","field":"companyType","value":[1,4]},{"op":"gte","field":"lastActivityDate","value":"'.date('Y-m-d\TH:i:s\Z', $params['date_from']).'"}]}]}';
}
$request = new HttpRequest("GET $entrypoint_url".'Companies/query?'.$url_query);

$request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json");


// TEST - manually feed response with stored data
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$test_data = file_get_contents('at_customers_response.json');
$response
    ->setHeader('Content-Type', 'application/json')
    ->setStatus(200)
    ->setBody($test_data);
*/

// PROD - request the provider
/** @var HttpResponse */
$response = $request->send();


// check response status
$status = $response->getStatusCode();

if($status != 200) {
    $data = $response->getBody(true);
    trigger_error("PHP::HTTP request rejected  for "."GET $entrypoint_url".'Companies/query?'.$url_query, QN_REPORT_ERROR);
    // upon request rejection, we stop the whole job
    throw new Exception("request to AT rejected with code $status, ".$data, QN_ERROR_INVALID_PARAM);
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
