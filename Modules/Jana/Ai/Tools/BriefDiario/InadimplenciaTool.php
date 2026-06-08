<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\BriefDiario;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Services\BriefDiarioService;
use Stringable;

/**
 * Tool BriefDiarioAgent — fonte 2/5: inadimplência por bucket de dias.
 *
 * Buckets: em_dia / 0_30 / 30_60 / 60_90 / mais_90.
 * Retorna count + total devido + top 5 devedores.
 *
 * ADR 0141 — Tier 0 mecânico via constructor.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
class InadimplenciaTool implements Tool
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna inadimplência consolidada do business: buckets por dias de atraso '
            .'(em_dia, 0-30, 30-60, 60-90, +90 dias), total devido atrasado, quantidade '
            .'de clientes inadimplentes, e top 5 maiores devedores. Use quando precisar '
            .'alertar sobre cobrança ou citar nomes específicos de clientes com risco.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = new BriefDiarioService($this->businessId);
        $data = $service->inadimplenciaBuckets();

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
