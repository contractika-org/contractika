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
    'description'   => 'Requests the list of employees from SDworx XML API and returns it as a JSON array.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'constants'     => [ 'PROVIDERS_SDWORX_API_USERNAME', 'PROVIDERS_SDWORX_API_PASSWORD'],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];


/*
    # SDworx Messages description

    ## Request
    One request must be sent for each employee.

    POST https://www.sd.be/pos/acc/V20/server/AbsencesWebService.asmx/GetEmployees


    ## Response

    <?xml version="1.0" encoding="utf-8"?>
    <ArrayOfEmployee xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://sdworx.com/hrsskmoabsencewebservicev2">
        <Employee>
            <EmployerNumber>4056772</EmployerNumber>
            <EmployeeNumber>0000001</EmployeeNumber>
            <FirstName>Thi Thanh D.</FirstName>
            <LastName>TRAN</LastName>
            <CurrentWorkscheme>| 7,6 | 7,6 | 3,8 : - | 7,6 | 7,6 | 0 | 0 |</CurrentWorkscheme>
            <StartDate>2019-05-01T00:00:00</StartDate>
            <EndDate xsi:nil="true" />
        </Employee>
        <Employee>
            [...]
        </Employee>
        [...]
    </ArrayOfEmployee>
*/


/*
    Request employees list from SDWorx
*/

// retrieve SDworx API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'sd_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('Missing mandatory setting: SD API entrypoint.', QN_ERROR_INVALID_CONFIG);
}

// build a request to endpoint holding API credentials
$uri = "{$entrypoint_url}AbsencesWebService.asmx/GetEmployees";
$authorization = 'Basic '.base64_encode(constant('PROVIDERS_SDWORX_API_USERNAME').':'.constant('PROVIDERS_SDWORX_API_PASSWORD'));
$body = 'Language=Fr';

$request = new HttpRequest("POST $uri");
$request
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->header('Authorization', $authorization)
    ->body($body, true);


// TEST - manually feed response with stored data
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$test_data = file_get_contents('sd_employees_response.xml');
$response
    ->setHeader('Content-Type', 'text/xml; charset=utf-8')
    ->setStatus(200)
    ->setBody($test_data);
*/

// PROD - request the provider
/** @var HttpResponse */
$response = $request->send();


// check response status
$status = $response->getStatusCode();

if ($status !== 200) {

    $dump = function ($value) {
        ob_start();
        var_dump($value);
        return ob_get_clean();
    };

    $debug = [
        'uri'        => $uri,
        'status'     => $status,
        'request'    => [
            'headers' => $request->getHeaders(),
            'body'    => $body,
        ],
        'response'   => [
            'headers' => $response->getHeaders(),
            'body'    => $response->body(),
        ],
    ];

    $message = "Request to SD rejected\n"
        . "Status: {$debug['status']}\n"
        . "URI: {$debug['uri']}\n"
        . "Authorization: {$authorization}\n"
        . "\n--- Request ---\n"
        . $dump($debug['request'])
        . "\n--- Response ---\n"
        . $dump($debug['response']);

    throw new Exception($message, QN_ERROR_INVALID_PARAM);
}

// we should have received a text/xml response, if so HttpMessage::body() contains a parsed version of the XML data
// #memo - raw body can be retrieved by using $response->getBody(true);
$envelope = $response->body();

// check response consistency
if(!isset($envelope['name']) || $envelope['name'] != 'ArrayOfEmployee') {
    throw new Exception("Invalid response received (valid XML but unexpected format).", QN_ERROR_UNKNOWN);
}

/*
    build result
*/
$result = [];

// if path is found, extract employees from received response
if(!isset($envelope['children'])) {
    // no absence data for employee, skip
    $reporter->warning("Empty data received (no employee).");
}
else {
    $lines = $envelope['children'];

    foreach($lines as $line) {
        if($line['name'] != 'Employee') {
            $reporter->debug("Ignoring unexpected node {$line['name']}.");
            continue;
        }
        if(!isset($line['children']) || !is_array($line['children'])) {
            $reporter->debug("Skipping empty line.");
            continue;
        }
        // flatten the Employee node to an associative array
        $values = array_column(array_map(function($k, $v) {
            return [$k, $v['value']];
        }, array_keys($line['children']), $line['children']), 1, 0);

        /*
            Example:
                "EmployerNumber": "8658100",
                "EmployeeNumber": "0000007",
                "FirstName": "GEOFFROY",
                "LastName": "DE FAYS",
                "CurrentWorkscheme": "| 7,6 | 7,6 | 7,6 | 7,6 | 7,6 | 0 | 0 |",
                "StartDate": "2005-01-01T00:00:00",
                "EndDate": null
        */
        $result[] = $values;
    }
}


$context
    ->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
