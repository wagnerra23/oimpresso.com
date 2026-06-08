<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Http\Requests;

/**
 * ConsultaPorEstagioRequest — listagem de OS por estagio (Wave 27 D8.a).
 *
 * Endpoint publico alternativo a `buscar(numero, estagio)`: filtro PROACTIVE
 * por estagio sem precisar do numero — util pra cliente acompanhar todas as
 * OS suas em determinado estagio (ex: "minhas OS em producao").
 *
 * Status: scaffold pronto pra US-CONSULTA-001 quando query real exigir filtro
 * por cliente (cpf_cnpj + ultimos 4 telefone — padrao Repair). Validacao
 * minima hoje, sem PII no body — Service real adicionara campos identificadores.
 *
 * Tier 0 multi-tenant (ADR 0093): rota publica NAO scopa por business_id
 * (cliente externo sem sessao). Quando US-CONSULTA-001 ativar query real,
 * Service deve resolver business_id via lookup do telefone/CNPJ informado.
 *
 * Defesa em profundidade contra enumeration:
 *   - Estagio em lista controlada (in:[]) — sem free-text
 *   - Throttle 30 req/min via middleware (mesma protecao de buscar)
 *   - Paginacao max 20 itens (anti-scraping)
 *
 * @see Modules/ConsultaOs/Http/Requests/ConsultaPublicaRequest (singular por numero)
 * @see Modules/ConsultaOs/Services/ConsultaOsMockService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ConsultaPorEstagioRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize(): bool
    {
        return true; // rota publica — autoriz via throttle middleware
    }

    public function rules(): array
    {
        return [
            'estagio' => [
                'required',
                'string',
                'in:aprovacao,producao,acabamento,expedicao,entregue',
            ],
            'pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:50', // anti-scraping: max 50 paginas
            ],
            'por_pagina' => [
                'nullable',
                'integer',
                'min:5',
                'max:20', // 20 itens/pagina max — anti-bulk-enum
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estagio.required' => 'Informe o estagio para filtrar.',
            'estagio.in' => 'Estagio invalido — use aprovacao, producao, acabamento, expedicao ou entregue.',
            'pagina.max' => 'Limite de paginacao excedido (anti-scraping).',
            'por_pagina.max' => 'Maximo 20 itens por pagina (anti-scraping).',
        ];
    }
}
