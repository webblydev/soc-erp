<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds or edits an employee document (docs/09 §3.3, spec H13). The file is an attachment on the
 * employee; uploading again stores the next version. A new expiry date re-arms the reminder.
 */
class SaveEmployeeDocument
{
    public function __construct(private UploadAttachment $uploadAttachment) {}

    /**
     * @param  array<string, mixed>  $input  employee_document_type_id, document_no, issue_date, expiry_date, notes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Employee $employee, array $input, ?UploadedFile $file = null, ?EmployeeDocument $document = null): EmployeeDocument
    {
        Gate::forUser($actor)->authorize('manageDocuments', $employee);

        if ($document !== null && $document->employee_id !== $employee->id) {
            throw new AuthorizationException;
        }

        $input = array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value, $input);

        $validator = Validator::make($input, [
            'employee_document_type_id' => ['required', new ActiveLookup('employee_document_types', $document?->employee_document_type_id)],
            'document_no' => ['nullable', 'string', 'max:60'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['employee_document_type_id' => __('document type')]);

        $validator->after(function ($validator) use ($input): void {
            $type = EmployeeDocumentType::query()->find($input['employee_document_type_id'] ?? 0);

            if ($type?->has_expiry && empty($input['expiry_date'])) {
                $validator->errors()->add('expiry_date', __('A :type needs an expiry date.', ['type' => $type->name]));
            }
        });

        /** @var array<string, mixed> $data */
        $data = $validator->validate();
        $type = EmployeeDocumentType::query()->findOrFail((int) $data['employee_document_type_id']);

        return DB::transaction(function () use ($actor, $employee, $data, $file, $document, $type): EmployeeDocument {
            $document ??= new EmployeeDocument(['employee_document_type_id' => $type->id]);
            $expiryBefore = $document->expiry_date?->toDateString();

            $document->fill($data);
            $document->employee_id = $employee->id;

            if ($document->expiry_date?->toDateString() !== $expiryBefore) {
                $document->expiry_notified_at = null;
            }

            if ($file !== null) {
                $previous = $document->attachment_id !== null ? $document->attachment()->first() : null;
                $attachment = $this->uploadAttachment->handle($employee, $file, $actor, ['title' => $type->name], $previous);
                $document->attachment_id = $attachment->id;
                $document->setRelation('attachment', $attachment);
            }

            $document->save();

            return $document;
        });
    }
}
