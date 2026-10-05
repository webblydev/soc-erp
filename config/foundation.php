<?php

return [
    /*
    | The first super admin created by AdminUserSeeder (docs/01 §4). The password is
    | required and must be changed on first login.
    */
    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'System Administrator'),
        'username' => env('INITIAL_ADMIN_USERNAME', 'admin'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
