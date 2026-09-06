<?php

namespace App\Http\Requests;

use App\Enums\MediaKind;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    private const GIB = 1024 * 1024 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quota_gb' => ['required', 'numeric', 'min:1', 'max:100000'],
            'photo_gb' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'video_gb' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'autre_gb' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'archive_after_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quota_gb.required' => 'Indique l’espace par compte, en Go.',
            'quota_gb.min' => 'Au moins 1 Go par compte.',
            'photo_gb.required' => 'Indique la taille maximale d’une photo, en Go.',
            'video_gb.required' => 'Indique la taille maximale d’une vidéo, en Go.',
            'autre_gb.required' => 'Indique la taille maximale des autres fichiers, en Go.',
            'archive_after_days.required' => 'Indique le délai d’archivage, en jours (0 pour jamais).',
            'archive_after_days.integer' => 'Un nombre de jours entier.',
            '*.numeric' => 'Un nombre de Go, par exemple 2,5.',
            '*.min' => 'Au moins 0,1 Go.',
            '*.max' => 'Ça fait beaucoup — vérifie la valeur.',
        ];
    }

    public function quotaBytes(): int
    {
        return (int) round((float) $this->input('quota_gb') * self::GIB);
    }

    public function maxBytesFor(MediaKind $kind): int
    {
        return (int) round((float) $this->input("{$kind->value}_gb") * self::GIB);
    }
}
