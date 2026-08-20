<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\SALine;

[$params, $providers] = eQual::announce([
    'description'   => "Delete a set of SA line. This is used for deleting CC lines from Service Accounts.",
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifiers of the targeted SA lines to delete.',
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

SALine::ids($params['ids'])->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();
