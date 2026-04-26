<?php

declare(strict_types=1);

namespace App\Services\Evolution\Agents;

use App\Services\Evolution\ProviderRouter;
use App\Services\Evolution\Tools\Tool;
use Prism\Prism\Facades\Prism;

/**
 * Base de todos os agentes EvolutionAgent.
 *
 * Compatível em forma com Vizra\BaseLlmAgent (`run()`, `withTool()`, `getSystemPrompt()`).
 * Quando Vizra publicar suporte a Laravel 13 (issue upstream), trocar `extends BaseAgent`
 * pra `extends Vizra\BaseLlmAgent` é o que esse layer existe pra permitir.
 *
 * @see memory/requisitos/EvolutionAgent/adr/arq/0001-vizra-adk-como-base.md
 */
abstract class BaseAgent
{
    /** @var array<string, Tool> */
    protected array $tools = [];

    protected string $model = 'claude-sonnet-4-5';

    protected string $scope = 'geral';

    public function withTool(Tool $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /** @return array<string, Tool> */
    public function getTools(): array
    {
        return $this->tools;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function withModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    abstract public function getSystemPrompt(): string;

    /**
     * Roda 1 turn da conversa. Sem chamada API real se ANTHROPIC_API_KEY vazio
     * (modo offline, retorna texto baseado em tools).
     */
    public function run(string $userMessage): AgentResponse
    {
        $start = microtime(true);
        $traces = [];

        // Tier read-only Fase 1: agente sempre puxa MemoryQuery primeiro pro contexto.
        $memoryHits = [];
        if (isset($this->tools['MemoryQuery'])) {
            $tool = $this->tools['MemoryQuery'];
            $hits = $tool(['query' => $userMessage, 'top_k' => 5, 'scope' => $this->scope === 'geral' ? null : $this->scope]);
            $traces[] = ['tool' => 'MemoryQuery', 'input' => ['query' => $userMessage], 'output_count' => is_array($hits) ? count($hits) : 0];
            $memoryHits = is_array($hits) ? $hits : [];
        }

        [$provider, $modelId] = ProviderRouter::resolve($this->model);
        $apiKey = (string) config('prism.providers.'.$provider->value.'.api_key', '');

        if ($apiKey === '') {
            // Modo offline: resposta baseada em tools, sem custo.
            $text = $this->offlineSummary($userMessage, $memoryHits);

            return new AgentResponse(
                text: $text,
                traces: $traces,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
            );
        }

        $context = $this->renderMemoryContext($memoryHits);
        $prompt = $context !== '' ? ($context."\n\n---\n\nPergunta: ".$userMessage) : $userMessage;

        try {
            $response = Prism::text()
                ->using($provider, $modelId)
                ->withSystemPrompt($this->getSystemPrompt())
                ->withPrompt($prompt)
                ->asText();

            return new AgentResponse(
                text: $response->text ?? '',
                traces: $traces,
                tokensIn: $response->usage->promptTokens ?? 0,
                tokensOut: $response->usage->completionTokens ?? 0,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
            );
        } catch (\Throwable $e) {
            return new AgentResponse(
                text: '['.static::class.'] erro: '.$e->getMessage()."\n\n".$this->offlineSummary($userMessage, $memoryHits),
                traces: $traces,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     */
    protected function renderMemoryContext(array $hits): string
    {
        if (empty($hits)) {
            return '';
        }

        $out = "Contexto extraído de memory/:\n";
        foreach ($hits as $i => $hit) {
            $heading = $hit['heading'] ?? '';
            $file = $hit['file'] ?? '?';
            $body = mb_substr((string) ($hit['content'] ?? ''), 0, 600);
            $out .= sprintf("\n[%d] %s%s\n%s\n", $i + 1, $file, $heading !== '' ? ' › '.$heading : '', $body);
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     */
    protected function offlineSummary(string $userMessage, array $hits): string
    {
        if (empty($hits)) {
            return sprintf(
                "[%s · offline] Nenhum trecho relevante encontrado para: %s\nDica: rode `php artisan evolution:index` antes ou configure ANTHROPIC_API_KEY.",
                static::class,
                $userMessage
            );
        }

        $lines = [sprintf('[%s · offline] Top trechos para "%s":', static::class, $userMessage)];
        foreach (array_slice($hits, 0, 3) as $i => $hit) {
            $lines[] = sprintf(
                "%d. %s%s — score %.3f",
                $i + 1,
                $hit['file'] ?? '?',
                isset($hit['heading']) && $hit['heading'] !== '' ? ' › '.$hit['heading'] : '',
                (float) ($hit['score'] ?? 0)
            );
        }

        return implode("\n", $lines);
    }
}
