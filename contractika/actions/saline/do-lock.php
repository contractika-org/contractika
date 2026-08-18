<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\SALine;

list($params, $providers) = eQual::announce([
    'description'   => "Force flag 'Report sent' to true (is_locked), to mark the line as sent.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted SA line.',
            'type'              => 'many2one',
            'foreign_object'    => SALine::getType(),
            'required'          => true
        ]
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

// reset computed fields related to points
SALine::id($params['id'])
    ->update(['is_locked' => true]);

$context->httpResponse()
        ->status(204)
        ->send();
