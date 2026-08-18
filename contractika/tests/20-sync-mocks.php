<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

$tests = [

    '2001' => [
        'description' => 'Fetch mock Autotask customers payload.',
        'help'        => 'The AT mock customer payload must keep the fields used by pull-customers.',
        'act'         => function () {
            return eQual::run('get', 'contractika_mock_payload', [
                'provider' => 'at',
                'resource' => 'customers'
            ]);
        },
        'assert'      => function ($payload) {
            if(!is_array($payload) || count($payload) !== 1) {
                return false;
            }

            $customer = reset($payload);
            $udf_names = array_column($customer['userDefinedFields'] ?? [], 'name');

            return (
                ($customer['id'] ?? 0) > 0
                && ($customer['companyType'] ?? null) === 1
                && in_array('NAV ID', $udf_names, true)
                && in_array('Service Price', $udf_names, true)
                && in_array('Payment Terms', $udf_names, true)
            );
        }
    ],

    '2002' => [
        'description' => 'Fetch mock SDworx employees payload.',
        'help'        => 'The SD mock employee payload must keep the fields used by pull-employees.',
        'act'         => function () {
            return eQual::run('get', 'contractika_mock_payload', [
                'provider' => 'sd',
                'resource' => 'employees'
            ]);
        },
        'assert'      => function ($payload) {
            if(!is_array($payload) || count($payload) !== 1) {
                return false;
            }

            $employee = reset($payload);

            return (
                strlen((string) ($employee['EmployerNumber'] ?? '')) > 0
                && strlen((string) ($employee['EmployeeNumber'] ?? '')) > 0
                && strlen((string) ($employee['FirstName'] ?? '')) > 0
                && strlen((string) ($employee['LastName'] ?? '')) > 0
                && strlen((string) ($employee['StartDate'] ?? '')) > 0
                && array_key_exists('EndDate', $employee)
            );
        }
    ],

    '2003' => [
        'description' => 'Fetch mock MS Dynamics customers payload.',
        'help'        => 'The Dynamics/BC mock customer payload must keep the normalized fields used by customer sync.',
        'act'         => function () {
            return eQual::run('get', 'contractika_mock_payload', [
                'provider' => 'ms_dynamics',
                'resource' => 'customers'
            ]);
        },
        'assert'      => function ($payload) {
            if(!is_array($payload) || count($payload) !== 1) {
                return false;
            }

            $customer = reset($payload);

            return (
                strlen((string) ($customer['Id'] ?? '')) > 0
                && strlen((string) ($customer['Name'] ?? '')) > 0
                && array_key_exists('Blocked', $customer)
                && array_key_exists('Vat', $customer)
                && array_key_exists('Discount', $customer)
                && array_key_exists('ServicePrice', $customer)
                && array_key_exists('PaymentTermsCode', $customer)
            );
        }
    ],

    '2004' => [
        'description' => 'Send mock payload without external API call.',
        'help'        => 'The send mock must return a deterministic HTTP 200 acknowledgement.',
        'act'         => function () {
            return eQual::run('do', 'contractika_mock_send', [
                'provider'  => 'at',
                'resource'  => 'companies',
                'operation' => 'patch',
                'payload'   => [
                    [
                        'id'       => 486001,
                        'isActive' => true
                    ]
                ]
            ]);
        },
        'assert'      => function ($payload) {
            $result = $payload['result'] ?? [];

            return (
                ($result['accepted'] ?? false) === true
                && ($result['provider'] ?? null) === 'at'
                && ($result['resource'] ?? null) === 'companies'
                && ($result['operation'] ?? null) === 'patch'
                && ($result['status_code'] ?? null) === 200
                && ($result['item_count'] ?? null) === 1
            );
        }
    ],

    '2005' => [
        'description' => 'Fetch empty mock scenario.',
        'help'        => 'The empty scenario must simulate a successful API call with no rows.',
        'act'         => function () {
            return eQual::run('get', 'contractika_mock_payload', [
                'provider' => 'at',
                'resource' => 'timeentries',
                'scenario' => 'empty'
            ]);
        },
        'assert'      => function ($payload) {
            return is_array($payload) && count($payload) === 0;
        }
    ],

    '2006' => [
        'description' => 'Fetch mock NAV line payload for credit reconciliation.',
        'help'        => 'NAV imports must be testable through deterministic Business Central lines without calling the real API.',
        'act'         => function () {
            return eQual::run('get', 'contractika_mock_payload', [
                'provider' => 'bc',
                'resource' => 'nav_lines'
            ]);
        },
        'assert'      => function ($payload) {
            if(!is_array($payload) || count($payload) !== 1) {
                return false;
            }

            $line = reset($payload);

            return (
                ($line['extref_document_no'] ?? null) === 'INV-MOCK-001'
                && ($line['extref_customer'] ?? null) === 'C-MOCK-001'
                && ($line['extref_description2'] ?? null) === '#AT-29683374#'
                && ($line['extref_uom_code'] ?? null) === 'PNT'
                && (float) ($line['extref_unit_price'] ?? 0) === 20.64
                && (float) ($line['points'] ?? 0) === 10.0
            );
        }
    ]

];
