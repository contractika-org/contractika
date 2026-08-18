<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

list($params, $providers) = announce([
    'description'   => "Attempt to release a selection of Reports.",
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifiers of the targeted Reports to release.',
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

// request release for all given Report ids
foreach($params['ids'] as $id) {
    // will raise an exception if action cannot be performed
    eQual::run('do', 'contractika_report_do-release', ['id' => $id]);
}

$context->httpResponse()
        ->status(204)
        ->send();
