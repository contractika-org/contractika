<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\sale\customer;

class Contact extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the contact.'
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'Code used by PSA software (AutoTask) for the Contact.',
                'unique'            => true
            ],

            'firstname' => [
                'type'              => 'string',
                'description'       => "Firstname of the contact.",
                'dependencies'      => ['name']
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Surname of the contact.',
                'dependencies'      => ['name']
            ],

            'language' => [
                'type'              => 'string',
                'description'       => 'Preferred language of the contact.',
                'default'           => 'fr'
            ],

            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Main email address of the contact."
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'description'       => 'The customer the contract relates to.'
            ]

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $res = $om->read(self::getType(), $oids, ['firstname', 'lastname']);
        foreach($res as $oid => $odata) {
            $parts = [];
            if( isset($odata['firstname']) && strlen($odata['firstname']) ) {
                $parts[] = $odata['firstname'];
            }
            if( isset($odata['lastname']) && strlen($odata['lastname'])) {
                $parts[] = $odata['lastname'];
            }
            $result[$oid] = implode(' ', $parts);
        }
        return $result;
    }

}