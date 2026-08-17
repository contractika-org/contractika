<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace inventory\finance\accounting;

class Invoice extends \finance\accounting\Invoice {
    public static function getColumns() {
        return [
            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\sale\customer\Customer',
                'description'       => 'The Customer to who refers the item.',
                'required'          => true,
                'dependencies'      =>['number']
            ],

        ];
    }
}