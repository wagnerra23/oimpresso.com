<?php

namespace Modules\Jana\Contracts;

use Modules\Jana\Entities\Conversa;
use Modules\Jana\Support\ContextoNegocio;

/**
 * Adapter de IA — ver adr/tech/0002-adapter-ia-laravelai-ou-openai.md.
 *
 * Implementações:
 * - Modules\Jana\Services\Ai\LaravelAiDriver (quando módulo LaravelAI ativo)
 * - Modules\Jana\Services\Ai\OpenAiDirectDriver (fallback via openai-php)
 */
interface AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string;

    /**
     * @return array Propostas estruturadas — ver SPEC.md seção Chat/US-COPI-003.
     */
    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array;

    public function responderChat(Conversa $conv, string $mensagem): string;

    /**
     * Streaming de chat: retorna Generator que yields chunks de texto à medida
     * que a IA gera. Permite UX SSE (token-por-token) em vez de blocking.
     *
     * O caller é responsável por:
     *   - Persistir a mensagem do user ANTES de chamar (igual responderChat)
     *   - Acumular chunks numa string e persistir Mensagem assistant ao fim
     *   - Persistir tokens_in/out lendo `ultimoUsoTokens()` DEPOIS de criar a
     *     mensagem assistant do turno
     *
     * @return \Generator<int, string, void, void> Yields chunks de texto.
     *         Itera até retornar (fim do stream OpenAI).
     */
    public function responderChatStream(Conversa $conv, string $mensagem): \Generator;

    /**
     * Resultado estrutural da ÚLTIMA chamada a responderChatStream().
     *
     * Não duplica a telemetria do provider/Langfuse: expõe somente a decisão
     * que o controller não consegue inferir dos chunks (cache, clarificação,
     * LLM e erro convertido em resposta amigável).
     *
     * @return array{
     *   path: 'idle'|'dry_run'|'semantic_cache'|'clarify'|'llm',
     *   status: 'idle'|'running'|'ok'|'error'|'partial_error',
     *   cache_hit: bool,
     *   recall_count: int,
     *   jobs_dispatched: int,
     *   error_class: class-string<\Throwable>|null
     * }
     */
    public function ultimoResultadoStream(): array;

    /**
     * Uso de tokens da ÚLTIMA chamada a responderChat()/responderChatStream()
     * nesta instância. Zerado no início de cada chamada; fica com `null` quando
     * não houve consumo real (cache hit semântico, clarify, dry-run, erro).
     *
     * ⚠️ O driver NÃO grava tokens no banco. Quem persiste é o caller — é o
     * único que sabe qual Mensagem pertence ao turno corrente. Os drivers
     * gravavam sozinhos via `latest('created_at')` e isso deslocava os tokens
     * em UM TURNO: no streaming o corpo do generator após o último `yield` roda
     * durante o `next()` do foreach do caller, ou seja ANTES do caller criar a
     * mensagem assistant do turno; no blocking o método retorna antes do
     * `Mensagem::create` do controller. Nos dois casos o UPDATE atingia a
     * mensagem do turno ANTERIOR (ou nenhuma, no 1º turno).
     *
     * Assinatura medida em prod 2026-07-28 (read-only, agregado): em conversas
     * com ≥2 turnos assistant, a ÚLTIMA mensagem estava 6/6 = 100% sem tokens.
     *
     * @return array{tokens_in: int|null, tokens_out: int|null}
     */
    public function ultimoUsoTokens(): array;
}
