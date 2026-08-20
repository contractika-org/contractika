<?php
use contractika\hr\employee\Employee;
use contractika\hr\absence\Absence;
use equal\email\Email;
use core\Mail;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => 'Synchronizes database ITs Dashboard with the local list of absences on data from SDworx.',
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
    'ignored' => 0,
    'created' => 0,
    'updated' => 0
];

// retrieve last_run from settings (defaults to 'all times'), i.e. the last time the absences were imported from SDworx
$last_run = Setting::get_value('contractika', 'sync', 'dash_sync.last_run', 0);

try {
    // fetch all active employees
    $employees = Employee::search(['is_active', '=', true])->read(['id', 'extref_sd_id', 'partner_identity_id']);

    foreach($employees as $employee) {
        // search all absences created or updated since last run
        $absences = Absence::search([['employee_id', '=', $employee['id']], ['is_holiday', '=', false], ['modified', '>=', $last_run]])->read(['id', 'extref_sd_id']);
        // export found absences to SD_Absence table
        foreach($absences as $absence) {
            if(!isset($absence['extref_sd_id']) || !$absence['extref_sd_id']) {
                ++$result['ignored'];
                continue;
            }
            $data = eQual::run('get', 'contractika_dash_absence', ['id' => $absence['extref_sd_id']]);
            // the record already exists: update it
            if(count($data)) {
                $data = eQual::run('do', 'contractika_dash_update-absence', ['id' => $absence['id']]);
                ++$result['updated'];
            }
            // the record doesn't exist yet: create it
            else {
                $data = eQual::run('do', 'contractika_dash_create-absence', ['id' => $absence['id']]);
                ++$result['created'];
            }
        }
    }

    // store new last_run value
    Setting::set_value('contractika', 'sync', 'dash_sync.last_run', time());
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
            </html>
            ");

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
