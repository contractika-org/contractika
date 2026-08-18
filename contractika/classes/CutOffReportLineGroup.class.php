<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2023
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika;

class CutOffReportLineGroup extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'report_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\CutOffReport',
                'description'       => 'The Cut-Off report the line relates to.',
                'ondelete'          => 'cascade'
            ],

            'report_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\CutOffReportLine',
                'foreign_field'     => 'report_line_group_id',
                'ondetach'          => 'delete',
                'description'       => 'The lines that relate to the group.',
            ],

            'sa_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'contractika\SACategory',
                'description'       => 'The Service Account Category the line relates to.',
            ],

            'year' => [
                'type'              => 'integer',
                'usage'             => 'date/year',
                'description'       => 'The year the group refers to (from report).',
            ],

            'month' => [
                'type'              => 'integer',
                'usage'             => 'date/month',
                'description'       => 'The month the group refers to (from report).',
            ],

            'total_points' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real:5.2',
                'store'             => true,
                'function'          => 'calcTotalPoints',
                'description'       => "Total points from all service accounts for report's period."
            ],

            'total_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:6.2',
                'store'             => true,
                'function'          => 'calcTotalAmount',
                'description'       => "Total amount from all service accounts balances for report's period."
            ]

        ];
    }

    public static function calcTotalPoints($self) {
        $result = [];
        $self->read(['report_lines_ids' => ['total_points']]);
        foreach($self as $id => $group) {
            $result[$id] = 0.0;
            foreach($group['report_lines_ids'] as $line) {
                if($line['total_points']) {
                    $result[$id] += $line['total_points'];
                }
            }
            $result[$id] = round($result[$id], 2);
        }
        return $result;
    }

    public static function calcTotalAmount($self) {
        $result = [];
        $self->read(['report_lines_ids' => ['total_amount']]);
        foreach($self as $id => $group) {
            $result[$id] = 0.0;
            foreach($group['report_lines_ids'] as $line) {
                if($line['total_amount']) {
                    $result[$id] += $line['total_amount'];
                }
            }
            $result[$id] = round($result[$id], 2);
        }
        return $result;
    }

    public static function canupdate($self) {
        $self->read(['report_id' => ['status']]);
        foreach($self as $id => $group) {
            if($group['report_id']['status'] != 'pending')
                return ['status' => ['not_allowed' => "Released cut-off report cannot be modified."]];
        }
        return parent::canupdate($self);
    }

}
