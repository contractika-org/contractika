<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\email\Email;
use equal\email\EmailAttachment;

use contractika\Report;
use core\Mail;

/**
 * This script generates email objects for delayed sending of the Reports.
 * We do the 'Send' transition in several controllers because PDF versions of the Reports are attached to emails,
 * and PDF generation can take up to a few seconds for each Report.
 *
 */
// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Add an email to Queue, with PDF version of the given service report (pdf) as attachment.",
    'help'      	=>	"This controller is planned by `contractika_report_do-send`, is meant to be run through CRON, and should not be called directly.\n
                        It allows multiple sending of a same Report : the status of the Report is neither checked, nor updated.",
    'params' 		=>	[
        'id' => [
            'description'       => 'Identifier of the report to send.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\Report',
            'required'          => true
        ],
        /*
        'lang' =>  [
            'description'   => 'Language to use for multilang contents.',
            'type'          => 'string',
            'usage'         => 'language/iso-639',
            'default'       => constant('DEFAULT_LANG')
        ]
        */
    ],
    'constants'             => ['DEFAULT_LANG', 'EMAIL_SA_REPORT_REPLY_TO', 'EMAIL_SA_REPORT_BCC', 'EMAIL_ERRORS_RECIPIENT'],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch'] ];

$report = Report::id($params['id'])
    ->read([
            'service_account_id' => [
                'id',
                'customer_id' => [
                    'id',
                    'name',
                    'extref_at_id',
                    'contacts_ids' => ['name', 'language', 'email']
                ],
                'extref_at_id'
            ],
            'date',
            'pdf_data',
            'status'
        ])
    ->first(true);

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

// #memo - reports not marked as 'sent' cannot generate an email
// we should allow arbitrary re-sending of a report

if($report['status'] != 'sent') {
    throw new Exception("non_marked_as_sent_report", QN_ERROR_LOCKED_OBJECT);
}

/*
    load attachments
*/

/** @var EmailAttachment[] */
$attachments = [];
$report_name = sprintf("{$report['service_account_id']['customer_id']['extref_at_id']}-{$report['service_account_id']['extref_at_id']}-%s", date("Ym", $report['date']));
$attachments[] = new EmailAttachment($report_name.'.pdf', (string) $report['pdf_data'], 'application/pdf');

// perform checks (generate or remove alerts)
eQual::run('do', 'contractika_report_check-contacts', ['id' => $params['id']]);
if(isset($report['service_account_id']['customer_id']['id'])) {
    eQual::run('do', 'contractika_customer_check-contacts', ['id' => $report['service_account_id']['customer_id']['id']]);
}

/*
    load contacts
*/
$has_error = false;
$contacts = null;
if(isset($report['service_account_id']['customer_id']['contacts_ids'])) {
    $contacts = $report['service_account_id']['customer_id']['contacts_ids'];
}

if(!$contacts || count($contacts) <= 0) {
    $has_error = true;
}
else {
    $contact_to = reset($contacts);
    $contacts_cc = [];
    if(count($contacts) > 1) {
        $contacts_cc = array_slice($contacts, 1);
    }
    if(!isset($contact_to['email']) || strlen($contact_to['email']) <= 0) {
        $has_error = true;
    }
}

if($has_error) {
    // send an email alert
    $message = new Email();
    $message->setTo(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('Contractika - Report sending error : contact missing')
            ->setContentType("text/html")
            ->setBody("<html>
            <body>
            <p>Erreur inattendue lors de l'exécution du script ".__FILE__." :</p>
            <pre>No contact found (missing) for customer {$report['service_account_id']['customer_id']['name']} ({$report['service_account_id']['customer_id']['extref_at_id']})</pre>
            </body>
            </html>");
    // queue message
    Mail::queue($message);
    throw new Exception("missing_contact", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    load mail template
*/
$file = QN_BASEDIR."/packages/contractika/views/Report.email.default.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}

// create message
$message = new Email();
$message
    ->setTo($contact_to['email'])
    // ->setTo('cedric@yesbabylon.com')
    // ->setTo('stephane.harchies@its.netika.com')
    ->setReplyTo(constant('EMAIL_SA_REPORT_REPLY_TO'))
    ->addBcc(constant('EMAIL_SA_REPORT_BCC'))
    ->setSubject('NETiKA IT Services - Relevé des services - Service Report')
    ->setContentType("text/html")
    ->setBody(file_get_contents($file));

// add secondary contacts
foreach($contacts_cc as $contact) {
    $message->addCc($contact['email']);
}

// append attachments to message
foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

// queue message
Mail::queue($message, 'contractika\Report', $params['id']);

// #memo - we don't alter the status here (keep distinction between archived and sent)
// Report::id($params['id'])->update(['status' => 'archived']);

$context->httpResponse()
        ->status(204)
        ->send();
