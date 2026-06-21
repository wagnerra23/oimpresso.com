<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Detector de task_ids US-* órfãs — presentes no DB (mcp_tasks) mas AUSENTES de
 * qualquer SPEC.md canônico (memory/requisitos/<Mod>/SPEC.md).
 *
 * Por que importa (incidente US-RB-052, 2026-06-20): uma US-* que vive só no DB
 * (criada ad-hoc / direto no DB, ou resíduo de sync antigo) é invisível a quem lê
 * o SPEC, mas o `tasks-create` PRECISA contá-la pra não reusar o ID. Se o ID for
 * reusado, o webhook sync casa por task_id e UPDATE-a a órfã (ADR 0144),
 * sobrescrevendo title/description em silêncio.
 *
 * `TaskCrudService::gerarProximoIdCanonical` já defende a ALOCAÇÃO (max(DB,SPEC) +
 * guarda de colisão); este comando é a TRIAGEM das órfãs que já existem — pra
 * decidir renomear / adotar como US do SPEC / cancelar.
 *
 * Tasks fechadas (done/cancelled) cujo bullet saiu do SPEC após o merge são
 * esperadas e NÃO colidem (o max(nDb) ainda as conta) — ficam ocultas por padrão;
 * use --include-closed pra vê-las.
 *
 * Saída: tabela no stdout (ou --json) + log estruturado. Exit 1 se houver órfã
 * visível (permite usar como gate/cron).
 *
 * Multi-tenant: `mcp_tasks` é project-wide (ADR 0070), sem business_id.
 */
class McpTasksOrphansCommand extends Command
{
    protected $signature = 'mcp:tasks:orphans
                            {--module= : Limita a um módulo (cruza só aquele SPEC.md + rows com module=<X>)}
                            {--include-closed : Inclui done/cancelled (default: oculta — protegidas pelo max(nDb))}
                            {--json : Output JSON em vez de tabela}';

    protected $description = 'Detecta task_ids US-* no DB ausentes do SPEC.md (órfãs) — risco de colisão/sobrescrita silenciosa no sync (ADR 0144 / incidente US-RB-052).';

    public function handle(): int
    {
        $apenasModulo = $this->option('module') ?: null;
        $incluirFechadas = (bool) $this->option('include-closed');

        $orfas = $this->detectarOrfas($apenasModulo, $incluirFechadas);

        if ($this->option('json')) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'module' => $apenasModulo,
                'include_closed' => $incluirFechadas,
                'total' => count($orfas),
                'orphans' => $orfas,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($orfas);
        }

        Log::channel('single')->info('mcp:tasks:orphans', [
            'module' => $apenasModulo,
            'include_closed' => $incluirFechadas,
            'total' => count($orfas),
            'task_ids' => array_map(static fn ($o) => $o['task_id'], $orfas),
        ]);

        return $orfas === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Devolve as órfãs: US-* no DB ausentes de qualquer SPEC.md.
     *
     * Público pra ser testável sem console parsing (espelha o padrão de
     * McpTasksHealthCheckCommand::scanStaleness).
     *
     * @return list<array{task_id:string, module:string, status:string, owner:?string, source_path:?string, title:string, created_at:?string}>
     */
    public function detectarOrfas(?string $apenasModulo = null, bool $incluirFechadas = false): array
    {
        $idsNoSpec = $this->idsDeclaradosNosSpecs($apenasModulo);

        $query = McpTask::query()
            ->where('task_id', 'LIKE', 'US-%')
            ->when($apenasModulo !== null, fn ($q) => $q->where('module', $apenasModulo))
            ->when(! $incluirFechadas, fn ($q) => $q->whereNotIn('status', ['done', 'cancelled']));

        $orfas = [];
        foreach ($query->get() as $task) {
            $id = strtoupper((string) $task->task_id);

            // Só US-XX-NNN canônicas — ad-hoc Linear-style (COPI-123) vive só no DB
            // por design (ADR 0070) e não compete por ID no SPEC.
            if (! preg_match('/^US-[A-Z0-9]+-\d+$/', $id)) {
                continue;
            }
            if (isset($idsNoSpec[$id])) {
                continue; // declarada no SPEC → não é órfã
            }

            $orfas[] = [
                'task_id' => (string) $task->task_id,
                'module' => (string) $task->module,
                'status' => (string) $task->status,
                'owner' => $task->owner,
                'source_path' => $task->source_path,
                'title' => (string) $task->title,
                'created_at' => optional($task->created_at)->toDateString(),
            ];
        }

        usort($orfas, static fn ($a, $b) => strcmp($a['task_id'], $b['task_id']));

        return $orfas;
    }

    /**
     * Set (TASK_ID => true) de todas as US-* declaradas nos SPEC.md — headers OU
     * bullets, os mesmos 2 formatos do alocador (ADR 0134).
     *
     * @return array<string, true>
     */
    protected function idsDeclaradosNosSpecs(?string $apenasModulo): array
    {
        $base = base_path('memory/requisitos');
        $ids = [];
        if (! is_dir($base)) {
            return $ids;
        }

        foreach (new \DirectoryIterator($base) as $dir) {
            if ($dir->isDot() || ! $dir->isDir()) {
                continue;
            }
            if ($apenasModulo !== null && $dir->getFilename() !== $apenasModulo) {
                continue;
            }
            $spec = $dir->getPathname() . '/SPEC.md';
            if (! is_file($spec)) {
                continue;
            }
            $content = (string) @file_get_contents($spec);
            // #{2,4} cobre ### E #### (ex: Cms/SPEC.md usa ####) — idem ao
            // US_HEADING_REGEX do parser. Sem isso, US declaradas em heading ####
            // viram falso-positivo de "órfã" (70 falsos no 1º run, 2026-06-21).
            if (preg_match_all('/(?:^#{2,4}|^-)\s+(?:\S+\s+)?(US-[A-Z0-9]+-\d+)/m', $content, $m)) {
                foreach ($m[1] as $id) {
                    $ids[strtoupper($id)] = true;
                }
            }
        }

        return $ids;
    }

    /** @param list<array<string,mixed>> $orfas */
    protected function renderTable(array $orfas): void
    {
        if ($orfas === []) {
            $this->info('✨ Nenhuma task_id US-* órfã (toda US-* no DB consta em algum SPEC.md).');
            return;
        }

        $this->warn(count($orfas) . ' task_id(s) US-* órfã(s) — no DB mas ausentes do SPEC.md:');
        $this->table(
            ['task_id', 'módulo', 'status', 'owner', 'criada', 'source_path'],
            array_map(static fn ($o) => [
                $o['task_id'],
                $o['module'],
                $o['status'],
                $o['owner'] ?? '—',
                $o['created_at'] ?? '—',
                mb_strimwidth((string) ($o['source_path'] ?? '—'), 0, 42, '…'),
            ], $orfas),
        );
        $this->newLine();
        $this->line('Triagem: renomeie pro próximo ID livre (tasks-create já evita reuso), '
            . 'adote a órfã como a US do SPEC, ou cancele (tasks-update <id> status:cancelled).');
    }
}
