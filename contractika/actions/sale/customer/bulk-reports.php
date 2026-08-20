<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\ServiceAccount;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Attempt to generate draft reports for a selection of Customers.",
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifiers of the targeted Customers.',
            'type'              => 'array',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

// global setting f_reporting is set to 'eurojob' : abort
$f_reporting = Setting::get_value('contractika', 'ts_reporting', 'f_reporting', 'eurojob');
if($f_reporting == 'eurojob') {
    throw new Exception('operation_skipped', QN_ERROR_INVALID_CONFIG);
}
else {
    $service_accounts_ids = ServiceAccount::search([['is_active', '=', true], ['customer_id', 'in', $params['ids']]])->ids();

    // request the generation of a new draft report for all given Service Account ids
    foreach($service_accounts_ids as $id) {
        // will raise an exception if action cannot be performed
        eQual::run('do', 'contractika_serviceaccount_do-report', ['id' => $id]);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
