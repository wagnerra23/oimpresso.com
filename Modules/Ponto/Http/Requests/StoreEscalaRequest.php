<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Ponto\Entities\Escala;

/**
 * StoreEscalaRequest — criação de escala de trabalho RH.
 *
 * Wave 18 RETRY D8.c — FormRequest dedicado pra Escala (cadastro de jornada CLT).
 * Substitui validação inline em Controllers/Service (SoC + reuse).
 *
 * Escala define a jornada padrão do colaborador conforme:
 *  - CLT Art. 58 (jornada normal 8h/44h semana)
 *  - CLT Art. 59 (HE ≤2h/dia, máximo 10h jornada)
 *  - CLT Art. 71 (intervalo intrajornada obrigatório)
 *  - Portaria 671/2021 Anexo I §6 (registro eletrônico precisa refletir escala real)
 *
 * Tier 0 multi-tenant (ADR 0093): `business_id` injetado pelo Controller via
 * session/auth; nunca aceito do request body (anti-tampering cross-tenant).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Ponto/Entities/Escala.php
 */
class StoreEscalaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $tipos = [
            Escala::TIPO_FIXA,
            Escala::TIPO_FLEXIVEL,
            Escala::TIPO_ESCALA_12X36,
            Escala::TIPO_ESCALA_6X1,
            Escala::TIPO_ESCALA_5X2,
        ];

        return [
            'nome'                  => 'required|string|min:3|max:120',
            'codigo'                => 'nullable|string|max:30',
            'tipo'                  => 'required|in:' . implode(',', $tipos),
            // CLT Art. 58 — jornada máxima 8h (480min) sem HE; permitimos até 12h escala 12x36
            'carga_diaria_minutos'  => 'required|integer|min:60|max:720',
            // CLT Art. 7º XIII — 44h/semana (2640min); 36h/semana mínimo (12x36 turnos)
            'carga_semanal_minutos' => 'required|integer|min:600|max:2640',
            'permite_banco_horas'   => 'boolean',
            'dias_semana'           => 'nullable|array|max:7',
            'dias_semana.*'         => 'integer|min:0|max:6',
            'horarios_padrao'       => 'nullable|array',
            'ativo'                 => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'carga_diaria_minutos.max' => 'Jornada diária não pode exceder 12h (CLT Art. 59 — limite legal).',
            'carga_semanal_minutos.max' => 'Carga semanal não pode exceder 44h (CLT Art. 7º XIII).',
            'tipo.in' => 'Tipo de escala inválido — use FIXA, FLEXIVEL, ESCALA_12X36, ESCALA_6X1 ou ESCALA_5X2.',
        ];
    }
}
