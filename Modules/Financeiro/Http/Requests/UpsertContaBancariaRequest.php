<?php

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Financeiro\Strategies\CnabDirectStrategy;

class UpsertContaBancariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $bancos = array_keys(CnabDirectStrategy::BANCO_MAP);

        return [
            'banco_codigo' => ['required', 'string', 'size:3', 'in:'.implode(',', $bancos)],
            'agencia' => ['required', 'string', 'max:10'],
            'agencia_dv' => ['nullable', 'string', 'max:2'],
            'conta_dv' => ['nullable', 'string', 'max:2'],
            'carteira' => ['required', 'string', 'max:10'],
            'convenio' => ['nullable', 'string', 'max:30'],
            'codigo_cedente' => ['nullable', 'string', 'max:30'],
            'variacao_carteira' => ['nullable', 'string', 'max:10'],
            'beneficiario_documento' => ['required', 'string', 'max:18'],
            'beneficiario_razao_social' => ['required', 'string', 'max:150'],
            'beneficiario_logradouro' => ['nullable', 'string', 'max:150'],
            'beneficiario_bairro' => ['nullable', 'string', 'max:80'],
            'beneficiario_cidade' => ['nullable', 'string', 'max:80'],
            'beneficiario_uf' => ['nullable', 'string', 'size:2'],
            'beneficiario_cep' => ['nullable', 'string', 'max:9'],
            'ativo_para_boleto' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
