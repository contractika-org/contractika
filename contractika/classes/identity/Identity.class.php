<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\identity;

use contractika\hr\employee\Employee;
use equal\text\TextTransformer;


class Identity extends \identity\Identity {

    public static function getColumns() {
        return [

            'display_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcDisplayName',
                'store'             => true,
                'instant'           => true,
                'description'       => 'Field used through direct reading in DB, for external reporting.',
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code assigned to the employee (manual).',
                'onupdate'          => 'onupdateCode'
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'Code used by PSA software (AutoTask) for the Resource or Department.',
                'onupdate'          => 'onupdateExtrefAtId',
                // #memo - might not be assigned at creation
                'unique'            => true
            ],

            // for Main organisation only
            'extref_sd_id' => [
                'type'              => 'string',
                'description'       => 'Code used by HR software (SDworx) as Employer identifier (for organisation only).',
                'visible'           => [ ['type', '<>', 'I'], ['id', '<', 10] ]
            ],

            'sa_line_types_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\SALineType',
                'foreign_field'     => 'department_id',
                'description'       => 'The types of services (service account lines) relating to the department.',
                'visible'           => [['has_parent', '=', true], ['id', '<', 10]]
            ],

            'firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role).",
                'visible'           => ['type', '=', 'I'],
                'onupdate'          => 'onupdateFirstname'
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.',
                'visible'           => ['type', '=', 'I'],
                'onupdate'          => 'onupdateLastname'
            ],

            'normalized_firstname' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcNormalizedFirstname',
                'store'             => true,
                'description'       => 'ASCII uppercase firstname for search and comparisons.',
            ],

            'normalized_lastname' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcNormalizedLastname',
                'store'             => true,
                'description'       => 'ASCII uppercase lastname for search and comparisons.',
            ],

            'employees_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\hr\employee\Employee',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'The employees that target the identity (should be one).',
            ]
        ];
    }

    public static function calcDisplayName($self) {
        $result = [];
        $self->read(['firstname', 'lastname']);
        foreach($self as $id => $identity) {
            $result[$id] = strtoupper($identity['lastname']) . ' ' . $identity['firstname'];
        }
        return $result;
    }

    public static function onupdateCode($self, $values) {
        foreach($self as $id => $identity) {
            Employee::search(['partner_identity_id', '=', $id])->update(['code' => $values['code']]);
        }
    }

    public static function calcNormalizedFirstname($om, $oids, $lang) {
        $result = [];
        $identities = $om->read(self::getType(), $oids, ['firstname']);
        if($identities > 0) {
            foreach($identities as $oid => $identity) {
                $result[$oid] = strtoupper(TextTransformer::normalize($identity['firstname']));
            }
        }
        return $result;
    }

    public static function calcNormalizedLastname($om, $oids, $lang) {
        $result = [];
        $identities = $om->read(self::getType(), $oids, ['lastname']);
        if($identities > 0) {
            foreach($identities as $oid => $identity) {
                $result[$oid] = strtoupper(TextTransformer::normalize($identity['lastname']));
            }
        }
        return $result;
    }

    public static function onupdateExtrefAtId($om, $oids, $values, $lang) {
        $identities = $om->read(self::getType(), $oids, ['partners_ids'], $lang);
        foreach($identities as $identity) {
            $om->update('identity\Partner', $identity['partners_ids'], [ 'extref_at_id' => null ], $lang);
        }
    }

    public static function onupdateFirstname($om, $oids, $values, $lang) {
        $om->update(self::getType(), $oids, ['display_name' => null, 'normalized_firstname' => null], $lang);
        // force immediate recompute
        $om->read(self::getType(), $oids, ['display_name', 'normalized_firstname'], $lang);
        $om->call(self::getType(), 'onupdateName', $oids, $values, $lang);
    }

    public static function onupdateLastname($om, $oids, $values, $lang) {
        $om->update(self::getType(), $oids, ['display_name' => null, 'normalized_lastname' => null], $lang);
        // force immediate recompute
        $om->read(self::getType(), $oids, ['display_name', 'normalized_lastname'], $lang);
        $om->call(self::getType(), 'onupdateName', $oids, $values, $lang);
    }

}