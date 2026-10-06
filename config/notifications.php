<?php

/*
| Notification keys users can opt out of (docs/01 §9). Each module adds its own keys.
| channels: database (in-app inbox), mail, sms (no gateway yet, never sent).
| user.password_reset and security.login_new_ip also go to the inbox (shared services spec S9).
| CRM keys: docs/03 §9, spec R14.
*/

return [
    'keys' => [
        'user.created' => ['label' => 'Your account was created', 'channels' => ['mail']],
        'user.password_reset' => ['label' => 'An admin reset your password', 'channels' => ['mail', 'database']],
        'security.login_new_ip' => ['label' => 'Sign-in from a new IP address', 'channels' => ['mail', 'database']],
        'crm.lead_assigned' => ['label' => 'A lead was assigned to you', 'channels' => ['database', 'mail']],
        'crm.follow_up_reminder' => ['label' => 'Follow-up reminders', 'channels' => ['database', 'mail']],
        'crm.daily_digest' => ['label' => 'Daily follow-up digest', 'channels' => ['mail']],
        'crm.lead_won' => ['label' => 'A lead was won', 'channels' => ['database']],
        'crm.lead_stale' => ['label' => 'A lead went stale', 'channels' => ['database']],
    ],

    /*
    | Roles alerted when they sign in from an IP address they have not used before.
    */
    'new_ip_roles' => ['accountant', 'finance_manager'],
];
