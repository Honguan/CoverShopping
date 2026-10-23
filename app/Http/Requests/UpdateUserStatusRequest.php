<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,active,suspended'],
            'role' => ['nullable', 'in:customer,seller,admin'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = $this->route('user');

            if ($target instanceof User && $target->is($this->user())
                && ($this->input('status') !== 'active' || ($this->filled('role') && $this->input('role') !== $target->role))) {
                $validator->errors()->add('status', __('ui.cannot_change_own_account'));
            }
        }];
    }
}
