<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\Report;

list($params, $providers) = eQual::announce([
    'description'   => "Generate a zip archive holding a series of PDF reports.",
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifiers of the targeted reports.',
            'type'              => 'one2many',
            'foreign_object'    => 'contractika\Report',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/zip',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

// generate the zip archive
$tmpfile = tempnam(sys_get_temp_dir(), "zip");
$zip = new ZipArchive();
if($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    // could not create the ZIP archive
    throw new Exception('Unable to create a ZIP file.', QN_ERROR_UNKNOWN);
}

$reports = Report::ids($params['ids'])
    ->read([
        'date',
        'pdf_data',
        'service_account_id' => ['extref_at_id', 'customer_id' => ['extref_at_id']]
    ]);

foreach($reports as $id => $report) {
    $report_name = sprintf("{$report['service_account_id']['customer_id']['extref_at_id']}-{$report['service_account_id']['extref_at_id']}-%s", date("Ym", $report['date']));
    $zip->addFromString($report_name.'.pdf', $report['pdf_data']);
}

$zip->close();

// read raw data
$data = file_get_contents($tmpfile);
unlink($tmpfile);

$context->httpResponse()
        ->status(202)
        ->header('Content-Disposition', 'attachment; filename="export.zip"')
        ->body($data, true)
        ->send();