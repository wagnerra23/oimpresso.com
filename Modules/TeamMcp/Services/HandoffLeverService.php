<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Modules\TeamMcp\Entities\CoworkHandoff;

/**
 * HandoffLeverService — PR-7b Loop de Handoff Zero-Paste (Fase 2 · ADR 0283).
 *
 * Núcleo COMPARTILHADO das 3 levers do loop (re-disparar/devolver/supersede),
 * extraído do {@see \Modules\TeamMcp\Mcp\Tools\HandoffLeverTool} (PR-7 #2924) pra
 * ser a fonte ÚNICA tanto da tool MCP (ator-agente, scope fino) quanto do endpoint
 * web do cockpit ({@see \Modules\TeamMcp\Http\Controllers\ForjaController::handoffLever},
 * ator-[W] na Forja) — o browser NÃO é cliente MCP, então as levers do front
 * roteiam por aqui. Mesmo padrão de {@see HandoffIngestService} (command + tool).
 *
 * Semântica IN-PLACE (definida no #2924), idempotente por estado de origem
 * ({@see self::ORIGINS}):
 *   - **re-disparar** (pending parado/"stale"): re-arma a freshness — `status`
 *     segue `pending`, `created_at = now()` (a Forja deriva stale de created_at).
 *   - **devolver** (rejected): reabre pro [CC] — volta a `pending` fresco e limpa
 *     os artefatos do ack (pr_url/gate_status/applied_*).
 *   - **supersede** (pending|applied): marca `superseded` — sai da lista ativa.
 *     Append-only: a linha FICA (NUNCA delete).
 *
 * Sem auto-merge (ADR 0283). Stateless e SEM efeito colateral além de
 * cowork_handoffs — NÃO audita (quem chama audita, com o ator certo). Observability
 * (ADR 0155 D9.a): roda dentro de `OtelHelper::span` (zero-cost quando OTel off),
 * igual {@see \Modules\TeamMcp\Services\Forja\ForjaMcpService}/{@see GitMainResolver}.
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffLeverTool
 * @see Modules\TeamMcp\Http\Controllers\ForjaController
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
final class HandoffLeverService
{
    /** action → status(es) de origem em que a lever morde (canônico, #2924). */
    public const ORIGINS = [
        're-disparar' => ['pending'],
        'devolver'    => ['rejected'],
        'supersede'   => ['pending', 'applied'],
    ];

    /**
     * Resolve a versão alvo no status de origem da lever e aplica o patch in-place.
     *
     * @param  string  $slug  slug do handoff
     * @param  string  $action  uma de {@see self::ORIGINS}
     * @param  int|null  $version  versão alvo (default: a maior no status de origem)
     * @return array{version:int, from:string, to:string}|null  null = action inválida
     *         OU não há versão no estado de origem (idempotência — não muta)
     */
    public function apply(string $slug, string $action, ?int $version = null): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || ! array_key_exists($action, self::ORIGINS)) {
            return null;
        }

        return OtelHelper::span('teammcp.handoff.lever', ['handoff.action' => $action], function () use ($slug, $action, $version): ?array {
            $query = CoworkHandoff::query()
                ->where('slug', $slug)
                ->whereIn('status', self::ORIGINS[$action]);
            if ($version !== null) {
                $query->where('version', $version);
            }

            $row = $query->orderByDesc('version')->first();
            if ($row === null) {
                return null;
            }

            $from = (string) $row->status;
            $changes = $this->changesFor($action);
            $row->update($changes);

            return ['version' => (int) $row->version, 'from' => $from, 'to' => (string) $changes['status']];
        });
    }

    /**
     * O patch de cada lever (semântica in-place do #2924). re-disparar/devolver
     * re-armam a freshness via `created_at = now()`; supersede é terminal.
     *
     * @return array<string,mixed>
     */
    public function changesFor(string $action): array
    {
        return match ($action) {
            // Re-arma na fila ativa: status segue pending, freshness reseta.
            're-disparar' => ['status' => 'pending', 'created_at' => now()],
            // Reabre pro [CC]: volta a pending fresco e limpa os artefatos do ack.
            'devolver' => [
                'status'      => 'pending',
                'created_at'  => now(),
                'applied_at'  => null,
                'applied_by'  => null,
                'pr_url'      => null,
                'gate_status' => null,
            ],
            // Obsoleto: sai da lista ativa (ForjaMcpService exclui superseded).
            'supersede' => ['status' => 'superseded'],
            // action inválida (apply() já barra antes; defesa em profundidade pro match).
            default => throw new \InvalidArgumentException("action inválida: {$action}"),
        };
    }
}
