<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;


list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for SA lines: returns a collection of lines matching extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'              => 'string',
            'default'           => 'contractika\ServiceAccount'
        ],
        'id' => [
            'type'              => 'integer',
            'description'       => "Contractika ID of the Service Account."
        ],
        'extref_at_id' => [
            'type'              => 'integer',
            'description'       => "AutoTask ID of the Contract (Service Account)."
        ],
        'name' => [
            'type'              => 'string',
            'description'       => "Clue for searching on ServiceAccount names."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval."
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval."
        ],
        'customer_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\sale\customer\Customer',
            'description'       => 'Customer the line relates to (depending on service account).'
        ],
        'customer_identifier' => [
            'type'              => 'integer',
            'description'       => 'Numeric value of the ID of the Customer on which to filter the lines.'
        ],
        'sa_category_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\SACategory',
            'description'       => 'The category the contract belongs to.'
        ],
        'sa_type_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\SAType',
            'description'       => 'The type the contract relates to.'
        ],
        'm_reporting' => [
            'type'              => 'string',
            'description'       => 'Reporting mode for the contract.',
            'default'           => 'all',
            'selection'         => [
                'all',
                'None',
                'Send',
                'Archive'
            ]
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];


/*
    Add conditions to the domain to consider advanced parameters
*/

$domain = $params['domain'];

if(isset($params['id']) && $params['id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['id', '=', $params['id']]);
}
if(isset($params['extref_at_id']) && $params['extref_at_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['extref_at_id', '=', $params['extref_at_id']]);
}
if(isset($params['name']) && strlen($params['name']) > 0) {
    $domain = Domain::conditionAdd($domain, ['name', 'ilike', '%'.$params['name'].'%']);
}
if(isset($params['date_from']) && $params['date_from'] > 0) {
    $domain = Domain::conditionAdd($domain, ['startDate', '>=', date('c', $params['date_from'])]);
}
if(isset($params['date_to']) && $params['date_to'] > 0) {
    $domain = Domain::conditionAdd($domain, ['startDate', '<=', date('c', $params['date_to'])]);
}
if(isset($params['customer_id']) && $params['customer_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['customer_id', '=', $params['customer_id']]);
}
if(isset($params['customer_identifier']) && $params['customer_identifier'] > 0) {
    $domain = Domain::conditionAdd($domain, ['customer_id', '=', $params['customer_identifier']]);
}
if(isset($params['sa_category_id']) && $params['sa_category_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['sa_category_id', '=', $params['sa_category_id']]);
}
if(isset($params['sa_type_id']) && $params['sa_type_id'] > 0 ) {
    $domain = Domain::conditionAdd($domain, ['sa_type_id', '=', $params['sa_type_id']]);
}
if(isset($params['m_reporting']) && $params['m_reporting'] != 'all' ) {
    $domain = Domain::conditionAdd($domain, ['m_reporting', '=', $params['m_reporting']]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
