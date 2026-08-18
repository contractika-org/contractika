<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\NAVLine;

list($params, $providers) = announce([
    'description'   => 'Try to auto reconcile imported NAV lines according to their current status.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'orm', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\orm\ObjectManager          $om
 * @var \equal\error\Reporter             $reporter
 */
list($context, $om, $reporter) = [ $providers['context'], $providers['orm'] , $providers['report'] ];

$result = [
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'processed' => 0,
    'warnings'  => 0,
    'logs'      => []
];

// retrieve all candidates NAV Lines (credits) : lines that should be allowed to be reconciled
$lines_ids = NAVLine::search([['status', '=', 'pending'], ['has_error', '=', false]])->ids();
$res = $om->transition(NAVLine::getType(), $lines_ids, 'reconcile');
if(count($res)) {
    $result['ignored'] = count($lines_ids);
    foreach($res as $line_id => $errors) {
        foreach($errors as $error_id => $msg) {
            $result['logs'][] = "WARN- Transition 'reconcile' not allowed for NAV Line [{$line_id}]: ".qn_error_name($error_id)." - $msg.";
            ++$result['warnings'];
        }
    }
}
else {
    $result['updated'] = count($lines_ids);
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
