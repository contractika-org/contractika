<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\SALine;

[$params, $providers] = eQual::announce([
    'description'   => 'Reset computed points for all existing lines of all service accounts.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report' ]
]);

// fetch Task&Ticket lines from all Service Account and force immediate regeneration of computed fields related to points
SALine::search([['sa_line_class_id', 'in', [1, 2]], ['is_locked', '=', false]])
    // reset computed fields
    ->update(['pause_time' => null, 'duration' => null, 'travel_time' => null, 'points' => null])
    // force immediate re-calculation
    ->read(['points']);

$context
    ->httpResponse()
    ->status(204)
    ->send();