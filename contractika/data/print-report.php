<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

/*
    We use this controller as a "print" controller, for printing the result of a Report.
    We force the generation of the resulting PDF, whatever the value of the `pdf_data` field.
*/
[$params, $providers] = eQual::announce([
    'description'   => "Generates a PDF version of a Report.",
    'params'        => [
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.default'
        ],
        'id' => [
            'description'   => 'Identifier of the report to print.',
            'type'          => 'integer',
            'required'      => true
        ],
        'details' => [
            'description'   => 'Flag for requesting time entries details.',
            'type'          => 'boolean',
            'default'       => true
        ],
        'logs' => [
            'description'   => 'Flag for requesting points calculation log.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'lang' =>  [
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
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


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$report = Report::id($params['id'])->first();

if(!$report) {
    throw new Exception('unknown_report', QN_ERROR_UNKNOWN_OBJECT);
}

$output = Report::generatePdf($params['id'], [
        'show_details'  => $params['details'],
        'show_logs'     => $params['logs']
    ]);

if($output === null) {
    throw new Exception('unknown_error', QN_ERROR_UNKNOWN);
}

$context->httpResponse()
        ->body($output)
        ->send();
