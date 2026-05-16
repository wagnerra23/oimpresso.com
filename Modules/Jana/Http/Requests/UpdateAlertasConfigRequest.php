<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateAlertasConfigRequest — POST /jana/alertas/config.
 *
 * D8.c (Wave 17 governance v3) — Controller `AlertasController@updateConfig`
 * estava aceitando Request raw sem validação; este FormRequest documenta o
 * shape esperado (canais opt-in + thresholds) e força whitelist explícita.
 *
 * Multi-tenant Tier 0 (ADR 0093): config persistida em
 * `business.essentials_settings.alertas` (per-business). Sem acesso superadmin.
 */
class UpdateAlertasConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled'                  => ['sometimes', 'boolean'],
            'canais'                   => ['sometimes', 'array'],
            'canais.email'             => ['sometimes', 'boolean'],
            'canais.whatsapp'          => ['sometimes', 'boolean'],
            'canais.dashboard'         => ['sometimes', 'boolean'],
            'thresholds'               => ['sometimes', 'array'],
            'thresholds.meta_atingida' => ['sometimes', 'numeric', 'min:0', 'max:200'],
            'thresholds.meta_drift'    => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'silencio_horario_inicio'  => ['sometimes', 'nullable', 'date_format:H:i'],
            'silencio_horario_fim'     => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'thresholds.meta_atingida.max'   => 'O threshold de meta atingida não pode passar de 200%.',
            'thresholds.meta_drift.max'      => 'O threshold de drift não pode passar de 100%.',
            'silencio_horario_inicio.date_format' => 'Use o formato HH:MM (ex: 22:00).',
            'silencio_horario_fim.date_format'    => 'Use o formato HH:MM (ex: 07:00).',
        ];
    }
}
