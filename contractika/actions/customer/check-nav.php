<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\sale\customer\Customer;

list($params, $providers) = eQual::announce([
    'description'   => "Check if a NAV ID is defined for a given Customer.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Customer for which the check is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\sale\customer\Customer',
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

$customer = Customer::id($params['id'])
    ->read(['id', 'is_active', 'extref_nav_id'])
    ->first();

if(!$customer) {
    throw new Exception("unknown_customer_for_customer_nav_id", QN_ERROR_UNKNOWN_OBJECT);
}

if($customer['is_active'] && (!$customer['extref_nav_id'] || strlen($customer['extref_nav_id']) <= 0)) {
    $result[] = $params['id'];
    $dispatch->dispatch('contractika.customer.missing_nav_id', 'contractika\sale\customer\Customer', $params['id'], 'important', 'contractika_customer_check-nav', $params);
}
else {
    $dispatch->cancel('contractika.customer.missing_nav_id', 'contractika\sale\customer\Customer', $params['id']);
}

$context->httpResponse()
        ->body($result)
        ->send();
