<?php

namespace App\Modules\Hrm\Notifications;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

/**
 * hrm.exit_checklist (docs/09 §7): an employee left; HR checks what needs reassigning.
 */
class ExitChecklist extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Employee $employee)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'hrm.exit_checklist';
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __(':name has left', ['name' => $this->employee->full_name]),
            'body' => __('Check projects, tasks and advances for reassignment.'),
            'url' => Route::has('hrm.employees.show') ? route('hrm.employees.show', $this->employee) : null,
        ];
    }
}
