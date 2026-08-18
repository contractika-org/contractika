<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

list($params, $providers) = eQual::announce([
    'description'   => "Send a report. Updates the Report status and requests an email sending with PDF report as attachment.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Report for which the sending is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\Report',
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
    'providers'     => ['context', 'cron']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\cron\Scheduler       $cron
 */
list($context, $cron) = [ $providers['context'], $providers['cron'] ];

$report = Report::id($params['id'])
    ->read([
            'status',
            'service_account_id' => [
                'm_reporting',
            ]
        ])
    ->first(true);

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

// reports cannot be marked as sent twice
if($report['status'] == 'sent') {
    throw new Exception("already_sent_report", QN_ERROR_NOT_ALLOWED);
}

// #memo - non released reports cannot be sent
if(!in_array($report['status'], ['released', 'archived'])) {
    throw new Exception("non_released_report", QN_ERROR_UNKNOWN_OBJECT);
}

if(!isset($report['service_account_id']['m_reporting']) || $report['service_account_id']['m_reporting'] != 'Send') {
    throw new Exception('incompatible_reporting_mode', QN_ERROR_NOT_ALLOWED);
}

// update report status (will trigger `onupdateStatus()` method)
Report::id($params['id'])->update(['status' => 'sent']);

// schedule for sending at next cron loop
$cron->schedule(
    "report.send.{$params['id']}",
    time(),
    'contractika_report_send',
    [ 'id' => $params['id'] ]
);

// schedule a check to make sure the report was sent correctly (delay 10 minutes)
$cron->schedule(
    "report.check_email.{$params['id']}",
    time() + (60*10),
    'contractika_report_check-email',
    [ 'id' => $params['id'] ]
);

$context->httpResponse()
        ->status(204)
        ->send();
