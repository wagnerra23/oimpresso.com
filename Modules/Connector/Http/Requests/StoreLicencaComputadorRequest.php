<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreLicencaComputadorRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de LicencaComputadorController@store (linhas 249-266).
 * Registra binding hardware (HD/processador/memória) à licença do Delphi legacy.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id é REQUIRED no payload (binding
 * cross-tenant exige escolha explícita do business via API token superadmin).
 * Tabela licenca_computador é repo-wide (escopo legacy multi-cliente WR2).
 */
class StoreLicencaComputadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint API token-based; auth middleware da rota cobre a porta.
        // Aqui só garantimos que existe um usuário autenticado.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer', 'exists:business,id'],
            'licenca_id'  => ['required', 'integer', 'exists:licenca,id'],
            'hd'          => ['required', 'string', 'max:191', 'unique:licenca_computador,hd'],
            'processador' => ['required', 'string', 'max:191'],
            'memoria'     => ['required', 'string', 'max:80'],
            'versao_exe'  => ['required', 'string', 'max:40'],
            'bloqueado'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_id.required' => 'O business_id é obrigatório.',
            'business_id.exists'   => 'business_id informado não existe.',
            'licenca_id.required'  => 'A licenca_id é obrigatória.',
            'licenca_id.exists'    => 'Licença informada não existe.',
            'hd.required'          => 'O identificador do HD é obrigatório.',
            'hd.unique'            => 'Este HD já está vinculado a outra licença.',
            'processador.required' => 'O processador é obrigatório.',
            'memoria.required'     => 'A memória é obrigatória.',
            'versao_exe.required'  => 'A versão do executável é obrigatória.',
        ];
    }
}
