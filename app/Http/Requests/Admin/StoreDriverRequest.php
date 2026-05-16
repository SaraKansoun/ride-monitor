<?php

namespace App\Http\Requests\Admin;

use App\Models\Driver;
use App\Services\PermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'license_number' => ['required', 'string', 'max:255', 'unique:drivers,license_number'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Driver::STATUSES)],
        ];
    }
}
