<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use Prism\Prism\Enums\Provider;

/**
 * Resolve string "<provider-slug>:<model-id>" → [Provider enum, string model].
 *
 * Quando o slug é omitido, default = anthropic (alinhado com ADR tech/0001).
 * Aceita aliases curtos pra ergonomia (`opus`, `sonnet`, `haiku`, `deepseek`, `grok`).
 *
 * @see memory/requisitos/EvolutionAgent/adr/tech/0004-roteamento-multi-provider.md
 */
class ProviderRouter
{
    /** @var array<string, array{0:Provider, 1:string}> */
    private const ALIASES = [
        'opus' => [Provider::Anthropic, 'claude-opus-4-5'],
        'sonnet' => [Provider::Anthropic, 'claude-sonnet-4-5'],
        'haiku' => [Provider::Anthropic, 'claude-haiku-4-5'],
        'deepseek' => [Provider::DeepSeek, 'deepseek-chat'],
        'deepseek-r1' => [Provider::DeepSeek, 'deepseek-reasoner'],
        'grok' => [Provider::XAI, 'grok-2-1212'],
        'gpt-4o' => [Provider::OpenAI, 'gpt-4o'],
        'gpt-4o-mini' => [Provider::OpenAI, 'gpt-4o-mini'],
    ];

    /**
     * @return array{0:Provider, 1:string}
     */
    public static function resolve(string $modelString): array
    {
        $modelString = trim($modelString);

        if (isset(self::ALIASES[$modelString])) {
            return self::ALIASES[$modelString];
        }

        if (! str_contains($modelString, ':')) {
            // sem slug — default Anthropic (ADR tech/0001)
            return [Provider::Anthropic, $modelString];
        }

        [$slug, $modelId] = explode(':', $modelString, 2);
        $slug = mb_strtolower(trim($slug));
        $modelId = trim($modelId);

        $provider = self::providerFromSlug($slug);

        return [$provider, $modelId];
    }

    private static function providerFromSlug(string $slug): Provider
    {
        foreach (Provider::cases() as $case) {
            if ($case->value === $slug) {
                return $case;
            }
        }

        // slug desconhecido — fallback Anthropic com warning silencioso (logger não aqui pra evitar boot custom)
        return Provider::Anthropic;
    }
}
