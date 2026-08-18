<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\hr\employee\Employee;

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

// #memo - this will trigger recomputing of the Identity display_name, if NULL
Employee::search(['relationship','=','employee'])
    ->read(['display_name']);

$context
    ->httpResponse()
    ->status(204)
    ->send();
