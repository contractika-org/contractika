<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

use contractika\sale\customer\Customer;

class NAVLine extends SALine {

    public function getTable() {
        return "contractika_navline";
    }

    public static function getColumns() {
        return [
            'customer_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'description'       => 'Customer the line relates to (depending on service account).',
                'help'              => 'The customer id retrieved by mapping the received NAV customer (`extref_customer`) with the field `extref_nav_id` in Customer objects.',
                'store'             => true,
                'function'          => 'calcCustomerId',
                'onupdate'          => 'onupdateCustomerId'
            ],

            'service_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'contractika\ServiceAccount',
                'description'       => 'The service account the line belongs to.',
                'store'             => true,
                'function'          => 'calcServiceAccountId',
                'onupdate'          => 'onupdateServiceAccountId'
            ],

            'extref_document_no' => [
                'type'              => 'string',
                'description'       => '[Document No_] - NAV invoice id',
            ],

            'extref_line_no' => [
                'type'              => 'integer',
                'description'       => '[Line No_] - NAV line id',
            ],

            'extref_line_uuid' => [
                'type'              => 'string',
                'description'       => '[id] - BC line id',
                'help'              => 'This value was added for synching with Business Central.',
            ],

            'extref_customer' => [
                'type'              => 'string',
                'description'       => '[Sell-to Customer No_] - Customer NAV ID',
                'dependencies'      => ['customer_id']
            ],

            'extref_no' => [
                'type'              => 'string',
                'description'       => '[No_] - line type : `NTK-PROVISION` or `NTK-SERVICEPACKAGE`',
            ],

            'extref_description2' => [
                'type'              => 'string',
                'description'       => '[Description 2] - value holding a ref to contract or service account',
                'dependencies'      => ['service_account_id']
            ],

            'extref_uom_code' => [
                'type'              => 'string',
                'description'       => '[Unit of Measure Code] - NAV line unit of measure code (``, `PCS`, `PNT`)',
                'onupdate'          => 'onupdateExtrefUomCode'
            ],

            'extref_unit_price' => [
                'type'              => 'string',
                'description'       => '[Unit Price] - NAV line unit price (to be compared with SA Customer service price)',
                'onupdate'          => 'onupdateExtrefUnitPrice'
            ],

            'extref_quantity' => [
                'type'              => 'string',
                'description'       => '[Quantity] - NAV line quantity (should be amount of points)',
            ],

            'extref_amount' => [
                'type'              => 'string',
                'description'       => '[Amount] - NAV line amount (amount / unit_price = quantity); if uom_code is not PNT, unit_price = amount / service_price',
            ],

            'has_error' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking a line as faulty.',
                'store'             => true,
                'function'          => 'calcHasError'
            ],

            'has_alert' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line has one or more warnings.',
                'store'             => true,
                'function'          => 'calcHasAlert',
                'dependencies'      => ['has_error']
            ],

            'has_error_customer' => [
                'type'              => 'boolean',
                'description'       => 'Unable to resolve customer.',
                'default'           => false,
                'dependencies'      => ['has_error']
            ],

            'has_error_service_account' => [
                'type'              => 'boolean',
                'description'       => 'Unable to resolve service account.',
                'default'           => false,
                'dependencies'      => ['has_error']
            ],

            'has_alert_uom' => [
                'type'              => 'boolean',
                'description'       => 'Unit of measure mismatch.',
                'default'           => false,
                'dependencies'      => ['has_alert']
            ],

            'has_alert_unit_price' => [
                'type'              => 'boolean',
                'description'       => 'Unit price mismatch.',
                'default'           => false,
                'dependencies'      => ['has_alert']
            ],

            'reconciliation_log' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Details about the auto-reconcile attempt.',
            ],

            'sa_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SALine',
                'description'       => 'Service Account line the NAV line has been reconciled with.',
                'visible'           => ['status', '=', 'reconciled']
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Status of the line.',
                'help'              => 'The status impacts the available actions along with the filtering within views. Possible status are degined in `getWorkflow()` method.',
                'default'           => 'pending',
                // #memo - selection list is to comply with the UI (should use getWorkflow)
                'selection' => [
                    'pending',
                    'reconciled',
                    'ignored'
                ]
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'transitions' => [
                    'reconcile' => [
                        'domain'      => [['service_account_id', '>', 0], ['customer_id', '>', 0], ['has_error', '=', false]],
                        'description' => "Update the line status to `reconciled`.",
                        'help'        => "The transition will be allowed only if there are no pending errors.",
                        'status'	  => 'reconciled',
                        'onafter'     => 'doReconcile'
                    ],
                    'ignore' => [
                        'description' => "Update the line status to `ignored`. This transition is meant to be called manually.",
                        'status'	  => 'ignored'
                    ]
                ]
            ],
            'ignored' => [
                'transitions' => [
                    'restore' => [
                        'description' => "Update the line status to 'pending'. This transition is meant to be called manually.",
                        'status'	  => 'pending'
                    ]
                ]
            ],
            'reconciled' => [
                'transitions' => [
                    // reconciliation cannot be undone : once reconciled, a SALine has been created, and might have been modified and/or invoiced
                ]
            ]
        ];
    }

    /**
     * Create a new SALine (CC) according to the given details of current NAV Line.
     */
    public static function doReconcile($self) {
        $self->read(['status', 'has_error', 'service_account_id', 'customer_id', 'date', 'description', 'points', 'reconciliation_log']);

        foreach($self as $id => $line) {
            if($line['status'] != 'reconciled' || $line['has_error']) {
                trigger_error("APP::doReconcile request on an invalid NAV Line {$id} with values '".serialize($line)."'", QN_REPORT_WARNING);
                continue;
            }
            // default class `credit`
            $line_class_id = 3;
            // if points is a negative value, class is `correction`
            if($line['points'] < 0) {
                $line_class_id = 4;
            }
            $values = [
                'customer_id'           => $line['customer_id'],
                'date'                  => $line['date'],
                'description'           => $line['description'],
                // #memo leave employee_id to null
                // 'employee_id'           => $line['employee_id'],
                // #memo - lines from NAV are always of class `credit`
                'sa_line_class_id'      => $line_class_id,
                'points'                => $line['points']
            ];

            $new_line = SALine::create($values)
                ->update(['service_account_id' => $line['service_account_id']])
                ->first();

            self::id($id)->update([
                    'sa_line_id'         => $new_line['id'],
                    'reconciliation_log' => date('Y-m-d H:i:s').' - '."NAV Line reconciled with SA line '{$new_line['id']}'"."\n".$line['reconciliation_log']
                ]);
            trigger_error("APP::Create SA Line for NAV line id {$id} with values '".serialize($values)."'", QN_REPORT_DEBUG);
        }
    }

    public static function onupdateServiceAccountId($om, $self, $values) {
        $self->read(['has_error_service_account']);
        if(isset($values['service_account_id']) && $values['service_account_id'] > 0) {
            foreach($self as $id => $line) {
                if($line['has_error_service_account']) {
                    self::id($id)->update(['has_error_service_account' => false]);
                }
            }
        }
        else {
            $self->update(['has_error_service_account' => true]);
        }
    }

    public static function onupdateCustomerId($self, $values) {
        $self->read(['has_error_customer']);
        if(isset($values['customer_id']) && $values['customer_id'] > 0) {
            foreach($self as $id => $line) {
                if($line['has_error_customer']) {
                    self::id($id)->update(['has_error_customer' => false]);
                }
            }
        }
        else {
            $self->update(['has_error_customer' => true]);
        }
    }

    public static function calcHasError($self) {
        $result = [];
        // #memo - alerts have to be discarded before allowing reconciliations
        $self->read(['has_error_customer','has_error_service_account','has_alert']);
        foreach($self as $id => $line) {
            $result[$id] = ($line['has_error_customer'] || $line['has_error_service_account'] || $line['has_alert']);
        }
        return $result;
    }

    public static function calcHasAlert($self) {
        $result = [];
        $self->read(['has_alert_uom','has_alert_unit_price']);
        foreach($self as $id => $line) {
            $result[$id] = ($line['has_alert_uom'] || $line['has_alert_unit_price']);
        }
        return $result;
    }

    public static function calcCustomerId($self) {
        $result =  [];
        $self->read(['extref_customer', 'has_error_customer']);
        foreach($self as $id => $line) {
            // skip line that are already on error
            if($line['has_error_customer']) {
                continue;
            }
            $customer = null;
            if(strlen($line['extref_customer'])) {
                $customer = Customer::search(['extref_nav_id', '=', $line['extref_customer']])->read(['id'])->first();
                if($customer) {
                    $result[$id] = $customer['id'];
                    self::id($id)->update(['has_error_customer' => false]);
                }
            }
            if(!$customer) {
                self::id($id)
                    ->update([
                        'has_error_customer'    => true,
                        'reconciliation_log'    => date('Y-m-d H:i:s').' - '."Could not associate '{$line['extref_customer']}' with a Customer."."\n".$line['reconciliation_log']
                    ]);
            }
        }
        return $result;
    }

    public static function calcServiceAccountId($self) {
        $result = [];
        $self->read(['extref_description2', 'customer_id', 'description', 'has_error_service_account', 'reconciliation_log']);
        foreach($self as $id => $line) {
            // skip line that are already on error
            if($line['has_error_service_account']) {
                continue;
            }

            $clue = $line['extref_description2'];
            $contract_id = 0;
            $logs = [];

            // CT SA ID #SA-12#, #SA-123# , #SA-1234#, or AT contract ID #AT-29683364#
            if(strlen($clue) > 0 && strpos($clue, '-') !== false) {
                $contract = null;
                list($type, $identifier) = explode('-', trim($clue, '#'));
                if($type == 'SA') {
                    $contract = ServiceAccount::search(['id', '=', intval($identifier)])->read(['id', 'customer_id'])->first();
                }
                elseif($type == 'AT') {
                    $contract = ServiceAccount::search(['contractId', '=', intval($identifier)])->read(['id', 'customer_id'])->first();
                }
                // contract found, assign if customer IDs do match
                if($contract) {
                    if($contract['customer_id'] == $line['customer_id']) {
                        $contract_id = $contract['id'];
                    }
                    else {
                        // check if customer targeted by service account ($contract['customer_id']) has a parent that matches the line customer
                        $customer = Customer::id($line['customer_id'])->read(['parent_customer_id'])->first();
                        if($customer && $contract['customer_id'] == $customer['parent_customer_id']) {
                            $contract_id = $contract['id'];
                        }
                        else {
                            $contract_id = -1;
                            $logs[] = "Resolved contract (customer: {$contract['customer_id']}, contract:{$contract['id']}) do not match resolved customer ({$line['customer_id']}).";
                        }
                    }
                }
            }
            else {
                $clue = $id;
            }

            // no contract match, look for a preLoads from same customer
            if($contract_id == 0) {
                $found = false;
                if(strlen($line['description']) && $line['customer_id']) {
                    $needles = ["Pre-installation", "Pré-installation"];
                    foreach($needles as $needle) {
                        if(strpos($line['description'], $needle) === 0) {
                            $found = true;
                            break;
                        }
                    }
                    if($found) {
                        $contract = ServiceAccount::search([['customer_id', '=', $line['customer_id']], ['name', '=', 'preLoads']])->read(['id'])->first();
                        if($contract) {
                            $contract_id = $contract['id'];
                        }
                        else {
                            $logs[] = 'Line is candidate for PreLoads service account but none found.';
                        }
                    }
                }

                if(!$found) {
                    $logs[] = 'Line is no candidate for PreLoads service account.';
                }
            }

            if($contract_id > 0) {
                $result[$id] = $contract_id;
                $logs[] = "{$clue} associated with Service Account {$contract_id}";
                self::id($id)->update(['has_error_service_account' => false]);
            }
            else {
                $logs[] = "Could not associate '{$clue}' with a Service Account.";
                self::id($id)->update(['has_error_service_account' => true]);
            }
            // compute resulting log
            $log = $line['reconciliation_log'];
            foreach($logs as $entry) {
                $log = date('Y-m-d H:i:s').' - '.$entry."\n".$log;
            }
            // prepend log
            self::id($id)->update(['reconciliation_log' => $log]);
        }
        return $result;
    }

    public static function onupdateExtrefUomCode($self) {
        $self->read(['extref_uom_code', 'extref_quantity']);
        foreach($self as $id => $line) {
            if($line['extref_uom_code'] != 'PNT') {
                self::id($id)
                    ->update([
                        'has_alert_uom'         => true,
                        'reconciliation_log'    => date('Y-m-d H:i:s').' - '."unit = '{$line['extref_uom_code']}' / quantity = {$line['extref_quantity']}."."\n".$line['reconciliation_log']
                    ]);
            }
            else {
                self::id($id)->update(['has_alert_uom' => false]);
            }
        }
    }

    public static function onupdateExtrefUnitPrice($self) {
        $self->read(['extref_unit_price', 'customer_id' => ['service_price']]);
        foreach($self as $id => $line) {
            $line_price = floatval($line['extref_unit_price']);
            $customer_price = round($line['customer_id']['service_price'], 2);
            if(abs($line_price - $customer_price) > 0.05) {
                self::id($id)
                    ->update([
                        'has_alert_unit_price'  => true,
                        'reconciliation_log'    => date('Y-m-d H:i:s').' - '."Service Price NAVLine ({$line_price}) <> Service Price CT ({$customer_price})."."\n".$line['reconciliation_log']
                    ]);
            }
            else {
                self::id($id)->update(['has_alert_unit_price' => false]);
            }
        }
    }
}