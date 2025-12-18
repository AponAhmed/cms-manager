<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add authentication logic here if needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'regex:/^[a-z0-9.-]+$/i',
                'unique:sites,domain',
                'max:255',
            ],
            'wp_admin_username' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:60',
            ],
            'wp_admin_password' => [
                'required',
                'string',
                'min:12',
            ],
            'wp_admin_email' => [
                'required',
                'email',
                'max:100',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'The domain format is invalid. Use only letters, numbers, dots, and hyphens.',
            'domain.unique' => 'This domain is already registered in the system.',
            'wp_admin_username.alpha_dash' => 'The username may only contain letters, numbers, dashes, and underscores.',
            'wp_admin_password.min' => 'The password must be at least 12 characters for security.',
        ];
    }
}
