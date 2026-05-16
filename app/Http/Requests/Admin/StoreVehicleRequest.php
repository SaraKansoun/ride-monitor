<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use App\Services\PermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionCatalog::MANAGE_VEHICLES) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:255', 'unique:vehicles,plate_number'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1980,2100'],
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
        ];
    }
}
