<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\CutOffReport;
use contractika\CutOffReportLine;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
    We use this controller as a "print" controller, for printing the result of a Report.
    We force the generation of the resulting PDF, whatever the value of the `pdf_data` field.
*/
list($params, $providers) = eQual::announce([
    'description'   => "Generates a XLS version of a CutOff Report.",
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the report to print.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['users'],
    ],
    'response'      => [
        'content-type'          => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'content-disposition'   => 'inline; filename="report.xlsx"',
        'accept-origin'         => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$report = CutOffReport::id($params['id'])
    ->read([
        'id',
        'date_from',
        'date_to',
        'report_line_groups_ids' => [
            'sa_category_id' => ['name'],
            'total_points',
            'total_amount',
            'report_lines_ids'
        ]
    ])
    ->first();

if(!$report) {
    throw new Exception('unknown_report', QN_ERROR_UNKNOWN_OBJECT);
}

// find previous report, if any
$previous_report = CutOffReport::search(['date_to', '<', $report['date_from']], ['sort' => ['id' => 'desc'], 'limit' => 1])
    ->read([
        'date_to',
        'report_line_groups_ids' => [
            'sa_category_id' => ['name'],
            'total_amount'
        ]
    ])
    ->first();


$doc = new Spreadsheet();
$doc->getProperties()
      ->setCreator('Contractika')
      ->setTitle('Export')
      ->setDescription('CutOff Report exported');

$doc->setActiveSheetIndex(0);

// create sheets
$sheet1 = $doc->getActiveSheet();
$sheet2 = $doc->createSheet();

/* generate sheet 1 */

$sheet1->setTitle("cut-off 1");

// 1) generate head row

$column = 'A';
$row = 1;

$header = [
    [
        'type'  => 'label',
        'value' => 'date'
    ],
    [
        'type'  => 'label',
        'value' => 'NAV ID'
    ],
    [
        'type'  => 'label',
        'value' => 'SA name'
    ],
    [
        'type'  => 'label',
        'value' => 'SA ID'
    ],
    [
        'type'  => 'label',
        'value' => 'Price'
    ],
    [
        'type'  => 'label',
        'value' => 'Total [#]'
    ],
    [
        'type'  => 'label',
        'value' => 'Total [€]'
    ],
    [
        'type'  => 'label',
        'value' => 'Var.'
    ],
    [
        'type'  => 'label',
        'value' => 'Last TE.'
    ],
    [
        'type'  => 'label',
        'value' => 'Comments'
    ]
];

// freeze the first row as header
$sheet1->freezePane('A2');
$sheet1->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet1->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// $sheet1->getPageSetup()->setPrintArea('A1:H210');
$sheet1->getPageSetup()->setPrintArea('A:H');
$sheet1->getPageSetup()->setFitToWidth(1);
$sheet1->getPageSetup()->setFitToHeight(0);

foreach($header as $item) {
    $value = $item['value'];
    if($value == 'date') {
        $value = date('Y-m-d', $report['date_to']);
        // 20 px font size
        $sheet1->getStyle($column.$row)->getFont()->setSize(20);
    }
    $sheet1->getStyle($column.$row)->getFont()->setBold(true);
    // black background
    $sheet1->getStyle($column.$row)
        ->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setRGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
    // white text
    $sheet1->getStyle($column.$row)->getFont()->getColor()->setRGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
    $sheet1->setCellValue($column.$row, $value);
    if($column == 'J') {
        $sheet1->getColumnDimension('J')->setWidth(60);
    }
    else {
        $sheet1->getColumnDimension($column)->setAutoSize(true);
    }
    $sheet1->getStyle($column.$row)->getAlignment()->setWrapText(true);
    ++$column;
}



// 2) generate table lines (with group_by support)

foreach($report['report_line_groups_ids'] as $group_id => $group) {
    ++$row;
    $column = 'A';

    // output the group name (category)
    $sheet1->mergeCells("A$row:J$row");
    $sheet1->setCellValue('A'.$row, $group['sa_category_id']['name']);

    $sheet1->getStyle('A'.$row)->getFont()->setBold(true)->setSize(20);

    $sheet1->getStyle('A'.$row)
        ->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('FF4F81BD');

    $lines = CutOffReportLine::ids($group['report_lines_ids'])->read([
            'customer_id'           => ['name', 'extref_nav_id'],
            'service_account_id'    => ['id', 'name', 'description'],
            'service_price',
            'total_points',
            'total_amount',
            'variation',
            'last_activity'
        ])
        ->get(true);

    // sort lines (from current group) on customer name
    usort($lines, function ($a, $b) {
            return strcasecmp($a['customer_id']['name'], $b['customer_id']['name']);
        });

    foreach($lines as $line) {
        ++$row;
        $column = 'A';

        $value = $line['customer_id']['name'];
        $sheet1->setCellValue($column.$row, $value);
        ++$column;

        $value = $line['customer_id']['extref_nav_id'];
        $sheet1->setCellValue($column.$row, $value);
        ++$column;

        $value = $line['service_account_id']['name'];
        $sheet1->setCellValue($column.$row, $value);
        ++$column;

        $value = $line['service_account_id']['id'];
        $sheet1->setCellValue($column.$row, $value);
        ++$column;

        $value = $line['service_price'];
        $sheet1->setCellValue($column.$row, $value);
        ++$column;

        $value = $line['total_points'];
        $sheet1->setCellValue($column.$row, $value);
        if($value < 0) {
            $sheet1->getStyle($column.$row)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
        }
        $sheet1->getStyle($column.$row)->getNumberFormat()->setFormatCode('#,#0.00;[Red]-#,#0.00;0.00');
        ++$column;

        $value = $line['total_amount'];
        $sheet1->setCellValue($column.$row, $value);
        if($value < 0) {
            $sheet1->getStyle($column.$row)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
        }
        $sheet1->getStyle($column.$row)->getNumberFormat()->setFormatCode('#,#0.00;[Red]-#,#0.00;0.00');
        ++$column;

        $value = ($line['variation'] > 0)?('⬈'):( ($line['variation'] < 0)?'⬊':'⬌');
        $sheet1->setCellValue($column.$row, $value);
        $sheet1->getStyle($column.$row)->getAlignment()->setHorizontal('center');
        ++$column;

        $value = $line['last_activity'];
        if($value) {
            $value = date('Y-m-d', $line['last_activity']);
        }
        $sheet1->setCellValue($column.$row, $value);
        ++$column;

        $value = $line['service_account_id']['description'];
        if(strlen($value)) {
            $sheet1->setCellValue($column.$row, strip_tags($value));
        }
        ++$column;

    }

    ++$row;
    // append one line for sums (points and amounts)
    $sheet1->setCellValue('F'.$row, $group['total_points']);
    $sheet1->getStyle('F'.$row)->getNumberFormat()->setFormatCode('#,#0.00;[Red]-#,#0.00;0.00');
    $sheet1->setCellValue('G'.$row, $group['total_amount']);
    $sheet1->getStyle('G'.$row)->getNumberFormat()->setFormatCode('#,#0.00;[Red]-#,#0.00;0.00');
    $sheet1->getStyle('F'.$row)->getFont()->setBold(true)->setSize(12);
    $sheet1->getStyle('G'.$row)->getFont()->setBold(true)->setSize(12);

    $styleArray = [
        'borders' => [

            'top' => [
                'borderStyle' =>  \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                'color' => ['argb' => '00000000']
            ]
        ]
    ];

    $sheet1->getStyle("F$row:G$row")->applyFromArray($styleArray);
}


/* generate sheet 2 */

$sheet2->setTitle("cut-off 2");

$sheet2->mergeCells("A1:F1");
$sheet2->setCellValue('A1', "Cut-off compta au ".date('d/m/y', $report['date_to']));

$sheet2->getRowDimension('13')->setRowHeight(4);
$sheet2->getRowDimension('17')->setRowHeight(4);
$sheet2->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet2->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

// set style (background & color)
$cells = ['A3', 'C3', 'D3', 'F3', 'A9', 'C9', 'D9', 'F9'];
foreach($cells as $cell) {
    $sheet2->getStyle($cell)
        ->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setRGB('757171');
    // white text
    $sheet2->getStyle($cell)->getFont()->getColor()->setRGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
}

// 20px font size + bold
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(20);
// black background
$sheet2->getStyle('A1')
    ->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()
    ->setRGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
// white text
$sheet2->getStyle('A1')->getFont()->getColor()->setRGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);


$sheet2->getColumnDimension('A')->setWidth(43);
$sheet2->getColumnDimension('B')->setWidth(1);
$sheet2->getColumnDimension('C')->setWidth(12);
$sheet2->getColumnDimension('D')->setWidth(12);
$sheet2->getColumnDimension('E')->setWidth(1);
$sheet2->getColumnDimension('F')->setWidth(12);
$sheet2->getColumnDimension('G')->setWidth(40);

$sheet2->setCellValue('A3', "Category");
$sheet2->getStyle('A3')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A4', "ServicePackage");
$sheet2->getStyle('A4')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A5', "Provisions");
$sheet2->getStyle('A5')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A6', "Régie");
$sheet2->getStyle('A6')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A7', "Solde à rep.");
$sheet2->getStyle('A7')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A9', "NAV");
$sheet2->getStyle('A9')->getAlignment()->setHorizontal('center');



// Extournes (headers)

$sheet2->setCellValue('A10', '="493.010"');
$sheet2->getStyle('A10')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A11', '="493.011"');
$sheet2->getStyle('A11')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A12', '="493.020"');
$sheet2->getStyle('A12')->getAlignment()->setHorizontal('right');

// Produits (headers)

$sheet2->setCellValue('A14', '="493.010"');
$sheet2->getStyle('A14')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A15', '="493.011"');
$sheet2->getStyle('A15')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A16', '="493.020"');
$sheet2->getStyle('A16')->getAlignment()->setHorizontal('right');

// Variations (headers)

$sheet2->setCellValue('A18', '="702.009"');
$sheet2->getStyle('A18')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A19', '="702.010"');
$sheet2->getStyle('A19')->getAlignment()->setHorizontal('right');

$sheet2->setCellValue('A20', '="703.109"');
$sheet2->getStyle('A20')->getAlignment()->setHorizontal('right');



$value = strtotime('2023-01-01');
if($previous_report) {
    $value = $previous_report['date_to'];
}
$sheet2->setCellValue('C3', date('d-m-y', $value));
$sheet2->getStyle('C3')->getAlignment()->setHorizontal('center');

$sheet2->setCellValue('D3', date('d-m-y', $report['date_to']));
$sheet2->getStyle('D3')->getAlignment()->setHorizontal('center');

$sheet2->setCellValue('F3', "variations");
$sheet2->getStyle('F3')->getAlignment()->setHorizontal('right');


$sheet2->setCellValue('C9', "débit");
$sheet2->getStyle('C9')->getAlignment()->setHorizontal('center');

$sheet2->setCellValue('D9', "crédit");
$sheet2->getStyle('D9')->getAlignment()->setHorizontal('center');


$sheet2->mergeCells("F9:G9");
$sheet2->mergeCells("F10:G10");
$sheet2->mergeCells("F11:G11");
$sheet2->mergeCells("F12:G12");

// add previous report (if any) grouped values
if($previous_report) {
    $column = 'C';
    $row = 4;
    foreach($previous_report['report_line_groups_ids'] as $group) {
        $sheet2->setCellValue($column.$row, $group['total_amount']);
        ++$row;
    }
}
else {
    $sheet2->setCellValue('C4', 0);
    $sheet2->setCellValue('C5', 0);
    $sheet2->setCellValue('C6', 0);
}

// add report grouped values
$column = 'D';
$row = 4;
foreach($report['report_line_groups_ids'] as $group) {
    $sheet2->setCellValue($column.$row, $group['total_amount']);
    ++$row;
}

// #memo - removed as of 2023-10-25 ("format number sans séparateur de milliers ni devise (permet le cut and paste dans NAV")
/*
$sheet2->getStyle('C4:D7')
    ->getNumberFormat()
    ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE);

$sheet2->getStyle('C10:D20')
    ->getNumberFormat()
    ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE);
*/

$sheet2->setCellValue('C7', "=SUM(C4:C6)");
$sheet2->setCellValue('D7', "=SUM(D4:D6)");

$sheet2->setCellValue('F4', "=D4-C4");
$sheet2->setCellValue('F5', "=D5-C5");
$sheet2->setCellValue('F6', "=D6-C6");

$sheet2->setCellValue('F7', "=SUM(F4:F6)");

$previous_date_to = strtotime('2023-01-01');

if($previous_report) {
    $previous_date_to = $previous_report['date_to'];
}


// Extournes (débit + crédit)

$sheet2->setCellValue('C10', '=IF(C4>0,C4,"")');
$sheet2->setCellValue('D10', '=IF(C4<0,-C4,"")');
$sheet2->setCellValue('F10', '="Extourne " & A4 & " au " & "'.date('d/m/y', $previous_date_to).'"');

$sheet2->setCellValue('C11', '=IF(C6>0,C6,"")');
$sheet2->setCellValue('D11', '=IF(C6<0,-C6,"")');
$sheet2->setCellValue('F11', '="Extourne " & A6 & " au " & "'.date('d/m/y', $previous_date_to).'"');

$sheet2->setCellValue('C12', '=IF(C5>0,C5,"")');
$sheet2->setCellValue('D12', '=IF(C5<0,-C5,"")');
$sheet2->setCellValue('F12', '="Extourne " & A5 & " au " & "'.date('d/m/y', $previous_date_to).'"');



// Produits (débit + crédit)

$sheet2->setCellValue('C14', '=IF(D4<0,-D4,"")');
$sheet2->setCellValue('D14', '=IF(D4>0,D4,"")');
$sheet2->setCellValue('F14', '="Produit à reporter " & A4 & " au " & "'.date('d/m/y', $report['date_to']).'"');

$sheet2->setCellValue('C15', '=IF(D6<0,-D6,"")');
$sheet2->setCellValue('D15', '=IF(D6>0,D6,"")');
$sheet2->setCellValue('F15', '="Produit à reporter " & A6 & " au " & "'.date('d/m/y', $report['date_to']).'"');

$sheet2->setCellValue('C16', '=IF(D5<0,-D5,"")');
$sheet2->setCellValue('D16', '=IF(D5>0,D5,"")');
$sheet2->setCellValue('F16', '="Produit à reporter " & A5 & " au " & "'.date('d/m/y', $report['date_to']).'"');


// Variations (débit + crédit)

$sheet2->setCellValue('C18', '=IF(F4>0,F4,"")');
$sheet2->setCellValue('D18', '=IF(F4<0,-F4,"")');
$sheet2->setCellValue('F18', '="Variation " & A4 & " au " & "'.date('d/m/y', $report['date_to']).'"');

$sheet2->setCellValue('C19', '=IF(F6>0,F6,"")');
$sheet2->setCellValue('D19', '=IF(F6<0,-F6,"")');
$sheet2->setCellValue('F19', '="Variation " & A6 & " au " & "'.date('d/m/y', $report['date_to']).'"');

$sheet2->setCellValue('C20', '=IF(F5>0,F5,"")');
$sheet2->setCellValue('D20', '=IF(F5<0,-F5,"")');
$sheet2->setCellValue('F20', '="Variation " & A5 & " au " & "'.date('d/m/y', $report['date_to']).'"');



$sheet1->setSelectedCell('A2');
$sheet2->setSelectedCell('A2');

$writer = IOFactory::createWriter($doc, "Xlsx");

ob_start();
$writer->save('php://output');
$output = ob_get_clean();

$context->httpResponse()
        ->body($output)
        ->send();
