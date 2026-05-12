<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\BriefDiario;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Services\BriefDiarioService;
use Stringable;

/**
 * Tool BriefDiarioAgent — fonte 1/5: vendas por período.
 *
 * ADR 0141 — Tier 0 mecânico: $businessId vem do constructor, NUNCA do LLM.
 * Mesmo que LLM tente passar `business_id` no schema (e não tem campo pra isso),
 * a tool ignora — usa só o do constructor.
 *
 * Output: JSON string com hoje/ontem/semana/mês + deltas — direto do
 * BriefDiarioService::vendasPeriodo() já validado em prod biz=1.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class VendasPeriodoTool implements Tool
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna vendas do business em períodos (hoje, ontem, semana atual vs anterior, '
            .'mês corrente vs anterior) com deltas percentuais. Use quando precisar comentar '
            .'tendência de faturamento, pico de venda do dia, ou comparativo mês-a-mês. '
            .'Não recebe parâmetros — sempre retorna o snapshot completo do business autenticado.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = new BriefDiarioService($this->businessId);
        $data = $service->vendasPeriodo();

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        // Sem parâmetros. LLM chama essa tool quando quer ver vendas.
        // Schema vazio é válido — laravel/ai aceita.
        return [];
    }
}
