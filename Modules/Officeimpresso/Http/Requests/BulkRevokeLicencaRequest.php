<?php

namespace Modules\Officeimpresso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Revogacao em lote (toggle bloqueio multiplo) de licencas-computador — D8.c Security.
 *
 * Wave 25 polish (2026-05-16) — par de RevokeLicencaRequest (unitario).
 * Permite Wagner bloquear N maquinas de uma vez (uso real: cliente cancela
 * contrato, todas as licencas viram bloqueadas; ou ataque ransomware exige
 * isolamento imediato de N maquinas).
 *
 * Bridge legacy: Delphi le `bloqueado=1` e impede login no executavel cliente.
 * Audit trail LGPD via LicencaLog — motivo obrigatório, persiste por evento.
 *
 * Lei Software 9.609/98: retention 5y do audit log mantida no LicencaLog.
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): Controller filtra IDs por business_id
 * da sessao ANTES de aplicar bloqueio (defesa-em-profundidade vs IDOR em
 * payload).
 */
class BulkRevokeLicencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'licenca_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'licenca_ids.*' => ['integer', 'min:1', 'exists:licenca_computador,id'],
            'motivo'        => ['required', 'string', 'min:5', 'max:500'],
            'bloqueado'     => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'licenca_ids.required'   => 'Informe ao menos uma licenca.',
            'licenca_ids.max'        => 'Operacao em lote limitada a 100 licencas por chamada.',
            'licenca_ids.*.exists'   => 'Uma ou mais licencas nao foram encontradas.',
            'motivo.required'        => 'Informe o motivo da operacao em lote (audit trail LGPD).',
            'motivo.min'             => 'Motivo precisa ter pelo menos 5 caracteres.',
            'motivo.max'             => 'Motivo nao pode passar de 500 caracteres.',
            'bloqueado.required'     => 'Informe se as licencas devem ser bloqueadas (true) ou desbloqueadas (false).',
        ];
    }
}
