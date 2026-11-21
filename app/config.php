<?php

return [
    'database' => [
        'host' => getenv('DB_HOST'),
        'port' => getenv('DB_PORT'),
        'name' => getenv('DB_NAME') ,
        'user' => getenv('DB_USER'),
         'password' => getenv('DB_PASSWORD'),
    ],

        'product' => [
        'name' => 'Test product',
        'amount' => '10.000' ,
        'currency' => 'BHD',
    ],
        'gatee' => [
        'url' => getenv('GATEE_URL'),
        'user' => getenv('GATEE_USER'),
        'key' => getenv('GATEE_KEY'),
        'gateway_id' => getenv('GATEE_GATEWAY_ID'),
         'redirect_url' => getenv('GATEE_REDIRECT_URL') ?: '' ,
        'callback_url' => getenv('GATEE_CALLBACK_URL') ?: '', 
        ],
    
    ];