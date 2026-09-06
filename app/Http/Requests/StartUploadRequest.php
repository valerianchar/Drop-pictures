<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le fichier n’a pas de nom.',
            'size.required' => 'La taille du fichier est inconnue.',
            'size.min' => 'Ce fichier est vide.',
        ];
    }
}
