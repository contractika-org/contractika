<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\NAVLine;
use contractika\Report;
use contractika\SACategory;
use contractika\SALine;
use contractika\SAType;
use contractika\ServiceAccount;

$tests = [

    '1001' => [
        'description' => 'Check Contractika custom model tables.',
        'help'        => 'Service account related models must use Contractika tables, not inherited Sale tables.',
        'act'         => function () {
            return [
                'service_account' => (new ServiceAccount())->getTable(),
                'sa_category'     => (new SACategory())->getTable(),
                'sa_type'         => (new SAType())->getTable()
            ];
        },
        'assert'      => function ($tables) {
            return $tables === [
                'service_account' => 'contractika_serviceaccount',
                'sa_category'     => 'contractika_sacategory',
                'sa_type'         => 'contractika_satype'
            ];
        }
    ],

    '1002' => [
        'description' => 'Check ServiceAccount status and AT references.',
        'help'        => 'Synced service accounts must stay signed and expose AutoTask references consistently.',
        'act'         => function () {
            $columns = ServiceAccount::getColumns();

            return [
                'status'       => $columns['status'] ?? [],
                'contractId'   => $columns['contractId'] ?? [],
                'contractLink' => $columns['contractLink'] ?? [],
                'extref_at_id' => $columns['extref_at_id'] ?? []
            ];
        },
        'assert'      => function ($columns) {
            return (
                ($columns['status']['type'] ?? null) === 'string'
                && ($columns['status']['default'] ?? null) === 'signed'
                && ($columns['status']['selection'] ?? []) === ['signed']
                && ($columns['contractId']['type'] ?? null) === 'integer'
                && ($columns['contractId']['unique'] ?? false) === true
                && in_array('extref_at_id', (array) ($columns['contractId']['dependencies'] ?? []), true)
                && ($columns['contractLink']['type'] ?? null) === 'computed'
                && ($columns['contractLink']['function'] ?? null) === 'calcContractLink'
                && ($columns['extref_at_id']['type'] ?? null) === 'computed'
                && ($columns['extref_at_id']['result_type'] ?? null) === 'integer'
                && ($columns['extref_at_id']['function'] ?? null) === 'calcExtrefAtId'
                && ($columns['extref_at_id']['store'] ?? false) === true
            );
        }
    ],

    '1003' => [
        'description' => 'Check SALine computed fields and callbacks.',
        'help'        => 'Time, points and report fields must keep the callbacks used by reporting and balances.',
        'act'         => function () {
            $columns = SALine::getColumns();

            return [
                'pause_time' => $columns['pause_time'] ?? [],
                'duration'   => $columns['duration'] ?? [],
                'procrastin' => $columns['procrastin'] ?? [],
                'points'     => $columns['points'] ?? [],
                'is_locked'  => $columns['is_locked'] ?? [],
                'report_id'  => $columns['report_id'] ?? []
            ];
        },
        'assert'      => function ($columns) {
            return (
                ($columns['pause_time']['type'] ?? null) === 'computed'
                && ($columns['pause_time']['result_type'] ?? null) === 'time'
                && ($columns['pause_time']['function'] ?? null) === 'calcPauseTime'
                && ($columns['pause_time']['store'] ?? false) === true
                && ($columns['duration']['type'] ?? null) === 'computed'
                && ($columns['duration']['result_type'] ?? null) === 'time'
                && ($columns['duration']['function'] ?? null) === 'calcDuration'
                && ($columns['duration']['store'] ?? false) === true
                && ($columns['procrastin']['function'] ?? null) === 'calcProcrastin'
                && ($columns['points']['function'] ?? null) === 'calcPoints'
                && ($columns['points']['onupdate'] ?? null) === 'onupdatePoints'
                && ($columns['points']['store'] ?? false) === true
                && ($columns['is_locked']['onupdate'] ?? null) === 'onupdateIsLocked'
                && ($columns['report_id']['onupdate'] ?? null) === 'onupdateReportId'
            );
        }
    ],

    '1004' => [
        'description' => 'Check Report totals, balances and release status.',
        'help'        => 'Reports must keep computed balance fields and the release callback that locks lines.',
        'act'         => function () {
            $columns = Report::getColumns();

            return [
                'status'         => $columns['status'] ?? [],
                'total_points'   => $columns['total_points'] ?? [],
                'total_credits'  => $columns['total_credits'] ?? [],
                'balance_old'    => $columns['balance_old'] ?? [],
                'balance_new'    => $columns['balance_new'] ?? [],
                'has_non_posted' => $columns['has_non_posted'] ?? [],
                'is_empty'       => $columns['is_empty'] ?? [],
                'link'           => $columns['link'] ?? []
            ];
        },
        'assert'      => function ($columns) {
            $statuses = (array) ($columns['status']['selection'] ?? []);

            return (
                ($columns['status']['default'] ?? null) === 'pending'
                && ($columns['status']['onupdate'] ?? null) === 'onupdateStatus'
                && count(array_intersect(['pending', 'released', 'archived', 'sent'], $statuses)) === 4
                && ($columns['total_points']['function'] ?? null) === 'calcTotalPoints'
                && ($columns['total_points']['store'] ?? false) === true
                && ($columns['total_credits']['function'] ?? null) === 'calcTotalCredits'
                && ($columns['total_credits']['store'] ?? false) === true
                && ($columns['balance_old']['function'] ?? null) === 'calcBalanceOld'
                && ($columns['balance_old']['store'] ?? false) === true
                && ($columns['balance_new']['function'] ?? null) === 'calcBalanceNew'
                && ($columns['balance_new']['store'] ?? false) === true
                && ($columns['has_non_posted']['function'] ?? null) === 'calcHasNonPosted'
                && ($columns['is_empty']['function'] ?? null) === 'calcIsEmpty'
                && ($columns['link']['function'] ?? null) === 'calcLink'
            );
        }
    ],

    '1005' => [
        'description' => 'Check NAVLine workflow and reconciliation fields.',
        'help'        => 'NAV lines can be reconciled only once required references are resolved and alerts cleared.',
        'act'         => function () {
            $columns = NAVLine::getColumns();
            $workflow = NAVLine::getWorkflow();

            return [
                'status'             => $columns['status'] ?? [],
                'customer_id'        => $columns['customer_id'] ?? [],
                'service_account_id' => $columns['service_account_id'] ?? [],
                'has_error'          => $columns['has_error'] ?? [],
                'has_alert'          => $columns['has_alert'] ?? [],
                'workflow'           => $workflow
            ];
        },
        'assert'      => function ($data) {
            $statuses = (array) ($data['status']['selection'] ?? []);
            $pending_transitions = $data['workflow']['pending']['transitions'] ?? [];
            $reconcile = $pending_transitions['reconcile'] ?? [];

            return (
                ($data['status']['default'] ?? null) === 'pending'
                && count(array_intersect(['pending', 'reconciled', 'ignored'], $statuses)) === 3
                && ($data['customer_id']['type'] ?? null) === 'computed'
                && ($data['customer_id']['function'] ?? null) === 'calcCustomerId'
                && ($data['service_account_id']['type'] ?? null) === 'computed'
                && ($data['service_account_id']['function'] ?? null) === 'calcServiceAccountId'
                && ($data['has_error']['function'] ?? null) === 'calcHasError'
                && ($data['has_alert']['function'] ?? null) === 'calcHasAlert'
                && ($reconcile['status'] ?? null) === 'reconciled'
                && ($reconcile['onafter'] ?? null) === 'doReconcile'
                && in_array(['service_account_id', '>', 0], (array) ($reconcile['domain'] ?? []), true)
                && in_array(['customer_id', '>', 0], (array) ($reconcile['domain'] ?? []), true)
                && in_array(['has_error', '=', false], (array) ($reconcile['domain'] ?? []), true)
                && (($pending_transitions['ignore']['status'] ?? null) === 'ignored')
                && (($data['workflow']['ignored']['transitions']['restore']['status'] ?? null) === 'pending')
                && empty($data['workflow']['reconciled']['transitions'] ?? [])
            );
        }
    ]

];
