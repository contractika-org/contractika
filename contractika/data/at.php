<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use core\setting\Setting;

/**
 * HTTP native support
 *
 */
[$params, $providers] = eQual::announce([
    'description'   => '',
    'params'        => [
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8'
    ],
    'constants'     => [ 'PROVIDERS_AT_API_APPKEY', 'PROVIDERS_AT_API_USERNAME', 'PROVIDERS_AT_API_PASSWORD'],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];



$appointment_id = 22209;

// retrieve Autotask API entry point
$entrypoint_url = Setting::get_value('contractika', 'sync', 'at_sync.api_entrypoint_url');
if(is_null($entrypoint_url)) {
    throw new Exception('missing_mandatory_setting', QN_ERROR_INVALID_CONFIG);
}

$request = new HttpRequest("GET {$entrypoint_url}Appointments/{$appointment_id}");

$response = $request
    ->header('ApiIntegrationcode', constant('PROVIDERS_AT_API_APPKEY'))
    ->header('username', constant('PROVIDERS_AT_API_USERNAME'))
    ->header('Secret', constant('PROVIDERS_AT_API_PASSWORD'))
    ->header('Content-Type', "application/json")
    ->send();

$status = $response->getStatusCode();

if($status != 200) {
    throw new Exception("request rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->getBody();

if(!is_array($data) || !isset($data['item'])) {
    throw new Exception("response is empty", QN_ERROR_UNKNOWN);
}

$appointment = $data['item'];

$context
    ->httpResponse()
    ->body($appointment)
    ->send();