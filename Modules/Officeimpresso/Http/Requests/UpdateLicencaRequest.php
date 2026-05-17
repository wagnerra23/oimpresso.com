<?php

namespace Modules\Officeimpresso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Atualizacao de licenca-computador legacy (bridge Delphi WR Comercial) — D8.c Security.
 *
 * Wave 25 polish (2026-05-16) — par de StoreLicencaRequest (Wave anterior).
 * Diferenca chave: `hd` unique IGNORA a propria licenca em curso (rota ID),
 * permitindo o usuário trocar processador/memoria sem disparar conflict.
 *
 * Bridge legacy: campos preservam contrato Delphi (licenca_id, hd, processador,
 * memoria, versao_exe, bloqueado). NAO mudar nomes — Delphi sincroniza via HTTP.
 *
 * Multi-tenant: business_id resolvido na Controller via session (cross-check
 * route-binding pertence ao biz da sessao — anti-IDOR).
 *
 * Lei Software 9.609/98: retention 5y mantida (NÃO permite UPDATE em
 * `licenca_log` — só `licenca_computador` é editavel).
 */
class UpdateLicencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('licenca_computador') ?? $this->route('id');

        return [
            'licenca_id'  => ['sometimes', 'integer', 'exists:licenca,id'],
            'hd'          => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('licenca_computador', 'hd')->ignore($id),
            ],
            'processador' => ['sometimes', 'string', 'max:255'],
            'memoria'     => ['sometimes', 'string', 'max:50'],
            'versao_exe'  => ['sometimes', 'string', 'max:20'],
            'bloqueado'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'licenca_id.exists' => 'Licenca pai nao encontrada.',
            'hd.unique'         => 'Este HD ja esta cadastrado em outra licenca.',
            'hd.max'            => 'HD nao pode passar de 255 caracteres.',
            'processador.max'   => 'Processador nao pode passar de 255 caracteres.',
            'memoria.max'       => 'Memoria nao pode passar de 50 caracteres.',
            'versao_exe.max'    => 'Versao do executavel nao pode passar de 20 caracteres.',
        ];
    }
}
