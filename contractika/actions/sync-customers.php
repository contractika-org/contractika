<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\email\Email;
use core\Mail;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Synchronizes AutoTask with the local list of customers based on data from Navision and AutoTask.',
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
['context' => $context, 'report' => $reporter] = $providers;

$result = [];

try {
    $data = eQual::run('do', 'contractika_pull-customers');
    $result['pull-customers'] = $data['result'];

    $data = eQual::run('do', 'contractika_patch-customers');
    $result['patch-customers'] = $data['result'];

    $data = eQual::run('do', 'contractika_push-customers');
    $result['push-customers'] = $data['result'];

    // store new last_run value
    Setting::set_value('contractika', 'sync', 'at_sync_customers.last_run', time());
}
catch(Exception $e) {
    // unexpected error: send an email alert
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('ERROR Contractika (ex-SyncBox)')
            ->setContentType("text/html")
            ->setBody("<html>
            <body>
            <p>Erreur inattendue lors de l'exécution du script ".__FILE__." :</p>
            <pre>".qn_error_name($e->getCode()).' : '.$e->getMessage()."</pre>
            </body>
            </html>");

    // queue message
    Mail::queue($message);
    // relay exception
    throw new Exception($e->getMessage(), $e->getCode());
}


/**
 * Send email report if there were warnings.
 */

if($result['pull-customers']['warnings'] > 0 || $result['patch-customers']['warnings'] > 0 || $result['push-customers']['warnings'] > 0) {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('WARNING Contractika')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Alertes lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
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
