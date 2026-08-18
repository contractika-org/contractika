<?php
use core\setting\Setting;
use contractika\sale\customer\Customer;

list($params, $providers) = announce([
    'description'   => 'Update AutoTask according to latest values for Customer objects retrieved from Navision.',
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

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [
    'ignored' => 0,
    'updated' => 0,
    'failed'  => 0,
    'logs'    => []
];

// retrieve last_run from settings (defaults to 'all times')
$last_run = Setting::get_value('contractika', 'sync', 'at_sync_customers.last_run', 0);

// 1) retrieve all customers modified since last sync
$customers = Customer::search([ ['modified', '>=', $last_run] ])
    ->read([
        'extref_at_id',
        'is_active',
        'discount',
        'service_price',
        'target_margin',
        'payment_terms',
        'partner_identity_id' => [
            'vat_number'
        ]
    ]);


// 2) patch companies based on local values
foreach($customers as $customer) {
    if(!isset($customer['extref_at_id'])) {
        // skip irrelevant customers
        ++$result['ignored'];
        $result['logs'][] = 'OK  - ignored customer with no AT id: '.$customer['name'];
        continue;
    }

    try {
        $data = eQual::run('do', 'contractika_at_update-company', [
                'id'            => $customer['extref_at_id'],
                'is_active'     => $customer['is_active'],
                'vat_id'        => format_vat($customer['partner_identity_id']['vat_number']),
                'discount'      => floatval($customer['discount']),
                'payment_terms' => ($customer['payment_terms'])?$customer['payment_terms']:'',
                'service_price' => $customer['service_price'],
                // #memo - this field had been set in Navision before AutoTask was being used, it is no longer needed (input is made directly within AT)
                // 'target_margin' => $customer['target_margin']
            ]);
        ++$result['updated'];
        $result['logs'][] = 'OK  - synced (updated) customer AT'.$customer['extref_at_id'].', '.$customer['name'];
    }
    catch(Exception $e) {
        ++$result['failed'];
        $reporter->warning("Cannot update company: ".$e->getMessage());
        $result['logs'][] = 'NOK - error updating customer AT'.$customer['extref_at_id'].', '.$customer['name'].':'.$e->getMessage();
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();


function format_vat($vat_id) {
    $vat_id = strtoupper(str_replace([' ', '.', '-'], '', $vat_id));
    $len = strlen($vat_id);
    if(substr($vat_id, 0, 2) == 'BE') {
        // 'BE' + 10 digits
        return substr($vat_id, 0, 2).' '.substr($vat_id, 2, 4).'.'.substr($vat_id, 6, 3).'.'.substr($vat_id, 9);
    }
    /*
        FR : 	'FR' + 2 digits (as validation key ) + 9 digits (as SIREN)
        example: FR 32 123456789

        DE: 'DE' + 9 digits
        example: DE 123456789
    */
    return substr($vat_id, 0, 2).' '.substr($vat_id, 2);
}