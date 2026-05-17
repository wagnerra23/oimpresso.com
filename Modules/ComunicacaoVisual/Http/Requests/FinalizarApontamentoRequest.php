<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FinalizarApontamentoRequest — validação payload do finalizar apontamento.
 *
 * Wave 18 D8 Security — pareado com IniciarApontamentoRequest.
 *
 * Cobre POST /com-visual/api/apontamentos/{id}/finalizar.
 * Operador informa m² produzido + observacoes opcionais.
 * Service calcula drift_percent vs m² orçado.
 *
 * Multi-tenant Tier 0: business_id NUNCA aceito do input — global scope filtra ID.
 * Append-only: registro existente não pode ser deletado, só finalizado.
 *
 * @see Modules/ComunicacaoVisual/Http/Controllers/ApontamentoController.php
 * @see Modules/ComunicacaoVisual/Services/ApontamentoTracker.php
 */
class FinalizarApontamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // m² produzido — obrigatório pra calcular drift
            'm2_produzido' => ['required', 'numeric', 'min:0.001', 'max:100000'],

            // Observações livres (texto curto — sem PII recomendado)
            'observacoes'  => ['nullable', 'string', 'max:1000'],

            // Timestamp finalização (opcional — default now())
            'finalizado_em' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'm2_produzido.required'        => 'Informe os m² produzidos para finalizar.',
            'm2_produzido.min'             => 'm² produzido deve ser maior que 0,001.',
            'm2_produzido.max'             => 'm² produzido inválido (limite 100.000).',
            'observacoes.max'              => 'Observações limitadas a 1000 caracteres.',
            'finalizado_em.before_or_equal'=> 'Data de finalização não pode estar no futuro.',
        ];
    }
}
