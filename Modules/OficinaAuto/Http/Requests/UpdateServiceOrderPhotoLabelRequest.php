<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F3 OS-V2-1 — edita a legenda (label) de uma foto do laudo OS-level.
 *
 * Protótipo Cowork (lightbox · oficina-page.jsx): legenda editável que persiste.
 * A legenda mapeia em `Arquivo.original_name` (Arquivo não tem coluna caption
 * dedicada; original_name é a string exibível canônica — getDisplayNameAttribute).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): scope + cross-guard no controller.
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderPhotoController::updateLabel
 */
class UpdateServiceOrderPhotoLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:180'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Legenda obrigatória.',
            'label.max'      => 'Legenda muito longa (máx. 180 caracteres).',
        ];
    }
}
