<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

use Exception;

class CutOffReport extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'date_to' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Date of the most recent line on the report (equivalent to date_to).',
                'help'              => 'Should be the last day included in the report date-range.',
                'function'          => 'calcDateTo',
                'store'             => true,
                'instant'           => true
            ],

            'date_from' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Day after the previous published report last date.',
                'help'              => 'There might be some remaining time entries included in report whose date precedes the report\'s date_from.',
                'function'          => 'calcDateFrom',
                'store'             => true
            ],

            'has_pending_report' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if the report can be released (based on date).',
                'default'           => false
            ],

            /*
            'can_release' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag telling if the report can be released (based on date).',
                'function'          => 'calcCanRelease',
                'store'             => true
            ],
            */

            'report_line_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\CutOffReportLineGroup',
                'foreign_field'     => 'report_id',
                'ondetach'          => 'delete',
                'description'       => 'Groups assigned to the report.',
            ],

            'report_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\CutOffReportLine',
                'foreign_field'     => 'report_id',
                'ondetach'          => 'delete',
                'description'       => 'Lines assigned to the report.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',                    // the report is a draft
                    'released'                    // final report: cannot be updated anymore
                ],
                'description'       => 'Status of the report.',
                'default'           => 'pending'
            ],

            'link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'description'       => 'URL for generating the XLS version of the Cut-off Report.',
                'function'          => 'calcLink',
                'readonly'          => true
            ],

            'calculation_log' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Detailed log of the report generation.'
            ],


        ];
    }

    public static function getPolicies() {
        return [
            'releasable' => [
                'description'   => "Provide conditions to check if a collection of Reports is releasable for a given user.",
                'function'      => 'policyReleasable'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'transitions' => [
                    'release' => [
                        'policies'    => ['releasable'],
                        'domain'      => ['has_pending_report', '=', false],
                        'description' => "Releases the Report.",
                        'help'        => "The transition will be allowed only if the report is releasable. This transition cannot be undone.",
                        'status'	  => 'released'
                    ],
                ]
            ],
            'released' => [
                'transitions' => [
                    // release cannot be undone
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'init' => [
                'description'   => "Generates the lines of the Report.",
                'policies'      => [],
                'function'      => 'doInit'
            ]
        ];
    }

    /**
     * Provide the link for generating the PDF version of the Report.
     */
    public static function calcLink($self) {
        $result = [];
        foreach($self as $id => $report) {
            $result[$id] = '/?get=contractika_cutoffreport_print&id='.$id;
        }
        return $result;
    }

    /**
     * Compute the value of the `date_to` field: the last day included in the report.
     *
     * @var \equal\orm\Collection $self
     */
    public static function calcDateTo($self) {
        $result = [];
        $self->read(['created', 'date_from']);
        foreach($self as $id => $report) {
            // last day of the month @ 23:59:59
            $result[$id] = strtotime(date("Y-m-t 23:59:59", $report['date_from']));
        }
        return $result;
    }

    /**
     * Compute the value of the `date_from` field: the day following the date_to of the previous report, or 01/01/2023 if none exists.
     *
     * @var \equal\orm\Collection $self
     */
    public static function calcDateFrom($self) {
        $result = [];
        foreach($self as $id => $report) {
            $previous = self::search([
                    ['status', '<>', 'pending'],
                ],
                [
                    'sort'  => ['date_to' => 'desc'],
                    'limit' => 1
                ])
                ->read(['date_to'])
                ->first();
            // #memo - by convention, no time entry before 01/01/2023 is present in Contractika
            // date_from is the next day of the last day included in previous released report
            $result[$id] = ($previous)?(strtotime('+1 day', $previous['date_to'])):strtotime("2023-01-01");
        }
        return $result;
    }

    public static function policyReleasable($self, $user_id) {
        $result = [];
        $self->read(['created', 'date_to']);
        $today = time();
        foreach($self as $id => $report) {
            if($report['date_to'] > $today) {
                $result[$id] = ['not_releasable' => "Report cannot be released before the end of the period it covers."];
            }
        }
        return $result;
    }

    public static function doInit($self) {
        // #memo - groups are automatically added in `oncreate()`
        $self->read([
                'status',
                'date_from',
                'report_line_groups_ids' => ['id', 'sa_category_id']
            ])
            // force reading date_to as step 2 (depends on date_from)
            ->read(['date_to']);

        $now = time();
        // number of seconds in 12 months (used for test on SA Line age)
        $months_12 = 86400 * 365;

        foreach($self as $id => $cutOffReport) {
            // keep track of warnings & errors
            $logs = [];

            // make sure to create lines only for new reports
            if($cutOffReport['status'] != 'pending') {
                continue;
            }

            // build associative array mapping categories with groups
            $map_groups = [];
            foreach($cutOffReport['report_line_groups_ids'] as $group) {
                $map_groups[$group['sa_category_id']] = $group['id'];
            }

            // retrieve the latest previously published report
            $previousReport = self::search([
                        ['status', '<>', 'pending']
                    ],
                    [
                        'sort'  => ['id' => 'desc'],
                        'limit' => 1
                    ]
                )
                ->read(['id'])
                ->first();

            $serviceAccounts = ServiceAccount::search([
                        ['is_active', '=', true],
                        ['is_invoiceable', '=', true]
                    ]
                )
                ->read([
                    'id',
                    'name',
                    'reporting_from',
                    'sa_category_id',
                    'customer_id' => ['id', 'name', 'is_active']
                ]);

            foreach($serviceAccounts as $sa_id => $serviceAccount) {
                // filter SA to keep only Service Account Categories: ‘Provisions’[3], ‘ServicePackage’ [2], ‘Régie’ [4]
                if(!in_array($serviceAccount['sa_category_id'], [2, 3, 4])) {
                    continue;
                }

                // discard inactive users
                if(!$serviceAccount['customer_id']['is_active']) {
                    continue;
                }
                // ignore SA for which reporting has not started yet
                if($serviceAccount['reporting_from'] > $cutOffReport['date_to']) {
                    continue;
                }

                // 1) compute previous balance
                $balance = 0.0;
                // retrieve init balance from latest published report, if any
                if($previousReport) {
                    $previousLine = CutOffReportLine::search([
                                ['report_id', '=', $previousReport['id']],
                                ['service_account_id', '=', $sa_id]
                            ],
                            [
                                'sort'  => ['id' => 'desc'],
                                'limit' => 1
                            ]
                        )
                        ->read(['total_points', 'total_amount'])
                        ->first();
                    $balance = $previousLine['total_points'];
                }
                // no previous CO report, fallback to SA report, if any (there should be one)
                else {
                    $previousSAReport = Report::search([
                                ['service_account_id', '=', $sa_id],
                                ['status', '<>', 'pending'],
                                ['date', '<', $cutOffReport['date_from']],
                            ],
                            [
                                'sort'  => ['date' => 'desc'],
                                'limit' => 1
                            ]
                        )
                        ->read(['id', 'balance_new'])
                        ->first();
                    if($previousSAReport) {
                        $balance = $previousSAReport['balance_new'];
                    }
                }

                // new balance must match the new_balance of the related Report (same Service Account) for the corresponding period, if any

                $report = Report::search([
                        ['service_account_id', '=', $sa_id],
                        ['date', '>=', $cutOffReport['date_from']],
                        ['date', '<=', $cutOffReport['date_to']],
                    ])
                    ->read(['id','status', 'balance_new'])
                    ->first();

                if(!$report) {
                    // some Report were not generated (yet)
                    // #memo - prevent the release of current Cut-off Report (is exclusively a draft)
                    self::id($id)->update(['has_pending_report' => true]);
                    $logs[] = "Cut-off Report {$id} : no report found for ServiceAccount {$sa_id} ({$serviceAccount['name']} {$serviceAccount['customer_id']['name']}) for period ".date("m/Y", $cutOffReport['date_from']);
                    trigger_error("APP::Cut-off Report {$id} : no report found for ServiceAccount {$sa_id} ({$serviceAccount['name']} {$serviceAccount['customer_id']['name']}) for period ".date("m/Y", $cutOffReport['date_from']), QN_REPORT_WARNING);
                    continue;
                }

                // #memo - when releasing a Cutoff report we need to know if it was generated based on a pending Report (which might have been regenerated or published in the meanwhile)
                if($report['status'] == 'pending') {
                    self::id($id)->update(['has_pending_report' => true]);
                    $logs[] = "Cut-off Report {$id}: pending report found ({$report['id']}) for ServiceAccount {$sa_id} ({$serviceAccount['name']} {$serviceAccount['customer_id']['name']}) for period ".date("m/Y", $cutOffReport['date_from']);
                    trigger_error("APP::Cut-off Report {$id} : pending report found ({$report['id']}) for ServiceAccount {$sa_id} ({$serviceAccount['name']} {$serviceAccount['customer_id']['name']}) for period ".date("m/Y", $cutOffReport['date_from']), QN_REPORT_WARNING);
                }

                $new_balance = $report['balance_new'];

                $last_activity = null;
                // retrieve date of latest SA LINE
                $last_line = SALine::search(['service_account_id', '=', $sa_id], ['sort' => ['date' => 'desc'], 'limit' => 1])->read(['date'])->first();

                // add latest SA LINE if older thant 12 months
                if( $last_line && ($now-$last_line['date']) > $months_12 ) {
                    $last_activity = $last_line['date'];
                }

                CutOffReportLine::create([
                        'report_id'             => $id,
                        'report_line_group_id'  => $map_groups[$serviceAccount['sa_category_id']],
                        'customer_id'           => $serviceAccount['customer_id']['id'],
                        'service_account_id'    => $sa_id,
                        'total_points'          => $new_balance,
                        'sa_category_id'        => $serviceAccount['sa_category_id'],
                        'variation'             => ($new_balance < $balance)?-1:(($new_balance > $balance)?1:0),
                        'last_activity'         => $last_activity
                    ]);
            }
            // #memo - total must be refreshed, because it might have been computed while the lines were being generated
            $cutOffReport['report_line_groups_ids']->update(['total_points' => null, 'total_amount' => null]);

            // store the report generation log
            self::id($id)->update(['calculation_log' => implode('<br />', $logs)]);
        }
    }

    public static function oncreate($self) {
        // generate the groups (by category)
        $self->read(['date_from']);
        foreach($self as $id => $report) {
            $year = intval(date('Y', $report['date_from']));
            $month = intval(date('m', $report['date_from']));
            // ‘Provisions’[3], ‘ServicePackage’ [2], ‘Régie’ [4]
            foreach([2, 3, 4] as $category_id) {
                CutOffReportLineGroup::create([
                    'report_id'         => $id,
                    'year'              => $year,
                    'month'             => $month,
                    'sa_category_id'    => $category_id
                ]);
            }
        }
    }

    public static function canupdate($self) {
        $self->read(['status']);
        foreach($self as $id => $report) {
            if($report['status'] != 'pending')
                return ['status' => ['not_allowed' => "Released cut-off report cannot be modified."]];
        }
        return parent::canupdate($self);
    }
}
