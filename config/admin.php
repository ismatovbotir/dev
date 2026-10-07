<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel account
    |--------------------------------------------------------------------------
    |
    | The single admin user, created or updated by `php artisan db:seed`.
    |
    */

    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),

];
