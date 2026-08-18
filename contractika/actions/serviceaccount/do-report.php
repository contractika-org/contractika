<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;
use contractika\SALine;
use contractika\ServiceAccount;
use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => "Generate a draft report with all posted lines from a given service account. If config doesn't allow it, generation is skipped.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted service account.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\ServiceAccount',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

// 1) if global setting f_reporting is set to 'eurojob' : quit
$f_reporting = Setting::get_value('contractika', 'ts_reporting', 'f_reporting', 'eurojob');
if($f_reporting == 'eurojob') {
    throw new Exception('operation_skipped', 0);
}

// 2) if customer's param f_reporting is set to 'eurojob' : quit
$serviceAccount = ServiceAccount::id($params['id'])
    ->read([
        'is_active',
        'm_reporting',
        'reporting_from',
        'customer_id' => [
            'is_active',
            'f_reporting'
        ]
    ])
    ->first();

if(!$serviceAccount['is_active']) {
    throw new Exception(serialize(['is_active' => ['inactive_contract' => "Contract marked as inactive."]]), QN_ERROR_INVALID_PARAM);
}

if(!isset($serviceAccount['m_reporting']) || is_null($serviceAccount['m_reporting'])) {
    throw new Exception(serialize(['m_reporting' => ['missing_reporting_mode' => "TSreport is not set (null) for contract."]]), QN_ERROR_INVALID_PARAM);
}

if($serviceAccount['m_reporting'] == 'None') {
    throw new Exception(serialize(['m_reporting' => ['incompatible_reporting_mode' => "TSreport set to 'None' for contract."]]), QN_ERROR_INVALID_PARAM);
}

if(!$serviceAccount['customer_id']['is_active']) {
    throw new Exception(serialize(['customer_id' => ['inactive_customer' => "Customer marked as inactive."]]), QN_ERROR_INVALID_PARAM);
}

if(!isset($serviceAccount['customer_id']['f_reporting']) || is_null($serviceAccount['customer_id']['f_reporting'])) {
    throw new Exception(serialize(['customer_id' => ['missing_frequency' => "TSreport_frequency value is not set (null) for customer."]]), QN_ERROR_INVALID_PARAM);
}

if($serviceAccount['customer_id']['f_reporting'] == 'eurojob') {
    throw new Exception(serialize(['customer_id' => ['incompatible_frequency' => "TSreport_frequency set to 'eurojob' for customer."]]), QN_ERROR_INVALID_PARAM);
}

// #memo - non invoiceable SA can generate reports, but those must not be sent

// permanently remove any existing pending report
// #memo - this triggers reset of fields has_report and report_id for related SA Lines
Report::search([['service_account_id', '=', $params['id']], ['status', '=', 'pending']])->delete(true);

$previous = Report::search([
        ['status', '<>', 'pending'],
        ['service_account_id', '=', $params['id']],
    ],
    [
        'sort'  => ['date' => 'desc'],
        'limit' => 1
    ])
    ->read(['date'])
    ->first();

$fallback_date = ($serviceAccount['reporting_from'])?$serviceAccount['reporting_from']:strtotime("2023-01-01");
$date_from = ($previous)?(strtotime('+1 day', $previous['date'])):$fallback_date;

if($serviceAccount['customer_id']['f_reporting'] == 'monthly') {
    // last day of the month @ 23:59:59
    $date_to = strtotime(date("Y-m-t 23:59:59", $date_from));
}
elseif($serviceAccount['customer_id']['f_reporting'] == 'weekly') {
    // six days after @ 23:59:59 (to cover 7 days)
    $date_to = strtotime(date("Y-m-d 23:59:59", strtotime('+6 day', $date_from)));
}
else {
    throw new Exception('invalid_customer_reporting_frequency', QN_ERROR_INVALID_CONFIG);
}

// do not generate Report for future months
if(intval(date('Ym', $date_to)) > intval(date('Ym'))) {
    throw new Exception('operation_skipped', 0);
}

// create a new empty report
$report = Report::create([
        'date'          => $date_to,
        'date_from'     => $date_from
    ])
    ->first();

try {
    // assign to the report all pending lines of the service account matching the period (any time before date_to, even if preceding date_from)
    SALine::search([
            ['service_account_id', '=', $params['id']],
            ['is_locked', '=', false],
            ['date', '<=', $date_to]
        ])
        ->update([
            'has_report'    => true,
            'report_id'     => $report['id']
        ]);

    Report::id($report['id'])
        // update the report (triggers update of 'balance_old' and 'has_lines')
        ->update(['service_account_id' => $params['id']])
        // force instant recalculation of computed fields
        ->update(['is_empty' => null, 'has_lines' => null, 'has_non_posted' => null, 'total_points' => null, 'total_credits' => null, 'balance_new' => null, 'pdf_data' => null])
        ->read(['is_empty', 'has_lines', 'has_non_posted', 'total_points', 'total_credits', 'balance_new']);
}
catch(Exception $e) {
    // operation failed : remove newly created (draft) report
    // #memo - this will rollback SALine report_id assignation and has_report value
    Report::id($report['id'])->delete(true);
    // relay exception
    throw $e;
}

$context->httpResponse()
        ->status(200)
        ->send();
