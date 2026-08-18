<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\hr\employee;

use contractika\identity\Identity;

/**
 *  @property string                                $code
 *  @property string                                $extref_sd_id
 *  @property int                                   $extref_at_id
 *  @property int                                   $partner_identity_id
 *  @property \contractika\hr\absence\Absence[]     $absences_ids
 *
 */
class Employee extends \hr\employee\Employee {

    // #memo - we need a distinct table
    // because Employees inherits from Partner
    public function getTable() {
        // #todo - move employees to a table hr_employee_employee
        return 'identity_partner';
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'alias',
                'alias'             => 'code',
                'description'       => '3-letters code relating to the employee.'
            ],

            'display_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => false,
                'function'          => 'calcDisplayName'
            ],

            'code' => [
                'type'              => 'string',
                'unique'            => true,
                'description'       => 'Unique code assigned to the employee (manual).',
                'onupdate'          => 'onupdateCode'
            ],

            'roles_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'contractika\hr\employee\Role',
                'foreign_field'     => 'employees_ids',
                'rel_table'         => 'contractika_hr_rel_role_employee',
                'rel_foreign_key'   => 'role_id',
                'rel_local_key'     => 'employee_id',
                'description'       => 'Roles the employee is assigned to.'
            ],

            'extref_sd_id' => [
                'type'              => 'string',
                'description'       => 'Code used by employment agency partner for the employee (SDworx Employee).',
                'unique'            => true
            ],

            'extref_at_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcExtRefAtId',
                'store'             => true,
                'description'       => 'Code used by PSA software for the identity (AutoTask Resource).',
            ],

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\identity\Identity',
                'description'       => 'The targeted identity.',
                'onupdate'          => 'onupdatePartnerIdentityId',
                'required'          => true
            ],

            'absences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\hr\absence\Absence',
                'foreign_field'     => 'employee_id',
                'description'       => 'Absences relating to the employee.',
            ]

        ];
    }

    public static function onupdateCode($self, $values) {
        $self->read(['partner_identity_id' => ['id', 'code']]);
        foreach($self as $employee) {
            if($values['code'] != $employee['partner_identity_id']['code']) {
                Identity::id($employee['partner_identity_id']['id'])
                    ->update(['code' => $values['code']]);
            }
        }
    }

    public static function calcDisplayName($om, $ids, $lang) {
        $result = [];
        $employees = $om->read(self::getType(), $ids, ['partner_identity_id.display_name'], $lang);
        foreach($employees as $id => $employee) {
            $result[$id] = $employee['partner_identity_id.display_name'];
        }
        return $result;
    }

    public static function calcCode($om, $ids, $lang) {
        $result = [];
        $employees = $om->read(self::getType(), $ids, ['partner_identity_id.code'], $lang);

        foreach($employees as $oid => $employee) {
            $result[$oid] = $employee['partner_identity_id.code'];
        }
        return $result;
    }

    public static function calcExtRefAtId($om, $ids, $lang) {
        $result = [];
        $employees = $om->read(self::getType(), $ids, ['partner_identity_id.extref_at_id'], $lang);

        foreach($employees as $oid => $employee) {
            $result[$oid] = $employee['partner_identity_id.extref_at_id'];
        }
        return $result;
    }

    public function getUnique() {
        // disable parent's constraints
        return [];
    }

    /**
     * Generates a raw XML holding a SOAP envelope matching SDWorx requirements.
     */
    public static function generateXmlPayload($employee_id, $date_from, $date_to, $last_run) {
        $xml = <<<XML
            <soap:Envelope
                xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <GetAbsenceReportExtendedWithSimpleParameters xmlns="http://sdworx.com/hrsskmoabsencewebservicev2"></GetAbsenceReportExtendedWithSimpleParameters>
                </soap:Body>
            </soap:Envelope>
        XML;

        $root = simplexml_load_string( $xml );

        if ($root === false) {
            $errors = libxml_get_errors();
            throw new \Exception('invalid_xml_envelope', QN_ERROR_INVALID_PARAM);
        }

        $body = self::getXmlChildNode($root, 'soap:Body');

        if($body === null) {
            throw new \Exception('invalid_soap_xml', QN_ERROR_INVALID_PARAM);
        }

        $parent = self::getXmlChildNode($body, 'GetAbsenceReportExtendedWithSimpleParameters');

        if($parent === null) {
            throw new \Exception('invalid_sdworx_request', QN_ERROR_INVALID_PARAM);
        }

        // #todo - store in custom settings
        $employer_id = 8658100;

        $parent->addChild('languageId', '2');
        $parent->addChild('fromDate_ddMMyyyy', date('dmY', $date_from));
        $parent->addChild('toDate_ddMMyyyy', date('dmY', $date_to));
        $parent->addChild('requestType', '2');
        $parent->addChild('withOvertime', '1');
        $parent->addChild('employerNumber', (string) $employer_id);
        $parent->addChild('employeeNumber', sprintf("%07d", $employee_id));
        $parent->addChild('changedSinceDate', date('Y-m-d\TH:i:s.000', $last_run));

        // export as formatted XML
        $dom = new \DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($root->asXML());
        return trim($dom->saveXML());
    }

    private static function getXmlChildNode(\SimpleXMLElement $parent, string $name) {
        $prefix = '';
        $parts = explode(':', $name);
        if(count($parts) > 1) {
            $prefix = $parts[0];
            $name = $parts[1];
        }
        $children = $parent->children();
        if(!count($children)) {
            $children = $parent->children($prefix, true);
        }
        foreach($children as $node) {
            if( (string) $node->getName() == $name) {
                return $node;
            }
        }
        return null;
    }

}