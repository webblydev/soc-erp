<?php

/*
| Notification keys users can opt out of (docs/01 §9). Each module adds its own keys.
| channels: database (in-app inbox), mail, sms (no gateway yet, never sent).
| user.password_reset and security.login_new_ip also go to the inbox (shared services spec S9).
| CRM keys: docs/03 §9, spec R14. HRM keys: docs/09 §7, spec H15. Projects keys: docs/04 §10, spec P20.
| Estimation & Site keys: docs/05 §9, spec E17.
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
        'hrm.document_expiring' => ['label' => 'An employee document is expiring', 'channels' => ['database', 'mail']],
        'hrm.probation_ending' => ['label' => 'A probation period is ending', 'channels' => ['database', 'mail']],
        'hrm.exit_checklist' => ['label' => 'An employee has left', 'channels' => ['database']],
        'projects.assigned_pm' => ['label' => 'You were made project manager', 'channels' => ['database', 'mail']],
        'projects.team_added' => ['label' => 'You were added to a project team', 'channels' => ['database']],
        'projects.tasks.assigned' => ['label' => 'A task was assigned to you', 'channels' => ['database', 'mail']],
        'projects.tasks.mentioned' => ['label' => 'You were mentioned in a task comment', 'channels' => ['database']],
        'projects.tasks.review_requested' => ['label' => 'A task is waiting for your review', 'channels' => ['database']],
        'projects.tasks.due_tomorrow' => ['label' => 'A task is due tomorrow', 'channels' => ['database']],
        'projects.tasks.overdue' => ['label' => 'Overdue tasks summary', 'channels' => ['database', 'mail']],
        'projects.approvals.overdue' => ['label' => 'An approval is overdue', 'channels' => ['database', 'mail']],
        'projects.approvals.status_changed' => ['label' => 'An approval status changed', 'channels' => ['database']],
        'projects.milestone_due' => ['label' => 'A payment milestone is due', 'channels' => ['database', 'mail']],
        'estimation.estimates.submitted' => ['label' => 'An estimate is waiting for your approval', 'channels' => ['database', 'mail']],
        'estimation.estimates.approved' => ['label' => 'Your estimate was approved', 'channels' => ['database']],
        'estimation.estimates.rejected' => ['label' => 'Your estimate was rejected', 'channels' => ['database', 'mail']],
        'site.mb.awaiting_verification' => ['label' => 'Measurements waiting for verification (daily)', 'channels' => ['database', 'mail']],
        'site.findings.assigned' => ['label' => 'A site finding was assigned to you', 'channels' => ['database', 'mail']],
        'site.findings.overdue' => ['label' => 'A site finding is overdue', 'channels' => ['database', 'mail']],
    ],

    /*
    | Roles alerted when they sign in from an IP address they have not used before.
    */
    'new_ip_roles' => ['accountant', 'finance_manager'],
];
