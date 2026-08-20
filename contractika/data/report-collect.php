<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;
use contractika\ServiceAccount;
use equal\orm\Domain;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Reports: returns a collection of Reports according to extra paramaters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'          => 'string',
            'default'       => 'contractika\Report'
        ],

        'date_from' => [
            'type'          => 'date',
            'description'   => "First date of the time interval.",
            'default'       => null
        ],

        'date_to' => [
            'type'          => 'date',
            'description'   => "Last date of the time interval.",
            'default'       => null
        ],

        'has_non_posted' => [
            'type'          => 'boolean',
            'description'   => "Reports with lines not yet posted."
        ],

        'is_empty' => [
            'type'          => 'boolean',
            'description'   => "Reports with no lines."
        ],

        'customer_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\sale\customer\Customer',
            'description'       => 'Customer of the Service Account the reports relate to.'
        ],

        'status' => [
            'type'              => 'string',
            'selection'         => [
                'pending',
                'released',
                'sent',
                'archived'
            ],
            'description'       => 'Reports with a specific status.'
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

$reports_ids = [];

/*
    Add conditions to the domain to consider advanced parameters
*/
$domain = $params['domain'];

if(isset($params['date_from']) && $params['date_from']) {
    $domain = Domain::conditionAdd($domain, ['date_from', '>=', $params['date_from']]);
}
if(isset($params['date_to']) && $params['date_to']) {
    $domain = Domain::conditionAdd($domain, ['date_from', '<=', $params['date_to']]);
}

if(isset($params['has_non_posted']) && $params['has_non_posted'] !== null) {
    $domain = Domain::conditionAdd($domain, ['has_non_posted', '=', $params['has_non_posted']]);
}

if(isset($params['is_empty']) && $params['is_empty'] !== null) {
    $domain = Domain::conditionAdd($domain, ['is_empty', '=', $params['is_empty']]);
}

if(isset($params['status']) && $params['status'] !== null) {
    $domain = Domain::conditionAdd($domain, ['status', '=', $params['status']]);
}


/*
    customer_id : filter on Service Accounts related customer
*/
if(isset($params['customer_id']) && $params['customer_id']) {

    $matches_ids = [];
    $serviceAccounts = ServiceAccount::search(['customer_id', '=', $params['customer_id']])->read(['reports_ids']);

    foreach($serviceAccounts as $account) {
        $matches_ids = array_merge($matches_ids, $account['reports_ids']);
    }

    if(count($matches_ids)) {
        if(count($reports_ids)) {
            $reports_ids = array_intersect(
                $reports_ids,
                $matches_ids
            );
        }
        else {
            $reports_ids = $matches_ids;
        }
        if(empty($reports_ids)) {
            // add a constraint to void the result set
            $reports_ids = [0];
        }
    }
    else {
        // add a constraint to void the result set
        $reports_ids = [0];
    }
}

if(count($reports_ids)) {
    $domain = Domain::conditionAdd($domain, ['id', 'in', $reports_ids]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
