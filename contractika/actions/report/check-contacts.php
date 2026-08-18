<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

list($params, $providers) = eQual::announce([
    'description'   => "Check if there is at least one contact associated to the Customer of a given report.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Report for which the check is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\Report',
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
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context              $context
 * @var \equal\dispatch\Dispatcher      $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$result = [];

$report = Report::id($params['id'])
    ->read(['id', 'customer_id' => ['contacts_ids']])
    ->first(true);

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

$customer = $report['customer_id'];

if(!$customer) {
    throw new Exception("unknown_customer_for_report_contacts", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$customer['contacts_ids'] || !count($customer['contacts_ids'])) {
    $result[] = $params['id'];
    $dispatch->dispatch('contractika.report.missing_contact', 'contractika\Report', $params['id'], 'important', 'contractika_report_check-contacts', $params);
}
else {
    $dispatch->cancel('contractika.report.missing_contact', 'contractika\Report', $params['id']);
}

$context->httpResponse()
        ->body($result)
        ->send();
