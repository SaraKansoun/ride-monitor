<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Models\IncidentReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('create', IncidentReview::class) ?? false)
            && $incident->isActive()
            && in_array($incident->status, [Incident::STATUS_PENDING, Incident::STATUS_UNDER_REVIEW], true)
            && ! $incident->activeReview()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fault_decision' => ['required', Rule::in(IncidentReview::FAULT_DECISIONS)],
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
