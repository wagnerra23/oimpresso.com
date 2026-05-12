<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\BriefDiario;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Services\BriefDiarioService;
use Stringable;

/**
 * Tool BriefDiarioAgent — fonte 4/5: status NF-e emitidas últimos 30 dias.
 *
 * Retorna emitidas / rejeitadas / pendentes 30d + taxa rejeição + top 5
 * códigos de status (cstat) de rejeição agrupados.
 *
 * Sinal fiscal crítico — taxa rejeição > 10% sinaliza problema operacional.
 *
 * ADR 0141 — Tier 0 mecânico via constructor.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
class NfeStatusTool implements Tool
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna status fiscal NF-e do business últimos 30 dias: total '
            .'emitidas, rejeitadas, pendentes, taxa de rejeição (%), e top 5 '
            .'códigos cstat (status SEFAZ) de rejeição agrupados. Use quando '
            .'precisar alertar sobre problemas fiscais ou citar cstat específicos.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = new BriefDiarioService($this->businessId);
        $data = $service->nfeStatus();

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
