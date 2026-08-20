<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

[$params, $providers] = eQual::announce([
    'description'   => "Delete all pending Reports. This is intended to be used as a batch task.",
    'params'        => [],
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

Report::search(['status', '=', 'pending'])->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();
