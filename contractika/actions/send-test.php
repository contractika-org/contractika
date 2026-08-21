<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SA, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\email\Email;
use core\Mail;


// announce script and fetch parameters values
[$params, $providers] = eQual::announce([
    'description'	=>	"Send an instant email with given details with a booking quote as attachment.",
    'params' 		=>	[
    ],
    'access' => [
        'visibility'            => 'private',
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'cron']
]);


// init local vars with inputs
list($context, $cron) = [ $providers['context'], $providers['cron'] ];


// create message
$message = new Email();
$message->setTo('cedricfrancoys@gmail.com')
        ->setSubject('test')
        ->setContentType("text/html")
        ->setBody('<html><body>test lorem ipsum</body></html>');


// queue message
Mail::queue($message);


$context->httpResponse()
        ->status(204)
        ->send();