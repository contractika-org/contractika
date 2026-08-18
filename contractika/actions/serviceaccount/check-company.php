<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\sale\customer\Customer;
use contractika\ServiceAccount;

list($params, $providers) = eQual::announce([
    'description'   => "Check if there is a match for the external reference of a customer on a given Service Account.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Service Account for which the check is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\ServiceAccount',
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

$service_account = ServiceAccount::id($params['id'])
    ->read(['id', 'companyID'])
    ->first(true);

if(!$service_account) {
    throw new Exception("unknown_service_account", QN_ERROR_UNKNOWN_OBJECT);
}

$customer = Customer::search(['extref_at_id','=', $service_account['companyID']])->read(['id'])->first();

if(!$customer) {
    $result[] = $params['id'];
    $dispatch->dispatch('contractika.service_account.unknown_company', 'contractika\ServiceAccount', $params['id'], 'important', 'contractika_serviceaccount_check-company', $params);
}
else {
    $dispatch->cancel('contractika.service_account.unknown_company', 'contractika\ServiceAccount', $params['id']);
}

$context->httpResponse()
        ->body($result)
        ->send();
