<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Accepts a Contractika synchronization payload without calling external APIs and returns HTTP 200.',
    'params'        => [
        'provider' => [
            'type'              => 'string',
            'description'       => 'External provider to simulate.',
            'selection'         => ['at', 'sd', 'bc', 'ms_dynamics'],
            'required'          => true
        ],
        'resource' => [
            'type'              => 'string',
            'description'       => 'Provider resource targeted by the simulated send.',
            'required'          => true
        ],
        'operation' => [
            'type'              => 'string',
            'description'       => 'External operation being simulated.',
            'default'           => 'upsert'
        ],
        'payload' => [
            'type'              => 'array',
            'description'       => 'Payload that would be sent to the external provider.',
            'default'           => []
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$provider = strtolower($params['provider']);
if($provider === 'ms_dynamics') {
    $provider = 'bc';
}

$payload = $params['payload'];
$item_count = 0;
if(is_array($payload)) {
    $is_list = empty($payload) || array_keys($payload) === range(0, count($payload) - 1);
    $item_count = $is_list ? count($payload) : 1;
}

$context
    ->httpResponse()
    ->status(200)
    ->body([
        'result' => [
            'accepted'    => true,
            'provider'    => $provider,
            'resource'    => strtolower(str_replace('-', '_', $params['resource'])),
            'operation'   => $params['operation'],
            'status_code' => 200,
            'item_count'  => $item_count,
            'payload'     => $payload
        ]
    ])
    ->send();
