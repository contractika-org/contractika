<?php
use equal\email\Email;
use core\Mail;

list($params, $providers) = announce([
    'description'   => 'Synchronizes the local list of employees based on data from SDworx and AutoTask.',
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
    // fetch the latest listing of employees from SDWorx
    $data = eQual::run('do', 'contractika_pull-employees');
    $result['pull-employees'] = $data['result'];

    // fetch the latest listing of employees from AT
    $data = eQual::run('do', 'contractika_pull-roles');
    $result['pull-roles'] = $data['result'];

    // fetch the latest listing of resources from AutoTask (using API)
    $data = eQual::run('do', 'contractika_patch-employees');
    $result['patch-employees'] = $data['result'];

    if($result['pull-employees']['errors']) {
        throw new Exception(serialize(['unexpected_error_pull_employees' => $result['pull-employees']['logs']]), QN_ERROR_UNKNOWN);
    }
    if($result['pull-roles']['errors']) {
        throw new Exception(serialize(['unexpected_error_pull_roles' => $result['pull-roles']['logs']]), QN_ERROR_UNKNOWN);
    }
    if($result['patch-employees']['errors']) {
        throw new Exception(serialize(['unexpected_error_patch_employees' => $result['patch-employees']['logs']]), QN_ERROR_UNKNOWN);
    }

}
catch(Exception $e) {
    // unexpected error: send an email alert

    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('ERROR Contractika - Sync Employees')
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
