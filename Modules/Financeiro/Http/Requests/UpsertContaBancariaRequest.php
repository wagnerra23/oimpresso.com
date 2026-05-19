<?php

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Financeiro\Strategies\CnabDirectStrategy;

class UpsertContaBancariaRequest extends FormRequest
{
    // Bancos gateway-only (sem CNAB tradicional)
    private const GATEWAY_ONLY = ['274'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $bancosCnab    = array_keys(CnabDirectStrategy::BANCO_MAP);
        $bancosPermit  = array_merge($bancosCnab, self::GATEWAY_ONLY);
        $isGatewayOnly = in_array($this->input('banco_codigo'), self::GATEWAY_ONLY, true);

        return [
            'banco_codigo' => ['required', 'string', 'size:3', 'in:' . implode(',', $bancosPermit)],

            // CNAB fields — obrigatórios apenas para bancos tradicionais
            'agencia'         => [$isGatewayOnly ? 'nullable' : 'required', 'string', 'max:10'],
            'agencia_dv'      => ['nullable', 'string', 'max:2'],
            'conta_dv'        => ['nullable', 'string', 'max:2'],
            'carteira'        => [$isGatewayOnly ? 'nullable' : 'required', 'string', 'max:10'],
            'convenio'        => ['nullable', 'string', 'max:30'],
            'codigo_cedente'  => ['nullable', 'string', 'max:30'],
            'variacao_carteira' => ['nullable', 'string', 'max:10'],

            // Beneficiário — sempre obrigatório (usado em boleto e nota)
            'beneficiario_documento'     => ['required', 'string', 'max:18'],
            'beneficiario_razao_social'  => ['required', 'string', 'max:150'],
            'beneficiario_logradouro'    => ['nullable', 'string', 'max:150'],
            'beneficiario_bairro'        => ['nullable', 'string', 'max:80'],
            'beneficiario_cidade'        => ['nullable', 'string', 'max:80'],
            'beneficiario_uf'            => ['nullable', 'string', 'size:2'],
            'beneficiario_cep'           => ['nullable', 'string', 'max:9'],

            'ativo_para_boleto' => ['boolean'],
            'metadata'          => ['nullable', 'array'],

            // Credenciais API (Inter OAuth2/mTLS, Asaas token, C6 etc) viviam
            // aqui até 2026-05-19; migraram pra /settings/payment-gateways com
            // FK canon payment_gateway_credentials.conta_bancaria_id
            // (PR #1153/#1154 + ADR 0170).
        ];
    }
}
