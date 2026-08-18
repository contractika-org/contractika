<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\ServiceAccount;

list($params, $providers) = eQual::announce([
    'description'   => "Generates a request for creating a new Ticket on Datto AutoTask, about renewing the contract relating to ServiceAccount given `id`.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted service account.',
            'type'              => 'many2one',
            'foreign_object'    => 'contractika\ServiceAccount',
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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

$serviceAccount = ServiceAccount::id($params['id'])->read(['renew_auto', 'renew_floor', 'balance_current', 'extref_at_id', 'customer_id' => ['extref_at_id']])->first();

if(!$serviceAccount) {
    throw new Exception('unknown_service_account', QN_ERROR_INVALID_PARAM);
}

eQual::run('do', 'contractika_at_create-ticket', [
		'title'   		=> "ServicePackage status alert ".date('d/m/Y')." - To renew",
		'description'   => "SA #{$params['id']}\n\nRenew auto : ".(($serviceAccount['renew_auto'])?'Yes':'No')."\n\nRenew floor: {$serviceAccount['renew_floor']}\n\nSolde au ".date('d/m/Y').": {$serviceAccount['balance_current']}",
		'company_id'    => $serviceAccount['customer_id']['extref_at_id'],
		'contract_id'   => $serviceAccount['extref_at_id'],
		'external_id'   => $params['id'],
		'due_datetime'  => strtotime("+4days", time())
	]);

$context->httpResponse()
        ->status(200)
        ->send();
