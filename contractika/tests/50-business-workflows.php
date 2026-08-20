<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\NAVLine;
use contractika\Report;
use contractika\SALine;

require_once(__DIR__ . '/lib/golden.php');

if(!function_exists('contractika_business_source')) {
    function contractika_business_source(string $path): string {
        return is_file($path) ? file_get_contents($path) : '';
    }
}

if(!function_exists('contractika_business_every_flag')) {
    function contractika_business_every_flag(array $value): bool {
        foreach($value as $item) {
            if(is_array($item)) {
                if(!contractika_business_every_flag($item)) {
                    return false;
                }
                continue;
            }
            if($item !== true) {
                return false;
            }
        }
        return true;
    }
}

$tests = [

    '5001' => [
        'description' => 'Golden master: report generation and release safeguards stay implemented.',
        'help'        => 'Reports must follow the documented eligibility and release workflow without involving external APIs.',
        'act'         => function () {
            $do_report = contractika_business_source('packages/contractika/actions/serviceaccount/do-report.php');
            $do_release = contractika_business_source('packages/contractika/actions/report/do-release.php');
            $report_cols = Report::getColumns();

            return [
                'report_generation' => [
                    'uses_reporting_setting'    => strpos($do_report, "Setting::get_value('contractika', 'ts_reporting', 'f_reporting', 'eurojob')") !== false,
                    'requires_eurojob_mode'     => strpos($do_report, "\$f_reporting == 'eurojob'") !== false,
                    'rejects_inactive_contract' => strpos($do_report, 'inactive_contract') !== false,
                    'rejects_missing_mode'      => strpos($do_report, 'missing_reporting_mode') !== false,
                    'rejects_incompatible_mode' => strpos($do_report, 'incompatible_reporting_mode') !== false,
                    'rejects_inactive_customer' => strpos($do_report, 'inactive_customer') !== false,
                    'rejects_missing_frequency' => strpos($do_report, 'missing_frequency') !== false,
                    'rejects_bad_frequency'     => strpos($do_report, 'incompatible_frequency') !== false,
                    'cleans_pending_report'     => strpos($do_report, "Report::search([['service_account_id', '=', \$params['id']], ['status', '=', 'pending']])->delete(true);") !== false,
                    'month_end_limit'           => strpos($do_report, 'Y-m-t 23:59:59') !== false,
                    'weekly_limit'              => strpos($do_report, '+6 day') !== false,
                    'uses_unlocked_lines'       => strpos($do_report, "['is_locked', '=', false]") !== false,
                    'uses_line_date_limit'      => strpos($do_report, "['date', '<=', \$date_to]") !== false,
                    'assigns_report_to_lines'   => strpos($do_report, "'report_id'     => \$report['id']") !== false
                ],
                'release' => [
                    'blocks_non_posted'       => strpos($do_release, 'has_non_posted') !== false,
                    'blocks_already_released' => strpos($do_release, 'already_released_report') !== false,
                    'sets_released_status'    => strpos($do_release, "['status' => 'released']") !== false,
                    'archives_empty_report'   => strpos($do_release, "['status' => 'archived']") !== false
                ],
                'report_columns' => [
                    'date_from_function'   => $report_cols['date_from']['function'] ?? null,
                    'is_sendable_function' => $report_cols['is_sendable']['function'] ?? null,
                    'status_onupdate'      => $report_cols['status']['onupdate'] ?? null
                ]
            ];
        },
        'assert'      => function ($data) {
            return (
                contractika_business_every_flag($data['report_generation'])
                && contractika_business_every_flag($data['release'])
                && (($data['report_columns']['date_from_function'] ?? null) === 'calcDateFrom')
                && (($data['report_columns']['is_sendable_function'] ?? null) === 'calcIsSendable')
                && (($data['report_columns']['status_onupdate'] ?? null) === 'onupdateStatus')
                && contractika_golden_assert('migration-report-business', $data)
            );
        }
    ],

    '5002' => [
        'description' => 'Golden master: NAV credit reconciliation has a deterministic mock payload.',
        'help'        => 'NAV credit behavior must be testable through a local mock payload instead of the Business Central import controller.',
        'act'         => function () {
            return [
                'nav_lines' => eQual::run('get', 'contractika_mock_payload', [
                    'provider' => 'bc',
                    'resource' => 'nav_lines'
                ])
            ];
        },
        'assert'      => fn($data) => contractika_golden_assert('migration-nav-mock', $data)
    ],

    '5003' => [
        'description' => 'Golden master: NAVLine reconciliation keeps the documented credit/correction workflow.',
        'help'        => 'A pending NAV line can reconcile only when references are resolved and it must create a credit or correction SA line.',
        'act'         => function () {
            $workflow = NAVLine::getWorkflow();
            $columns = NAVLine::getColumns();
            $source = contractika_business_source('packages/contractika/classes/NAVLine.class.php');
            $reconcile = $workflow['pending']['transitions']['reconcile'] ?? [];

            return [
                'columns' => [
                    'customer_id_function'        => $columns['customer_id']['function'] ?? null,
                    'service_account_id_function' => $columns['service_account_id']['function'] ?? null,
                    'has_error_function'          => $columns['has_error']['function'] ?? null,
                    'has_alert_function'          => $columns['has_alert']['function'] ?? null
                ],
                'workflow' => [
                    'pending_reconcile_status'  => $reconcile['status'] ?? null,
                    'pending_reconcile_onafter' => $reconcile['onafter'] ?? null,
                    'requires_service_account'  => in_array(['service_account_id', '>', 0], (array) ($reconcile['domain'] ?? []), true),
                    'requires_customer'         => in_array(['customer_id', '>', 0], (array) ($reconcile['domain'] ?? []), true),
                    'requires_no_error'         => in_array(['has_error', '=', false], (array) ($reconcile['domain'] ?? []), true),
                    'pending_ignore_status'     => $workflow['pending']['transitions']['ignore']['status'] ?? null,
                    'ignored_restore_status'    => $workflow['ignored']['transitions']['restore']['status'] ?? null,
                    'reconciled_is_terminal'    => empty($workflow['reconciled']['transitions'] ?? [])
                ],
                'reconciliation' => [
                    'creates_sa_line'          => strpos($source, 'SALine::create($values)') !== false,
                    'creates_credit_line'      => strpos($source, '$line_class_id = 3') !== false,
                    'creates_correction_line'  => strpos($source, '$line_class_id = 4') !== false,
                    'stores_sa_line_link'      => strpos($source, "'sa_line_id'") !== false,
                    'detects_alert_uom'        => strpos($source, 'has_alert_uom') !== false,
                    'detects_alert_unit_price' => strpos($source, 'has_alert_unit_price') !== false
                ]
            ];
        },
        'assert'      => function ($data) {
            return (
                (($data['columns']['customer_id_function'] ?? null) === 'calcCustomerId')
                && (($data['columns']['service_account_id_function'] ?? null) === 'calcServiceAccountId')
                && (($data['columns']['has_error_function'] ?? null) === 'calcHasError')
                && (($data['columns']['has_alert_function'] ?? null) === 'calcHasAlert')
                && (($data['workflow']['pending_reconcile_status'] ?? null) === 'reconciled')
                && (($data['workflow']['pending_reconcile_onafter'] ?? null) === 'doReconcile')
                && (($data['workflow']['requires_service_account'] ?? null) === true)
                && (($data['workflow']['requires_customer'] ?? null) === true)
                && (($data['workflow']['requires_no_error'] ?? null) === true)
                && (($data['workflow']['pending_ignore_status'] ?? null) === 'ignored')
                && (($data['workflow']['ignored_restore_status'] ?? null) === 'pending')
                && (($data['workflow']['reconciled_is_terminal'] ?? null) === true)
                && contractika_business_every_flag($data['reconciliation'])
                && contractika_golden_assert('migration-nav-business', $data)
            );
        }
    ],

    '5004' => [
        'description' => 'Golden master: Contractika alert controllers dispatch and cancel their alerts.',
        'help'        => 'Coherence checks must create an alert while data is invalid and cancel it once the retry succeeds.',
        'act'         => function () {
            $sources = [
                'customer_contacts' => contractika_business_source('packages/contractika/actions/customer/check-contacts.php'),
                'customer_nav'      => contractika_business_source('packages/contractika/actions/customer/check-nav.php'),
                'customer_identity' => contractika_business_source('packages/contractika/actions/customer/check-identity.php'),
                'service_company'   => contractika_business_source('packages/contractika/actions/serviceaccount/check-company.php'),
                'report_contacts'   => contractika_business_source('packages/contractika/actions/report/check-contacts.php'),
                'report_email'      => contractika_business_source('packages/contractika/actions/report/check-email.php')
            ];
            $alerts = [
                'customer_contacts' => 'contractika.customer.missing_contact',
                'customer_nav'      => 'contractika.customer.missing_nav_id',
                'customer_identity' => 'contractika.customer.missing_identity',
                'service_company'   => 'contractika.service_account.unknown_company',
                'report_contacts'   => 'contractika.report.missing_contact',
                'report_email'      => 'contractika.report.failed_email_sending'
            ];
            $snapshot = [];
            foreach($alerts as $key => $alert) {
                $source = $sources[$key] ?? '';
                $snapshot[$key] = [
                    'alert'    => $alert,
                    'dispatch' => strpos($source, "dispatch('{$alert}'") !== false,
                    'cancel'   => strpos($source, "cancel('{$alert}'") !== false
                ];
            }

            return $snapshot;
        },
        'assert'      => function ($snapshot) {
            foreach($snapshot as $alert) {
                if(($alert['dispatch'] ?? null) !== true || ($alert['cancel'] ?? null) !== true) {
                    return false;
                }
            }

            return contractika_golden_assert('migration-alert-business', $snapshot);
        }
    ],

    '5005' => [
        'description' => 'Golden master: point calculation keeps the documented coefficient inputs.',
        'help'        => 'The point formula must keep using duration, pause, calendar, service type, priority, travel and role factors.',
        'act'         => function () {
            $columns = SALine::getColumns();
            $source = contractika_business_source('packages/contractika/classes/SALine.class.php');

            return [
                'columns' => [
                    'points_function'      => $columns['points']['function'] ?? null,
                    'duration_function'    => $columns['duration']['function'] ?? null,
                    'pause_time_function'  => $columns['pause_time']['function'] ?? null,
                    'travel_time_function' => $columns['travel_time']['function'] ?? null
                ],
                'formula_inputs' => [
                    'duration'              => strpos($source, '$duration = $line[\'duration\'];') !== false,
                    'pause'                 => strpos($source, '$pause = $line[\'pause_time\'];') !== false,
                    'weekday'               => strpos($source, "date('N', \$line['date'])") !== false,
                    'holiday'               => strpos($source, 'Holiday::search') !== false,
                    'helpdesk'              => strpos($source, "if(\$line['helpdesk'])") !== false,
                    'standby'               => strpos($source, "if(\$line['standby'])") !== false,
                    'priority'              => strpos($source, "switch(\$line['priority'])") !== false,
                    'coef_limit'            => strpos($source, "if(\$coef > \$coef_limit)") !== false,
                    'on_site'               => strpos($source, "if(\$line['on_site'])") !== false,
                    'role_hourly_factor'    => strpos($source, "\$line['role_id']['hourly_factor']") !== false,
                    'quarter_hour_rounding' => strpos($source, 'round($time / (15 * 60), 2)') !== false
                ]
            ];
        },
        'assert'      => function ($data) {
            return (
                (($data['columns']['points_function'] ?? null) === 'calcPoints')
                && (($data['columns']['duration_function'] ?? null) === 'calcDuration')
                && (($data['columns']['pause_time_function'] ?? null) === 'calcPauseTime')
                && (($data['columns']['travel_time_function'] ?? null) === 'calcTravelTime')
                && contractika_business_every_flag($data['formula_inputs'])
                && contractika_golden_assert('migration-saline-business', $data)
            );
        }
    ]

];
