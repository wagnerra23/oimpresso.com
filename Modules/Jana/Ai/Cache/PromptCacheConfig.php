<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Cache;

/**
 * GAP D4 #5 — Prompt caching live (Anthropic).
 *
 * Gerencia `cache_control` markers que sinalizam blocos cacheáveis nos
 * requests pra Anthropic Messages API. Anthropic reusa o **prefixo** do
 * prompt (tools → system → messages, nesta ordem) até o último bloco
 * marcado com `cache_control` — cache hit gera `cache_read_input_tokens`,
 * 90% mais baratos que input regular.
 *
 * Estratégia oimpresso (validada pelo padrão ~74% cache_read atual referenciado
 * em ADR 0053):
 *  - Bloco 1: SYSTEM PROMPT (instructions agent — raramente muda) → CACHE
 *  - Bloco 2: BUSINESS CONTEXT (ContextoNegocio do business — muda por sessão
 *    mas estável dentro da janela 5min) → CACHE
 *  - Bloco 3: TOOL DEFINITIONS (raríssimo mudar) → CACHE
 *  - Bloco 4: USER MESSAGE (sempre novo) → NÃO CACHE
 *
 * Limites Anthropic:
 *  - Máximo 4 cache breakpoints por request
 *  - Default TTL `ephemeral` 5min; `1h` disponível com `ttl: "1h"` (custa 2x
 *    write, mas hit segue 0.1x — ideal pra system prompts reusados > 5min)
 *
 * Pattern canônico laravel/ai: Agent implementa `HasProviderOptions` retornando
 * `['system' => [...blocks com cache_control], 'tools' => [...]]` que
 * `BuildsTextRequests::buildTextRequestBody` faz `array_merge` por cima do body
 * — sobrescreve `$body['system']` (string default) sem precisar fork do SDK.
 *
 * @see https://docs.anthropic.com/en/docs/build-with-claude/prompt-caching
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md (laravel/ai canônico)
 * @see memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md (driver custom)
 */
final class PromptCacheConfig
{
    /** TTL ephemeral default da Anthropic (5min). */
    public const TTL_EPHEMERAL = 'ephemeral';

    /** TTL estendido — 2x write cost, ideal pra system prompts reusados > 5min. */
    public const TTL_1_HOUR = '1h';

    /**
     * Declaração canônica dos blocos cacheáveis no oimpresso.
     * Source-of-truth single — qualquer agent novo consulta aqui.
     */
    public static function shouldCacheBlock(string $blockType): bool
    {
        return match ($blockType) {
            'system'             => true,  // instructions do agent
            'business_context'   => true,  // ContextoNegocio compacto
            'tool_definitions'   => true,  // funções declaradas pra LLM
            'user_message'       => false, // sempre novo
            'assistant_response' => false, // sempre novo
            default              => false,
        };
    }

    /**
     * Marker padrão pra anexar em qualquer bloco cacheável.
     * Retorna `['type' => 'ephemeral']` — TTL default 5min Anthropic.
     *
     * @return array{type:string}
     */
    public static function cacheControlMarker(): array
    {
        return ['type' => self::TTL_EPHEMERAL];
    }

    /**
     * Marker com TTL 1h — usar quando system prompt é estável e reusado
     * em janela > 5min (ex: BriefingAgent rodando 1x/dia por business).
     *
     * @return array{type:string, ttl:string}
     */
    public static function cacheControlMarker1Hour(): array
    {
        return ['type' => self::TTL_EPHEMERAL, 'ttl' => self::TTL_1_HOUR];
    }

    /**
     * Monta bloco `text` Anthropic com `cache_control` injetado.
     * Helper pra evitar repetição em Agents.
     *
     * @return array{type:string, text:string, cache_control:array{type:string}}
     */
    public static function textBlockCached(string $text, bool $oneHour = false): array
    {
        return [
            'type'          => 'text',
            'text'          => $text,
            'cache_control' => $oneHour
                ? self::cacheControlMarker1Hour()
                : self::cacheControlMarker(),
        ];
    }

    /**
     * Indica se cache está habilitado globalmente (kill-switch operacional).
     * Default ON — desliga via env `COPILOTO_PROMPT_CACHE_ENABLED=false`
     * em caso de regressão observada.
     */
    public static function isEnabled(): bool
    {
        return (bool) env('COPILOTO_PROMPT_CACHE_ENABLED', true);
    }

    /**
     * Tamanho mínimo (chars) abaixo do qual NÃO vale marcar pra cache.
     * Anthropic exige conteúdo mínimo (1024 tokens pra Sonnet/Opus, 2048 pra Haiku)
     * — usamos heurística conservadora: ~4 chars/token → 4096 chars mínimos.
     */
    public static function minCacheableChars(): int
    {
        return (int) env('COPILOTO_PROMPT_CACHE_MIN_CHARS', 4096);
    }
}
