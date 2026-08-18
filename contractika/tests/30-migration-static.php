<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

require_once(__DIR__ . '/lib/golden.php');

function contractika_migration_entrypoints(): array {
    return [
        ['do', 'contractika_sync-customers'],
        ['do', 'contractika_pull-contracts'],
        ['do', 'contractika_sync-timeentries'],
        ['do', 'contractika_calc-points'],
        ['do', 'contractika_sync-deletedentries'],
        ['do', 'contractika_pull-credits'],
        ['do', 'contractika_reconcile-credits'],
        ['do', 'contractika_push-balances'],
        ['do', 'contractika_sync-employees'],
        ['do', 'contractika_sync-absences'],
        ['do', 'contractika_sync-holidays'],
        ['do', 'contractika_sync-dashboard'],
        ['do', 'contractika_serviceaccount_batch-reports'],
        ['do', 'contractika_cutoffreport_batch-create-recap'],
        ['do', 'contractika_employees_collect']
    ];
}

function contractika_migration_scheduled_contracts(): array {
    return [
        'contractika_pull-customers'               => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_pull-contracts'               => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_sync-timeentries'             => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_serviceaccount_batch-reports' => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'month', 'repeat_step' => 1],
        'contractika_calc-points'                  => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_sync-deletedentries'          => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_pull-credits'                 => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_reconcile-credits'            => ['is_active' => true,  'is_recurring' => true,  'repeat_axis' => 'day',   'repeat_step' => 1],
        'contractika_reset-points'                 => ['is_active' => false, 'is_recurring' => false, 'repeat_axis' => 'week',  'repeat_step' => 4]
    ];
}

function contractika_migration_param_contracts(): array {
    return [
        ['get', 'contractika_at_customers',          ['date_from']],
        ['get', 'contractika_at_contacts',           ['date_from', 'ids']],
        ['get', 'contractika_at_contracts',          []],
        ['get', 'contractika_at_timeentries',        ['date_field', 'date_from', 'fields', 'ids']],
        ['get', 'contractika_at_tickets',            ['date_from', 'ids']],
        ['get', 'contractika_at_tasks',              ['date_from', 'ids']],
        ['get', 'contractika_at_projects',           ['ids']],
        ['get', 'contractika_at_billingitems',       ['date_from']],
        ['get', 'contractika_at_resources',          []],
        ['get', 'contractika_at_resource',           ['id']],
        ['get', 'contractika_at_roles',              []],
        ['get', 'contractika_at_holidays',           []],
        ['get', 'contractika_bc_token',              []],
        ['get', 'contractika_bc_customers',          ['date_from']],
        ['get', 'contractika_sd_employees',          []],
        ['get', 'contractika_sd_absences',           ['future_months', 'id', 'last_run', 'passed_months']],
        ['get', 'contractika_dash_absence',          ['id']],
        ['do',  'contractika_at_update-contract',    ['balance', 'id']],
        ['do',  'contractika_at_update-company',     ['id']],
        ['do',  'contractika_at_create-ticket',      ['description', 'title']],
        ['do',  'contractika_at_create-appointment', ['datetime_from', 'datetime_to', 'description', 'resource_id', 'title']],
        ['do',  'contractika_at_update-appointment', ['datetime_from', 'datetime_to', 'description', 'id', 'resource_id', 'title']],
        ['do',  'contractika_at_delete-appointment', ['id']],
        ['do',  'contractika_mock_send',             ['operation', 'payload', 'provider', 'resource']],
        ['get', 'contractika_mock_payload',          ['envelope', 'limit', 'provider', 'resource', 'scenario']]
    ];
}

function contractika_migration_surface_is_valid(array $surface): bool {
    if(!$surface['file_exists'] || !$surface['has_announce'] || !$surface['has_description']) {
        return false;
    }

    if($surface['visibility'] !== 'protected') {
        return false;
    }

    foreach($surface['calls'] as $call) {
        if(!$call['resolves']) {
            return false;
        }
    }

    return true;
}

$tests = [
    '3001' => [
        'description' => 'Contractika migration entrypoints and transitive controllers remain available.',
        'help'        => 'Sync and batch operations may change internally, but every Contractika controller they call must still resolve and expose a protected announce contract.',
        'act'         => fn() => contractika_golden_operation_graph(contractika_migration_entrypoints()),
        'assert'      => function ($graph) {
            foreach($graph as $surface) {
                if(!contractika_migration_surface_is_valid($surface)) {
                    return false;
                }
            }
            return true;
        }
    ],

    '3002' => [
        'description' => 'Scheduled Contractika tasks keep resolving to callable action controllers.',
        'help'        => 'The task seed must keep the required migration jobs active/inactive as expected and point only to existing action controllers.',
        'act'         => function () {
            $tasks = contractika_golden_task_seed();
            $controllers = [];
            foreach($tasks as $task) {
                $controllers[$task['controller']] = contractika_golden_controller_surface('do', $task['controller']);
            }
            return [
                'tasks'       => $tasks,
                'controllers' => $controllers
            ];
        },
        'assert'      => function ($data) {
            $tasks = [];
            foreach($data['tasks'] as $task) {
                $tasks[$task['controller']] = $task;
            }

            foreach(contractika_migration_scheduled_contracts() as $controller => $expected) {
                if(!isset($tasks[$controller])) {
                    return false;
                }
                foreach($expected as $key => $value) {
                    if(($tasks[$controller][$key] ?? null) !== $value) {
                        return false;
                    }
                }
            }

            foreach($data['controllers'] as $surface) {
                if(!contractika_migration_surface_is_valid($surface)) {
                    return false;
                }
            }

            return true;
        }
    ],

    '3003' => [
        'description' => 'Provider and outbound controller parameter names used by migration flows stay compatible.',
        'help'        => 'Actions can be refactored, but the /data and outbound /actions contracts they rely on must keep accepting the same parameters.',
        'act'         => function () {
            $surfaces = [];
            foreach(contractika_migration_param_contracts() as [$type, $controller, $params]) {
                $surfaces[$type . ':' . $controller] = contractika_golden_controller_surface($type, $controller);
            }
            return $surfaces;
        },
        'assert'      => function ($surfaces) {
            foreach(contractika_migration_param_contracts() as [$type, $controller, $params]) {
                $surface = $surfaces[$type . ':' . $controller] ?? null;
                if(!$surface || !contractika_migration_surface_is_valid($surface)) {
                    return false;
                }
                foreach($params as $param) {
                    if(!in_array($param, $surface['params'], true)) {
                        return false;
                    }
                }
            }
            return true;
        }
    ]
];
