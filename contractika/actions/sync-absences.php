<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\hr\employee\Employee;
use equal\email\Email;
use core\Mail;
use core\setting\Setting;

list($params, $providers) = eQual::announce([
    'description'   => 'Synchronizes AutoTask with the local list of absences based on data from SDworx.',
    'params'        => [
        'future_months'   => [
            'description'       => 'Number of months in the future to cover.',
            'type'              => 'integer',
            'default'           => 6
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'constants'         => ['EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [
        'errors'    => 0,
        'logs'      => []
    ];

try {
    $data = eQual::run('do', 'contractika_pull-absences', ['future_months' => $params['future_months']]);
    $result['pull-absences'] = $data['result'];

    // store new last_run value
    if(!isset($result['pull-absences']['errors']) || $result['pull-absences']['errors'] == 0) {
        Setting::set_value('contractika', 'sync', 'sd_sync.last_run', time());
    }

    $data = eQual::run('do', 'contractika_push-absences');
    $result['push-absences'] = $data['result'];

    // store new last_run value
    if(!isset($result['push-absences']['errors']) || $result['push-absences']['errors'] == 0) {
        Setting::set_value('contractika', 'sync', 'at_sync_absences.last_run', time());
    }
}
catch(Exception $e) {
    ++$result['errors'];
    $result['logs'][] = $e->getMessage()." (".$e->getCode().")";
}



/**
 * Workaround to make sure display_name is always computed in Identity (it is reset somehow in an unidentified fashion)
 */
// #memo - this will trigger recomputing of the Identity display_name, if NULL
Employee::search(['relationship','=','employee'])
    ->read(['display_name']);


/**
 * Send email report.
 */

if( $result['errors'] > 0
    || (isset($result['push-absences']['errors']) && $result['push-absences']['errors'] > 0)
    || (isset($result['pull-absences']['errors']) && $result['pull-absences']['errors'] > 0) ) {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    // unexpected error: send an email alert
    $message = new Email();
    $message->setTo(constant('EMAIL_ERRORS_RECIPIENT'))
            ->addCc(constant('EMAIL_REPORT_RECIPIENT'))
            ->setSubject('ERROR Contractika (ex-SyncBox)')
            ->setContentType("text/html")
            ->setBody("<html>
            <body>
            <p>Erreur inattendue lors de la synchronisation des absences (script ".__FILE__.") :</p>
            <pre>".$report."</pre>
            </body>
            </html>");

    // queue message
    Mail::queue($message);
    // mark current script as failing
    // #memo - doing this prevents showing output in logs
    // throw new Exception('unexpected_error', QN_ERROR_UNKNOWN);
}
else {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->setSubject('SUCCESS Contractika (ex-SyncBox)')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Nouvelle synchronisation des absences au ".date('d/m/Y').' à '.date('H:i')." :</p>
                    <pre>".$report."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
