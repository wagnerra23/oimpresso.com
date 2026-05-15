<?php

declare(strict_types=1);

use Laravel\Ai\Enums\Lab;
use Modules\Jana\Ai\Agents\BriefingAgent;
use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Ai\Cache\PromptCacheConfig;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Support\ContextoNegocio;

uses(Tests\TestCase::class);

/**
 * GAP D4 #5 — Prompt caching live (Anthropic).
 *
 * Cobre 7 invariantes:
 *  001. `shouldCacheBlock('system')` → true (whitelist canônica)
 *  002. `shouldCacheBlock('user_message')` → false (sempre novo)
 *  003. `shouldCacheBlock('unknown')` → false (default seguro)
 *  004. `cacheControlMarker()` retorna shape Anthropic exato
 *  005. ChatCopilotoAgent->providerOptions(Anthropic) injeta cache_control
 *  006. ChatCopilotoAgent->providerOptions(OpenAI) retorna [] (não-Anthropic)
 *  007. Kill-switch env `COPILOTO_PROMPT_CACHE_ENABLED=false` desliga
 *
 * @see memory/requisitos/Jana/PROMPT-CACHING-LIVE.md
 */
it('R-COPI-D4-5-001 — shouldCacheBlock(system) é cacheável (whitelist)', function () {
    expect(PromptCacheConfig::shouldCacheBlock('system'))->toBeTrue();
    expect(PromptCacheConfig::shouldCacheBlock('business_context'))->toBeTrue();
    expect(PromptCacheConfig::shouldCacheBlock('tool_definitions'))->toBeTrue();
});

it('R-COPI-D4-5-002 — shouldCacheBlock(user_message) NÃO é cacheável (sempre novo)', function () {
    expect(PromptCacheConfig::shouldCacheBlock('user_message'))->toBeFalse();
    expect(PromptCacheConfig::shouldCacheBlock('assistant_response'))->toBeFalse();
});

it('R-COPI-D4-5-003 — shouldCacheBlock(unknown) default seguro = false', function () {
    expect(PromptCacheConfig::shouldCacheBlock('foobar_unknown'))->toBeFalse();
    expect(PromptCacheConfig::shouldCacheBlock(''))->toBeFalse();
});

it('R-COPI-D4-5-004 — cacheControlMarker retorna shape Anthropic exato', function () {
    $marker = PromptCacheConfig::cacheControlMarker();

    expect($marker)->toBe(['type' => 'ephemeral']);

    $marker1h = PromptCacheConfig::cacheControlMarker1Hour();
    expect($marker1h)->toBe(['type' => 'ephemeral', 'ttl' => '1h']);

    $block = PromptCacheConfig::textBlockCached('foo bar baz');
    expect($block)->toHaveKeys(['type', 'text', 'cache_control']);
    expect($block['type'])->toBe('text');
    expect($block['text'])->toBe('foo bar baz');
    expect($block['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('R-COPI-D4-5-005 — ChatCopilotoAgent->providerOptions(Anthropic) injeta cache_control no system', function () {
    // Garante cache habilitado pra este teste
    putenv('COPILOTO_PROMPT_CACHE_ENABLED=true');
    putenv('COPILOTO_PROMPT_CACHE_MIN_CHARS=10'); // forçar passar threshold

    // ContextoNegocio mínimo válido (sem PII real — biz=1 ADR 0101)
    $ctx = new ContextoNegocio(
        businessId: 1,
        businessName: 'Empresa Teste Ltda',
        faturamento90d: [],
        clientesAtivos: 5,
        modulosAtivos: ['copiloto'],
        metasAtivas: [],
        observacoes: null,
    );

    // Conversa mock-mínima (não persiste — só usado em messages() que não é
    // chamado em providerOptions).
    $conv = new Conversa();
    $conv->id = 1;
    $conv->business_id = 1;
    $conv->user_id = 1;

    $agent = new ChatCopilotoAgent($conv, memoriaContexto: '', ctx: $ctx);

    $opts = $agent->providerOptions(Lab::Anthropic);

    expect($opts)->toHaveKey('system');
    expect($opts['system'])->toBeArray();
    expect(count($opts['system']))->toBeGreaterThanOrEqual(2); // persona + ctx

    // Último bloco DEVE ter cache_control (Anthropic cacheia prefixo até o
    // marker — colocar no último cobre tudo).
    $lastBlock = $opts['system'][count($opts['system']) - 1];
    expect($lastBlock)->toHaveKey('cache_control');
    expect($lastBlock['cache_control'])->toBe(['type' => 'ephemeral']);

    // Primeiros blocos NÃO precisam de cache_control (prefix matching cobre)
    expect($opts['system'][0])->toHaveKey('text');
    expect($opts['system'][0])->not->toHaveKey('cache_control');
});

it('R-COPI-D4-5-006 — providerOptions(OpenAI) retorna [] — só Anthropic ativa cache', function () {
    putenv('COPILOTO_PROMPT_CACHE_ENABLED=true');
    putenv('COPILOTO_PROMPT_CACHE_MIN_CHARS=10');

    $conv = new Conversa();
    $conv->id = 1;
    $conv->business_id = 1;
    $conv->user_id = 1;

    $agent = new ChatCopilotoAgent($conv);

    // OpenAI tem prompt caching automático (não precisa marker) — driver
    // não injeta nada.
    expect($agent->providerOptions(Lab::OpenAI))->toBe([]);
    expect($agent->providerOptions(Lab::Gemini))->toBe([]);
    expect($agent->providerOptions(Lab::Bedrock))->toBe([]);
});

it('R-COPI-D4-5-007 — kill-switch env=false desliga cache (regressão emergencial)', function () {
    putenv('COPILOTO_PROMPT_CACHE_ENABLED=false');
    putenv('COPILOTO_PROMPT_CACHE_MIN_CHARS=10');

    expect(PromptCacheConfig::isEnabled())->toBeFalse();

    $conv = new Conversa();
    $conv->id = 1;
    $conv->business_id = 1;
    $conv->user_id = 1;

    $agent = new ChatCopilotoAgent($conv);

    // Com kill-switch off, providerOptions retorna [] pra qualquer provider —
    // driver volta a usar `system` string default em BuildsTextRequests.
    expect($agent->providerOptions(Lab::Anthropic))->toBe([]);

    // Restaura pro próximo test
    putenv('COPILOTO_PROMPT_CACHE_ENABLED=true');
});

it('R-COPI-D4-5-008 — BriefingAgent também implementa HasProviderOptions', function () {
    putenv('COPILOTO_PROMPT_CACHE_ENABLED=true');
    putenv('COPILOTO_PROMPT_CACHE_MIN_CHARS=10');

    $ctx = new ContextoNegocio(
        businessId: 1,
        businessName: 'Empresa Teste Ltda',
        faturamento90d: [],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
        observacoes: null,
    );

    $agent = new BriefingAgent($ctx);

    $opts = $agent->providerOptions(Lab::Anthropic);

    expect($opts)->toHaveKey('system');
    expect($opts['system'][0])->toHaveKey('cache_control');
    expect($opts['system'][0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('R-COPI-D4-5-009 — abaixo do mínimo de chars, NÃO marca cache (overhead inútil)', function () {
    putenv('COPILOTO_PROMPT_CACHE_ENABLED=true');
    putenv('COPILOTO_PROMPT_CACHE_MIN_CHARS=999999'); // threshold gigante → não passa

    $conv = new Conversa();
    $conv->id = 1;
    $conv->business_id = 1;

    $agent = new ChatCopilotoAgent($conv);

    // Com threshold acima do tamanho real do instructions, retorna [] —
    // Anthropic ignora marker em conteúdo abaixo do mínimo (~1024 tokens),
    // melhor não enviar.
    expect($agent->providerOptions(Lab::Anthropic))->toBe([]);

    // Restaura
    putenv('COPILOTO_PROMPT_CACHE_MIN_CHARS=4096');
});
