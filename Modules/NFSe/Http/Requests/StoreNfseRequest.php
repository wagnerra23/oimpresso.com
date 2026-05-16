<?php

declare(strict_types=1);

namespace Modules\NFSe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave S — FormRequest extraído de NfseController::store (US-NFSE-006).
 *
 * Regras fiscais ABRASF/Município (NFSe Nacional) preservadas integralmente:
 *   - competencia: Y-m (mês/ano fiscal).
 *   - lc116_codigo: código LC 116/2003 (até 5 chars, ex "1.04").
 *   - aliquota_iss: fração 0..1 (ex 0.05 = 5%).
 *   - tomador: nome obrigatório; CNPJ/CPF/email opcionais (depende prefeitura).
 *
 * NÃO relaxar — compliance fiscal. authorize via gate `nfse.emit` (espelhado do
 * `$this->authorize('nfse.emit')` original).
 */
class StoreNfseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfse.emit') ?? false;
    }

    public function rules(): array
    {
        return [
            'competencia'     => ['required', 'date_format:Y-m'],
            'tomador_nome'    => ['required', 'string', 'max:150'],
            'tomador_cnpj'    => ['nullable', 'string'],
            'tomador_cpf'     => ['nullable', 'string'],
            'tomador_email'   => ['nullable', 'email'],
            'descricao'       => ['required', 'string', 'max:2000'],
            'lc116_codigo'    => ['required', 'string', 'max:5'],
            'valor_servicos'  => ['required', 'numeric', 'min:0.01'],
            'aliquota_iss'    => ['required', 'numeric', 'min:0', 'max:1'],
            'iss_retido'      => ['boolean'],
            'transaction_id'  => ['nullable', 'integer'],
        ];
    }
}
