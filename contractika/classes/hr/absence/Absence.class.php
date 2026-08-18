<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\hr\absence;

class Absence extends \hr\absence\Absence {


    public static function getColumns() {
        return [
            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'requested',
                    'planned',
                    'approved',
                    'refused',
                    'requesteddeleted',
                    'approveddeleted'
                ],
                'default'           => 'requested'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\hr\employee\Employee',
                'description'       => 'The employee the absence relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'extref_sd_id' => [
                'type'              => 'string',
                'description'       => 'SDworx absence identifier (string: [0-9]{2}-[0-9]{20}).',
                'unique'            => true
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'AutoTask appointment identifier (int).'
            ],

            'is_holiday' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => ''
            ],

            'holiday_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\hr\holiday\Holiday',
                'description'       => 'The holiday the absence relates to, if any.',
                'visible'           => ['is_holiday', '=', true]
            ],

            'layer' => [
                'type'              => 'string',
                'selection'         => [
                    'absences',
                    'planning'
                ],
                'default'           => 'absences'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['employee_id', 'date', 'day_part', 'code_id', 'extref_sd_id']
        ];
    }

}