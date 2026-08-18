<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

list($params, $providers) = eQual::announce([
    'description'   => "Re-send a report. Requests an email sending with PDF report as attachment.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Report for which the sending is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\Report',
            'required'          => true
        ],
        'confirm' =>  [
            'type'              => 'boolean',
            'description'       => 'Manual confirmation to re-send the email.',
            'help'              => 'An explicit confirmation is requested in order to avoid sending a same report several times by mistake.',
            'default'           => false
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
if(!$params['confirm']) {
    throw new Exception("missing_confirmation", QN_ERROR_NOT_ALLOWED);
}

// #memo - non sent reports cannot be re-sent
if($report['status'] != 'sent') {
    throw new Exception("non_sent_report", QN_ERROR_NOT_ALLOWED);
}

if(!isset($report['service_account_id']['m_reporting']) || $report['service_account_id']['m_reporting'] != 'Send') {
    throw new Exception('incompatible_reporting_mode', QN_ERROR_NOT_ALLOWED);
}

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
