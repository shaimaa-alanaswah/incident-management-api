<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEscalationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'repeat_count' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_order' => ['required', 'integer'],
            'steps.*.delay_minutes' => ['required', 'integer', 'min:1'],
            'steps.*.notify_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', app('current_tenant')?->id),
            ],
            'steps.*.notify_channel' => ['required', Rule::enum(NotificationChannel::class)],
        ];
    }
}
