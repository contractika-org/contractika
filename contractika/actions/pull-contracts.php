<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use contractika\ServiceAccount;
use contractika\SACategory;
use contractika\SAType;
use contractika\sale\customer\Customer;

list($params, $providers) = announce([
    'description'   => 'Updates the list of Contract objects based on list from AT, and create objects that do not exist yet.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'constants'         => ['EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report', 'dispatch' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 * @var \equal\dispatch\Dispatcher        $dispatch
 */
list($context, $reporter, $dispatch) = [ $providers['context'], $providers['report'], $providers['dispatch'] ];

$result = [
    'errors'    => 0,
    'warnings'  => 0,
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'processed' => 0,
    'logs'      => []
];

// preload contract categories
$contract_categories_map = [];
$contractCategories = SACategory::search()->read(['id', 'extref_at_id']);
foreach($contractCategories as $category) {
    $contract_categories_map[$category['extref_at_id']] = $category['id'];
}

// preload contract types
$contract_types_map = [];
$contractTypes = SAType::search()->read(['id', 'extref_at_id']);
foreach($contractTypes as $type) {
    $contract_types_map[$type['extref_at_id']] = $type['id'];
}


try {
    // fetch the latest listing of contracts from AT (using API)
    $data = eQual::run('get', 'contractika_at_contracts');

    foreach($data as $at_contract) {
        // normalize user defined fields (inject UDF values as object properties)
        foreach($at_contract['userDefinedFields'] as $user_defined_field) {
            $at_contract[$user_defined_field['name']] = $user_defined_field['value'];
        }

        // search for local entity (AT contract = Contractika ServiceAccount)
        $serviceAccounts = ServiceAccount::search(['contractId', '=', intval($at_contract['id'])])->read(['id', 'code', 'name']);
        $contract = $serviceAccounts->first();

        // find related customer
        $customer = Customer::search(['extref_at_id','=', $at_contract['companyID']])->read(['id', 'extref_at_id'])->first();

        // #memo - if a Customer linked to a Contract is not found in Contractika, the Customer is effectively inactive (either inactive in AT or not imported yet) and therefore ignored
        if(!$customer) {
            ++$result['ignored'];
            $result['logs'][] = "INFO- unknown referenced AT Company ID {$at_contract['companyID']} for contract ID {$at_contract['id']}";
            continue;
        }
        else {
            if(!$contract) {
                // entity does not exist yet: create it
                $contract = ServiceAccount::create([
                        'name'                  => trim($at_contract['contractName']),
                        // #memo - extref_at_id is an alias
                        'contractId'            => $at_contract['id'],
                        'contactName'           => $at_contract['contactName'],
                        'companyID'             => $at_contract['companyID'],
                        'customer_id'           => $customer['id'],
                        'description'           => $at_contract['description'],
                        'is_active'             => $at_contract['status'],
                        'sa_category_id'        => isset($contract_categories_map[$at_contract['contractCategory']]) ? $contract_categories_map[$at_contract['contractCategory']] : null,
                        'contractCategoryId'    => $at_contract['contractCategory'],
                        'sa_type_id'            => isset($contract_types_map[$at_contract['contractType']]) ? $contract_types_map[$at_contract['contractType']] : null,
                        'contractTypeId'        => $at_contract['contractType'],
                        'startDate'             => strtotime($at_contract['startDate']),
                        'endDate'               => strtotime($at_contract['endDate']),
                        'is_bonus'              => (isset($at_contract['Bonus']) && $at_contract['Bonus'] === 'Yes'),
                        'is_invoiceable'        => (isset($at_contract['CutOff']) && $at_contract['CutOff'] === 'Yes'),
                        'm_reporting'           => isset($at_contract['TSreport']) ? $at_contract['TSreport'] : 'None',
                        'renew_auto'            => (isset($at_contract['SP_Renew_auto']) && $at_contract['SP_Renew_auto'] === 'Yes'),
                        'renew_amount'          => (isset($at_contract['SP_Renew_amount'])) ? floatval($at_contract['SP_Renew_amount']) : 0,
                        'renew_floor'           => (isset($at_contract['SP_Renew_floor'])) ? floatval($at_contract['SP_Renew_floor']) : 0
                    ])
                    ->read(['id', 'name'])
                    ->first();

                // force re-computing has_sa
                Customer::id($customer['id'])
                    ->update(['has_sa' => null])
                    // #memo - 'instant' doesn't seem to work
                    ->read(['has_sa']);

                ++$result['created'];
            }
            else {
                $serviceAccounts->update([
                        'name'                  => trim($at_contract['contractName']),
                        'contactName'           => $at_contract['contactName'],
                        'customer_id'           => $customer['id'],
                        'companyID'             => $at_contract['companyID'],
                        'description'           => $at_contract['description'],
                        'is_active'             => $at_contract['status'],
                        'sa_category_id'        => isset($contract_categories_map[$at_contract['contractCategory']])?$contract_categories_map[$at_contract['contractCategory']]:null,
                        'contractCategoryId'    => $at_contract['contractCategory'],
                        'sa_type_id'            => isset($contract_types_map[$at_contract['contractType']])?$contract_types_map[$at_contract['contractType']]:null,
                        'contractTypeId'        => $at_contract['contractType'],
                        'startDate'             => strtotime($at_contract['startDate']),
                        'endDate'               => strtotime($at_contract['endDate']),
                        'is_bonus'              => (isset($at_contract['Bonus']) && $at_contract['Bonus'] === 'Yes'),
                        'is_invoiceable'        => (isset($at_contract['CutOff']) && $at_contract['CutOff'] === 'Yes'),
                        'm_reporting'           => isset($at_contract['TSreport'])?$at_contract['TSreport']:'None',
                        'renew_auto'            => (isset($at_contract['SP_Renew_auto']) && $at_contract['SP_Renew_auto'] === 'Yes'),
                        'renew_amount'          => (isset($at_contract['SP_Renew_amount']))?floatval($at_contract['SP_Renew_amount']):0,
                        'renew_floor'           => (isset($at_contract['SP_Renew_floor']))?floatval($at_contract['SP_Renew_floor']):0
                    ]);

                ++$result['updated'];
            }
        }

        if($contract) {
            eQual::run('do', 'contractika_serviceaccount_check-company', ['id' => $contract['id']]);
        }
    }
}
catch(Exception $e) {
    /**
     * An unexpected error occurred.
     */

    // send an email alert
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('Contractika ERRORS')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Erreur inattendue lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i')." :</p>
                    <pre>".qn_error_name($e->getCode()).' : '.$e->getMessage()."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
    // relay exception
    throw new Exception($e->getMessage(), $e->getCode());
}

if($result['warnings'] > 0) {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('WARNING Contractika')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Alertes lors de l'exécution du script " . __FILE__ . " au " . date('d/m/Y') . ' à ' . date('H:i') . ":</p>
                    <pre>".$report."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
