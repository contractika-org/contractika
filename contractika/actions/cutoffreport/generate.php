<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\CutOffReport;

list($params, $providers) = eQual::announce([
    'description'   => "Generates a new draft cutoff report.",
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
CutOffReport::search(['status', '=', 'pending'])->delete(true);

// create a new pending report (will compute date_from and date_to according to latest released report)
CutOffReport::create()
    ->do('init')
    ->read(['report_line_groups_ids' => ['total_points', 'total_amount']]);

$context->httpResponse()
        ->status(204)
        ->send();
