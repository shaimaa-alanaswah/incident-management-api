<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'string', 'max:255'],
            'assigned_to' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', app('current_tenant')?->id),
            ],
        ];
    }
}
