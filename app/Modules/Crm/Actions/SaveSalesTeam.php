<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Models\SalesTeamMember;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a sales team and syncs its current members (docs/03 §3.2–3.3, spec R7).
 * Members left out of the list are closed with today's date, never deleted.
 */
class SaveSalesTeam
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?SalesTeam $team = null): SalesTeam
    {
        Gate::forUser($actor)->authorize('crm.teams.manage');

        $input['monthly_target_amount'] = is_string($input['monthly_target_amount'] ?? null)
            ? (str_replace(',', '', trim($input['monthly_target_amount'])) ?: null)
            : ($input['monthly_target_amount'] ?? null);
        $input['business_line_id'] = ($input['business_line_id'] ?? null) ?: null;

        /** @var array{name: string, manager_user_id: int, business_line_id: ?int, monthly_target_amount: ?string, is_active?: bool, members?: list<array{user_id: int, joined_on: string}>} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:80', Rule::unique('sales_teams', 'name')->ignore($team?->id)],
            'manager_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'business_line_id' => ['nullable', new ActiveLookup('business_lines', $team?->business_line_id, fn (Builder $query) => $query->where('is_internal', false))],
            'monthly_target_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'is_active' => ['boolean'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'members.*.joined_on' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'members.*.user_id.distinct' => __('This person is listed twice.'),
        ], [
            'manager_user_id' => __('manager'),
            'business_line_id' => __('business line'),
            'members.*.user_id' => __('member'),
        ])->validate();

        $this->ensureNoOtherActiveTeam($data['members'] ?? [], $team);

        return DB::transaction(function () use ($data, $team): SalesTeam {
            $team ??= new SalesTeam;
            $team->fill(Arr::only($data, ['name', 'manager_user_id', 'business_line_id', 'monthly_target_amount', 'is_active']));
            $team->save();

            $this->syncMembers($team, $data['members'] ?? []);

            return $team;
        });
    }

    /**
     * @param  list<array{user_id: int, joined_on: string}>  $members
     *
     * @throws ValidationException
     */
    private function ensureNoOtherActiveTeam(array $members, ?SalesTeam $team): void
    {
        $errors = [];

        foreach ($members as $index => $member) {
            $other = SalesTeamMember::query()
                ->with('team:id,name')
                ->where('user_id', $member['user_id'])
                ->whereNull('left_on')
                ->when($team !== null, fn ($query) => $query->where('sales_team_id', '!=', $team?->id))
                ->first();

            if ($other !== null) {
                $errors["members.{$index}.user_id"] = __('This person is already in :team.', ['team' => $other->team->name]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array{user_id: int, joined_on: string}>  $members
     */
    private function syncMembers(SalesTeam $team, array $members): void
    {
        $wanted = collect($members)->keyBy('user_id');
        $active = $team->activeMembers()->get()->keyBy('user_id');

        foreach ($active as $userId => $row) {
            if (! $wanted->has($userId)) {
                $row->update(['left_on' => today()]);
            } elseif ($row->joined_on->toDateString() !== $wanted[$userId]['joined_on']) {
                $row->update(['joined_on' => $wanted[$userId]['joined_on']]);
            }
        }

        foreach ($wanted as $userId => $member) {
            if (! $active->has($userId)) {
                $team->members()->create(['user_id' => $userId, 'joined_on' => $member['joined_on']]);
            }
        }
    }
}
