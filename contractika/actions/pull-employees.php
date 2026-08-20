<?php
use contractika\hr\employee\Employee;
use contractika\identity\Identity;
use equal\text\TextTransformer;

list($params, $providers) = announce([
    'description'   => 'Updates the list of Employee objects based on latest list from SDWorx.',
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
    'errors'    => 0,
    'warnings'  => 0,
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'processed' => 0,
    'logs'      => []
];

/**
 * Normalizes first and last names with UTF-8 support: Capitalize, no number, space chars accepted: [ -']
 * @example  "charLes-henri o'brian 't Wallant \n van den hoof2";" => "Charles-Henri O'Brian 'T Wallant Van Den Hoof"
 *
 * @param string    $a      String to normalize (UTF-8 supported).
 */
$normalize_name = function ($a) {
    // remove invalid spaces
    $a = str_replace(["\r", "\n", "\t", "  "], ' ', $a);
    // capitalize
    $a = mb_convert_case($a, MB_CASE_TITLE, "UTF-8");
    // remove invalid glyphs (accept letters + latin chars + '(single quote) + -(dash) + spaces)
    $a = preg_replace('/[^\pL \'-]/u', '', $a);
    // remove multiple and trailing spaces
    $res = trim(preg_replace('/\s+/', ' ', $a));
    if(strlen($res) <= 0) {
        $res = 'anonymous';
    }
    return $res;
};

$organisation = Identity::id(1)->read(['name', 'extref_sd_id'])->first();

// fetch the latest listing of employees from SDWorx (using API)
$data = eQual::run('get', 'contractika_sd_employees');

foreach($data as $values) {
    try {
        if($values['EmployerNumber'] != $organisation['extref_sd_id'] || $values['EmployeeNumber'] == '9999999') {
            // employee does not belong to current organisation,
            // or employee is the organisation itself: ignore
            ++$result['ignored'];
            continue;
        }
        $firstname = $normalize_name($values['FirstName']);
        $lastname  = $normalize_name($values['LastName']);
        $normalized_firstname = strtoupper(TextTransformer::normalize($firstname));
        $normalized_lastname = strtoupper(TextTransformer::normalize($lastname));
        $end_date  = ($values['EndDate'])?strtotime($values['EndDate']):null;
        $is_active = ( $end_date == null || ($end_date && $end_date > time()) );

        $ids = Employee::search(['extref_sd_id', '=', $values['EmployeeNumber']])->ids();
        if(count($ids)) {
            ++$result['updated'];
            // employee already exists in db: update its identity
            $collection = Employee::ids($ids)->read(['partner_identity_id']);
            $employee = $collection->first();
            $collection->update([
                    'is_active'     => $is_active,
                    'date_start'    => strtotime($values['StartDate']),
                    'date_end'      => ($values['EndDate']) ? strtotime($values['EndDate']) : null
                ]);
            $ids = Identity::ids($employee['partner_identity_id'])->ids();
            if(!count($ids)) {
                // identity does not exist yet, assign a new one or a matching one
                // search for an identity with matching firstname and lastname
                $ids = Identity::search([['normalized_firstname', '=', $normalized_firstname], ['normalized_lastname', '=', $normalized_lastname]])->ids();
                if(!count($ids)) {
                    $ids = Identity::create([
                            'firstname' => $firstname,
                            'lastname'  => $lastname
                        ])
                        ->read(['id', 'display_name'])
                        ->ids();
                }
                $collection->update(['partner_identity_id' => reset($ids)]);
            }
            else {
                // update related identity
                Identity::ids($employee['partner_identity_id'])
                    ->update([
                        'firstname' => $firstname,
                        'lastname'  => $lastname
                    ])
                    ->read(['id', 'display_name']);
            }
        }
        // no employee with matching SDworx number
        else {
            // search for an identity with matching firstname and lastname
            $identity = Identity::search([['normalized_firstname', '=', $normalized_firstname], ['normalized_lastname', '=', $normalized_lastname]])->read(['id', 'employees_ids'])->first();

            if(!$identity) {
                $identity = Identity::create([
                        'firstname' => $firstname,
                        'lastname'  => $lastname
                    ])
                    ->read(['id', 'display_name'])
                    ->first();
            }
            else {
                Identity::id($identity['id'])
                    ->update([
                        'firstname' => $firstname,
                        'lastname'  => $lastname
                    ])
                    ->read(['id', 'display_name']);
            }

            // if an Employee object already exist, use it and update its SDworx ID (EmployeeNumber)
            if(isset($identity['employees_ids']) && count($identity['employees_ids'])) {
                ++$result['updated'];
                $employee_id = reset($identity['employees_ids']);
                Employee::id($employee_id)
                    ->update([
                        'extref_sd_id'          => $values['EmployeeNumber'],
                        'is_active'             => $is_active,
                        'date_start'            => strtotime($values['StartDate']),
                        'date_end'              => ($values['EndDate'])?strtotime($values['EndDate']):null
                    ]);
            }
            // no Employee object yet : create a new one
            else {
                ++$result['created'];
                Employee::create([
                        'partner_identity_id'   => $identity['id'],
                        'extref_sd_id'          => $values['EmployeeNumber'],
                        'is_active'             => $is_active,
                        'date_start'            => strtotime($values['StartDate']),
                        'date_end'              => ($values['EndDate'])?strtotime($values['EndDate']):null
                    ]);
            }

        }
        ++$result['processed'];
    }
    catch(Exception $e) {
        ++$result['errors'];
        $result['logs'][] = "Error while processing SD id {$values['EmployeeNumber']}: " .$e->getMessage();
    }
}


$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
