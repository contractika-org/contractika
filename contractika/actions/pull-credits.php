<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use contractika\NAVLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Import (create) NAVLine from NAVISION for reconciliation as SALine.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'constants'     => [ 'PROVIDERS_BC_TENANT_ID' ],
    'providers'     => [ 'context', 'report' ]
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\error\Reporter             $reporter
 */
list($context, $reporter) = [ $providers['context'], $providers['report'] ];

$result = [
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'processed' => 0,
    'logs'      => []
];


$environment = 'Production';
$company = 'NETiKA IT Services';

$NTK_SERVICEPACKAGE_id = 'eab0d2af-5278-f011-b976-00155d2dad15';
$NTK_PROVISION_id = 'ecb2d2af-5278-f011-b976-00155d2dad15';

$tenant_id = constant('PROVIDERS_BC_TENANT_ID');
$entrypoint_url = "https://api.businesscentral.dynamics.com/v2.0";

$data = eQual::run('get', 'contractika_bc_token', []);

$token = $data['token'];


// prod
$min_date = date('Y-m-d', strtotime("-2 month"));

$nav_lines_ids = [];


/* step-1 - fetch lines from invoices */
$request = new HttpRequest("GET {$entrypoint_url}/{$tenant_id}/{$environment}/api/v2.0/salesInvoices");

$response = $request
    ->body([
        '$count'    => 'true',
        'company'   => $company,
        '$select'   => 'number,postingDate,customerNumber,customerName,status',
        '$filter'   => 'postingDate ge ' . $min_date . ' and contains(number,\'-\')',
        '$expand'   => 'salesInvoiceLines($select=id,lineObjectNumber,description,description2,quantity,unitPrice,amountExcludingTax ; $filter= itemId eq '.$NTK_SERVICEPACKAGE_id.' or itemId eq '. $NTK_PROVISION_id . ')'
    ])
    ->header('Authorization', "Bearer $token")
    ->send();


// check response status
$status = $response->getStatusCode();

if($status != 200) {
    ob_start();
    print_r($response->body());
    $out = ob_get_clean();
    $reporter->error('Error fetching Invoices ' . $out);
    // upon request rejection, we stop the whole job
    throw new Exception("Error fetching Invoices - Request to BC rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();

foreach($data['value'] as $invoice) {
    foreach($invoice['salesInvoiceLines'] as $invoice_line) {
        $line = NAVLine::search([
                ['extref_document_no', '=', $invoice['number']],
                ['extref_line_uuid', '=', $invoice_line['id']]
            ])
            ->read(['id', 'has_error'])
            ->first();

        if($line) {
            // if has_error, update Description2
            if($line['has_error']) {
                // #memo - will trigger reset of service_account_id and has_error
                NAVLine::id($line['id'])
                    ->update(['has_error_service_account' => false])
                    ->update(['extref_description2' => $invoice_line['description2']]);
                $nav_lines_ids[] = $line['id'];
                ++$result['updated'];
            }
            // otherwise, ignore
            else {
                continue;
            }
        }
        else {
            $line = NAVLine::create([
                    'extref_document_no'    => $invoice['number'],
                    'extref_line_uuid'      => $invoice_line['id'],
                    'extref_customer'       => $invoice['customerNumber'],
                    'extref_no'             => $invoice_line['lineObjectNumber'],
                    'extref_description2'   => $invoice_line['description2'],
                    'extref_unit_price'     => (string) floatval($invoice_line['unitPrice']),
                    'extref_quantity'       => (string) floatval($invoice_line['quantity']),
                    'extref_amount'         => (string) floatval($invoice_line['amountExcludingTax']),
                    'description'           => $invoice_line['description'],
                    'date'                  => strtotime($invoice['postingDate']),
                    'points'                => floatval($invoice_line['quantity'])
                ])
                ->read(['id'])
                ->first();

            $nav_lines_ids[] = $line['id'];
            ++$result['created'];
        }
    }
}



/* step-2 - fetch lines from credit notes */

$request = new HttpRequest("GET {$entrypoint_url}/{$tenant_id}/{$environment}/api/v2.0/salesCreditMemos");

$response = $request
    ->body([
        '$count'    => 'true',
        'company'   => $company,
        '$select'   => 'number,postingDate,customerNumber,customerName',
        '$filter'   => 'postingDate ge ' . $min_date,
        '$expand'   => 'salesCreditMemoLines($select=id,lineObjectNumber,description,description2,quantity,unitPrice,amountExcludingTax;$filter=itemId eq ' . $NTK_SERVICEPACKAGE_id . ' or itemId eq ' . $NTK_PROVISION_id . ')'
    ])
    ->header('Authorization', "Bearer $token")
    ->send();


// check response status
$status = $response->getStatusCode();

if($status != 200) {
    ob_start();
    print_r($response->body());
    $out = ob_get_clean();
    $reporter->error('Error fetching CreditMemo ' . $out);
    // upon request rejection, we stop the whole job
    throw new Exception("Error fetching CreditMemo - Request to BC rejected with code $status", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();


foreach($data['value'] as $credit_note) {

    foreach($credit_note['salesCreditMemoLines'] as $credit_note_line) {

        $line = NAVLine::search([
                ['extref_document_no', '=', $credit_note['number']],
                ['extref_line_uuid', '=', $credit_note_line['id']]
            ])
            ->read(['id', 'has_error'])
            ->first();

        if($line) {
            // if has_error, update Description2
            if($line['has_error']) {
                // #memo - will trigger reset of service_account_id and has_error
                NAVLine::id($line['id'])
                    ->update(['has_error_service_account' => false])
                    ->update(['extref_description2' => $credit_note_line['description2']]);
                $nav_lines_ids[] = $line['id'];
                ++$result['updated'];
            }
            // otherwise, ignore
            else {
                continue;
            }
        }
        else {
            $line = NAVLine::create([
                    'extref_document_no'    => $credit_note['number'],
                    'extref_line_uuid'      => $credit_note_line['id'],
                    'extref_customer'       => $credit_note['customerNumber'],
                    'extref_no'             => $credit_note_line['lineObjectNumber'],
                    'extref_description2'   => $credit_note_line['description2'],
                    'extref_unit_price'     => (string) floatval($credit_note_line['unitPrice']),
                    'extref_quantity'       => (string) floatval($credit_note_line['quantity']),
                    'extref_amount'         => (string) floatval($credit_note_line['amountExcludingTax']),
                    'description'           => $credit_note_line['description'],
                    'date'                  => strtotime($credit_note['postingDate']),
                    'points'                => (-1.0) * floatval($credit_note_line['quantity'])
                ])
                ->read(['id'])
                ->first();

            $nav_lines_ids[] = $line['id'];
            ++$result['created'];
        }
    }
}

// force generating computed fields
if(count($nav_lines_ids)) {
    NAVLine::ids($nav_lines_ids)
        ->read(['customer_id', 'service_account_id'])
        ->read(['has_error']);
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
