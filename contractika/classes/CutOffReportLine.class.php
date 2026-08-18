<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class CutOffReportLine extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'report_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\CutOffReport',
                'description'       => 'The Cut-Off report the line relates to.',
                'ondelete'          => 'cascade'
            ],

            'report_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\CutOffReportLineGroup',
                'description'       => 'The group of lines the lines is attached to (based on sa_category_id).',
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

            'sa_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SACategory',
                'description'       => 'The Service Account Category the line relates to.',
            ],

            'service_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:5.2',
                'store'             => true,
                'function'          => 'calcServicePrice',
                'dependencies'      => ['total_amount']
            ],

            'total_points' => [
                'type'              => 'float',
                'usage'             => 'number/real:5.2',
                'description'       => "Total points from the service account for report's period.",
                'dependencies'      => ['total_amount']
            ],

            'total_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:5.2',
                'store'             => true,
                'function'          => 'calcTotalAmount',
                'description'       => "Total amount of the balance for the service account on report's period.",
            ],

            'variation' => [
                'type'              => 'integer',
                'usage'             => 'number/integer{-1,1}',
                'default'           => 0
            ],

            'last_activity' => [
                'type'              => 'date',
                'description'       => 'Last Time Entry date (only if older than 18 months).'
            ]

        ];
    }

    public static function calcServicePrice($self) {
        $result = [];
        $self->read(['customer_id' => ['service_price']]);
        foreach($self as $id => $line) {
            $result[$id] = round(floatval($line['customer_id']['service_price']), 2);
        }
        return $result;
    }

    public static function calcTotalAmount($self) {
        $result = [];
        $self->read(['total_points', 'service_price']);
        foreach($self as $id => $line) {
            $result[$id] = round(floatval($line['total_points']) * floatval($line['service_price']), 2);
        }
        return $result;
    }

    public static function canupdate($self) {
        $self->read(['report_id' => ['status']]);
        foreach($self as $id => $line) {
            if($line['report_id']['status'] != 'pending')
                return ['status' => ['not_allowed' => "Released cut-off report cannot be modified."]];
        }
        return parent::canupdate($self);
    }
}
