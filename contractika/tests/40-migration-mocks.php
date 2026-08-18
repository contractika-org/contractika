<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

require_once(__DIR__ . '/lib/golden.php');

$tests = [
    '4001' => [
        'description' => 'Golden master: deterministic external payloads used for migration regression.',
        'act'         => function () {
            $resources = [
                'at' => [
                    'billingcode',
                    'billingitems',
                    'contacts',
                    'contracts',
                    'customers',
                    'holidays',
                    'projects',
                    'resources',
                    'roles',
                    'tasks',
                    'tickets',
                    'timeentries'
                ],
                'bc' => [
                    'customers',
                    'nav_lines',
                    'payment_terms'
                ],
                'sd' => [
                    'absences',
                    'employees'
                ]
            ];

            $snapshot = [];
            foreach($resources as $provider => $provider_resources) {
                foreach($provider_resources as $resource) {
                    $payload = eQual::run('get', 'contractika_mock_payload', [
                        'provider' => $provider,
                        'resource' => $resource
                    ]);
                    $snapshot[$provider][$resource] = $payload;
                }
            }

            $snapshot['empty_timeentries'] = eQual::run('get', 'contractika_mock_payload', [
                'provider' => 'at',
                'resource' => 'timeentries',
                'scenario' => 'empty'
            ]);

            return $snapshot;
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-external-mocks', $snapshot)
    ],

    '4002' => [
        'description' => 'Golden master: deterministic outbound payload acknowledgements.',
        'act'         => function () {
            return [
                'at_update_contract' => eQual::run('do', 'contractika_mock_send', [
                    'provider'  => 'at',
                    'resource'  => 'contracts',
                    'operation' => 'patch',
                    'payload'   => [
                        'id'      => 29683374,
                        'Balance' => 42.5
                    ]
                ])['result'],
                'at_create_appointment' => eQual::run('do', 'contractika_mock_send', [
                    'provider'  => 'at',
                    'resource'  => 'appointments',
                    'operation' => 'create',
                    'payload'   => [
                        'resource_id'   => 29682899,
                        'datetime_from' => 1770019200,
                        'datetime_to'   => 1770053400,
                        'title'         => 'SDworx ~ Holiday ~ approved',
                        'description'   => 'SD-MOCK-ABS-001'
                    ]
                ])['result'],
                'bc_credit_line' => eQual::run('do', 'contractika_mock_send', [
                    'provider'  => 'ms_dynamics',
                    'resource'  => 'salesCreditMemoLines',
                    'operation' => 'read',
                    'payload'   => [
                        [
                            'documentNo' => 'CM-MOCK-001',
                            'quantity'   => 2
                        ]
                    ]
                ])['result']
            ];
        },
        'assert'      => fn($snapshot) => contractika_golden_assert('migration-external-sends', $snapshot)
    ]
];

