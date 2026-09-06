<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

class FinishUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'checksum' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            // Déposé depuis la page d'un groupe : le fichier y arrive directement.
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'checksum.required' => 'L’empreinte du fichier manque : impossible de garantir son intégrité.',
            'checksum.regex' => 'L’empreinte du fichier est mal formée.',
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

    public function group(): ?Group
    {
        return $this->filled('group_id') ? Group::query()->find($this->integer('group_id')) : null;
    }
}
