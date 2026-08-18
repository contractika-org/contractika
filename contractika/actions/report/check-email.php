<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;
use core\Mail;

list($params, $providers) = eQual::announce([
    'description'   => "Check if an email has actually been sent for a given report.",
    'help'          => "If no mail have been sent or if the most recent mail is marked as failed, an alert is raised. \n
                        This controller is meant for reports marked as sent. If the report is not marked as sent, it is discarded.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Report for which the check is requested.',
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
    'providers'     => ['context', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context              $context
 * @var \equal\cron\Scheduler           $cron
 * @var \equal\dispatch\Dispatcher      $dispatch
 */
list($context, $cron, $dispatch) = [ $providers['context'], $providers['cron'], $providers['dispatch']];

$result = [];

$report = Report::id($params['id'])
    ->read(['status'])
    ->first(true);

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

// this check is meant only for sent reports
if($report['status'] != 'sent') {
    throw new Exception("non_sent_report", QN_ERROR_NOT_ALLOWED);
}

$mail = Mail::search([
        ['object_class', '=', Report::getType()],
        ['object_id', '=', $params['id']]
    ], ['sort' => ['created' => 'desc']])
    ->read(['status'])
    ->first();

if(!$mail || $mail['status'] != 'sent') {
    $result[] = $params['id'];
    $dispatch->dispatch('contractika.report.failed_email_sending', 'contractika\Report', $params['id'], 'important', 'contractika_report_check-email', $params);
}
else {
    $dispatch->cancel('contractika.report.failed_email_sending', 'contractika\Report', $params['id']);
}

$context->httpResponse()
        ->body($result)
        ->send();
