<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use contractika\hr\employee\Employee;
use contractika\hr\employee\Role;
use contractika\identity\Identity;


[$params, $providers] = eQual::announce([
    'description'   => 'Enriches the Employee and Identity objects with Resource objects from AT.',
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
['context' => $context, 'report' => $reporter] = $providers;


$result = [
    'ignored'   => 0,
    'created'   => 0,
    'updated'   => 0,
    'processed' => 0,
    'unknown'   => 0,
    'errors'    => 0,
    'logs'      => []
];

/**
 * Normalizes first and last names with UTF-8 support: Capitalize, no number, space chars accepted: [ -']
 * @example  "charLes-henri o'brian 't Wallant \n van den hoof2";" => "Charles-Henri O'Brian 'T Wallant Van Den Hoof"
 *
 * @param string    $a      String to normalize (UTF-8 supported).
 */
$getNormalizedName = function ($a) {
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

$getNormalizedPhone = function ($a) {
    $res = null;
    if($a && is_string($a) && strlen($a) > 0) {
        $res = str_replace([' ', '.'], '', $a);
    }
    return strlen($res > 0) ? $res : null;
};


// fetch the latest listing of resources from AutoTask (using API)
$data = eQual::run('get', 'contractika_at_resources');

// map the returned data set on the "initials" field (when present), to link each Resource to its related Employee object
$resources_map = [];
foreach($data as $at_resource) {
    if(!isset($at_resource['initials']) || empty($at_resource['initials'])) {
        continue;
    }
    // 'initials' is the "Payroll Identifier" field (holds the Employee ID from SDworx)
    $resources_map[$at_resource['initials']] = $at_resource;
}

// remember the processed resources ids
$map_ressources_ids = [];

// fetch all active employees (created with pull-employees)
// #memo - we need to fetch all employees otherwise, activation in AT could have no effect if SDWorx sync is failing
$employees = Employee::search([/*['is_active', '=', true],*/ ['relationship', '=', 'employee']])
    ->read(['id', 'extref_sd_id', 'partner_identity_id']);

foreach($employees as $employee) {
    if(!isset($employee['extref_sd_id']) || !strlen($employee['extref_sd_id'])) {
        // ignore employees with no SDworx ID
        ++$result['ignored'];
        continue;
    }
    $extref_sd_id = $employee['extref_sd_id'];
    if(!isset($resources_map[$extref_sd_id])) {
        // ignore employees not listed as resource
        ++$result['unknown'];
        continue;
    }
    if(isset($employee['partner_identity_id']) && $employee['partner_identity_id'] > 0) {
        ++$result['updated'];
        $resource = $resources_map[$extref_sd_id];
        $map_ressources_ids[$resource['id']] = true;

        Identity::id($employee['partner_identity_id'])
            ->update([
                'extref_at_id'  => $resource['id'],
                'email'         => $resource['email'],
                'gender'        => $resource['gender'],
                'firstname'     => $getNormalizedName($resource['firstName']),
                'lastname'      => $getNormalizedName($resource['lastName']),
                'mobile'        => $getNormalizedPhone($resource['mobilePhone'])
            ])
            ->read(['id', 'display_name']);

        $values = [];
        if(isset($resource['isActive'])) {
            $values['is_active'] = in_array($resource['isActive'], ['1', 1, 'true', true], true);
        }
        if(!is_null($resource['defaultServiceDeskRoleID'])) {
            $role = Role::search(['extref_at_id', '=', $resource['defaultServiceDeskRoleID']])->read(['id'])->first();
            if($role) {
                $values['role_id'] = $role['id'];
            }
        }
        if(count($values)) {
            Employee::id($employee['id'])->update($values);
        }
        ++$result['processed'];
    }
    else {
        ++$result['unknown'];
    }

}

// pass-2 : import employees that are only in AT (history + resources without contract)
foreach($data as $at_resource) {
    // if resource/employee is in AT but not in SD
    if($at_resource['id'] > 0 && !isset($map_ressources_ids[$at_resource['id']])) {
        try {
            // check if an identity with extref_at_id already exist
            $ids = Identity::search([
                    ['extref_at_id', '=', $at_resource['id']],
                    // #memo - unique constraints applies also on archived objects
                    ['state', '<>', 'invalid']
                ])
                ->ids();
            if(!count($ids)) {
                // create identity
                $ids = Identity::create([
                        'firstname'     => $getNormalizedName($at_resource['firstName']),
                        'lastname'      => $getNormalizedName($at_resource['lastName']),
                        'extref_at_id'  => $at_resource['id'],
                        'email'         => $at_resource['email']
                    ])
                    ->read(['id', 'display_name'])
                    ->ids();
                $identity_id = reset($ids);

                // create employee
                Employee::create([
                        'partner_identity_id'   => $identity_id,
                        'is_active'             => $at_resource['isActive'],
                        'date_start'            => $at_resource['hireDate'],
                        'extref_at_id'          => $at_resource['id']
                    ]);

                $result['logs'][] = "created new identity:".implode(',', $at_resource);
                ++$result['created'];
            }
            else {
                // $result['logs'][] = "found matching identity [{$ids[0]}] for AT {$at_resource['id']}";
            }
        }
        catch(Exception $e) {
            ++$result['errors'];
            $result['logs'][] = "Error updating AT resource {$at_resource['id']}: " . $e->getMessage();
        }
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
