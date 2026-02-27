<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends ApiController
{
    /**
     * GET /api/profile
     * Devuelve el perfil del usuario autenticado (dirección + tarjeta preferida).
     */
    public function show(Request $request): JsonResponse
    {
        $profile = UserProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
        );

        return $this->success($profile);
    }

    /**
     * PUT /api/profile
     * Guarda / actualiza dirección y tarjeta preferida del usuario.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Dirección
            'direccion'    => 'nullable|string|max:255',
            'ciudad'       => 'nullable|string|max:100',
            'pais'         => 'nullable|string|max:100',
            'codigo_postal'=> 'nullable|string|max:20',

            // Tarjeta (nunca guardamos número completo)
            'card_holder'  => 'nullable|string|max:100',
            'card_last4'   => 'nullable|string|size:4|regex:/^\d{4}$/',
            'card_brand'   => ['nullable', 'string', Rule::in(['visa', 'mastercard', 'amex', 'diners', 'other'])],
            'card_expiry'  => 'nullable|string|max:7',
        ]);

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        return $this->success($profile, 'Perfil actualizado correctamente.');
    }
}
