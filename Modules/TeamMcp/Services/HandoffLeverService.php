<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use Illuminate\Support\Facades\DB;
use Modules\TeamMcp\Entities\CoworkHandoff;

/**
 * HandoffLeverService — PR-7 Loop de Handoff Zero-Paste (Fase 2 · ADR 0283).
 *
 * Núcleo COMPARTILHADO das 3 levers do loop (re-disparar/devolver/supersede),
 * extraído pra ser a fonte ÚNICA de verdade tanto da tool MCP
 * {@see \Modules\TeamMcp\Mcp\Tools\HandoffLeverTool} (ator-agente, scope fino)
 * quanto do endpoint web do cockpit
 * {@see \Modules\TeamMcp\Http\Controllers\ForjaController::handoffLever} (ator-[W],
 * gate copiloto.mcp.usage.all). Mesmo padrão de {@see HandoffIngestService}, que
 * o `handoff:ingest` (command) e o `handoff-submit` (tool) já compartilham — uma
 * mutação só, dois invólucros de autorização/auditoria.
 *
 * **APPEND-ONLY** ({@see ADR 0130}/0003 · entity {@see CoworkHandoff}): NUNCA delete.
 * Toda lever é uma transição de estado no plano (slug, version):
 *   - **re-disparar** (pending parado/"stale"): lápide na versão atual (`superseded`)
 *     + clone fresco `pending` (version+1). Re-arma o relógio de staleness sem
 *     sobrescrever `created_at` da versão original (esta vira histórico).
 *   - **devolver** (rejected→[CC]): NOVA versão `pending` (version+1) com o mesmo
 *     corpo, pro [CC] retrabalhar. A versão `rejected` FICA como histórico (terminal,
 *     NÃO vira `superseded` — espelha {@see HandoffIngestService} que só dá lápide
 *     em `applied`).
 *   - **supersede** (pending|applied→lápide): marca a versão atual `superseded`.
 *     SEM substituta aqui — o replacement chega depois via `handoff-submit` (Cowork).
 *
 * Sem auto-merge (ADR 0283): nenhuma lever abre/mergeia PR. Stateless e SEM efeito
 * colateral além de cowork_handoffs — NÃO audita (quem chama audita, com o ator
 * certo) e NÃO toca o heartbeat (que é sinal do transporte de INGEST, não de
 * operação manual do [W] — pulsá-lo aqui pintaria "transporte ok" falso).
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffLeverTool
 * @see Modules\TeamMcp\Http\Controllers\ForjaController
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
final class HandoffLeverService
{
    /** Levers válidas (espelha leverFor() do front ForjaMcp.tsx). */
    public const ACTIONS = ['re-disparar', 'devolver', 'supersede'];

    /**
     * Aplica a lever sobre a versão MAIS RECENTE do slug (a que a Forja projeta).
     *
     * @param  string  $action  uma de {@see self::ACTIONS}
     * @param  string  $slug  slug do handoff
     * @param  int|null  $expectedVersion  versão que o operador viu (drift guard A4);
     *                                      null = aceita a maior sem checar
     * @return array{
     *     outcome: 'rejected'|'redisparado'|'devolvido'|'superseded',
     *     reason: string|null, slug: string, version: int, superseded_version: int|null
     * }
     *   reason (quando rejected) ∈ not_found | stale_view | state | action
     */
    public function apply(string $action, string $slug, ?int $expectedVersion = null): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return $this->reject('not_found', $slug, 0);
        }
        if (! in_array($action, self::ACTIONS, true)) {
            return $this->reject('action', $slug, 0);
        }

        $latest = CoworkHandoff::query()
            ->where('slug', $slug)
            ->orderByDesc('version')
            ->first();

        if ($latest === null) {
            return $this->reject('not_found', $slug, 0);
        }

        // A4 drift guard: a fila que o operador viu mudou desde o render.
        if ($expectedVersion !== null && (int) $latest->version !== $expectedVersion) {
            return $this->reject('stale_view', $slug, (int) $latest->version);
        }

        return match ($action) {
            're-disparar' => $this->reDisparar($latest),
            'devolver'    => $this->devolver($latest),
            'supersede'   => $this->supersede($latest),
            // Inalcançável (action já validado acima); default explícito = match
            // exaustivo pro phpstan (codebase usa match-com-default).
            default       => $this->reject('action', $slug, (int) $latest->version),
        };
    }

    /** stale (pending) → lápide + clone fresco pending (append-only). */
    private function reDisparar(CoworkHandoff $latest): array
    {
        if ($latest->status !== 'pending') {
            return $this->reject('state', $latest->slug, (int) $latest->version);
        }

        $newVersion = (int) $latest->version + 1;

        // Duas escritas (lápide + clone) numa transação — sem versão órfã se a 2ª falhar.
        DB::transaction(function () use ($latest, $newVersion): void {
            CoworkHandoff::query()->where('id', $latest->id)->update(['status' => 'superseded']);
            $this->cloneAsPending($latest, $newVersion);
        });

        return [
            'outcome'            => 'redisparado',
            'reason'             => null,
            'slug'               => $latest->slug,
            'version'            => $newVersion,
            'superseded_version' => (int) $latest->version,
        ];
    }

    /** rejected → nova versão pending pro [CC] retrabalhar (rejected fica histórico). */
    private function devolver(CoworkHandoff $latest): array
    {
        if ($latest->status !== 'rejected') {
            return $this->reject('state', $latest->slug, (int) $latest->version);
        }

        $newVersion = (int) $latest->version + 1;
        $this->cloneAsPending($latest, $newVersion);

        return [
            'outcome'            => 'devolvido',
            'reason'             => null,
            'slug'               => $latest->slug,
            'version'            => $newVersion,
            'superseded_version' => null,
        ];
    }

    /** pending|applied → lápide (sem substituta; replacement vem do Cowork depois). */
    private function supersede(CoworkHandoff $latest): array
    {
        if (! in_array($latest->status, ['pending', 'applied'], true)) {
            return $this->reject('state', $latest->slug, (int) $latest->version);
        }

        CoworkHandoff::query()->where('id', $latest->id)->update(['status' => 'superseded']);

        return [
            'outcome'            => 'superseded',
            'reason'             => null,
            'slug'               => $latest->slug,
            'version'            => (int) $latest->version,
            'superseded_version' => (int) $latest->version,
        ];
    }

    /**
     * Clona um handoff numa nova versão `pending` (re-emite o MESMO design). Mantém
     * created_by/source_hash/sig do original (autoria do design e proveniência
     * preservadas); `created_at` fresco re-arma o relógio de staleness na leitura.
     */
    private function cloneAsPending(CoworkHandoff $src, int $version): void
    {
        CoworkHandoff::create([
            'slug'            => $src->slug,
            'version'         => $version,
            'tela'            => $src->tela,
            'status'          => 'pending',
            'audited_against' => $src->audited_against,
            'body_md'         => $src->body_md,
            'files_json'      => $src->files_json,
            'source_hash'     => $src->source_hash,
            'sig'             => $src->sig,
            'created_by'      => $src->created_by,
            'created_at'      => now(),
        ]);
    }

    /**
     * @return array{outcome: 'rejected', reason: string, slug: string, version: int, superseded_version: null}
     */
    private function reject(string $reason, string $slug, int $version): array
    {
        return [
            'outcome'            => 'rejected',
            'reason'             => $reason,
            'slug'               => $slug,
            'version'            => $version,
            'superseded_version' => null,
        ];
    }
}
