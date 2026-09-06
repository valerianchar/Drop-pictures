<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaTagsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tags' => ['present', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tags.max' => 'Dix tags au plus par fichier.',
            'tags.*.max' => 'Un tag fait 40 caractères au plus.',
        ];
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return array_values(array_filter(array_map('strval', $this->input('tags', []))));
    }
}
