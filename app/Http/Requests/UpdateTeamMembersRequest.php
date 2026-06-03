<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('team');

        return $this->user()->can('manageMembers', $team);
    }

    public function rules(): array
    {
        return [
            'members' => ['array'],
            'members.*.employee_id' => ['required', 'exists:employees,id'],
            'members.*.role' => ['required', 'in:member,leader'],
            'members.*.is_primary' => ['boolean'],
        ];
    }
}
