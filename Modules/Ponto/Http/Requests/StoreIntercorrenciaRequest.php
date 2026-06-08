<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreIntercorrenciaRequest — criação de intercorrência (justificativa de ponto).
 *
 * Intercorrência é o ÚNICO mecanismo legal pra ajustar efeito de marcação
 * APPEND-ONLY (Portaria MTP 671/2021 Anexo I §15 — marcação nunca é alterada,
 * apenas anulada/justificada via intercorrência).
 *
 * Tier 0 multi-tenant (ADR 0093): colaborador_config_id é validado via FK +
 * cross-tenant scope no MarcacaoService::criar().
 *
 * Nota: esta classe substitui IntercorrenciaRequest com naming canônico Store*
 * (consistente com FormRequest pattern Laravel 13). IntercorrenciaRequest legada
 * permanece pra back-compat até migração completa dos Controllers.
 *
 * @see memory/requisitos/Ponto/SPEC.md US-PONTO-014 (criação intercorrência)
 * @see Modules\Ponto\Http\Requests\IntercorrenciaRequest (legada)
 */
class StoreIntercorrenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'colaborador_config_id' => 'required|exists:ponto_colaborador_config,id',
            'tipo'                  => 'required|in:CONSULTA_MEDICA,ATESTADO_MEDICO,REUNIAO_EXTERNA,VISITA_CLIENTE,HORA_EXTRA_AUTORIZADA,ESQUECIMENTO_MARCACAO,PROBLEMA_EQUIPAMENTO,OUTRO',
            'data'                  => 'required|date|before_or_equal:today',
            'dia_todo'              => 'boolean',
            'intervalo_inicio'      => 'nullable|required_unless:dia_todo,true|date_format:H:i',
            'intervalo_fim'         => 'nullable|required_unless:dia_todo,true|date_format:H:i|after:intervalo_inicio',

            // CLT Art. 818 — ônus da prova; justificativa robusta evita litígio trabalhista
            'justificativa'         => 'required|string|min:10|max:2000',

            // Anexo (atestado, comprovante) — Portaria 671 §15 II
            'anexo'                 => 'nullable|file|mimes:pdf,jpg,png|max:5120',

            'prioridade'            => 'nullable|in:NORMAL,URGENTE',
            'impacta_apuracao'      => 'boolean',
            'descontar_banco_horas' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'justificativa.min' => 'A justificativa deve ter pelo menos 10 caracteres (CLT Art. 818 — ônus da prova).',
            'intervalo_fim.after' => 'O horário final deve ser posterior ao inicial.',
            'data.before_or_equal' => 'A data da intercorrência não pode ser futura.',
        ];
    }
}
