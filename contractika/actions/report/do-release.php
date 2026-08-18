<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;
use contractika\SALine;

list($params, $providers) = announce([
    'description'   => "Release a report. Status will change from 'pending' to 'released' and all posted lines will be marked as invoiced.",
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
        'is_empty',
        'has_non_posted',
        'service_account_id' => ['m_reporting']
    ])
    ->first();

if(!$report) {
    throw new Exception("unknown_report", QN_ERROR_UNKNOWN_OBJECT);
}

if($report['has_non_posted']) {
    throw new Exception(serialize(['has_non_posted' => ['cannot_be_released' => "Report has non posted time entries."]]), QN_ERROR_INVALID_PARAM);
}

// reports cannot be marked as released twice
if($report['status'] != 'pending') {
    throw new Exception("already_released_report", QN_ERROR_NOT_ALLOWED);
}

// update report status (will trigger `onupdateStatus()` method)
Report::id($params['id'])->update(['status' => 'released']);


if(isset($report['service_account_id']['m_reporting']) && $report['service_account_id']['m_reporting'] == 'Archive') {
    // instantly update status to 'archived' if the related contract setting implies it
    Report::id($params['id'])->update(['status' => 'archived']);
}
elseif($report['is_empty']) {
    // empty reports are not meant to be sent and their status is automatically set to 'archived'
    Report::id($params['id'])->update(['status' => 'archived']);
}

$context->httpResponse()
        ->status(204)
        ->send();
