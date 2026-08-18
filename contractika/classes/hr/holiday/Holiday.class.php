<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace contractika\hr\holiday;

class Holiday extends \hr\holiday\Holiday {

    public static function getDescription() {
        return "Holidays are legal days-off imported from AutoTask.
        These entities allow to synch back Autotask by creating Appointments for employees,
        in order to keep track of subsequent unavailability.";
    }

    public static function getColumns() {

        return [

            'extref_at_id' => [
                'type'              => 'integer',
                'unique'            => true,
                'description'       => 'External id (AutoTask ID).'
            ],

            'absences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'contractika\hr\absence\Absence',
                'foreign_field'     => 'holiday_id',
                'description'       => 'The absences relating to the holiday.'
            ]

        ];
    }

}