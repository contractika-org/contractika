<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\SALine;

list($params, $providers) = eQual::announce([
    'description'   => 'Run points calculation for non-locked lines that require it.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context' ]
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

// fetch Task&Ticket lines (from all Service Account) that require regeneration of computed fields related to points
$lines = SALine::search([['sa_line_class_id', 'in', [1, 2]], ['is_locked', '=', false], ['points', 'is', null]])
    // trigger re-calculation
    ->read(['points', 'service_account_id', 'procrastin']);

$context
    ->httpResponse()
    ->status(204)
    ->send();
