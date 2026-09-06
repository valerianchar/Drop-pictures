<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupMediaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_id' => [
                'required',
                // On ne partage que ce qu'on a déposé soi-même.
                Rule::exists('media', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media_id.required' => 'Choisis le fichier à partager.',
            'media_id.exists' => 'Ce fichier est introuvable.',
        ];
    }

    public function media(): Media
    {
        return Media::query()->findOrFail($this->integer('media_id'));
    }
}
