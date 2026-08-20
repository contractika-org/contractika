<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\NAVLine;

[$params, $providers] = eQual::announce([
    'description'   => "Attempt to restore given NAV lines.",
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifiers of the targeted NAV lines to restore.',
            'type'              => 'array',
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
    'providers'     => ['orm', 'context']
]);

/**
 * @var \equal\orm\ObjectManager    $om
 * @var \equal\php\Context          $context
 */
list($om, $context) = [$providers['orm'], $providers['context']];

$res = $om->canTransition(NAVLine::getType(), $params['ids'], 'restore');

if(count($res)) {
    foreach($res as $id => $errors) {
        foreach($errors as $error_code => $error_descr) {
            if(is_array($error_descr)) {
                $error_descr = serialize($error_descr);
            }
            // send error using the same format as the announce method
            throw new \Exception($error_descr, (int) $error_code);
        }
    }
}

$om->transition(NAVLine::getType(), $params['ids'], 'restore');

$context->httpResponse()
        ->status(204)
        ->send();
