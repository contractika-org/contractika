<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use contractika\SALine;

[$params, $providers] = eQual::announce([
    'description'   => 'Checks if entries present in Contractika have been removed in Autotask.',
    'help'          => 'This is necessary because we only get notified of new entries, but not about deletions. \n
                        Besides, once detelete, an entry cannot be found in the AT database.',
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'constants'         => ['EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => [ 'context', 'report' ]
]);


$result = [
    'warnings'      => 0,
    'deleted'       => 0,
    'ignored'       => 0,
    'logs'          => [],
    'deleted_ids'   => '',
    'ignored_ids'   => ''
];

// we check 8 weeks backward for entry removal
$from = strtotime('-8 week');

// #memo - can return up to 10k items
// request AT entries for the covered period
$time_entries = eQual::run('get', 'contractika_at_timeentries', ['date_from' => $from, 'date_field' => 'dateWorked', 'fields' => ['id', 'billingCodeID']]);

$map_at_ids = [];

foreach($time_entries as $time_entry) {
    // #memo - ignore deprecated code for Travels (now included in SALines)
    // #memo - the list of ID to ignore can adapted be over time
    if(in_array((string) $time_entry['billingCodeID'], ['29683328'])) {
		continue;
    }
	$map_at_ids[$time_entry['id']] = true;
}

// request CT entries for the covered period
$lines = SALine::search([['date', '>=', $from],['sa_line_class_id', 'in', [1, 2]]])->read(['timeEntryID', 'is_locked', 'is_orphan']);

// retrieve lines that are in CT but not in AT anymore
$removed_lines_ids = [];
foreach($lines as $id => $line) {
    if($line['is_orphan']) {
        // do not re-check lines marked as orphan (deleted in AT but present in CT)
        continue;
    }
    if(!isset($map_at_ids[$line['timeEntryID']])) {
        if($line['is_locked']) {
            ++$result['warnings'];
            ++$result['ignored'];
            $result['ignored_ids'] .= $line['timeEntryID'].',';
            $result['logs'][] = "WARN- time entry removed in AT but invoiced in CT [{$line['timeEntryID']}]: ignoring deletion";
            SALine::id($id)->update(['is_orphan' => true]);
            continue;
        }
        $removed_lines_ids[] = $id;
        ++$result['deleted'];
        $result['deleted_ids'] .= $line['timeEntryID'].',';
    }
    else {
        // entry present in both AT and CT : nothing to do
    }
}

// delete all lines that are no longer present in AT
SALine::ids($removed_lines_ids)->delete(true);


/**
 * Send email report.
 */

// #memo - we don't send report in case of success
// #todo - send success to another mailbox
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
                    <p>Alertes lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
                    <pre>".$report."</pre>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
}
else {
    // convert result to string
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
            ->setSubject('SUCCESS Contractika')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Exécution réussie du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
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