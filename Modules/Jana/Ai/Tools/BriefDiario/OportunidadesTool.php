<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\BriefDiario;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Services\BriefDiarioService;
use Stringable;

/**
 * Tool BriefDiarioAgent — fonte 5/5: oportunidades de upsell.
 *
 * Retorna 2 buckets:
 *  - combo_candidatos: clientes que compram produto X >= 5x em 90d
 *    (sinal de reposição automática ou bundle)
 *  - reativacao_candidatos: clientes com LTV > 0 e > 180 dias sem comprar
 *
 * ADR 0141 — Tier 0 mecânico via constructor.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
class OportunidadesTool implements Tool
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna oportunidades de upsell do business em 2 buckets: '
            .'combo_candidatos (clientes que compram mesmo produto >= 5x em 90d — '
            .'sinal de reposição/bundle) e reativacao_candidatos (clientes com '
            .'LTV > 0 e > 180 dias sem comprar, top 10). Use quando precisar '
            .'sugerir ações comerciais ou citar clientes específicos pra reativar.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = new BriefDiarioService($this->businessId);
        $data = $service->oportunidadesUpsell();

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
