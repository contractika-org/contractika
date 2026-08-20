<?php
use equal\email\Email;
use core\Mail;

[$params, $providers] = eQual::announce([
    'description'   => 'Synchronizes AutoTask with the local list of holidays based on data from AutoTask.',
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

$result = [];

try {
    $data = eQual::run('do', 'contractika_pull-holidays');
    $result['pull-holidays'] = $data['result'];

    $data = eQual::run('do', 'contractika_push-holidays');
    $result['push-holidays'] = $data['result'];
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


$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
