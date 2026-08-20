<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
[$params, $providers] = eQual::announce([
    'description'   => 'Redirect to the Apps webapp.',
    'params'        => [],
    'response'      => [
        'location'      => '/apps/'
    ]
]);