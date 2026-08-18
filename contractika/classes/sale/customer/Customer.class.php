<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\sale\customer;

use contractika\NAVLine;

class Customer extends \sale\customer\Customer {

    // #memo - we need a distinct table (this should be done at parent Customer class level, done to preserve compatibility)
    // because Customer inherits from Partner which is also used for Employees (if using the same table, there is a conflict for field extref_at_id)
    public function getTable() {
        return 'sale_customer_customer';
    }

    public static function getLink() {
        return "/contractika/#/customer/object.id";
    }

    public static function getColumns() {

        return [

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\identity\Identity',
                'description'       => 'The targeted identity (the partner).',
                'onupdate'          => 'onupdatePartnerIdentityId',
                'required'          => true
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'State of the customer (synced from NAV and AT).',
                'default'           => true
            ],

            'has_sa' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the customer has having at least one active service account (contract).',
                'function'          => 'calcHasSa',
                'store'             => true,
                'instant'           => true
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\sale\customer\Contact',
                'foreign_field'     => 'customer_id',
                'description'       => 'The targeted identity (the partner).'
            ],

            'service_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\ServiceAccount',
                'foreign_field'     => 'customer_id',
                'description'       => 'The service accounts of the customer.'
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'Code used by PSA software (AutoTask) for the Customer.',
                // #memo - might not be assigned at creation
                'unique'            => true
            ],

            'extref_at_parent_id' => [
                'type'              => 'integer',
                'description'       => 'Code used by PSA software (AutoTask) for the Parent Company, if any.'
            ],

            'extref_nav_id' => [
                'type'              => 'string',
                'description'       => 'Code used by Accounting software (Navision) as Customer identifier.'
            ],

            'parent_customer_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'description'       => 'Customer to use as a (virtual) parent Company, if any.',
                'help'              => 'Parent Customer is a virtual company used to group several customers under a same contract. The customer id retrieved by mapping the received AT `parentCompanyID` (stored in `extref_at_parent_id`) with the field `extref_at_id` of another Customer object.',
                'store'             => true,
                'function'          => 'calcParentCustomerId'
            ],

            'children_customers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'foreign_field'     => 'parent_customer_id',
                'description'       => 'Children customers of the Company, if any.'
            ],

            // #todo - use a computed field to resolve this to Payment Terms objects
            'payment_terms' => [
                'type'              => 'string',
                'description'       => 'Text identification of the default payment terms to apply on invoices emitted to this customer.'
            ],

            'discount' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => 'Discount specific to customer, to apply on his sales.'
            ],

            'service_price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Billable price per quarter of hour as agreed by Contract.',
                'onupdate'          => 'onupdateServicePrice'
            ],

            'target_margin' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => ''
            ],

            'companyType' => [
                'type'              => 'integer',
                'selection'         => [
                    1       => 'Customer',
                    2       => 'Lead',
                    3       => 'Prospect',
                    4       => 'Dead'
                ],
                'description'       => 'Type of customer.',
                'default'           => 1
            ],

            'customer_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerType',
                'description'       => "Type of customer. Defaults to 'company'.",
                'help'              => "If partner is a customer, it can be assigned a customer type",
                'visible'           => ['relationship', '=', 'customer'],
                'default'           => 3
            ],

            /* settings specific to customer, meant to override default config */

            'd_travel' => [
                'type'              => 'float',
                'description'       => 'Travel time associated to the customer (depends customer office location).',
                'default'           => 0.0,
                'visible'           => ['has_d_travel', '=', true],
                'onupdate'          => 'onupdateDTravel'
            ],

            'has_d_travel' => [
                'type'              => 'boolean',
                'description'       => 'Marks the customer as having a custom travel time.',
                'default'           => false
            ],

            'f_hfd_discount' => [
                'type'              => 'boolean',
                'description'       => 'Halday / FullDay reduction',
                'default'           => false
            ],

            'c_halfday' => [
                'type'              => 'float',
                'description'       => 'Halfday coefficient',
                'visible'           => ['f_hfd_discount', '=', true]
            ],

            'c_fullday' => [
                'type'              => 'float',
                'description'       => 'Halfday coefficient',
                'visible'           => ['f_hfd_discount', '=', true]
            ],

            'c_saturday' => [
                'type'              => 'float',
                'description'       => 'Coefficient for Halfday coefficient.'
            ],

            'c_sunday' => [
                'type'              => 'float',
                'description'       => 'Coefficient for Halfday coefficient.'
            ],

            'c_dayoff' => [
                'type'              => 'float',
                'description'       => 'Coefficient for Day Off (not working day).'
            ],

            'c_helpdesk' => [
                'type'              => 'float',
                'description'       => 'Coefficient for Helpdesk.'
            ],

            'c_priority_critical' => [
                'type'              => 'float',
                'description'       => 'Coefficient for priority Critial.'
            ],

            'c_priority_high' => [
                'type'              => 'float',
                'description'       => 'Coefficient for priority high.'
            ],

            'c_priority_normal' => [
                'type'              => 'float',
                'description'       => 'Coefficient for priority Normal.'
            ],

            'c_priority_low' => [
                'type'              => 'float',
                'description'       => 'Coefficient for priority Low.'
            ],

            'c_limit' => [
                'type'              => 'float',
                'description'       => 'Coefficient limit (cap).'
            ],

            'renewal_balance_floor' => [
                'type'              => 'float',
                'description'       => 'Balance limit at which a renewal has to be made.'
            ],

            'f_reporting' => [
                'type'              => 'string',
                'selection'         => [
                    'weekly',
                    'monthly',
                    'eurojob'
                ],
                'description'       => 'Reporting strategy for the customer.',
                'default'           => 'eurojob'
            ],

            // #deprecated
            // #memo - reporting_from must be set at Service Account level
            'reporting_from' => [
                'type'              => 'date',
                'description'       => 'Date of the start of reporting (switch from eurojob to contractika).'
            ],

            'vat_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcVatNumber',
                'description'       => 'Value Added Tax identification number (from identity).',
                'store'             => true
            ],

        ];
    }

    public static function calcHasSa($self) {
        $result = [];
        $self->read(['service_accounts_ids' => ['is_active']]);
        foreach($self as $id => $customer) {
            $result[$id] = false;
            if(isset($customer['service_accounts_ids']) && count($customer['service_accounts_ids'])) {
                foreach($customer['service_accounts_ids'] as $serviceAccount) {
                    if($serviceAccount['is_active']) {
                        $result[$id] = true;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    public static function calcVatNumber($self) {
        $result = [];
        $self->read(['partner_identity_id' => ['vat_number']]);
        foreach($self as $id => $customer) {
            $result[$id] = $customer['partner_identity_id']['vat_number'];
        }
        return $result;
    }

    public static function calcParentCustomerId($self) {
        $result = [];
        $self->read(['extref_at_parent_id']);
        foreach($self as $id => $customer) {
            if(!isset($customer['extref_at_parent_id'])) {
                continue;
            }
            $parent = self::search(['extref_at_id', '=', $customer['extref_at_parent_id']])->read(['id'])->first();
            if($parent) {
                $result[$id] = $parent['id'];
            }
        }
        return $result;
    }

    public static function onupdateServicePrice($self) {
        $self->read(['service_price']);
        foreach($self as $id => $customer) {
            $customer_price = round($customer['service_price'], 2);
            $nav_lines = NAVLine::search([['status', '=', 'pending'], ['customer_id', '=', $id]])->read(['id', 'extref_unit_price']);
            foreach($nav_lines as $line_id => $line) {
                $line_price = floatval($line['extref_unit_price']);
                $alert = (abs($line_price - $customer_price) > 0.05);
                // will reset has_alert and has_error
                NAVLine::id($line_id)->update(['has_alert_unit_price' => $alert]);
            }
        }
    }

    public static function onupdateDTravel($self, $values) {
        if(isset($values['d_travel'])) {
            $self->update(['has_d_travel' => !($values['d_travel'] == null)]);
        }
    }

    public function getUnique() {
        return [
            ['owner_identity_id', 'partner_identity_id']
        ];
    }

}