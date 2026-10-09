<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    |
    | "none" means no SMS provider is set up yet: the ticketing desk then offers
    | the message to copy and send by hand (Viber, Messenger, a call) and the
    | "Send SMS" option stays off. "log" writes each message to the application
    | log instead of sending it, for trying the flow out.
    |
    | When a provider is chosen, add its driver to App\Services\SmsSender::deliver()
    | and its credentials under "providers" below, then set SMS_DRIVER in .env.
    |
    */

    'driver' => env('SMS_DRIVER', 'none'),

    // Shown as the sender name where the provider allows one.
    'sender' => env('SMS_SENDER', 'AMEGA'),

    'providers' => [
        // 'semaphore' => ['api_key' => env('SEMAPHORE_API_KEY')],
    ],

];
