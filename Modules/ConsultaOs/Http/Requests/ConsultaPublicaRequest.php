<?php

namespace Modules\ConsultaOs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Busca publica de OS por numero — D8.c Security.
 *
 * Acesso publico (sem auth): anti-enumeration via formato alfanumerico + tamanho max:20.
 * Throttle/rate-limit aplicado via middleware na rota (defesa em profundidade).
 *
 * Mock-only: ConsultaOsController.buscar() retorna mockData() ate Wagner decidir
 * mapping real (invoice_no + ultimos 4 telefone — padrao Repair).
 */
class ConsultaPublicaRequest extends FormRequest
{
    /**
     * Endpoint publico — autorizacao por throttle middleware na rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras anti-enumeration:
     * - numero: required, alfanumerico puro (sem espacos/simbolos), max 20 chars
     * - estagio: nullable, lista controlada (todos|aprovacao|producao|acabamento|expedicao|entregue)
     */
    public function rules(): array
    {
        return [
            'numero'  => ['required', 'string', 'alpha_num', 'max:20'],
            'estagio' => ['nullable', 'string', 'in:todos,aprovacao,producao,acabamento,expedicao,entregue'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero.required'  => 'Informe o numero da OS.',
            'numero.alpha_num' => 'O numero da OS deve conter apenas letras e numeros.',
            'numero.max'       => 'O numero da OS nao pode ter mais de 20 caracteres.',
            'estagio.in'       => 'Estagio invalido.',
        ];
    }
}
