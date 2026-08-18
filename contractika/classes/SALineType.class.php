<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class SALineType extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'externalNumber' => [
                'type'              => 'string',
                'description'       => 'Invoicing code used for display only (might be duplicates).'
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'External identifier used by AutoTask PSA software for related BillingCode entity.',
                'required'          => true
            ],

            'isActive' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the type as active (update through sync).',
                'default'           => true
            ],

            'department_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\identity\Identity',
                'description'       => 'The department the type refers to.',
                'domain'            => [['has_parent', '=', true], ['parent_id', '=', 1]]
            ],

            'sa_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\SALine',
                'foreign_field'     => 'sa_line_type_id',
                'description'       => 'The lines that relates to the type.',
            ]

        ];
    }

}
