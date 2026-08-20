<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\BonusReport;

/*
    We use this controller as a "print" controller, for printing the result of a Report.
    We force the generation of the resulting PDF, whatever the value of the `pdf_data` field.
*/
[$params, $providers] = eQual::announce([
    'description'   => "Generates a PDF version of a Bonus Report.",
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the report to print.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['users'],
    ],
    'response'      => [
        'content-type'          => 'application/pdf',
        'content-disposition'   => 'inline; filename="document.pdf"',
        'accept-origin'         => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$report = BonusReport::id($params['id'])->read(['pdf_data'])->first();

if(!$report) {
    throw new Exception('unknown_report', QN_ERROR_UNKNOWN_OBJECT);
}

$context->httpResponse()
        ->body($report['pdf_data'])
        ->send();
