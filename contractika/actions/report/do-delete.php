<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

[$params, $providers] = eQual::announce([
    'description'   => "Delete a report. The report will be deleted and SA lines detached from it.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted report.',
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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

$report = Report::id($params['id'])
    ->read([
        'status',
        'sa_lines_ids'
    ])
    ->first();

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

// #memo - released reports cannot be deleted
if($report['status'] != 'pending') {
    throw new Exception("released_report", QN_ERROR_NOT_ALLOWED);
}

// permanently delete report (will trigger reset of has_report and report_id for related SA Lines)
Report::id($params['id'])->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();
