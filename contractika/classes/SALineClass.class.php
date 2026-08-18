<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class SALineClass extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'default'           => true
            ],

            'sa_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\SALine',
                'foreign_field'     => 'sa_line_type_id',
                'description'       => 'The lines that relates to the class.'
            ]

        ];
    }

}
