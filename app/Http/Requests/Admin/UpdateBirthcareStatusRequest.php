<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBirthcareStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow admin users (system_role_id = 1)
        return auth()->check() && auth()->user()->system_role_id === 1;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = $this->route('action');
        
        $rules = [];
        
        // If rejecting, require a reason
        if ($action === 'reject') {
            $rules['reason'] = ['required', 'string', 'min:5', 'max:500'];
        }
        
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for rejection is required.',
            'reason.min' => 'The rejection reason must be at least 5 characters.',
            'reason.max' => 'The rejection reason cannot exceed 500 characters.',
        ];
    }
}

