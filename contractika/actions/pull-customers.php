<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use contractika\sale\customer\Customer;
use contractika\identity\Identity;
use contractika\sale\customer\Contact;
use contractika\SALine;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Updates the list of Customer objects based on latest list of Companies from AutoTask.',
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
    'constants'         => ['EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report', 'dispatch' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 * @var \equal\dispatch\Dispatcher        $dispatch
 */
['context' => $context, 'report' => $reporter, 'dispatch' => $dispatch] = $providers;


$result = [
    'errors'    => 0,
    'warnings'  => 0,
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'failed'    => 0,
    'logs'      => []
];

// retrieve last_run from settings (defaults to 'all times')
$last_run = Setting::get_value('contractika', 'sync', 'at_sync_customers.last_run', 0);

if($params['date_from'] && $params['date_from'] > 0) {
    $last_run = $params['date_from'];
}

try {
    // pass-1 : fetch customers updated since last sync from AutoTask (using API)
    $data = eQual::run('get', 'contractika_at_customers', ['date_from' => $last_run]);

    // keep track of involved Customer objects
    $customers_ids = [];

    foreach($data as $values) {
        try {
            // ignore entities that are neither 'Customer' nor 'Dead'
            if(!in_array($values['companyType'], [1, 4])) {
                continue;
            }
            if(!isset($values['id']) || $values['id'] <= 0) {
                ++$result['ignored'];
                $result['logs'][] = 'OK  - ignored customer with no id '.$values['companyName'];
                continue;
            }
            // normalize VAT number
            if(isset($values['taxID'])) {
                $values['taxID'] = str_replace([' ', '.'], '', $values['taxID']);
            }
            // normalize user defined fields (inject UDF values as object properties)
            foreach($values['userDefinedFields'] as $user_defined_field) {
                $values[$user_defined_field['name']] = $user_defined_field['value'];
            }

            // there should be 0 or 1 match
            $ids = Customer::search(['extref_at_id', '=', $values['id']])->ids();

            // customer already exists in db: update its identity
            if(count($ids)) {
                $collection = Customer::ids($ids)
                    ->read([
                        'id',
                        'partner_identity_id',
                        'd_travel',
                        'f_hfd_discount',
                        'c_halfday',
                        'c_fullday',
                        'c_saturday',
                        'c_sunday',
                        'c_dayoff',
                        'c_helpdesk',
                        'c_priority_critical',
                        'c_priority_high',
                        'c_priority_normal',
                        'c_priority_low',
                        'c_limit',
                        'renewal_balance_floor',
                        'f_reporting',
                        'has_sa'
                    ]);

                $customer = $collection->first();

                // if Customer is 'Dead', try to remove it
                if($values['companyType'] == 4) {
                    if($customer['has_sa']) {
                        $result['logs'][] = "WARN- customer AT{$values['id']} ({$values['companyName']}) marked as 'Dead', but cannot be removed since has Contract.";
                        ++$result['warnings'];
                        throw new Exception('cannot_remove_customer_with_contract', EQ_ERROR_NOT_ALLOWED);
                    }
                    else {
                        Customer::id($customer['id'])->delete();
                        $result['logs'][] = "INFO- removed customer AT{$values['id']} ({$values['companyName']}) marked as 'Dead' and without contract.";
                        continue;
                    }
                }

                // if NAV ID is empty: alert sales
                if((!isset($values['NAV ID']) || !strlen((string) $values['NAV ID'])) && $values['isActive']) {
                    // #memo #virtual_customer - there are known exception of "non-real" customers (no VAT) but virtual groups of customers
                    $map_exceptions = [
                            697     => 'EMEAA network'
                        ];
                    if(!in_array($values['id'], array_keys($map_exceptions))) {
                        $result['logs'][] = "WARN- active customer AT{$values['id']} ({$values['companyName']}) with no NAV ID";
                        ++$result['warnings'];
                    }
                }

                if(isset($customer['partner_identity_id']) && $customer['partner_identity_id'] > 0) {
                    Identity::id($customer['partner_identity_id'])
                        ->update([
                            'legal_name'  => str_replace([',', ';', '+', '/', '_'], '', $values['companyName']),
                            'has_vat'     => (bool) strlen($values['taxID']),
                            'vat_number'  => $values['taxID'],
                            'type'        => 'C',
                            'type_id'     => 3
                        ]);
                }
                else {
                    $result['logs'][] = 'WARN- missing identity entity for customer AT'.$values['id'].', '.$values['companyName'];
                    ++$result['warnings'];
                }

                $item = [
                        'name'                  => null,
                        'extref_at_parent_id'   => $values['parentCompanyID'],
                        'extref_nav_id'         => $values['NAV ID'],
                        // #memo - these fields are only set at creation from AT, afterward only NAV is considered
                        // 'payment_terms'         => $values['Payment Terms'],
                        // 'discount'              => round($values['Discount'], 2),
                        // 'service_price'         => round($values['Service Price'], 2),
                        'target_margin'         => round($values['Target Margin'], 2),
                        'companyType'           => $values['companyType'],
                        'lang_id'               => isset($values['Language'])?(['ENU' => 1, 'FRA' => 2, 'NLD' => 3][$values['Language']]):1
                    ];

                // while Customer is not linked to NAV (most probably a Prospect in AT), we sync CT:is_active with AT:isActive
                if(!isset($values['NAV ID']) || !strlen((string) $values['NAV ID'])) {
                    $item['is_active'] = $values['isActive'];
                }

                // customer settings can be left to null, in which case the default setting applies
                $settings = [
                    'Travel'                => 'd_travel',
                    'HDFD_reduction'        => 'f_hfd_discount',
                    'HD_coef'               => 'c_halfday',
                    'FD_coef'               => 'c_fullday',
                    'Saturday_coef'         => 'c_saturday',
                    'Sunday_coef'           => 'c_sunday',
                    'DayOff_coef'           => 'c_dayoff',
                    'Helpdesk_coef'         => 'c_helpdesk',
                    'PriorityCritical_coef' => 'c_priority_critical',
                    'PriorityHigh_coef'     => 'c_priority_high',
                    'PriorityNormal_coef'   => 'c_priority_normal',
                    'PriorityLow_coef'      => 'c_priority_low',
                    'Coefficient_limit'     => 'c_limit',
                    'SP_Renew_floor'        => 'renewal_balance_floor',
                    'TSreport_frequency'    => 'f_reporting'
                ];
                $float_settings = [
                    'd_travel',
                    'c_halfday',
                    'c_fullday',
                    'c_saturday',
                    'c_sunday',
                    'c_dayoff',
                    'c_helpdesk',
                    'c_priority_critical',
                    'c_priority_high',
                    'c_priority_normal',
                    'c_priority_low',
                    'c_limit',
                    'renewal_balance_floor'
                ];
                $has_setting_change = false;
                foreach($settings as $setting => $field) {
                    if(isset($values[$setting]) && !is_null($values[$setting])) {
                        $value = $values[$setting];
                        if($setting == 'TSreport_frequency') {
                            $value = strtolower(trim($value));
                            if($value == 'none') {
                                $value = 'eurojob';
                            }
                        }
                        elseif(in_array($field, $float_settings, true) && is_numeric($value)) {
                            $value = round(floatval($value), 2);
                        }
                        $item[$field] = $value;
                    }
                    // not set or null
                    else {
                        if($setting == 'TSreport_frequency') {
                            $item[$field] = 'eurojob';
                        }
                        else {
                            $item[$field] = null;
                        }
                    }
                    if($item[$field] != $customer[$field]) {
                        $has_setting_change = true;
                    }
                }

                // update the customer object
                $collection
                    ->update($item)
                    ->update(['vat_number' => null]);

                // perform checks (generate or remove alerts)
                eQual::run('do', 'contractika_customer_check-nav', ['id' => $customer['id']]);
                eQual::run('do', 'contractika_customer_check-identity', ['id' => $customer['id']]);

                ++$result['updated'];
                $result['logs'][] = 'OK  - updated customer AT'.$values['id'].', '.$values['companyName'];

                // if a setting impacting the points calculation has been updated, reset computed fields for all impacted lines
                if($has_setting_change) {
                    SALine::search([
                            ['customer_id', '=', $customer['id']],
                            ['sa_line_class_id', 'in', [1, 2]],
                            ['is_locked', '=', false]
                        ])
                        // reset computed fields
                        ->update(['pause_time' => null, 'duration' => null, 'travel_time' => null, 'points' => null]);
                }
            }
            // customer does not exist yet: create it
            else {
                // search for an identity with matching VAT number, if any (invalid VAT are ignored and kept as is)
                if(isset($values['taxID']) && !is_null($values['taxID']) && strlen($values['taxID'])) {
                    if($values['taxID'] == 'BE0123456789') {
                        ++$result['ignored'];
                        $result['logs'][] = "OK  - ignored virtual customer {$values['companyName']} with VAT 'BE0123456789'";
                        continue;
                    }
                    $identity = Identity::search([ ['vat_number', '<>', null], ['vat_number', '=', $values['taxID']] ])->read(['legal_name'])->first();
                    if($identity) {
                        // #memo - Netika allows having several customer with the same VAT (for distinguishing departments of a same entity but with distinct addresses)
                        // error : VAT duplicate - should not occur
                        // $result['logs'][] = "ERR - cannot create customer {$values['companyName']} since VAT number {$values['taxID']} is already assigned to {$identity['legal_name']}";
                        $result['logs'][] = "WARN- customer {$values['companyName']} uses same VAT number {$values['taxID']} as {$identity['legal_name']}";
                        ++$result['warnings'];
                    }
                }
                /** @var Identity */
                $identity = Identity::create([
                        'legal_name'  => str_replace([',', ';', '+', '/', '_'], '', $values['companyName']),
                        'has_vat'     => (bool) strlen($values['taxID']),
                        'vat_number'  => $values['taxID'],
                        'type'        => 'C',
                        'type_id'     => 3
                    ])
                    ->first();

                $customer = Customer::create([
                        'owner_identity_id'     => 1,
                        'partner_identity_id'   => $identity['id'],
                        'extref_at_id'          => $values['id'],
                        'extref_at_parent_id'   => $values['parentCompanyID'],
                        'extref_nav_id'         => $values['NAV ID'],
                        'payment_terms'         => $values['Payment Terms'],
                        'discount'              => round($values['Discount'], 2),
                        'service_price'         => round($values['Service Price'], 2),
                        'target_margin'         => round($values['Target Margin'], 2),
                        'companyType'           => $values['companyType'],
                        // #todo - should be always true since there is a condition
                        'is_active'             => $values['isActive'],
                        'lang_id'               => isset($values['Language'])?(['ENU' => 1, 'FRA' => 2, 'NLD' => 3][$values['Language']]):1,
                        // #memo - by convention reporting will start on the first day of the month of the creation of the Customer (can be modified by user)
                        // #memo - deprecated (use value from ServiceAccount instead)
                        'reporting_from'        => strtotime(date('Y-m-01 00:00:00'))
                    ])
                    ->first();

                ++$result['created'];
                $result['logs'][] = 'OK  - created customer AT'.$values['id'].', '.$values['companyName'];
            }

            // remember involved Customers
            $customers_ids[] = $customer['id'];
        }
        catch(Exception $e) {
            $reporter->debug('error while processing AT id ' . $values['id'] . ' [' . $customer['id'] . ']:' . $e->getMessage());
            $result['logs'][] = 'ERR - error while processing customer AT' . $values['id'] . ', ' . $values['companyName'].':'.$e->getMessage();
            ++$result['failed'];
            ++$result['warnings'];
        }
    }

    // force immediate generation of computed fields
    Customer::ids($customers_ids)->read(['id', 'name', 'vat_number']);


    // pass-2 : create or update contacts
    $at_contacts = eQual::run('get', 'contractika_at_contacts', ['date_from' => $last_run]);

    foreach($at_contacts as $at_contact) {

        // normalize user defined fields (inject UDF values as object properties)
        foreach($at_contact['userDefinedFields'] as $user_defined_field) {
            $at_contact[$user_defined_field['name']] = $user_defined_field['value'];
        }

        // fix incomplete contacts
        if(!isset($at_contact['primaryContact'])) {
            $at_contact['primaryContact'] = false;
        }
        if(!isset($at_contact['TimeSheets Report'])) {
            $at_contact['TimeSheets Report'] = null;
        }

        // check if we must consider the contact (must be present in the company TS contacts)
        $valid = false;
        if($at_contact['primaryContact'] == true && $at_contact['TimeSheets Report'] != 'No') {
            $valid = true;
        }
        if($at_contact['TimeSheets Report'] == 'Yes') {
            $valid = true;
        }
        // #memo - unsure if isActive is an int or bool (varies across entities)
        if($at_contact['isActive'] === 0 || $at_contact['isActive'] === false) {
            $valid = false;
        }

        // retrieve customer_id based on companyID (=extref_at_id)
        $customer = Customer::search(['extref_at_id', '=', $at_contact['companyID']])->read(['id'])->first();

        if(!$customer) {
            // contact's customer is unknown from Contractika : ignore
            $valid = false;
        }

        $ids = Contact::search(['extref_at_id', '=', $at_contact['id']])->ids();

        // existing contact : update
        if(count($ids)) {
            $contact_id = reset($ids);
            if($valid) {
                // Language : ENU, FRA, NLD => que fr ou en
                Contact::id($contact_id)->update([
                        'email'            => $at_contact['emailAddress'],
                        'firstname'        => $at_contact['firstName'],
                        'lastname'         => $at_contact['lastName'],
                        'language'         => ['ENU' => 'en', 'FRA' => 'fr', 'NLD' => 'nl'][$at_contact['Language']],
                        'customer_id'      => $customer['id']
                    ]);
                $result['logs'][] = 'OK  - updated contact AT'.$at_contact['id'].', '.$at_contact['firstName'].' '.$at_contact['lastName'];
            }
            else {
                Contact::id($contact_id)->delete(true);
            }
        }
        // new contact
        elseif($valid) {
            Contact::create([
                    'extref_at_id'     => $at_contact['id'],
                    'email'            => $at_contact['emailAddress'],
                    'firstname'        => $at_contact['firstName'],
                    'lastname'         => $at_contact['lastName'],
                    'language'         => ['ENU' => 'en', 'FRA' => 'fr', 'NLD' => 'nl'][$at_contact['Language']],
                    'customer_id'      => $customer['id']
                ]);
            $result['logs'][] = 'OK  - created contact AT'.$at_contact['id'].', '.$at_contact['firstName'].' '.$at_contact['lastName'];
        }
    }

    foreach($customers_ids as $customer_id) {
        // perform checks (generate or remove alerts)
        eQual::run('do', 'contractika_customer_check-vat', ['id' => $customer_id]);
        eQual::run('do', 'contractika_customer_check-contacts', ['id' => $customer_id]);
    }
}
catch(Exception $e) {
    /**
     * An unexpected error occurred.
     */

    // send an email alert
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('ERROR Contractika')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Erreur inattendue lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i')." :</p>
                    <pre>".qn_error_name($e->getCode()).' : '.$e->getMessage()."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
    // relay exception
    throw new Exception($e->getMessage(), $e->getCode());
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
