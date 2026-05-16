<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SendChatMessageRequest — envio de mensagem do usuário ao chat Jana IA.
 *
 * Validação canônica do endpoint POST /copiloto/chat/{id}/send (e variante
 * sendStream SSE). Substitui validação inline `$request->validate([...])` que
 * estava espalhada nos métodos send() e sendStream() do ChatController.
 *
 * REGRAS PII (LGPD Art. 7º + ADR 0061):
 * - Conteúdo é redacted pelo PiiRedactor ANTES de chegar ao modelo (CPF/CNPJ
 *   reais NUNCA vão pro Claude/Groq). Redaction acontece no AiAdapter/Agent,
 *   não aqui — o request precisa preservar o conteúdo original pra UI mostrar
 *   ao usuário que digitou.
 * - max:5000 mantém limite original — anti-prompt-injection e custo Brain B.
 * - Autorização (ownership da conversa) é validada no Controller via
 *   abort_unless($conversa->user_id === auth()->id(), 403) — request só valida
 *   forma do payload, não posse.
 *
 * Tier 0 multi-tenant (ADR 0093): conversa é resolvida por id de rota e o
 * Controller confere business_id via global scope (ScopeByBusiness).
 *
 * @see memory/requisitos/Jana/SPEC.md US-COPI-001 (chat conversacional)
 * @see Modules\Jana\Services\PiiRedactor
 * @see memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md
 */
class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autenticação básica; ownership da conversa é validada no Controller
        // (abort_unless) porque depende do parâmetro de rota {id}.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Conteúdo da mensagem do usuário.
            // - required: chat vazio é noop
            // - string: previne array injection
            // - max:5000: limite UX + custo LLM + anti-prompt-stuffing
            'content' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Digite uma mensagem antes de enviar.',
            'content.max' => 'Mensagem muito longa (máx 5000 caracteres).',
        ];
    }
}
