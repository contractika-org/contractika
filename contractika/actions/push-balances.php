<?php
use contractika\ServiceAccount;

list($params, $providers) = eQual::announce([
    'description'   => "Recompute ServiceAccount balance if necessary,\n
                    update the balance values stored in AT Contract as UDF `Balance` for Service Accounts marked with `has_balance_changed`,\n
                    and set field `Balance_LastUpdated` with current date.",
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context' ]
]);

/**
 * @var \equal\php\Context                $context
 */
list($context) = [ $providers['context'] ];

$result = [
    'updated'       => 0,
    'failed'        => 0,
    'renewal_alert' => [],
    'logs'          => []
];

$serviceAccounts = ServiceAccount::search([['is_active', '=', true], ['has_balance_changed', '=', true]])
    ->read(['id', 'extref_at_id', 'balance_current', 'sa_category_id', 'renew_auto', 'renew_floor', 'has_renew_alert_sent']);

foreach($serviceAccounts as $id => $serviceAccount) {
    // check if a renewal ticket must be created
    if($serviceAccount['balance_current'] < $serviceAccount['renew_floor'] && !$serviceAccount['has_renew_alert_sent'])  {
        // balance renewal is only for Service Packages [sa_category_id=2]
        if($serviceAccount['sa_category_id'] == 2) {
            try {
                eQual::run('do', 'contractika_serviceaccount_send-renew', ['id' => $id]);
                ServiceAccount::id($id)->update(['has_renew_alert_sent' => true]);
                $result['renewal_alert'][] = "[$id - {$serviceAccount['extref_at_id']}]";
            }
            catch(Exception $e) {
                // unable to sync Contract
                $result['logs'][] = "Unable to create renew Ticket for Contract AT{$serviceAccount['extref_at_id']} - CT{$serviceAccount['id']}]: ".$e->getMessage();
            }
        }
    }
    // update balance of Contract related to Service Account
    try {
        eQual::run('do', 'contractika_at_update-contract', ['id' => $serviceAccount['extref_at_id'], 'balance' => $serviceAccount['balance_current']]);
        ServiceAccount::id($id)->update(['has_balance_changed' => false]);
        ++$result['updated'];
    }
    catch(Exception $e) {
        // unable to sync Contract
        $result['logs'][] = "Unable to update Contract in AT [AT{$serviceAccount['extref_at_id']} - CT{$serviceAccount['id']}]: ".$e->getMessage();
        ++$result['failed'];
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
