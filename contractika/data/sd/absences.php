<?php
use equal\http\HttpRequest;
use equal\http\HttpResponse;
use hr\absence\AbsenceCode;
use contractika\hr\employee\Employee;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Requests the updated list of absences for a given employee from SDworx XML API and returns it as a JSON array.',
    'params'        => [
        'id'   => [
            'description'       => 'Identifier of the Employee for which we want to update the absences.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\hr\employee\Employee',
            'required'          => true
        ],
        'last_run'   => [
            'description'       => 'Date for filtering records on which a change occurred since.',
            'type'              => 'date'
        ],
        'passed_months'   => [
            'description'       => 'Number of months in the past to cover.',
            'type'              => 'integer',
            'default'           => 1
        ],
        'future_months'   => [
            'description'       => 'Number of months in the future to cover.',
            'type'              => 'integer',
            'default'           => 6
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'examples' => [
        'CLI'           => './equal.run --get=contractika_sd_absences --id=16'
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

    POST https://www.sd.be/pos/acc/V20/server/AbsencesWebService.asmx
    <?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Body>
                <GetAbsenceReportExtendedWithSimpleParameters xmlns="http://sdworx.com/hrsskmoabsencewebservicev2">
                    <languageId>2</languageId>
                    <fromDate_ddMMyyyy>2022-01-01</fromDate_ddMMyyyy>
                    <toDate_ddMMyyyy>2022-12-31</toDate_ddMMyyyy>
                    <requestType>2</requestType>
                    <withOvertime>1</withOvertime>
                    <employerNumber>8658100</employerNumber>
                    <employeeNumber>0000018</employeeNumber>
                    <changedSinceDate>2022-07-21T10:49:10.000</changedSinceDate>
                </GetAbsenceReportExtendedWithSimpleParameters>
            </soap:Body>
        </soap:Envelope>

    ## Response

    <?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope
        xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:xsd="http://www.w3.org/2001/XMLSchema">
        <soap:Body>
            <GetAbsenceReportExtendedWithSimpleParametersResponse
                xmlns="http://sdworx.com/hrsskmoabsencewebservicev2">
                <GetAbsenceReportExtendedWithSimpleParametersResult>
                    <WebserviceResultLine>
                        <EmployerNumber>8658100</EmployerNumber>
                        <EmployeeNumber>0000018</EmployeeNumber>
                        <FirstName>Anonymous</FirstName>
                        <LastName>Example</LastName>
                        <Date>2022-01-01T00:00:00</Date>
                        <DayPart>FullDay</DayPart>
                        <OriginalAmount>0.00</OriginalAmount>
                        <OriginalMeasureUnit>---</OriginalMeasureUnit>
                        <InterpretedAmount>0.00</InterpretedAmount>
                        <InterpretedMeasureUnit>Hours</InterpretedMeasureUnit>
                        <AbsenceCodeId>---</AbsenceCodeId>
                        <AbsenceCodeLabel>---</AbsenceCodeLabel>
                        <Layer>Planning</Layer>
                        <Status>Planned</Status>
                        <ShiftCode />
                        <LastModificationDate>2022-09-05T22:32:31.98</LastModificationDate>
                        <Id>44-63776592000000000001</Id>
                    </WebserviceResultLine>
                    <WebserviceResultLine>
                        [...]
                    </WebserviceResultLine>
                    [...]
                </GetAbsenceReportExtendedWithSimpleParametersResult>
            </GetAbsenceReportExtendedWithSimpleParametersResponse>
        </soap:Body>
    </soap:Envelope>
*/


/*
    Request absences for given employee from SDWorx
*/

$result = [];

// retrieve SDworx API entry point (mandatory setting)
$entrypoint_url = Setting::get_value('contractika', 'sync', 'sd_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('Missing mandatory setting: SD API entrypoint.', QN_ERROR_INVALID_CONFIG);
}

// create a template request holding API credentials
$request = new HttpRequest('POST '.$entrypoint_url.'AbsencesWebService.asmx');
$request
    ->header('Content-Type', 'text/xml')
    ->header('Authorization', 'Basic '.base64_encode(constant('PROVIDERS_SDWORX_API_USERNAME').':'.constant('PROVIDERS_SDWORX_API_PASSWORD')));


// build absence codes map (prefetch all absence codes)
$absence_code_map = [];
$absenceCodes = AbsenceCode::search()->read(['id', 'code']);
if(!count($absenceCodes)) {
    throw new Exception('Missing mandatory setting: absence codes listing.', QN_ERROR_INVALID_CONFIG);
}
foreach($absenceCodes as $oid => $code) {
    $absence_code_map[$code['code']] = $code['id'];
}

// scope the search on the range -1 month (to capture data if the script has not been run in the meantime) and +11 months
$date_from = strtotime("-{$params['passed_months']} months");
$date_to   = strtotime("+{$params['future_months']} months");
$last_run  = $date_from;

// retrieve last_run from settings (defaults to 'all times'), i.e. the last time the absences were imported from SDworx
if(isset($params['last_run'])) {
    $last_run = $params['last_run'];
}
else {
    // #memo - we cannot rely on sd_sync.last_run since this applies to all employees and does not allow to know if an error occurred for a specific employee (which may result in missing some absences)
    // $last_run = Setting::get_value('contractika', 'sync', 'sd_sync.last_run', 0);
}

// fetch Employee object
$employee = Employee::id($params['id'])->read(['id', 'name', 'extref_sd_id', 'extref_at_id', 'date_start', 'date_end'])->first();
if(!isset($employee['extref_sd_id']) || strlen($employee['extref_sd_id']) <= 0) {
    // #memo - silently ignore employees with missing SDworx id (this is a data provider)
    // throw new Exception("Missing SD id for employee {$employee['name']} [CT-{$employee['id']}, SD-{$employee['extref_sd_id']}, AT-{$employee['extref_at_id']}].", QN_ERROR_INVALID_CONFIG);
}
$employee_sd_id = $employee['extref_sd_id'];

if($date_from < $employee['date_start']) {
    $date_from = $employee['date_start'];
}

if($employee['date_end'] && $date_to > $employee['date_end']) {
    $date_to = $employee['date_end'];
}

$reporter->debug("Requesting data for employee {$employee_sd_id}: {$date_from} - {$date_to}, $last_run.");

// generate payload for requesting all absences lines for a given employee, that have been updated since last run
$xml = Employee::generateXmlPayload($employee_sd_id, $date_from, $date_to, $last_run);

$reporter->debug("Requesting SDWorx with payload: {$xml}");

// TEST - manually feed response with stored data (identical for all employees)
/** @var HttpResponse */
/*
$response = new HttpResponse('');
$test_data = file_get_contents('sd_absences_response.xml');
$response
    ->setHeader('Content-Type', 'text/xml')
    ->setStatus(200)
    ->setBody($test_data);
*/

// PROD - request the provider
/** @var HttpResponse */
$response = $request->setBody($xml, true)->send();

$status = $response->getStatusCode();

if($status != 200) {
    $reporter->error("Received response with error status: " . $response->body());
    // upon request rejection, we stop the whole job
    throw new Exception('request_rejected_status_' . $status, QN_ERROR_INVALID_PARAM);
}

// we should have received a text/xml response, if so HttpMessage::body() contains a parsed version of the XML data
// #memo - raw body can be retrieved by using $response->getBody(true);
$envelope = $response->body();

// check response consistency
if(!isset($envelope['name']) || !in_array($envelope['name'], ['Envelope', 'soap:Envelope'])) {
    throw new Exception('Invalid response received (valid XML but unexpected format).', QN_ERROR_UNKNOWN);
}

/*
    build result
*/

$result = [];
// if path is found, extract absences from received response (current employee)
if(isset($envelope['children']['soap:Body']['children']['GetAbsenceReportExtendedWithSimpleParametersResponse']['children']['GetAbsenceReportExtendedWithSimpleParametersResult']['children'])) {
    $lines = $envelope['children']['soap:Body']['children']['GetAbsenceReportExtendedWithSimpleParametersResponse']['children']['GetAbsenceReportExtendedWithSimpleParametersResult']['children'];

    foreach($lines as $line) {
        if($line['name'] != 'WebserviceResultLine') {
            $reporter->debug("Ignoring unexpected node {$line['name']}.");
            continue;
        }
        if(!isset($line['children']) || !is_array($line['children'])) {
            $reporter->debug("Skipping empty line.");
            continue;
        }
        $values = array_column(array_map(function($k, $v) {
                return [$k, $v['value']];
            },
            array_keys($line['children']), $line['children']), 1, 0);

        // check absence consistency
        if(!isset($values['AbsenceCodeId']) || strlen($values['AbsenceCodeId']) <= 0) {
            throw new Exception("Missing absence code for SD absence {$values['Id']}.", QN_ERROR_UNKNOWN);
        }

        $absence_code = (string) $values['AbsenceCodeId'];

        /* check absence code */

        // discard non-absence records
        if(in_array($absence_code, [
                '---',              // 'working day'
                '7010',             // 'working day'
                '7410',             // partial return to work in case of illness
                '9870',             // part. incap. due to sickness (not assimilated)
                'T350'              // holiday (handled using Holiday entities)
            ])) {
            $reporter->info("Ignoring non-absence code {$absence_code}.");
            continue;
        }
        // check absence code validity
        if(!isset($absence_code_map[$absence_code])) {
            throw new Exception("Unknown absence code for code {$absence_code} (sync ?).", QN_ERROR_UNKNOWN);
        }
        // adapt and append absence
        $result[] = $values;
    }
}

$context
    ->httpResponse()
    ->body($result)
    ->status(200)
    ->send();
