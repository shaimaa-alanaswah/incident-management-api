<?php

namespace App\Http\Requests;

use App\Enums\IncidentSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'body' => ['sometimes', 'array'],
        ];
    }
}
