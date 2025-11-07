<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterBirthcareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow authenticated owners to register a birthcare
        // The backend controller will handle subscription creation (free trial)
        return auth()->check() && auth()->user()->system_role_id === 2;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = auth()->user();
        $existingBirthcare = \App\Models\BirthCare::where('user_id', $user->id)->first();
        $isResubmit = $existingBirthcare && $existingBirthcare->status === 'rejected';
        
        // If resubmitting, documents are optional (keep existing if not uploaded)
        $documentRule = $isResubmit ? 'sometimes' : 'required';
        
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            
            // Documents: required for new registration, optional for resubmit
            'business_permit' => [$documentRule, 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'doh_cert' => [$documentRule, 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'philhealth_cert' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The facility name is required.',
            'name.max' => 'The facility name cannot exceed 100 characters.',
            'latitude.required' => 'Please select a location on the map.',
            'longitude.required' => 'Please select a location on the map.',
            'latitude.between' => 'The selected latitude is invalid.',
            'longitude.between' => 'The selected longitude is invalid.',
            'business_permit.required' => 'Business Permit is required for registration.',
            'doh_cert.required' => 'DOH Certificate is required for registration.',
            'business_permit.mimes' => 'Business Permit must be a PDF or image file (jpg, jpeg, png).',
            'doh_cert.mimes' => 'DOH Certificate must be a PDF or image file (jpg, jpeg, png).',
            'philhealth_cert.mimes' => 'PhilHealth Certificate must be a PDF or image file (jpg, jpeg, png).',
            'business_permit.max' => 'Business Permit file size must not exceed 5MB.',
            'doh_cert.max' => 'DOH Certificate file size must not exceed 5MB.',
            'philhealth_cert.max' => 'PhilHealth Certificate file size must not exceed 5MB.',
        ];
    }
}

