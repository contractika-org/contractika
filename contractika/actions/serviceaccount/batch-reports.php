<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\sale\customer\Customer;
use contractika\ServiceAccount;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Generate draft reports for all contracts for the current period.",
    'params'        => [],
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

$result = [
    'warnings'    => 0,
    'processed'   => 0,
    'logs'        => []
];

// 1) if global setting f_reporting is set to 'eurojob' : quit
$f_reporting = Setting::get_value('contractika', 'ts_reporting', 'f_reporting', 'eurojob');
if($f_reporting == 'eurojob') {
    $result['logs'][] = "Reporting globally set to Eurojob: operation skipped.";
}
else {
    // retrieve all active contracts from customers with reporting set to contractika (not eurojob)
    // #memo - non invoiceable SA can generate reports, but those must not be sent
    // #memo - values observed from AutoTask UDF field TSReport are 'none', 'eurojob', 'monthly'
    $customers_ids = Customer::search([['is_active', '=', true], ['f_reporting', 'in', ['weekly', 'monthly']]])->ids();
    $service_accounts_ids = ServiceAccount::search([['is_active', '=', true], ['m_reporting', '<>', 'None'], ['customer_id', 'in', $customers_ids]])->ids();

    // request the generation of a new draft report (will be ignored if global or customer setting 'f_reporting' is set to 'eurojob')
    foreach($service_accounts_ids as $id) {
        try {
            $args = ['id' => $id];
            eQual::run('do', 'contractika_serviceaccount_do-report', $args);
            ++$result['processed'];
        }
        catch(Exception $e) {
            trigger_error("APP::error while generating report for Service Account {$id}: ".$e->getMessage(), QN_REPORT_WARNING);
            ++$result['warnings'];
            $result['logs'][] = "WARN- Error while generating report for Service Account {$id}: ".$e->getMessage();
        }
    }
}

$context->httpResponse()
        ->status(200)
        ->body(['result' => $result])
        ->send();
