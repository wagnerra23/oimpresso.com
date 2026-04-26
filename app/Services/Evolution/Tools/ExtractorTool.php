<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use App\Services\Evolution\ProviderRouter;
use Prism\Prism\Facades\Prism;

/**
 * Extração mecânica em volume: tagging, summarization, reformulação estruturada.
 * Usa DeepSeek-V3 por default — ~3× mais barato que Haiku 4.5 com qualidade
 * empate-tecnico em benchmarks de extração (MMLU 88).
 *
 * Modo offline (sem DEEPSEEK_API_KEY): retorna passthrough do texto + nota.
 *
 * @see memory/requisitos/EvolutionAgent/adr/tech/0004-roteamento-multi-provider.md
 */
class ExtractorTool implements Tool
{
    public function __construct(
        private readonly ?string $defaultModel = null,
    ) {}

    public function name(): string
    {
        return 'Extractor';
    }

    public function description(): string
    {
        return 'Extração mecânica em volume (DeepSeek-V3): tag, resumo, reformulação JSON.';
    }

    public function __invoke(array $args = [])
    {
        $text = (string) ($args['text'] ?? '');
        $instruction = (string) ($args['instruction'] ?? 'Resuma em 2 linhas e extraia 3 tags.');
        $modelString = (string) ($args['model']
            ?? $this->defaultModel
            ?? config('evolution.extractor_provider', 'deepseek').':'.config('evolution.extractor_model', 'deepseek-chat'));

        if ($text === '') {
            return ['error' => 'parâmetro "text" obrigatório'];
        }

        [$provider, $modelId] = ProviderRouter::resolve($modelString);
        $apiKey = (string) config('prism.providers.'.$provider->value.'.api_key', '');

        if ($apiKey === '') {
            return [
                'mode' => 'offline',
                'provider' => $provider->value,
                'model' => $modelId,
                'text_preview' => mb_substr($text, 0, 200),
                'note' => 'Sem '.strtoupper($provider->value).'_API_KEY — retornando passthrough.',
            ];
        }

        try {
            $response = Prism::text()
                ->using($provider, $modelId)
                ->withSystemPrompt('Você é extrator mecânico. Devolva exatamente no formato pedido. Sem prosa extra.')
                ->withPrompt($instruction."\n\n---\n\n".$text)
                ->asText();

            return [
                'mode' => 'live',
                'provider' => $provider->value,
                'model' => $modelId,
                'output' => trim((string) ($response->text ?? '')),
                'tokens_in' => $response->usage->promptTokens ?? 0,
                'tokens_out' => $response->usage->completionTokens ?? 0,
            ];
        } catch (\Throwable $e) {
            return [
                'mode' => 'error',
                'provider' => $provider->value,
                'model' => $modelId,
                'error' => $e->getMessage(),
            ];
        }
    }
}
