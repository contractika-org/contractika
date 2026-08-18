<?php
use contractika\hr\employee\Employee;
use contractika\hr\employee\Role;
use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => 'Updates the list of Role objects based on list from AT, and create objects that do not exist yet.',
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
    'ignored' => 0,
    'created' => 0,
    'updated' => 0,
    'errors'  => 0,
    'unknown' => 0
];

// fetch the latest listing of roles from AT (using API)
$data = eQual::run('get', 'contractika_at_roles');

// fetch all active employees
$employees = Employee::search(['is_active', '=', true])->read(['id', 'extref_at_id']);
$resources_map = [];

foreach($data as $at_role) {
    // search for local entity
    $roles = Role::search(['extref_at_id', '=', $at_role['id']])->read(['id', 'code', 'name']);
    $role = $roles->first();
    if(!$role) {
        ++$result['created'];
        // entity does not exist yet: create it
        Role::create([
                'fullname'      => $at_role['name'],
                'description'   => $at_role['description'],
                'extref_at_id'  => $at_role['id'],
                'hourly_factor' => $at_role['hourlyFactor'],
                'hourly_rate'   => $at_role['hourlyRate'],
                'is_active'     => $at_role['isActive']
            ])
            ->read(['id', 'name'])
            ->first();
    }
    else {
        ++$result['updated'];
        $roles->update([
            'fullname'      => $at_role['name'],
            'description'   => $at_role['description'],
            'hourly_factor' => $at_role['hourlyFactor'],
            'hourly_rate'   => $at_role['hourlyRate'],
            'is_active'     => $at_role['isActive']
        ]);
    }
}


$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
