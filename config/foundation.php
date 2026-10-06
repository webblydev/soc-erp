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

    /*
    | Attachment rules (FD-BR-08). A document type may narrow the extensions and set its own
    | size limit; without one these apply.
    */
    'attachments' => [
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'dwg', 'dxf', 'xlsx', 'docx', 'zip'],
        'max_size_mb' => 20,
        'disk' => 'private',
    ],
];
