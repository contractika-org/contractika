<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\NAVLine;
use contractika\Report;
use contractika\SALine;

if(!function_exists('contractika_business_source')) {
    function contractika_business_source(string $path): string {
        return is_file($path) ? file_get_contents($path) : '';
    }
}

$tests = [

    '5001' => [
        'description' => 'Documented report generation and release safeguards stay implemented.',
        'help'        => 'Reports must follow the documented eligibility and release workflow without involving external APIs.',
        'act'         => function () {
            return [
                'do_report'   => contractika_business_source('packages/contractika/actions/serviceaccount/do-report.php'),
                'do_release'  => contractika_business_source('packages/contractika/actions/report/do-release.php'),
                'report_cols' => Report::getColumns()
            ];
        },
        'assert'      => function ($data) {
            $do_report = $data['do_report'];
            $do_release = $data['do_release'];
            $report_cols = $data['report_cols'];

            return (
                strpos($do_report, "Setting::get_value('contractika', 'ts_reporting', 'f_reporting', 'eurojob')") !== false
                && strpos($do_report, "\$f_reporting == 'eurojob'") !== false
                && strpos($do_report, "inactive_contract") !== false
                && strpos($do_report, "missing_reporting_mode") !== false
                && strpos($do_report, "incompatible_reporting_mode") !== false
                && strpos($do_report, "inactive_customer") !== false
                && strpos($do_report, "missing_frequency") !== false
                && strpos($do_report, "incompatible_frequency") !== false
                && strpos($do_report, "Report::search([['service_account_id', '=', \$params['id']], ['status', '=', 'pending']])->delete(true);") !== false
                && strpos($do_report, 'Y-m-t 23:59:59') !== false
                && strpos($do_report, '+6 day') !== false
                && strpos($do_report, "['is_locked', '=', false]") !== false
                && strpos($do_report, "['date', '<=', \$date_to]") !== false
                && strpos($do_report, "'report_id'     => \$report['id']") !== false
                && strpos($do_release, 'has_non_posted') !== false
                && strpos($do_release, 'already_released_report') !== false
                && strpos($do_release, "['status' => 'released']") !== false
                && strpos($do_release, "['status' => 'archived']") !== false
                && (($report_cols['date_from']['function'] ?? null) === 'calcDateFrom')
                && (($report_cols['is_sendable']['function'] ?? null) === 'calcIsSendable')
                && (($report_cols['status']['onupdate'] ?? null) === 'onupdateStatus')
            );
        }
    ],

    '5002' => [
        'description' => 'NAV credit reconciliation has a deterministic mock payload.',
        'help'        => 'NAV credit behavior must be testable through a local mock payload instead of the Business Central import controller.',
        'act'         => function () {
            return [
                'nav_lines' => eQual::run('get', 'contractika_mock_payload', [
                    'provider' => 'bc',
                    'resource' => 'nav_lines'
                ])
            ];
        },
        'assert'      => function ($data) {
            if(!is_array($data['nav_lines']) || count($data['nav_lines']) !== 1) {
                return false;
            }

            $line = reset($data['nav_lines']);
            foreach(['extref_document_no', 'extref_line_uuid', 'extref_customer', 'extref_description2', 'extref_uom_code', 'extref_unit_price', 'extref_quantity', 'extref_amount', 'points'] as $field) {
                if(!array_key_exists($field, $line)) {
                    return false;
                }
            }

            return (
                ($line['extref_customer'] ?? null) === 'C-MOCK-001'
                && ($line['extref_description2'] ?? null) === '#AT-29683374#'
                && ($line['extref_uom_code'] ?? null) === 'PNT'
                && (float) ($line['extref_unit_price'] ?? 0) === 20.64
                && (float) ($line['points'] ?? 0) === 10.0
            );
        }
    ],

    '5003' => [
        'description' => 'NAVLine reconciliation keeps the documented credit/correction workflow.',
        'help'        => 'A pending NAV line can reconcile only when references are resolved and it must create a credit or correction SA line.',
        'act'         => function () {
            return [
                'workflow' => NAVLine::getWorkflow(),
                'columns'  => NAVLine::getColumns(),
                'source'   => contractika_business_source('packages/contractika/classes/NAVLine.class.php')
            ];
        },
        'assert'      => function ($data) {
            $reconcile = $data['workflow']['pending']['transitions']['reconcile'] ?? [];
            $source = $data['source'];

            return (
                (($data['columns']['customer_id']['function'] ?? null) === 'calcCustomerId')
                && (($data['columns']['service_account_id']['function'] ?? null) === 'calcServiceAccountId')
                && (($data['columns']['has_error']['function'] ?? null) === 'calcHasError')
                && (($data['columns']['has_alert']['function'] ?? null) === 'calcHasAlert')
                && (($reconcile['status'] ?? null) === 'reconciled')
                && (($reconcile['onafter'] ?? null) === 'doReconcile')
                && in_array(['service_account_id', '>', 0], (array) ($reconcile['domain'] ?? []), true)
                && in_array(['customer_id', '>', 0], (array) ($reconcile['domain'] ?? []), true)
                && in_array(['has_error', '=', false], (array) ($reconcile['domain'] ?? []), true)
                && strpos($source, 'SALine::create($values)') !== false
                && strpos($source, '$line_class_id = 3') !== false
                && strpos($source, '$line_class_id = 4') !== false
                && strpos($source, "'sa_line_id'") !== false
                && strpos($source, "has_alert_uom") !== false
                && strpos($source, "has_alert_unit_price") !== false
            );
        }
    ],

    '5004' => [
        'description' => 'Documented Contractika alert controllers dispatch and cancel their alerts.',
        'help'        => 'Coherence checks must create an alert while data is invalid and cancel it once the retry succeeds.',
        'act'         => function () {
            return [
                'customer_contacts' => contractika_business_source('packages/contractika/actions/customer/check-contacts.php'),
                'customer_nav'      => contractika_business_source('packages/contractika/actions/customer/check-nav.php'),
                'customer_identity' => contractika_business_source('packages/contractika/actions/customer/check-identity.php'),
                'service_company'   => contractika_business_source('packages/contractika/actions/serviceaccount/check-company.php'),
                'report_contacts'   => contractika_business_source('packages/contractika/actions/report/check-contacts.php'),
                'report_email'      => contractika_business_source('packages/contractika/actions/report/check-email.php')
            ];
        },
        'assert'      => function ($sources) {
            $alerts = [
                'customer_contacts' => 'contractika.customer.missing_contact',
                'customer_nav'      => 'contractika.customer.missing_nav_id',
                'customer_identity' => 'contractika.customer.missing_identity',
                'service_company'   => 'contractika.service_account.unknown_company',
                'report_contacts'   => 'contractika.report.missing_contact',
                'report_email'      => 'contractika.report.failed_email_sending'
            ];

            foreach($alerts as $key => $alert) {
                $source = $sources[$key] ?? '';
                if(strpos($source, "dispatch('{$alert}'") === false || strpos($source, "cancel('{$alert}'") === false) {
                    return false;
                }
            }

            return true;
        }
    ],

    '5005' => [
        'description' => 'Point calculation keeps the documented coefficient inputs.',
        'help'        => 'The point formula must keep using duration, pause, calendar, service type, priority, travel and role factors.',
        'act'         => function () {
            return [
                'columns' => SALine::getColumns(),
                'source'  => contractika_business_source('packages/contractika/classes/SALine.class.php')
            ];
        },
        'assert'      => function ($data) {
            $source = $data['source'];
            return (
                (($data['columns']['points']['function'] ?? null) === 'calcPoints')
                && (($data['columns']['duration']['function'] ?? null) === 'calcDuration')
                && (($data['columns']['pause_time']['function'] ?? null) === 'calcPauseTime')
                && (($data['columns']['travel_time']['function'] ?? null) === 'calcTravelTime')
                && strpos($source, '$duration = $line[\'duration\'];') !== false
                && strpos($source, '$pause = $line[\'pause_time\'];') !== false
                && strpos($source, "date('N', \$line['date'])") !== false
                && strpos($source, "Holiday::search") !== false
                && strpos($source, "if(\$line['helpdesk'])") !== false
                && strpos($source, "if(\$line['standby'])") !== false
                && strpos($source, "switch(\$line['priority'])") !== false
                && strpos($source, "if(\$coef > \$coef_limit)") !== false
                && strpos($source, "if(\$line['on_site'])") !== false
                && strpos($source, "\$line['role_id']['hourly_factor']") !== false
                && strpos($source, 'round($time / (15 * 60), 2)') !== false
            );
        }
    ]

];
