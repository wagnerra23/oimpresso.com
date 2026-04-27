<?php

namespace Modules\Copiloto\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Modules\Copiloto\Support\ContextoNegocio;
use Stringable;

/**
 * SugestoesMetasAgent — gera propostas de metas via structured output JSON Schema.
 *
 * Usa Laravel AI SDK (laravel/ai) — ver ADR 0034 + ADR 0035 (verdade canônica).
 * Substitui o método sugerirMetas() do antigo OpenAiDirectDriver.
 */
class SugestoesMetasAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public ContextoNegocio $ctx,
        public string $promptUsuario,
    ) {
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o Copiloto do oimpresso, um assistente de IA para gestores de PMEs brasileiras.
        Responda sempre em português brasileiro.
        Gere propostas realistas baseadas APENAS nos dados fornecidos — nunca invente números.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'propostas' => $schema->array()->items(
                $schema->object([
                    'nome' => $schema->string()->required(),
                    'metrica' => $schema->string()->required(),
                    'valor_alvo' => $schema->number()->required(),
                    'periodo' => $schema->string()->required(),
                    'dificuldade' => $schema->string()->enum(['facil', 'realista', 'ambicioso'])->required(),
                    'racional' => $schema->string()->required(),
                    'dependencias' => $schema->array()->items($schema->string())->required(),
                ])
            )->required(),
        ];
    }

    public function montarPromptSugestoes(): string
    {
        $ctx = $this->ctx;

        $fat = collect($ctx->faturamento90d)
            ->map(fn ($m) => "  {$m['mes']}: R$ " . number_format($m['valor'], 2, ',', '.'))
            ->implode("\n");

        return <<<PROMPT
        Contexto da empresa "{$ctx->businessName}":
        - Clientes ativos: {$ctx->clientesAtivos}
        - Faturamento últimos 90 dias:
        {$fat}
        - Módulos ativos: {$ctx->modulosAtivos}

        Pedido do gestor: {$this->promptUsuario}

        Gere de 3 a 5 propostas de metas em 3 cenários de dificuldade (facil, realista, ambicioso).
        Pelo menos uma proposta deve ser do tipo "realista".
        Baseie os valores numéricos no histórico fornecido — não invente dados.
        PROMPT;
    }
}
