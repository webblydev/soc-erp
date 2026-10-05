<?php

/*
| Notification keys users can opt out of (docs/01 §9). Delivery is built in sub-project 3;
| each module adds its own keys. channels: database, mail, sms.
*/

return [
    'keys' => [
        'user.created' => ['label' => 'Your account was created', 'channels' => ['mail']],
        'user.password_reset' => ['label' => 'An admin reset your password', 'channels' => ['mail']],
        'security.login_new_ip' => ['label' => 'Sign-in from a new IP address', 'channels' => ['mail']],
    ],
];
