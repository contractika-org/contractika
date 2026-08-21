<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\identity\Identity;
use contractika\sale\customer\Customer;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Enriches the Customer objects with data from NAV/BC.',
    'params'        => [
        'date_from'   => [
            'description'       => 'First date of the range.',
            'type'              => 'date'
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
['context' => $context, 'report' => $reporter] = $providers;


$result = [
    'errors'  => 0,
    'warnings'=> 0,
    'ignored' => 0,
    'updated' => 0,
    'failed'  => 0,
    'unknown' => 0,
    'logs'    => []
];

// retrieve last_run from settings (defaults to 'all times')
$last_run = Setting::get_value('contractika', 'sync', 'at_sync_customers.last_run', strtotime("-4 weeks"));

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $last_run = $params['date_from'];
}

// fetch the latest listing of resources from AutoTask (using API)
// #memo - to prevent updating all AT customers, we provide the date since last sync
/*

    {
        "Id": "C05693",
        "Name": "Heinrich-Boll-Stiftung",
        "Blocked": 0,
        "Vat": "BE 0850.545.785",
        "Discount": 2,
        "TargetMargin": 0.15,
        "ServicePrice": 32.6,
        "PaymentTermsCode": "1M-5J-2%-E",
        "PaymentTermsDescription": "1 month, Payment discount 2% max 5 days"
    },
    {
        "Id": "C05466",
        "Name": "Accountancy Europe",
        "Blocked": 0,
        "Vat": "BE 0430.069.789",
        "Discount": 2,
        "TargetMargin": 0.15,
        "ServicePrice": 23.78,
        "PaymentTermsCode": "1M-5J-2%-E",
        "PaymentTermsDescription": "1 month, Payment discount 2% max 5 days"
    },
*/
// #memo - date_from has no effect: we always load all customers and update only updated fields below
$data = eQual::run('get', 'contractika_bc_customers', ['date_from' => $last_run]);

// map the returned data set on the "Id" field, to link each Resource to its related Employee object
$nav_customers_map = [];
foreach($data as $nav_customer) {
    if(!isset($nav_customer['Id']) || empty($nav_customer['Id'])) {
        continue;
    }
    $nav_customers_map[$nav_customer['Id']] = $nav_customer;
}

// fetch all customers (active or not, as the active flag might have been updated on NAV side)
// #memo - customers are created based on AT (this script only updates existing customers)
$customers = Customer::search(['extref_nav_id', 'in', array_keys($nav_customers_map)])
    ->read([
            'id',
            'extref_nav_id',
            'extref_at_id',
            'partner_identity_id' => ['id', 'legal_name', 'has_vat', 'vat_number'],
            'is_active',
            'discount',
            'service_price',
            'target_margin',
            'payment_terms'
        ]);

foreach($customers as $customer) {
    if(!isset($customer['extref_nav_id'])) {
        // ignore customers without a NAV identifier
        ++$result['ignored'];
        continue;
    }
    $extref_nav_id = $customer['extref_nav_id'];
    if($extref_nav_id && isset($nav_customers_map[$extref_nav_id]) && $nav_customers_map[$extref_nav_id]) {
        $nav_customer = $nav_customers_map[$extref_nav_id];
        // update customer when a value change is detected
        try {
            $has_changes = false;
            if(isset($customer['partner_identity_id']) && $customer['partner_identity_id']['id'] > 0) {
                $values = [];
                $legal_name = $nav_customer['Name'];
                $has_vat = (bool) strlen($nav_customer['Vat']);
                $vat_number = str_replace([' ','.'], '', $nav_customer['Vat']);

                if($customer['partner_identity_id']['legal_name'] != $legal_name) {
                    $values['legal_name'] = $legal_name;
                }
                if($customer['partner_identity_id']['has_vat'] != $has_vat) {
                    $values['has_vat'] = $has_vat;
                }
                if($customer['partner_identity_id']['vat_number'] != $vat_number) {
                    $values['vat_number'] = $vat_number;
                }

                if(count($values)) {
                    // remember there is a change involving current customer
                    $has_changes = true;
                    Identity::id($customer['partner_identity_id']['id'])->update($values);
                }
            }

            $values = [];

            $is_active = !((bool) $nav_customer['Blocked']);
            $discount = round($nav_customer['Discount'] / 100, 2);
            $service_price = round($nav_customer['ServicePrice'], 2);
            $target_margin = round($nav_customer['TargetMargin'] ?? 0, 2);
            $payment_terms = $nav_customer['PaymentTermsDescription'];

            // force updating the customer at least for 1 field if the identity was updated

            if($has_changes || $is_active != $customer['is_active']) {
                $values['is_active'] = $is_active;
            }
            if($discount != $customer['discount']) {
                $values['discount'] = $discount;
            }
            if($service_price != $customer['service_price']) {
                $values['service_price'] = $service_price;
            }
            if($target_margin != $customer['target_margin']) {
                // #memo - this field had been set in Navision before AutoTask was being used, it is no longer needed (input is made directly within AT)
                // $values['target_margin'] = $target_margin;
            }
            if($payment_terms != $customer['payment_terms']) {
                $values['payment_terms'] = $payment_terms;
            }

            // updated customers will trigger a PUSH towards AT
            if(count($values)) {
                Customer::id($customer['id'])->update($values);
                $result['logs'][] = "OK  - updated customer AT{$customer['extref_at_id']} NAV{$extref_nav_id} CT{$customer['id']}";
                ++$result['updated'];
            }
        }
        catch(Exception $e) {
            // unexpected error
            ++$result['failed'];
            $result['logs'][] = "ERR - unexpected error when updating customer {$nav_customer['Name']} (NAV{$customer['extref_nav_id']})";
        }
    }
    else {
        $result['logs'][] = "WARN- customer AT{$customer['extref_nav_id']} with no NAV id";
        ++$result['ignored'];
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
