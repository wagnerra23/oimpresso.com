<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * ADR 0317 Onda 3 (M3) — flush TRIMESTRAL da fila de revisão de ADR.
 *
 * A linha diária do brief (AdrReviewBriefLineService) tem teto de vazão (top-3);
 * a cauda da fila fica silenciosa por design. Este comando é o contrapeso: 1x por
 * trimestre (Kernel `quarterlyOn`) despeja a fila COMPLETA dos Checks O
 * (morta-mas-canon) e R (revisão vencida) no console + log estruturado, pra
 * triagem Onda 4 (humano + adversarial, 1×1 — invariante Tier 0 da 0317: a
 * máquina NUNCA reescreve frontmatter sozinha).
 *
 * NÃO escreve arquivo em memory/ (árvore deployada perde write sem git — vetor
 * catalogado do incidente P11/distiller): a persistência é o log estruturado +
 * o próprio git canônico onde a triagem acontece.
 *
 * Uso: php artisan governance:adr-review-flush
 * Exit: 0 sempre (sentinela/surfacing, não gate).
 *
 * @see Modules\Governance\Services\AdrReviewBriefLineService (linha diária, top-3)
 * @see scripts/governance/memory-health.mjs (fonte única — Checks O/R)
 */
class AdrReviewFlushCommand extends Command
{
    protected $signature = 'governance:adr-review-flush';

    protected $description = 'Flush trimestral da fila de revisão de ADR (Checks O/R do memory-health) — fila completa, sem teto de vazão (ADR 0317 M3)';

    private const KINDS = ['O' => 'morta-mas-canon', 'R' => 'revisao-vencida'];

    public function handle(): int
    {
        try {
            $result = Process::path(base_path())
                ->timeout(60)
                ->run(['node', 'scripts/governance/memory-health.mjs', '--json']);
            $data = json_decode($result->output(), true);
        } catch (Throwable $e) {
            $this->warn("memory-health indisponível (node ausente?): {$e->getMessage()}");

            return self::SUCCESS; // surfacing best-effort, nunca vira incidente
        }

        if (! is_array($data)) {
            $this->warn('memory-health não produziu JSON — flush pulado.');

            return self::SUCCESS;
        }

        $fila = [];
        $entries = [...array_values((array) ($data['fails'] ?? [])), ...array_values((array) ($data['warns'] ?? []))];
        foreach ($entries as $entry) {
            $check = is_array($entry) ? (string) ($entry['check'] ?? '') : '';
            if (isset(self::KINDS[$check]) && ($entry['kind'] ?? null) === self::KINDS[$check]) {
                $fila[$check] = [
                    'count' => (int) ($entry['count'] ?? 0),
                    'sample' => array_values((array) ($entry['sample'] ?? [])),
                ];
            }
        }

        if ($fila === []) {
            $this->info('Fila de revisão de ADR vazia — nada a triar. ✓');

            return self::SUCCESS;
        }

        foreach ($fila as $check => $q) {
            $this->line(sprintf('Check %s (%s): %d item(s)', $check, self::KINDS[$check], $q['count']));
            foreach ($q['sample'] as $slug) {
                $this->line("  - {$slug}");
            }
        }

        Log::warning('[adr-review-flush] fila trimestral de revisão de ADR pendente de triagem Onda 4 (ADR 0317)', $fila);

        return self::SUCCESS;
    }
}
