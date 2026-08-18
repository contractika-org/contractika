<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

use contractika\sale\customer\Customer;
use Dompdf\Dompdf;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

class BonusReport extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'date_to' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Date of the most recent line on the report.',
                'help'              => 'Should be the last day included in the report date-range.',
                'function'          => 'calcDateTo',
                'store'             => true
            ],

            'date_from' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Day after the previous published report last date.',
                'function'          => 'calcDateFrom',
                'store'             => true
            ],

            'link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'description'       => 'URL for generating the PDF version of the report.',
                'function'          => 'calcLink'
            ],

            'report_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\BonusReportLine',
                'foreign_field'     => 'report_id',
                'order'             => 'customer_id',
                'sort'              => 'asc',
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

            'pdf_data' => [
                'type'              => 'computed',
                'result_type'       => 'binary',
                'description'       => 'Raw data of the Report rendered as PDF file.',
                'function'          => 'calcPdfData',
                'store'             => true
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Total amount of points from the lines attached to the Report.',
                'function'          => 'calcTotal',
                'store'             => true
            ]

        ];
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $id => $report) {
            if($report['status'] != 'pending') {
                return ['status' => ['non_removable' => 'Released Reports cannot be deleted.']];
            }
        }
        return parent::candelete($self);
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'transitions' => [
                    'release' => [
                        'policies'    => ['releasable'],
                        'description' => "Release the Report.",
                        'status'	  => 'released'
                    ],
                ]
            ],
            'released' => [
                'readonly'      => true,
                'columns'       => [
                    // override specific fields if necessary
                ],
                'transitions'   => [
                    // release cannot be undone
                ]
            ]
        ];
    }

    public static function getPolicies() {
        return [
            'releasable' => [
                    'description'   => "Provide conditions to check if a collection of Bonus Reports is releasable for a given user.",
                    'function'      => 'policyReleasable'
            ]
        ];
    }

    public static function getActions() {
        return [
            'init' => [
                'description'   => "Provide conditions to check if a collection of Reports is releasable for a given user.",
                'policies'      => [],
                'function'      => 'doInit'
            ]
        ];
    }

    public static function calcTotal($self) {
        $result = [];
        $self->read(['report_lines_ids' => ['total_points']]);
        foreach($self as $id => $report) {
            $result[$id] = round(array_reduce($report['report_lines_ids']->get(true), function ($c, $a) { return $c + $a['total_points']; }, 0), 2);
        }
        return $result;
    }

    public static function calcLink($self) {
        $result = [];
        foreach($self as $id => $report) {
            $result[$id] = '/?get=contractika_bonusreport_print&id='.$id;
        }
        return $result;
    }

    /**
     * Compute the value of the `date` field: the last day included in the report.
     *
     * @var \equal\orm\Collection $self
     */
    public static function calcDateTo($self) {
        $result = [];
        $self->read(['date_from']);
        foreach($self as $id => $report) {
            // last day of the quarter @ 23:59:59
            $result[$id] = strtotime(date("Y-m-t 23:59:59", strtotime("+3 months -1 day", $report['date_from'])));
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
                    [
                        ['status', '<>', 'pending'],
                    ],
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

    /**
     * Generate a PDF version of a Report, intended for printing.
     * @param int   $id         Identifier of the report to print.
     * @param array $params     Accepted params are: show_details: print time entries details (description); show_logs: print point calculation logs.
     *
     */
    public static function calcPdfData($self) {
        $result = [];

        $self->read([
                'id',
                'created',
                'status',
                'date_from',
                'date_to',
                'total',
                'report_lines_ids' => [
                    'customer_id' => ['id', 'name', 'extref_nav_id'],
                    'service_account_id' => ['id', 'name'],
                    'total_points'
                ]
            ]);

        // retrieve target timezone
        // #memo - printed dates are intended to use local time
        $tz = new \DateTimeZone("Europe/Brussels");

        foreach($self as $id => $report) {
            try {
                $lines = [];

                foreach($report['report_lines_ids'] as $line_id => $line) {
                    $lines[] = [
                        'customer'              => $line['customer_id']['name'],
                        'customer_nav_id'       => $line['customer_id']['extref_nav_id'],
                        'service_account'       => $line['service_account_id']['name'],
                        'service_account_id'    => $line['service_account_id']['id'],
                        'points'                => number_format((float) round($line['total_points'], 2), 2, ',', '.')
                    ];
                }

                // sort lines on customer field
                usort($lines, function ($a, $b) {
                    return strcmp($a['customer'], $b['customer']);
                });

                // compose the associative array to feed the template with
                $values = [
                    'created'           => date("d/m/Y", $report['created']),
                    'date_from'         => date("d/m/Y", $report['date_from']),
                    'date_to'           => date("d/m/Y", $report['date_to']),
                    'lines'             => $lines,
                    'total'             => number_format((float) round($report['total'], 2), 2, ',', '.')
                ];

                /*
                    Inject all values into the template
                */

                try {
                    $loader = new TwigFilesystemLoader(QN_BASEDIR."/packages/contractika/views/");
                    $twig = new TwigEnvironment($loader);
                    $template = $twig->load("BonusReport.print.default.html");
                    $html = $template->render($values);
                }
                catch(\Exception $e) {
                    trigger_error("ORM::error while parsing template - ".$e->getMessage(), QN_REPORT_DEBUG);
                    throw new \Exception("template_parsing_issue", QN_ERROR_INVALID_CONFIG);
                }

                /*
                    Convert HTML to PDF
                */

                $dompdf = new Dompdf();
                $dompdf->loadHtml((string) $html);
                $dompdf->render();

                $canvas = $dompdf->getCanvas();
                $font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
                $canvas->page_text(520, 750, "page {PAGE_NUM} / {PAGE_COUNT}", $font, 10, array(0,0,0));

                // get generated PDF raw binary
                $result[$id] = $dompdf->output();
            }
            catch(\Exception $e) {
                trigger_error("ORM::unable to generate PDF Report - ".$e->getMessage(), QN_REPORT_ERROR);
            }
        }

        return $result;
    }

    /**
     * Create BonusReportLines objects based on Report date_from and date_to.
     * We consider only active service accounts marked as candidate for Bonus Report from active Customers.
     * From those, only lines referring to time entries (from task or ticket) that are locked and within the report date range are considered.
     */
    public static function doInit($self) {
        $self->read(['status', 'report_lines_ids', 'date_from'])->read(['date_to']);
        foreach($self as $id => $report) {
            // make sure to create lines only for new reports
            if($report['status'] != 'pending') {
                continue;
            }
            // remove any previously created lines
            BonusReportLine::ids($report['report_lines_ids'])->delete(true);

            $serviceAccounts = ServiceAccount::search([
                    // ['is_active', '=', true],
                    ['is_bonus', '=', true]
                ])
                ->read(['customer_id']);

            foreach($serviceAccounts as $sa_id => $service_account) {
                $lines = SALine::search([
                        ['service_account_id', '=', $sa_id],
                        ['sa_line_class_id', 'in', [1, 2]],
                        // ['is_locked', '=', true],
                        ['date', '>=', $report['date_from']],
                        ['date', '<=', $report['date_to']]
                    ])
                    ->read(['id', 'points'])
                    ->get(true);

                $sum = round(array_reduce($lines, function($c, $a) { return $c + floatval($a['points']); }, 0), 2);

                if($sum > 0.0) {
                    BonusReportLine::create([
                            'report_id'             => $id,
                            'customer_id'           => $service_account['customer_id'],
                            'service_account_id'    => $sa_id,
                            'total_points'          => $sum
                        ]);
                }
            }

        }
        // #memo - total must be refreshed, because it might have been computed while the lines were being generated
        $self->update(['total' => null]);
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

}
