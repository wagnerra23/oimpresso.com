<?php

declare(strict_types=1);

namespace Modules\Brief\Services;

use Illuminate\Support\Collection;
use Modules\Jana\Services\WorkLease\WorkLeaseService;
use Throwable;

/**
 * Item C2+C3 (SDD Leva 2) — bloco de leases ATIVOS no Daily Brief (ADR 0091).
 *
 * Superfície determinística (pós-LLM) que injeta, sob a seção `## EM VOO AGORA`,
 * a lista de leases de coordenação ativos (D1, ADR 0278) + um nudge "claim antes
 * de pegar" (ADR 0278) pra que o agente não colida com trabalho já reivindicado.
 *
 * Por que pós-LLM (e não no stored proc / num novo header):
 *  - O corpo do brief é gerado pelo Brain B; leases vivem em runtime (TTL no
 *    Service, não no schema) — o modelo nunca poderia inventar esse estado.
 *  - BriefValidator exige EXATAMENTE 7 headers ordenados; criar um header novo
 *    quebraria a validação. Por isso o bloco entra SOB um header existente
 *    (`## EM VOO AGORA`), exatamente como SddBriefLineService faz com `## FLAGS`.
 *
 * Best-effort: tabela ausente, zero leases, ou qualquer exceção → devolve o
 * conteúdo intacto. O brief NUNCA quebra por causa deste bloco.
 * Kill-switch: `brief.lease_section` false → no-op (default ON).
 *
 * @see Modules\Governance\Services\SddBriefLineService (idiom espelhado)
 * @see Modules\Jana\Services\WorkLease\WorkLeaseService::activeLeases()
 * @see Modules\Brief\Console\Commands\GenerateBriefCommand (plug-point inject)
 * @see memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md
 */
final class LeaseBriefSectionService
{
    /** Teto de leases listados — alinhado ao default de WorkLeaseService::activeLeases(). */
    private const MAX_LEASES = 15;

    /**
     * Injeta o bloco de leases ativos sob `## EM VOO AGORA`. Best-effort:
     * sem leases (ou qualquer falha) devolve o conteúdo intacto.
     * Kill-switch: `brief.lease_section` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('brief.lease_section', true)) {
            return $content;
        }

        try {
            $block = $this->block();
        } catch (Throwable) {
            return $content;
        }

        if ($block === null) {
            return $content;
        }

        $injected = preg_replace(
            '/^## EM VOO AGORA$/m',
            "## EM VOO AGORA\n{$block}",
            $content,
            1,
            $count
        );

        return ($count === 1 && is_string($injected)) ? $injected : $content;
    }

    /**
     * Bloco markdown dos leases ativos, ou null quando não há nenhum
     * (ou a fonte está indisponível). Roteia por WorkLeaseService::activeLeases(),
     * que já varre os expirados (sweepExpired) — leases vencidos NÃO aparecem.
     */
    public function block(): ?string
    {
        /** @var Collection<int, \stdClass> $leases */
        $leases = app(WorkLeaseService::class)->activeLeases(self::MAX_LEASES);

        if ($leases->isEmpty()) {
            return null;
        }

        $bullets = $leases
            ->map(fn (object $lease): string => sprintf(
                '- 🔒 `%s` → %s (expira %s)',
                (string) ($lease->task_id ?? '—'),
                (string) ($lease->human_principal ?? '—'),
                (string) ($lease->expires_at ?? '—'),
            ))
            ->implode("\n");

        $nudge = '↳ claim antes de pegar: rode tasks-claim pra não colidir (ADR 0278)';

        return "{$bullets}\n{$nudge}";
    }
}
