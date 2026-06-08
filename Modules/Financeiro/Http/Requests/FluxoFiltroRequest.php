<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest pro endpoint GET /financeiro/fluxo (projeção 35d).
 *
 * Wave 18 D8 saturação Financeiro (68→95) — 4º FormRequest tipado da onda.
 * Antes os filtros vinham via $request->input(...) inline no Controller; agora
 * canon validado + type-hintable.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NUNCA aceito do request —
 * vem da sessão no Controller via FluxoCaixaService::projetar(businessId).
 *
 * @see Modules\Financeiro\Services\FluxoCaixaService::projetar
 * @see resources/js/Pages/Financeiro/Fluxo/Index.tsx
 */
class FluxoFiltroRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissão fina via can:financeiro.dashboard.view no roteador.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dias'             => ['nullable', 'integer', 'min:7', 'max:90'],
            'conta_bancaria_id' => ['nullable', 'integer', 'min:1'],
            'margem_minima'    => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'tipo_origem'      => ['nullable', 'string', Rule::in(['todos', 'manual', 'sells', 'repair', 'assinatura', 'boleto'])],
        ];
    }

    public function messages(): array
    {
        return [
            'dias.min'         => 'Projeção mínima 7 dias.',
            'dias.max'         => 'Projeção máxima 90 dias (acima sobrecarrega — use relatório).',
            'tipo_origem.in'   => 'Origem inválida (use manual, sells, repair, assinatura, boleto, ou todos).',
        ];
    }

    /**
     * Helpers tipados consumidos pelo Controller.
     */
    public function dias(): int
    {
        return (int) $this->input('dias', 35);
    }

    public function margemMinima(): float
    {
        return (float) $this->input('margem_minima', 5000.00);
    }
}
