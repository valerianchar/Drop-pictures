<?php

namespace App\Http\Requests;

use App\Enums\GroupLifetime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'lifetime' => ['required', Rule::enum(GroupLifetime::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Donne un nom au groupe.',
            'name.max' => 'Le nom du groupe fait 80 caractères au plus.',
            'lifetime.required' => 'Choisis une durée de vie.',
            'lifetime.enum' => 'Cette durée de vie n’existe pas.',
        ];
    }

    public function lifetime(): GroupLifetime
    {
        return GroupLifetime::from($this->string('lifetime')->value());
    }
}
