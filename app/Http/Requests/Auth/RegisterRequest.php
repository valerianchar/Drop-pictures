<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Dis-nous ton prénom.',
            'email.required' => 'Indique ton e-mail.',
            'email.email' => 'Cet e-mail ne semble pas valide.',
            'email.unique' => 'Un compte existe déjà avec cet e-mail — connecte-toi.',
            'password.required' => 'Choisis un mot de passe.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
        ];
    }
}
