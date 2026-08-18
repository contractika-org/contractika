<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\BonusReport;
use contractika\CutOffReport;
use contractika\NAVLine;
use contractika\Report;
use contractika\SALine;
use contractika\ServiceAccount;
use contractika\hr\absence\Absence;
use contractika\hr\employee\Employee;
use contractika\sale\customer\Customer;

require_once(__DIR__ . '/lib/golden.php');

$scheduled_controllers = [
    'contractika_sync-customers',
    'contractika_pull-contracts',
    'contractika_sync-timeentries',
    'contractika_calc-points',
    'contractika_sync-deletedentries',
    'contractika_pull-credits',
    'contractika_reconcile-credits',
    'contractika_push-balances',
    'contractika_sync-employees',
    'contractika_sync-absences',
    'contractika_sync-holidays',
    'contractika_sync-dashboard',
    'contractika_cutoffreport_batch-create-recap',
    'contractika_employees_collect'
];

$tests = [
    '3001' => [
        'description' => 'Golden master: Contractika model, workflow and document surface.',
        'act'         => function () {
            $saline_columns = SALine::getColumns();
            $report_columns = Report::getColumns();
            $nav_columns = NAVLine::getColumns();
            $service_account_columns = ServiceAccount::getColumns();
            $customer_columns = Customer::getColumns();
            $employee_columns = Employee::getColumns();
            $absence_columns = Absence::getColumns();

            return [
                'models' => [
                    'Customer' => [
                        'table'  => (new Customer())->getTable(),
                        'fields' => [
                            'partner_identity_id' => $customer_columns['partner_identity_id'],
                            'extref_at_id'        => $customer_columns['extref_at_id'],
                            'extref_nav_id'       => $customer_columns['extref_nav_id'],
                            'service_price'       => $customer_columns['service_price'],
                            'has_sa'              => $customer_columns['has_sa']
                        ]
                    ],
                    'Employee' => [
                        'table'  => (new Employee())->getTable(),
                        'fields' => [
                            'partner_identity_id' => $employee_columns['partner_identity_id'],
                            'display_name'        => $employee_columns['display_name'],
                            'extref_sd_id'        => $employee_columns['extref_sd_id'],
                            'extref_at_id'        => $employee_columns['extref_at_id']
                        ]
                    ],
                    'Absence' => [
                        'fields' => [
                            'status'      => $absence_columns['status'],
                            'employee_id' => $absence_columns['employee_id'],
                            'extref_sd_id'=> $absence_columns['extref_sd_id'],
                            'extref_at_id'=> $absence_columns['extref_at_id'],
                            'layer'       => $absence_columns['layer']
                        ]
                    ],
                    'ServiceAccount' => [
                        'table'  => (new ServiceAccount())->getTable(),
                        'fields' => [
                            'status'              => $service_account_columns['status'],
                            'contractId'          => $service_account_columns['contractId'],
                            'balance_current'     => $service_account_columns['balance_current'],
                            'has_balance_changed' => $service_account_columns['has_balance_changed'],
                            'renew_auto'          => $service_account_columns['renew_auto'],
                            'renew_floor'         => $service_account_columns['renew_floor']
                        ]
                    ],
                    'SALine' => [
                        'fields' => [
                            'service_account_id' => $saline_columns['service_account_id'],
                            'pause_time'         => $saline_columns['pause_time'],
                            'duration'           => $saline_columns['duration'],
                            'travel_time'        => $saline_columns['travel_time'],
                            'points'             => $saline_columns['points'],
                            'is_locked'          => $saline_columns['is_locked'],
                            'is_orphan'          => $saline_columns['is_orphan'],
                            'is_async'           => $saline_columns['is_async'],
                            'report_id'          => $saline_columns['report_id']
                        ]
                    ],
                    'Report' => [
                        'fields' => [
                            'status'         => $report_columns['status'],
                            'total_points'   => $report_columns['total_points'],
                            'total_credits'  => $report_columns['total_credits'],
                            'balance_old'    => $report_columns['balance_old'],
                            'balance_new'    => $report_columns['balance_new'],
                            'has_non_posted' => $report_columns['has_non_posted'],
                            'pdf_data'       => $report_columns['pdf_data'],
                            'link'           => $report_columns['link']
                        ]
                    ],
                    'NAVLine' => [
                        'table'    => (new NAVLine())->getTable(),
                        'fields'   => [
                            'customer_id'        => $nav_columns['customer_id'],
                            'service_account_id' => $nav_columns['service_account_id'],
                            'has_error'          => $nav_columns['has_error'],
                            'has_alert'          => $nav_columns['has_alert'],
                            'status'             => $nav_columns['status'],
                            'sa_line_id'         => $nav_columns['sa_line_id']
                        ],
                        'workflow' => NAVLine::getWorkflow()
                    ],
                    'CutOffReport' => [
                        'workflow' => CutOffReport::getWorkflow(),
                        'actions'  => CutOffReport::getActions()
                    ],
                    'BonusReport' => [
                        'workflow' => BonusReport::getWorkflow(),
                        'actions'  => BonusReport::getActions()
                    ]
                ],
                'document_links' => [
                    'report_pdf'        => $report_columns['link']['function'],
                    'report_pdf_data'   => $report_columns['pdf_data']['function'],
                    'cutoff_xls'        => CutOffReport::getColumns()['link']['function'],
                    'bonus_pdf'         => BonusReport::getColumns()['link']['function'],
                    'bonus_pdf_data'    => BonusReport::getColumns()['pdf_data']['function']
                ]
            ];
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-static-surface', $snapshot)
    ],

    '3002' => [
        'description' => 'Golden master: scheduled task seed and protected batch action surface.',
        'act'         => function () use($scheduled_controllers) {
            return [
                'task_seed'       => contractika_golden_task_seed(),
                'scheduled_files' => contractika_golden_action_surface($scheduled_controllers)
            ];
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-scheduled-surface', $snapshot)
    ]
];

