<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class SAType extends \sale\contract\ContractType {

    public function getTable() {
        return "contractika_satype";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string'
            ],

            'status' => [
                'type'              => 'boolean',
                'default'           => true
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'Code used by AutoTask PSA software for the type.',
                'unique'            => true
            ],

            'service_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\ServiceAccount',
                'foreign_field'     => 'sa_type_id',
                'description'       => 'The type the account (contract) relates to.',
            ]

        ];
    }

}
