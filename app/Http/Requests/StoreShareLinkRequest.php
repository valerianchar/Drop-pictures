<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShareLinkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limited' => ['boolean'],
        ];
    }

    public function isLimited(): bool
    {
        return $this->boolean('limited');
    }
}
