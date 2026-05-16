<?php

namespace App\Http\Requests;

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIncidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Incident::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $driver = $this->user()?->driverProfile;
        $driverId = $driver instanceof Driver ? $driver->id : 0;
        $driverVehicleTable = (new DriverVehicle)->getTable();

        return [
            'type' => ['required', Rule::in(Incident::TYPES)],
            'description' => ['required', 'string', 'max:5000'],
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists($driverVehicleTable, 'vehicle_id')
                    ->where(fn (Builder $query) => $query
                        ->where('driver_id', $driverId)
                        ->whereNull('unassigned_at')),
            ],
            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,mp4,mov,avi',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/quicktime,video/x-msvideo',
                'max:20480',
            ],
        ];
    }

    /**
     * Get the after validation callbacks for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()?->driverProfile === null) {
                    $validator->errors()->add('driver', 'A driver profile is required to report incidents.');
                }
            },
        ];
    }
}
