<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class CutOffRecap extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'report_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\CutOffReport',
                'description'       => 'The Cut-Off report the recap relates to.',
                'ondelete'          => 'cascade'
            ],

            'date' => [
                'type'              => 'date',
                'description'       => '',
                'help'              => ''
            ],

            'total' => [
                'type'              => 'float',
                'description'       => ''
            ],

            'ServicePackage' => [
                'type'              => 'float',
                'description'       => 'Flag telling if the report can be released (based on date).',
            ],

            'Provisions' => [
                'type'              => 'float',
                'description'       => 'Groups assigned to the report.',
            ],

            'Regie' => [
                'type'              => 'float',
                'description'       => 'Lines assigned to the report.'
            ]

        ];
    }

}
