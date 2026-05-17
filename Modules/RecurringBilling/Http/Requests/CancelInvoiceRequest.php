<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest pro endpoint POST /recurring-billing/invoices/{id}/cancel.
 *
 * Wave 18 D8 saturação RecurringBilling (69→95) — 3º FormRequest tipado.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NUNCA aceito do request —
 * vem da sessão no Controller. Invoice é resolvida via $invoiceId scoped no
 * AssinaturaCobrancaService::cancelInvoice($businessId, $invoiceId).
 *
 * @see Modules\RecurringBilling\Services\AssinaturaCobrancaService::cancelInvoice
 */
class CancelInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', Rule::in([
                'ACERTOS', 'DUPLICIDADE', 'PEDIDO_CLIENTE', 'ERRO_OPERADOR',
                'INADIMPLENCIA', 'OUTROS',
            ])],
            'observacao' => ['nullable', 'string', 'max:500'],
            'notificar_cliente' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.in' => 'Motivo invalido. Use ACERTOS, DUPLICIDADE, PEDIDO_CLIENTE, ERRO_OPERADOR, INADIMPLENCIA ou OUTROS.',
            'observacao.max' => 'Observacao maxima 500 caracteres.',
        ];
    }

    /**
     * Helper tipado consumido pelo Controller.
     */
    public function motivo(): string
    {
        return (string) $this->input('motivo', 'ACERTOS');
    }
}
