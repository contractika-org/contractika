<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate;
use equal\orm\Model;

class Property extends \identity\Establishment {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the property.",
                'required'          => true
            ]

        ];
    }    
}