<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\BonusReport;

list($params, $providers) = eQual::announce([
    'description'   => "Generates a new draft bonus report.",
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

// if there is a pending report, remove it
BonusReport::search(['status', '=', 'pending'])->delete(true);

// create a new pending report (will compute date_from and date_to according to latest released report), and initialize it (will generate lines)
BonusReport::create()->do('init');

$context->httpResponse()
        ->status(204)
        ->send();
