<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Self-service profile edit (docs/01 §5.13). Username and email stay admin-managed.
 */
class UpdateProfile
{
    /**
     * @param  array{name?: mixed, phone?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input, ?UploadedFile $avatar = null): User
    {
        $phone = (string) preg_replace('/[\s\-()]/', '', (string) ($input['phone'] ?? ''));

        /** @var array{name: string, phone: ?string} $data */
        $data = Validator::make(['name' => $input['name'] ?? '', 'phone' => $phone === '' ? null : $phone, 'avatar' => $avatar], [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?880|0)1[3-9]\d{8}$/'],
            'avatar' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ])->validate();

        $newAvatar = $avatar?->store('avatars', 'public');
        $oldAvatar = $user->avatar_path;

        try {
            DB::transaction(function () use ($user, $data, $newAvatar): void {
                $user->fill(['name' => $data['name'], 'phone' => $data['phone']]);

                if ($newAvatar) {
                    $user->avatar_path = $newAvatar;
                }

                $user->save();
            });
        } catch (Throwable $exception) {
            if ($newAvatar) {
                Storage::disk('public')->delete($newAvatar);
            }

            throw $exception;
        }

        if ($newAvatar && $oldAvatar !== null && $oldAvatar !== $newAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return $user;
    }
}
