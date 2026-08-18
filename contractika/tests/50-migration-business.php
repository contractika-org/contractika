<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\NAVLine;
use contractika\Report;
use contractika\SALine;
use contractika\SALineType;
use contractika\ServiceAccount;
use contractika\identity\Identity;
use contractika\sale\customer\Customer;
use equal\orm\ObjectManager;

require_once(__DIR__ . '/lib/golden.php');

function contractika_migration_cleanup(): void {
    foreach([
        fn() => NAVLine::search(['extref_document_no', 'like', 'GM-%'])->delete(true),
        fn() => Report::search([['service_account_id', 'in', ServiceAccount::search(['contractId', '>=', 990000000])->ids()], ['status', '=', 'pending']])->delete(true),
        fn() => SALine::search([['timeEntryID', '>=', 990000000], ['is_locked', '=', false]])->delete(true),
        fn() => SALine::search([['description', 'like', 'GM-%'], ['is_locked', '=', false]])->delete(true),
        fn() => ServiceAccount::search([['contractId', '>=', 990000000], ['name', '=', 'GM Service Package']])->delete(true),
        fn() => Customer::search(['extref_at_id', '>=', 990000000])->delete(true),
        fn() => SALineType::search(['extref_at_id', '>=', 990000000])->delete(true),
        fn() => Identity::search(['lastname', 'like', 'Goldenmaster%'])->delete(true)
    ] as $cleanup) {
        try {
            $cleanup();
        }
        catch(Exception $e) {
            // Stale migration fixtures may be intentionally non-removable through ORM guards.
        }
    }

    $providers = eQual::inject(['access']);
    $providers['access']->revoke(QN_R_CREATE | QN_R_READ | QN_R_WRITE | QN_R_DELETE);
}

function contractika_migration_fixture(): array {
    $providers = eQual::inject(['access']);
    $providers['access']->grant(QN_R_CREATE | QN_R_READ | QN_R_WRITE | QN_R_DELETE);

    contractika_migration_cleanup();

    $providers['access']->grant(QN_R_CREATE | QN_R_READ | QN_R_WRITE | QN_R_DELETE);

    $extref = 990000000 + random_int(100000, 999999);

    $identity = Identity::create([
            'firstname'    => 'Migration',
            'lastname'     => 'Goldenmaster Customer',
            'extref_at_id' => $extref
        ])
        ->read(['id'])
        ->first();

    $customer = Customer::create([
            'partner_identity_id' => $identity['id'],
            'extref_at_id'        => $extref,
            'extref_nav_id'       => 'GM-CUST-001',
            'service_price'       => 20.64,
            'companyType'         => 1,
            'is_active'           => true,
            'has_d_travel'        => true,
            'd_travel'            => 1.0
        ])
        ->read(['id'])
        ->first();

    $type = SALineType::create([
            'name'           => 'GM Helpdesk',
            'externalNumber' => 'GM-HD',
            'extref_at_id'   => $extref
        ])
        ->read(['id'])
        ->first();

    $account = ServiceAccount::create([
            'name'          => 'GM Service Package',
            'customer_id'   => $customer['id'],
            'contractId'    => $extref,
            'companyID'     => $extref,
            'is_active'     => true,
            'm_reporting'   => 'Send',
            'renew_auto'    => true,
            'renew_floor'   => 10.0,
            'reporting_from'=> strtotime('2026-01-01')
        ])
        ->read(['id'])
        ->first();

    return [
        'identity_id' => $identity['id'],
        'customer_id' => $customer['id'],
        'type_id'     => $type['id'],
        'account_id'  => $account['id'],
        'contract_id' => $extref
    ];
}

function contractika_migration_read(string $class, int $id, array $fields): array {
    $om = ObjectManager::getInstance();
    $rows = $om->read($class, [$id], $fields);
    $row = $rows[$id] ?? reset($rows) ?: [];
    return contractika_migration_to_array($row);
}

function contractika_migration_to_array($value) {
    if(is_object($value) && method_exists($value, 'toArray')) {
        $value = $value->toArray();
    }
    if(is_array($value)) {
        foreach($value as $key => $item) {
            $value[$key] = contractika_migration_to_array($item);
        }
    }
    return $value;
}

$tests = [
    '5001' => [
        'description' => 'Golden master: SALine calculations and locked-line guards.',
        'arrange'     => fn() => contractika_migration_fixture(),
        'act'         => function ($fixture) {
            $line_collection = SALine::create([
                    'service_account_id' => $fixture['account_id'],
                    'customer_id'         => $fixture['customer_id'],
                    'timeEntryID'         => 990000001,
                    'description'         => 'GM-ticket-line',
                    'date'                => strtotime('2026-01-15'),
                    'start'               => strtotime('2026-01-15 09:07:00'),
                    'end'                 => strtotime('2026-01-15 10:02:00'),
                    'pause'               => -0.25,
                    'createDateTime'      => strtotime('2026-01-15 12:00:00'),
                    'ticketID'            => 990001,
                    'has_ticket'          => true,
                    'sa_line_class_id'    => 2,
                    'sa_line_type_id'     => $fixture['type_id'],
                    'ticketCategory'      => 108,
                    'priority'            => 2,
                    'points'              => null
                ]);
            $line_id = reset($line_collection->ids());
            $line = contractika_migration_read(SALine::getType(), $line_id, [
                    'timeEntryID',
                    'pause_time',
                    'delta_time',
                    'duration',
                    'procrastin',
                    'travel_time',
                    'on_site',
                    'helpdesk',
                    'standby',
                    'ticketLink'
                ]);

            return [
                'line' => $line,
                'guards' => [
                    'locked_update_guard' => method_exists(SALine::getType(), 'canupdate'),
                    'locked_delete_guard' => method_exists(SALine::getType(), 'candelete')
                ]
            ];
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-saline-business', $snapshot),
        'rollback'    => fn() => contractika_migration_cleanup()
    ],

    '5002' => [
        'description' => 'Golden master: report totals, balance and release locking.',
        'arrange'     => fn() => contractika_migration_fixture(),
        'act'         => function ($fixture) {
            $report_collection = Report::create([
                    'date'               => strtotime('2026-01-31'),
                    'service_account_id' => $fixture['account_id']
                ]);
            $report_id = reset($report_collection->ids());

            SALine::create([
                'service_account_id' => $fixture['account_id'],
                'customer_id'         => $fixture['customer_id'],
                'timeEntryID'         => 990000011,
                'description'         => 'GM-report-ticket',
                'date'                => strtotime('2026-01-15'),
                'has_ticket'          => true,
                'sa_line_class_id'    => 2,
                'points'              => 4.0,
                'is_posted'           => false,
                'report_id'           => $report_id
            ]);
            SALine::create([
                'service_account_id' => $fixture['account_id'],
                'customer_id'         => $fixture['customer_id'],
                'description'         => 'GM-report-credit',
                'date'                => strtotime('2026-01-20'),
                'sa_line_class_id'    => 3,
                'points'              => 12.5,
                'report_id'           => $report_id
            ]);

            Report::id($report_id)
                ->update([
                    'total_points'   => null,
                    'total_credits'  => null,
                    'balance_old'    => null,
                    'balance_new'    => null,
                    'has_non_posted' => null,
                    'is_empty'       => null
                ]);
            $before = contractika_migration_read(Report::getType(), $report_id, ['status', 'total_points', 'total_credits', 'balance_old', 'balance_new', 'has_non_posted', 'is_empty']);

            return [
                'before_release' => $before,
                'release_guard' => [
                    'status_callback' => Report::getColumns()['status']['onupdate'],
                    'expected_lock_field' => 'is_locked'
                ]
            ];
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-report-business', $snapshot),
        'rollback'    => fn() => contractika_migration_cleanup()
    ],

    '5003' => [
        'description' => 'Golden master: NAVLine errors, alerts and reconciliation workflow.',
        'arrange'     => fn() => contractika_migration_fixture(),
        'act'         => function ($fixture) {
            try {
                $nav_collection = NAVLine::create([
                    'extref_document_no'  => 'GM-INV-001',
                    'extref_line_uuid'    => 'GM-LINE-001',
                    'extref_customer'     => 'GM-CUST-001',
                    'extref_no'           => 'NTK-SERVICEPACKAGE',
                    'extref_description2' => '#AT-' . $fixture['contract_id'] . '#',
                    'extref_uom_code'     => 'PNT',
                    'extref_unit_price'   => '20.64',
                    'extref_quantity'     => '8',
                    'extref_amount'       => '165.12',
                    'description'         => 'GM-NAV-credit',
                    'date'                => strtotime('2026-01-31'),
                    'points'              => 8.0
                ]);
            }
            catch(Exception $e) {
                return [
                    'creation_error' => [
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage()
                    ],
                    'workflow' => NAVLine::getWorkflow()
                ];
            }

            $nav_id = reset($nav_collection->ids());
            $nav = contractika_migration_read(NAVLine::getType(), $nav_id, ['customer_id', 'service_account_id', 'has_alert', 'has_error', 'status']);

            $om = ObjectManager::getInstance();
            $transition_errors = $om->transition(NAVLine::getType(), [$nav_id], 'reconcile');

            $reconciled = contractika_migration_read(NAVLine::getType(), $nav_id, ['status', 'has_error', 'has_alert', 'sa_line_id' => ['description', 'sa_line_class_id', 'points']]);

            $alert_collection = NAVLine::create([
                    'extref_document_no'  => 'GM-INV-002',
                    'extref_line_uuid'    => 'GM-LINE-002',
                    'extref_customer'     => 'GM-CUST-001',
                    'extref_description2' => '#AT-' . $fixture['contract_id'] . '#',
                    'extref_uom_code'     => 'PCS',
                    'extref_unit_price'   => '25.00',
                    'description'         => 'GM-NAV-alert',
                    'date'                => strtotime('2026-01-31'),
                    'points'              => -2.0
                ]);
            $alert_id = reset($alert_collection->ids());
            $alert = contractika_migration_read(NAVLine::getType(), $alert_id, ['customer_id', 'service_account_id', 'has_alert_uom', 'has_alert_unit_price', 'has_alert', 'has_error']);

            return [
                'candidate'         => $nav,
                'transition_errors' => $transition_errors,
                'reconciled'        => $reconciled,
                'alert'             => $alert
            ];
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-nav-business', $snapshot),
        'rollback'    => fn() => contractika_migration_cleanup()
    ]
];
