<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkMediaDownloadedRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function mediaIds(): array
    {
        return collect($this->input('ids'))->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }
}
