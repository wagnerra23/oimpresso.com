<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RecusarOrcamentoRequest — valida payload de recusa de orçamento pelo cliente.
 *
 * Wave 26 — boost D8 security (FormRequest dedicado ao fluxo recusa, separado
 * de AprovarOrcamentoRequest pra evitar permitir UPDATE livre via mesma rota).
 *
 * Multi-tenant Tier 0: business_id resolvido em runtime pela sessão (não vem do payload).
 * Bind do orçamento via route model binding com global scope ativo (filtra biz automaticamente).
 *
 * @see Modules/ComunicacaoVisual/Http/Controllers/OrcamentoController::recusar()
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */
class RecusarOrcamentoRequest extends FormRequest
{
    /**
     * Autorização — usuário autenticado + sessão tem business_id (Tier 0).
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && (session('user.business_id') !== null || session('business.id') !== null);
    }

    /**
     * Regras de validação.
     *
     * `motivo` é texto livre potencialmente PII — passa por PiiRedactor no Controller
     * antes de log/audit (PII-LGPD.md §2).
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * Mensagens PT-BR (UX cliente final em pt-BR).
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Por favor, informe o motivo da recusa do orçamento.',
            'motivo.min'      => 'O motivo precisa ter ao menos 5 caracteres.',
            'motivo.max'      => 'O motivo está muito longo (máximo 500 caracteres).',
        ];
    }
}
