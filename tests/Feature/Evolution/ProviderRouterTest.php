<?php

declare(strict_types=1);

use App\Services\Evolution\ProviderRouter;
use Prism\Prism\Enums\Provider;

it('aliases curtos são resolvidos', function () {
    expect(ProviderRouter::resolve('opus'))->toBe([Provider::Anthropic, 'claude-opus-4-5']);
    expect(ProviderRouter::resolve('sonnet'))->toBe([Provider::Anthropic, 'claude-sonnet-4-5']);
    expect(ProviderRouter::resolve('haiku'))->toBe([Provider::Anthropic, 'claude-haiku-4-5']);
    expect(ProviderRouter::resolve('deepseek'))->toBe([Provider::DeepSeek, 'deepseek-chat']);
    expect(ProviderRouter::resolve('grok'))->toBe([Provider::XAI, 'grok-2-1212']);
    expect(ProviderRouter::resolve('gpt-4o-mini'))->toBe([Provider::OpenAI, 'gpt-4o-mini']);
});

it('formato <slug>:<modelo> resolve provider correto', function () {
    expect(ProviderRouter::resolve('deepseek:deepseek-reasoner'))
        ->toBe([Provider::DeepSeek, 'deepseek-reasoner']);
    expect(ProviderRouter::resolve('xai:grok-2-1212'))
        ->toBe([Provider::XAI, 'grok-2-1212']);
    expect(ProviderRouter::resolve('openai:gpt-4o'))
        ->toBe([Provider::OpenAI, 'gpt-4o']);
    expect(ProviderRouter::resolve('anthropic:claude-sonnet-4-5'))
        ->toBe([Provider::Anthropic, 'claude-sonnet-4-5']);
});

it('sem slug → default Anthropic (ADR tech/0001)', function () {
    expect(ProviderRouter::resolve('claude-sonnet-4-5'))
        ->toBe([Provider::Anthropic, 'claude-sonnet-4-5']);
});

it('slug desconhecido cai em Anthropic (fallback silencioso)', function () {
    [$provider, $model] = ProviderRouter::resolve('inexistente:foo-bar');
    expect($provider)->toBe(Provider::Anthropic);
    expect($model)->toBe('foo-bar');
});

it('case-insensitive no slug', function () {
    expect(ProviderRouter::resolve('DEEPSEEK:deepseek-chat'))
        ->toBe([Provider::DeepSeek, 'deepseek-chat']);
});
