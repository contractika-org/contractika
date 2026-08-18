<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\hr\employee;

class Role extends \hr\employee\Role {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'alias',
                'alias'             => 'code'
            ],

            'fullname' => [
                'type'              => 'string',
                'description'       => 'Full name of  the role.'
            ],

            'extref_at_id' => [
                'type'              => 'integer',
                'description'       => 'Code used by PSA software (AutoTask) for the Role.',
                // #memo - might not be assigned at creation
                'unique'            => true
            ],

            // we keep the code but make it optional
            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code identifying the role.',
                'unique'            => true
            ],

            'is_active' => [
                'type'              => 'boolean',
                'default'           => true,
                'description'       => "Is the pricelist currently applicable?"
            ],

            'hourly_factor' => [
                'type'              => 'float',
                'usage'             => 'amount/rate:2',
                'description'       => 'Factor to apply for tasks performed by the role.',
            ],

            'hourly_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Fare rate to apply for tasks performed by the role.',
            ],

            'employees_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'contractika\hr\employee\Employee',
                'foreign_field'     => 'roles_ids',
                'rel_table'         => 'contractika_hr_rel_role_employee',
                'rel_foreign_key'   => 'employee_id',
                'rel_local_key'     => 'role_id',
                'description'       => 'Employees that are assigned to the Role.'
            ]

        ];
    }

    public static function calcCode($om, $ids, $lang) {
        $result = [];
        $employees = $om->read(self::getType(), $ids, ['partner_identity_id.code'], $lang);

        foreach($employees as $oid => $employee) {
            $result[$oid] = $employee['partner_identity_id.code'];
        }
        return $result;
    }

    public static function calcExtRefATId($om, $ids, $lang) {
        $result = [];
        $employees = $om->read(self::getType(), $ids, ['partner_identity_id.extref_at_id'], $lang);

        foreach($employees as $oid => $employee) {
            $result[$oid] = $employee['partner_identity_id.extref_at_id'];
        }
        return $result;
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