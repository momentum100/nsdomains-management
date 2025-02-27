<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'dynadot' => [
        'api_key' => env('DYNADOT_API_KEY'),
    ],

    'godaddy' => [
        'api_key' => env('GODADDY_API_KEY'),
    ],

    'namecom' => [
        'api_key' => env('NAMECOM_API_KEY'),
    ],

    'namecheap' => [
        'api_key' => env('NAMECHEAP_API_KEY'),
        'username' => env('NAMECHEAP_USERNAME'),
        'client_ip' => env('NAMECHEAP_CLIENT_IP', '127.0.0.1'),
    ],

    'porkbun' => [
        'api_key' => env('PORKBUN_API_KEY'),
        'api_secret' => env('PORKBUN_API_SECRET'),
    ],

    'spaceship' => [
        'api_key' => env('SPACESHIP_API_KEY'),
        'api_secret' => env('SPACESHIP_API_SECRET'),
    ],

];
