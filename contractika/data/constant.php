<?php

[$params, $providers] = eQual::announce([
    'description'   => 'Provide the final value of a given constant (from config file).',
    'params'        => [
        'constant' =>  [
            'type'          => 'string',
            'description'   => 'name of the constant for which the value is requested.',
            'required'      => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'private'
    ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

if(!\config\constant($params['constant']) && !defined($params['constant'])) {
    throw new Exception('unknown_property', QN_ERROR_INVALID_PARAM);
}

\config\export($params['constant']);

$context->httpResponse()
        ->status(200)
        ->body(['result' => constant($params['constant'])])
        ->send();