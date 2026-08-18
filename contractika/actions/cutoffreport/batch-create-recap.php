<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use contractika\CutOffRecap;
use contractika\CutOffReport;

list($params, $providers) = eQual::announce([
    'description'   => "Generates a Recap instance for all released Cutoff reports that do not yet have one.",
    'params'        => [],
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


$map_cut_off_reports_ids = [];
$cutOffRecaps = CutOffRecap::search()->read(['report_id']);

foreach($cutOffRecaps as $recap) {
    $map_cut_off_reports_ids[$recap['report_id']] = true;
}

$cutOffReports = CutOffReport::search([['status', '=', 'released'], ['id', 'not in', array_keys($map_cut_off_reports_ids)]])
    ->read([
        'id',
        'date_from',
        'date_to',
        'status',
        'report_line_groups_ids' => [
            'sa_category_id',
            'year',
            'month',
            'total_amount'

        ]
    ]);

foreach($cutOffReports as $report) {
    $values = [
        'report_id'     => $report['id'],
        'date'          => $report['date_to']
    ];
    $total = 0;
    foreach($report['report_line_groups_ids'] as $group) {
        if($group['sa_category_id'] == 2) {
            $values['ServicePackage'] = $group['total_amount'];
            $total += $group['total_amount'];
        }
        elseif($group['sa_category_id'] == 3) {
            $values['Provisions'] = $group['total_amount'];
            $total += $group['total_amount'];
        }
        elseif($group['sa_category_id'] == 4) {
            $values['Regie'] = $group['total_amount'];
            $total += $group['total_amount'];
        }
    }
    $values['total'] = round($total, 2);
    CutOffRecap::create($values);
}

$context->httpResponse()
        ->status(204)
        ->send();
