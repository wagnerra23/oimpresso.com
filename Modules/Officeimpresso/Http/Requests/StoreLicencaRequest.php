<?php

namespace Modules\Officeimpresso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro de licenca-computador legacy (bridge Delphi WR Comercial) — D8.c Security.
 *
 * Bridge legacy: campos preservam contrato Delphi (licenca_id, hd, processador,
 * memoria, versao_exe, bloqueado). NAO mudar nomes — Delphi sincroniza via HTTP.
 *
 * Multi-tenant: business_id resolvido na Controller via session (linha 22-23 do
 * LicencaComputadorController.index()). FormRequest valida payload Delphi puro.
 */
class StoreLicencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras compativeis com schema legacy `licenca_computador`:
     * - licenca_id: FK licenca.id
     * - hd: unique (identificador unico maquina — serial HD)
     * - processador/memoria/versao_exe: strings descritivas do hardware
     * - bloqueado: flag boolean revogacao
     *
     * Bridge legacy preserva nomes Delphi — nao renomear.
     */
    public function rules(): array
    {
        return [
            'licenca_id'  => ['required', 'integer', 'exists:licenca,id'],
            'hd'          => ['required', 'string', 'max:255', 'unique:licenca_computador,hd'],
            'processador' => ['required', 'string', 'max:255'],
            'memoria'     => ['required', 'string', 'max:50'],
            'versao_exe'  => ['required', 'string', 'max:20'],
            'bloqueado'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'licenca_id.required'  => 'Informe a licenca pai.',
            'licenca_id.exists'    => 'Licenca pai nao encontrada.',
            'hd.required'          => 'Informe o identificador do HD.',
            'hd.unique'            => 'Este HD ja esta cadastrado em outra licenca.',
            'processador.required' => 'Informe o processador.',
            'memoria.required'     => 'Informe a memoria.',
            'versao_exe.required'  => 'Informe a versao do executavel.',
        ];
    }
}
