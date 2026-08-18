<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class BonusReportLine extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'report_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\BonusReport',
                'description'       => 'The Cut-Off report the line relates to.',
                'ondelete'          => 'cascade'
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\sale\customer\Customer',
                'description'       => 'Customer the line relates to (depending on service account).'
            ],

            'service_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\ServiceAccount',
                'description'       => 'The Service Account (contract) the line relates to.',
            ],

            'total_points' => [
                'type'              => 'float',
                'usage'             => 'number/real:3.2',
                'description'       => "Total points from the service account for report's period."
            ]

        ];
    }

}
