<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\identity\Identity;
use contractika\sale\customer\Customer;

list($params, $providers) = eQual::announce([
    'description'   => "Check if the VAT number associated to a given Customer is unique.",
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
list($context, $dispatch) = [ $providers['context'], $providers['dispatch'] ];

$result = [];

$customer = Customer::id($params['id'])
    ->read([
        'id',
        'is_active',
        'partner_identity_id'    => ['id', 'vat_number'],
        'parent_customer_id'     => ['is_active', 'partner_identity_id'],
        'children_customers_ids' => ['is_active', 'partner_identity_id']
    ])
    ->first(true);

if(!$customer) {
    throw new Exception("unknown_customer_for_customer_vat_number", QN_ERROR_UNKNOWN_OBJECT);
}

if( $customer['is_active']
        && isset($customer['partner_identity_id']['vat_number'])
        && strlen($customer['partner_identity_id']['vat_number']) ) {

    $children_identities_ids = [];
    foreach($customer['children_customers_ids'] ?? [] as $child_customer) {
        if($child_customer['is_active']) {
            $children_identities_ids[] = $child_customer['partner_identity_id'];
        }
    }

    $identities = Identity::search([
            ['id', '<>', $customer['partner_identity_id']['id']],
            ['vat_number', '<>', null],
            ['vat_number', '=', $customer['partner_identity_id']['vat_number']]
        ])
        ->read(['id'])
        ->get(true);

    foreach($identities as $index => $identity) {
        // discard parent identity of customer
        if($identity['id'] == $customer['parent_customer_id']['partner_identity_id'] ?? null) {
            unset($identities[$index]);
        }
        // discard children identities of customer
        if(in_array($identity['id'], $children_identities_ids)) {
            unset($identities[$index]);
        }
        // discard sibling of customer (having same parent)
        $identityCustomer = Customer::search([
                ['partner_identity_id', '=', $identity['id']],
                ['is_active', '=', true]
            ])
            ->read(['parent_customer_id' => ['partner_identity_id']])
            ->first();

        if(!$identityCustomer) {
            unset($identities[$index]);
        }
        elseif(isset($identityCustomer['parent_customer_id']['partner_identity_id'])) {
            if($identityCustomer['parent_customer_id']['partner_identity_id'] == $customer['parent_customer_id']['partner_identity_id'] ?? null) {
                unset($identities[$index]);
            }
        }
    }

    if(count($identities)) {
        $result[] = $params['id'];
        $dispatch->dispatch('contractika.customer.duplicate_vat', 'contractika\sale\customer\Customer', $params['id'], 'important', 'contractika_customer_check-vat', $params);
    }
    else {
        $dispatch->cancel('contractika.customer.duplicate_vat', 'contractika\sale\customer\Customer', $params['id']);
    }
}


$context->httpResponse()
        ->body($result)
        ->send();
