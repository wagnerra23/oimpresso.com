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

            // Credenciais de gateway (Inter / Asaas) — todos nullable no request;
            // o controller decide o que salvar
            'gateway_ambiente'         => ['nullable', 'string', 'in:production,sandbox'],
            'gateway_client_id'        => ['nullable', 'string', 'max:200'],
            'gateway_client_secret'    => ['nullable', 'string', 'max:500'],
            'gateway_certificado_crt'  => ['nullable', 'string'],
            'gateway_certificado_key'  => ['nullable', 'string'],
            'gateway_api_key'          => ['nullable', 'string', 'max:200'],
        ];
    }
}
