<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indique l’e-mail de ton compte.',
            'email.email' => 'Cet e-mail ne semble pas valide.',
            'password.required' => 'Choisis un nouveau mot de passe.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
        ];
    }
}
