<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupMediaRequest extends FormRequest
{
    /**
     * Un seul fichier (media_id) ou une sélection (media_ids[]) : les deux
     * formes se rejoignent dans media().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // On ne partage que ce qu'on a déposé soi-même.
        $mine = Rule::exists('media', 'id')->where('user_id', $this->user()->id);

        return [
            'media_id' => ['required_without:media_ids', 'integer', $mine],
            'media_ids' => ['required_without:media_id', 'array', 'max:500'],
            'media_ids.*' => ['integer', $mine],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media_id.required_without' => 'Choisis le fichier à partager.',
            'media_ids.required_without' => 'Choisis au moins un fichier à partager.',
            'media_id.exists' => 'Ce fichier est introuvable.',
            'media_ids.*.exists' => 'Un des fichiers est introuvable.',
            'media_ids.max' => 'Cinq cents fichiers au plus d’un coup.',
        ];
    }

    /**
     * @return Collection<int, Media>
     */
    public function media(): Collection
    {
        $ids = collect($this->input('media_ids', []))
            ->when($this->filled('media_id'), fn ($ids) => $ids->push($this->input('media_id')))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        return Media::query()->whereIn('id', $ids)->orderBy('id')->get();
    }
}
