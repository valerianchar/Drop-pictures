<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Le journal côté client : les échecs qui n'atteignent jamais le serveur —
 * sélecteur annulé par iOS, fichier remis vide, lecture refusée, dépôt coupé —
 * laissent ici une trace lisible, avec le navigateur qui les a vus. Rien
 * d'autre : pas de contenu, pas d'analyse d'usage.
 */
class ClientLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9._-]+$/'],
            'data' => ['nullable', 'array', 'max:20'],
        ]);

        Log::info('Client : '.$validated['event'], [
            'user_id' => $request->user()->id,
            'agent' => Str::limit((string) $request->userAgent(), 200),
            ...collect($validated['data'] ?? [])->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value))->all(),
        ]);

        return response()->json(status: 204);
    }
}
