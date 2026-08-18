<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Returns deterministic mock payloads for Contractika external synchronizations without calling external APIs.',
    'params'        => [
        'provider' => [
            'type'              => 'string',
            'description'       => 'External provider to simulate.',
            'selection'         => ['at', 'sd', 'bc', 'ms_dynamics'],
            'required'          => true
        ],
        'resource' => [
            'type'              => 'string',
            'description'       => 'Provider resource to simulate.',
            'default'           => 'catalog'
        ],
        'scenario' => [
            'type'              => 'string',
            'description'       => 'Payload scenario to return.',
            'selection'         => ['nominal', 'empty'],
            'default'           => 'nominal'
        ],
        'limit' => [
            'type'              => 'integer',
            'description'       => 'Maximum number of payload items to return. Zero means no limit.',
            'default'           => 0
        ],
        'envelope' => [
            'type'              => 'boolean',
            'description'       => 'Wrap payload with provider, resource and scenario metadata.',
            'default'           => false
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$provider = strtolower($params['provider']);
if($provider === 'ms_dynamics') {
    $provider = 'bc';
}

$resource = strtolower(str_replace('-', '_', $params['resource']));
$scenario = $params['scenario'];

$payloads = [
    'at' => [
        'billingcode' => [
            [
                'id'             => 29683517,
                'name'           => 'Helpdesk',
                'externalNumber' => 'HD',
                'isActive'       => true,
                'departmentID'   => 29682808
            ],
            [
                'id'             => 29683507,
                'name'           => 'Remote Service',
                'externalNumber' => 'RS',
                'isActive'       => true,
                'departmentID'   => 29682808
            ]
        ],
        'billingitems' => [
            [
                'id'           => 900001,
                'timeEntryID'  => 700001,
                'nonBillable'  => false,
                'postedOnTime' => '2026-01-16T08:00:00Z'
            ]
        ],
        'contacts' => [
            [
                'id'                => 300001,
                'companyID'         => 486001,
                'firstName'         => 'Jane',
                'lastName'          => 'Doe',
                'emailAddress'      => 'jane.doe@example.test',
                'isActive'          => true,
                'primaryContact'    => true,
                'userDefinedFields' => [
                    ['name' => 'Language', 'value' => 'FRA'],
                    ['name' => 'TimeSheets Report', 'value' => 'Yes']
                ]
            ]
        ],
        'contracts' => [
            [
                'id'                => 29683374,
                'companyID'         => 486001,
                'contractName'      => 'ServicePackage Mock',
                'contactName'       => 'Jane Doe',
                'description'       => 'Mock service account contract.',
                'status'            => true,
                'contractCategory'  => 11,
                'contractType'      => 7,
                'startDate'         => '2026-01-01T00:00:00Z',
                'endDate'           => '2026-12-31T00:00:00Z',
                'userDefinedFields' => [
                    ['name' => 'Bonus', 'value' => 'No'],
                    ['name' => 'CutOff', 'value' => 'Yes'],
                    ['name' => 'TSreport', 'value' => 'Send'],
                    ['name' => 'SP_Renew_auto', 'value' => 'Yes'],
                    ['name' => 'SP_Renew_amount', 'value' => '250.00'],
                    ['name' => 'SP_Renew_floor', 'value' => '10.00']
                ]
            ]
        ],
        'customers' => [
            [
                'id'                => 486001,
                'companyName'       => 'Mock Customer',
                'companyType'       => 1,
                'isActive'          => true,
                'parentCompanyID'   => null,
                'taxID'             => 'BE0123456789',
                'lastActivityDate'  => '2026-01-15T10:00:00Z',
                'userDefinedFields' => [
                    ['name' => 'NAV ID', 'value' => 'C-MOCK-001'],
                    ['name' => 'Payment Terms', 'value' => '30D'],
                    ['name' => 'Discount', 'value' => '0.0000'],
                    ['name' => 'Service Price', 'value' => '20.6400'],
                    ['name' => 'Target Margin', 'value' => '0.1500'],
                    ['name' => 'Language', 'value' => 'FRA'],
                    ['name' => 'Travel', 'value' => '1.0000'],
                    ['name' => 'Work Report', 'value' => 'Yes']
                ]
            ]
        ],
        'holidays' => [
            [
                'id'          => 800001,
                'holidayName' => 'Mock Holiday',
                'holidayDate' => '2026-05-01T00:00:00Z'
            ]
        ],
        'projects' => [
            [
                'id'            => 500001,
                'projectNumber' => 'PRJ-MOCK',
                'projectName'   => 'Mock Project',
                'contractID'    => 29683374
            ]
        ],
        'resources' => [
            [
                'id'             => 29682899,
                'firstName'      => 'Alex',
                'lastName'       => 'Worker',
                'email'          => 'alex.worker@example.test',
                'isActive'       => true,
                'defaultRoleID'  => 29683355
            ]
        ],
        'roles' => [
            [
                'id'          => 29683355,
                'name'        => 'Engineer',
                'isActive'    => true,
                'hourlyFactor'=> 1.0
            ]
        ],
        'tasks' => [
            [
                'id'             => 600001,
                'taskNumber'     => 'T-MOCK-001',
                'description'    => 'Mock task',
                'projectID'      => 500001,
                'taskCategoryID' => 2,
                'lastActivityDateTime' => '2026-01-15T12:00:00Z'
            ]
        ],
        'tickets' => [
            [
                'id'               => 8998,
                'ticketNumber'     => 'TK-MOCK-001',
                'title'            => 'Mock ticket',
                'description'      => 'Mock ticket description',
                'contractID'       => 29683374,
                'contactID'        => 300001,
                'priority'         => 2,
                'ticketCategory'   => 108,
                'lastActivityDate' => '2026-01-15T12:00:00Z'
            ]
        ],
        'timeentries' => [
            [
                'id'                   => 700001,
                'billingCodeID'        => 29683517,
                'contractID'           => 29683374,
                'createDateTime'       => '2026-01-15T08:00:00Z',
                'dateWorked'           => '2026-01-15T00:00:00Z',
                'endDateTime'          => '2026-01-15T10:00:00Z',
                'hoursWorked'          => 1.0,
                'isNonBillable'        => false,
                'lastModifiedDateTime' => '2026-01-15T10:05:00Z',
                'offsetHours'          => 0.0,
                'resourceID'           => 29682899,
                'roleID'               => 29683355,
                'startDateTime'        => '2026-01-15T09:00:00Z',
                'summaryNotes'         => 'Mock time entry',
                'taskID'               => null,
                'ticketID'             => 8998,
                'timeEntryType'        => 2
            ]
        ]
    ],
    'bc' => [
        'customers' => [
            [
                'Id'                      => 'C-MOCK-001',
                'Name'                    => 'Mock Customer',
                'Blocked'                 => false,
                'Vat'                     => 'BE0123456789',
                'Discount'                => 0.0,
                'ServicePrice'            => 20.64,
                'PaymentTermsCode'        => '30D',
                'PaymentTermsDescription' => '30 days'
            ]
        ],
        'nav_lines' => [
            [
                'extref_document_no'  => 'INV-MOCK-001',
                'extref_line_uuid'    => '00000000-0000-0000-0000-000000000001',
                'extref_customer'     => 'C-MOCK-001',
                'extref_no'           => 'NTK-SERVICEPACKAGE',
                'extref_description2' => '#AT-29683374#',
                'extref_uom_code'     => 'PNT',
                'extref_unit_price'   => '20.64',
                'extref_quantity'     => '10',
                'extref_amount'       => '206.40',
                'description'         => 'Mock service package credit',
                'date'                => '2026-01-15',
                'points'              => 10.0
            ]
        ],
        'payment_terms' => [
            [
                'Code'        => '30D',
                'Description' => '30 days',
                'Discount'    => 0.0
            ]
        ]
    ],
    'sd' => [
        'absences' => [
            [
                'Id'                      => 'SD-MOCK-ABS-001',
                'EmployerNumber'          => '4056772',
                'EmployeeNumber'          => '0000001',
                'Date'                    => '2026-02-02T00:00:00',
                'DayPart'                 => 'FullDay',
                'InterpretedMeasureUnit'  => 'FullDay',
                'InterpretedAmount'       => '1.00',
                'Layer'                   => 'Planning',
                'Status'                  => 'Approved',
                'AbsenceCodeId'           => 'T350'
            ]
        ],
        'employees' => [
            [
                'EmployerNumber'     => '4056772',
                'EmployeeNumber'     => '0000001',
                'FirstName'          => 'Alex',
                'LastName'           => 'Worker',
                'CurrentWorkscheme'  => '| 7,6 | 7,6 | 7,6 | 7,6 | 7,6 | 0 | 0 |',
                'StartDate'          => '2026-01-01T00:00:00',
                'EndDate'            => null
            ]
        ]
    ]
];

if(!isset($payloads[$provider])) {
    throw new Exception('unknown_mock_provider', EQ_ERROR_INVALID_PARAM);
}

if($resource === 'catalog') {
    $payload = array_keys($payloads[$provider]);
}
elseif(!isset($payloads[$provider][$resource])) {
    throw new Exception('unknown_mock_resource', EQ_ERROR_INVALID_PARAM);
}
elseif($scenario === 'empty') {
    $payload = [];
}
else {
    $payload = $payloads[$provider][$resource];
}

if($params['limit'] > 0) {
    $payload = array_slice($payload, 0, $params['limit']);
}

if($params['envelope']) {
    $payload = [
        'provider' => $provider,
        'resource' => $resource,
        'scenario' => $scenario,
        'count'    => count($payload),
        'items'    => $payload
    ];
}

$context
    ->httpResponse()
    ->status(200)
    ->body($payload)
    ->send();
