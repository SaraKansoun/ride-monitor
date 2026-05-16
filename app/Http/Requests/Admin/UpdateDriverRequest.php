<?php

namespace App\Http\Requests\Admin;

use App\Models\Driver;
use App\Models\User;
use App\Services\PermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionCatalog::MANAGE_DRIVERS) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $driver = $this->route('driver');
        $userId = $driver instanceof Driver ? $driver->user_id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'user_status' => ['required', Rule::in(User::STATUSES)],
            'license_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('drivers', 'license_number')->ignore($this->route('driver')),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Driver::STATUSES)],
        ];
    }
}
