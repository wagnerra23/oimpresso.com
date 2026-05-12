<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\BriefDiario;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Services\BriefDiarioService;
use Stringable;

/**
 * Tool BriefDiarioAgent — fonte 3/5: top 5 tickets priorizados.
 *
 * Retorna conversas omnichannel abertas com unread_count + última msg +
 * prioridade heurística (P1-P4) + flag palavra crítica.
 *
 * ADR 0141 — Tier 0 mecânico via constructor.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
class TicketsTopTool implements Tool
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna os top 5 tickets/conversas mais urgentes do business: '
            .'conv_id, nome do contato, unread_count, status, última mensagem, '
            .'prioridade heurística P1-P4, flag se contém palavra crítica '
            .'(reclamação, cancelar, urgente, sefaz). Use quando precisar alertar '
            .'sobre atendimentos pendentes ou citar conversas específicas.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = new BriefDiarioService($this->businessId);
        $data = $service->ticketsPriorizados();

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
