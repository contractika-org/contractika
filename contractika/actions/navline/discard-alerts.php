<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\NAVLine;

list($params, $providers) = announce([
    'description'   => "Discard all alerts for given NAV line.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted NAV line.',
            'type'              => 'many2one',
            'foreign_object'    => NAVLine::getType(),
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

// void has_alert flag (this will recompute has_error)
NAVLine::id($params['id'])->update(['has_alert' => false]);

$context->httpResponse()
        ->status(204)
        ->send();
