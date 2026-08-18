<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

use contractika\sale\customer\Customer;
use equal\orm\ObjectManager;

class ServiceAccount extends \sale\contract\Contract {

    public function getTable() {
        return 'contractika_serviceaccount';
    }

    public static function getDescription() {
        return "Service Accounts relate to Customers and are equivalent to Contracts. These entities are Read-Only and synced from AutoTask.";
    }

    public static function getLink() {
        return "/contractika/#/serviceaccount/object.id";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Memo assigned to the account for distinguishing amongst customer contracts.'
            ],

            // override status: always 'signed' (contracts come from AutoTask)
            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'signed'
                ],
                'description'       => 'Status of the contract.',
                'default'           => 'signed'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Description of the contract (from AT).'
            ],

            'contractId' => [
                'type'              => 'integer',
                'description'       => 'External reference. Code used by AutoTask PSA software for the contract.',
                'unique'            => true,
                'dependencies'      => ['extref_at_id']
            ],

            'contractLink' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'function'          => 'calcContractLink',
                'description'       => 'Direct link to AutoTask contract edition URL.'
            ],

            'extref_at_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcExtrefAtId',
                'store'             => true
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'description'       => 'The customer the contract relates to.',
                'dependencies'      => ['name']
            ],

            'reporting_from' => [
                'type'              => 'date',
                'description'       => 'Date of the start of reporting (switch from eurojob to contractika).',
                'default'           => time()
            ],

            'companyID' => [
                'type'              => 'integer',
                'description'       => 'Code used by Autotask PSA software to identifying Company the contract relates to.'
            ],

            'contactName' => [
                'type'              => 'string',
                'description'       => 'Customer contact using "firstName, lastName" format.'
            ],

            'sa_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SACategory',
                'description'       => 'The category the contract belongs to.',
            ],

            'sa_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SAType',
                'description'       => 'The type the contract relates to.',
            ],

            'sa_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\SALine',
                'foreign_field'     => 'service_account_id',
                'description'       => 'List of all lines referring to the service account.',
            ],

            'sa_lines_tt_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\SALine',
                'foreign_field'     => 'service_account_id',
                'description'       => 'Virtual field for listing accountTasks & Tickets lines.',
                'domain'            => ['sa_line_class_id', 'in', [1, 2]]
            ],

            'sa_lines_cc_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\SALine',
                'foreign_field'     => 'service_account_id',
                'description'       => 'Virtual field for listing account Credits & Corrections lines.',
                'domain'            => ['sa_line_class_id', 'in', [3, 4]],
                'ondetach'          => 'delete'
            ],

            'reports_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\Report',
                'foreign_field'     => 'service_account_id',
                'description'       => 'List of reports of the service account.',
                'ondetach'          => 'delete'
            ],

            'contractCategoryId' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the AT Category the contract relates to.'
            ],

            'contractTypeId' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the AT Category the contract relates to.'
            ],

            'startDate' => [
                'type'              => 'date'
            ],

            'endDate' => [
                'type'              => 'date'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Mark the contract as being active or not.',
                'onupdate'          => 'onupdateIsActive',
                'default'           => true
            ],

            'balance_current' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'store'             => true,
                'description'       => 'The amount of remaining points in the account according to all existing lines.',
                'function'          => 'calcBalanceCurrent'
            ],

            'has_balance_changed' => [
                'type'              => 'boolean',
                'description'       => 'Mark the balance as changed (used for sync with AutoTask).',
                'default'           => false
            ],

            'last_line_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'contractika\SALine',
                'function'          => 'calcLastLineId',
                'description'       => 'The most recent line relating to the the account.'
            ],

            'is_bonus' => [
                'type'              => 'boolean',
                'description'       => 'The contract is candidate for bonus calculation.',
                'default'           => false
            ],

            'is_invoiceable' => [
                'type'              => 'boolean',
                'description'       => 'The contract as implies CutOff (will be listed in Cut-Off Reports).',
                'default'           => true
            ],

            'm_reporting' => [
                'type'              => 'string',
                'description'       => 'Reporting mode for the contract.',
                'default'           => 'None',
                'selection'         => [
                    'None',
                    'Send',
                    'Archive'
                ]
            ],

            'renew_auto' => [
                'type'              => 'boolean',
                'description'       => "Automatic Renewal of ServicePackage.",
                'default'           => false
            ],

            'renew_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Renewal ServicePack amount in €.",
                'visible'           => ['renew_auto', '=', true]
            ],

            'renew_floor' => [
                'type'              => 'float',
                'description'       => "Floor in # for triggering renewal.",
                'default'           => 0,
                'visible'           => ['renew_auto', '=', true]
            ],

            'has_renew_alert_sent' => [
                'type'              => 'boolean',
                'description'       => "Balance is below floor and ticket has been sent to AT.",
                'default'           => false
            ]

        ];
    }

    public static function onupdateIsActive($self) {
        $self->read(['customer_id']);
        foreach($self as $serviceAccount) {
            Customer::id($serviceAccount['customer_id'])
                ->update(['has_sa' => null])
                // #memo - 'instant' doesn't seem to work
                ->read(['has_sa']);
        }
    }

    public static function calcBalanceCurrent($om, $ids, $lang) {
        $result = [];
        $accounts = self::ids($ids)->read(['sa_lines_tt_ids' => ['points', 'is_locked'], 'sa_lines_cc_ids' => ['points', 'is_locked']]);
        foreach($accounts as $id => $account) {
            // retrieve the amount of balance_new from the latest released report
            $report = Report::search([
                        ['service_account_id', '=', $id],
                        ['status', '<>', 'pending']
                    ],
                    [
                        'sort'  => ['id' => 'desc'],
                        'limit' => 1
                    ]
                )
                ->read(['balance_new'])
                ->first();
            // use report balance_new and, if missing, fallback to account initial balance
            if($report) {
                $balance = $report['balance_new'];
            }
            else {
                $balance = 0.0;
            }
            // adjust balance (decrement) with points from TT SAlines not yet locked
            foreach($account['sa_lines_tt_ids'] as $tt_line) {
                if(!$tt_line['is_locked']) {
                    $balance -= $tt_line['points'];
                }
            }
            // adjust balance (increment) with points from CC SAlines not yet locked
            foreach($account['sa_lines_cc_ids'] as $cc_line) {
                if(!$cc_line['is_locked']) {
                    $balance += $cc_line['points'];
                }
            }
            $result[$id] = $balance;
        }
        return $result;
    }

    public static function calcLastLineId($om, $oids, $lang) {
        $result = [];
        foreach($oids as $oid) {
            $line = SALine::search(['service_account_id', '=', $oid], ['sort'  => ['date' => 'desc'], 'limit' => 1])
                ->read(['id'])
                ->first();
            if($line) {
                $result[$oid] = $line['id'];
            }
        }
        return $result;
    }

    public static function calcContractLink($om, $ids, $lang) {
        $result = [];
        $link = 'https://ww19.autotask.net/contracts/views/contractView.asp?contractID=';

        $accounts = self::ids($ids)->read(['contractId']);
        foreach($accounts as $id => $account) {
            $result[$id] = $link.$account['contractId'];
        }
        return $result;
    }

    public static function calcExtrefAtId($om, $ids, $lang) {
        $result = [];
        $accounts = self::ids($ids)->read(['contractId']);
        foreach($accounts as $id => $account) {
            $result[$id] = $account['contractId'];
        }
        return $result;
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $ids        List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {

        return parent::canupdate($om, $ids, $values, $lang);
    }

}