<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave S — FormRequest extraído de NfeEmissaoController::emitir.
 *
 * Preserva validação fiscal SEFAZ:
 *   - modelo: 55 (NFe B2B) ou 65 (NFC-e B2C) — único conjunto válido CONFAZ.
 *
 * Permissão: `nfebrasil.emit.manage` (authorize abaixo).
 *
 * NÃO relaxar regras — compliance fiscal. Multi-tenant guard segue no Controller
 * (business_id da sessão + cross-tenant guard via Transaction lookup).
 */
class StoreEmissaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Lote 3.1 da auditoria NfeBrasil (N5-03, decisão [W] B-02 2026-11-10): emitir nota é ato
        // fiscal e exige a permissão própria. Antes bastava estar logado. Quem já criava venda
        // recebeu a permissão pela migration 2026_11_10_000001 — ninguém perde acesso no dia.
        return (bool) $this->user()?->can('nfebrasil.emit.manage');
    }

    public function rules(): array
    {
        return [
            'modelo' => ['nullable', 'string', Rule::in(['55', '65'])],
            // US-NFE-069: NF-e só da parte mercadoria (botão "Emitir NF-e + NFS-e").
            'separada' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'modelo.in' => 'Modelo deve ser 55 (NFe) ou 65 (NFC-e).',
        ];
    }
}
