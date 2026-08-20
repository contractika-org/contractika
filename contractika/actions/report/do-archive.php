<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

[$params, $providers] = eQual::announce([
    'description'   => "Archive a report. Status will change from 'released' to 'archived'.",
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
        'has_non_posted',
        'service_account_id' => ['m_reporting']
    ])
    ->first();

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

// #memo - non released reports cannot be archived
if($report['status'] != 'released') {
    throw new Exception("non_released_report", QN_ERROR_NOT_ALLOWED);
}

// update report status (will trigger `onupdateStatus()` method)
Report::id($params['id'])->update(['status' => 'archived']);

$context->httpResponse()
        ->status(204)
        ->send();
