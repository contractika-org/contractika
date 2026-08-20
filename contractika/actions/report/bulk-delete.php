<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

[$params, $providers] = eQual::announce([
    'description'   => "Delete a set of Reports. This is used for deleting pending Reports in bulk.",
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifiers of the targeted Reports to delete.',
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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

Report::ids($params['ids'])->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();
