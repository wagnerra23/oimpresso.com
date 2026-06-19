<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Modules\Jana\Services\Memoria\DistillerModuloVerdade;
use Modules\Jana\Services\Memoria\ModuleTruthEventCollector;

/**
 * PR-C2 do keystone distiller-módulo-verdade ([ADR 0291] D-C · emenda 0270 F3).
 *
 * Driver do motor: COLETA os eventos recentes de um módulo (sessions/handoffs/PRs/
 * audits — parte impura, FS + git) e chama o DistillerModuloVerdade (PR-C1) que
 * reescreve a porta BRIEFING.md. Cron diário torna o brief-update obrigatório/
 * auditável (D-3). EXECUÇÃO em prod é gate Wagner/CT100 (ADR 0291 D-E) — este comando
 * existe pra rodar quando o Wagner mandar (smoke skim 10min/lote), não automaticamente
 * sem supervisão até o gate liberar.
 *
 * Dirs configuráveis (testabilidade — espelha jana.handoffs_dir):
 *   jana.requisitos_dir · jana.sessions_dir · jana.handoffs_dir
 */
class DistillModuleTruthCommand extends Command
{
    protected $signature = 'jana:distill-module-truth
                            {--module= : Módulo específico (ex: Financeiro)}
                            {--all : Destila todos os módulos que já têm porta BRIEFING.md}
                            {--dry-run : Calcula e mostra o resultado, NÃO escreve}';

    protected $description = 'Destila a verdade-do-módulo: eventos recentes → reescreve BRIEFING.md (ADR 0291 · porta única mutável)';

    public function handle(DistillerModuloVerdade $distiller): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $modules = $this->resolveModules();

        if ($modules === []) {
            $this->error('Nenhum módulo a destilar. Use --module=<Nome> ou --all.');

            return self::FAILURE;
        }

        $now = now()->toDateString();
        $failures = 0;

        foreach ($modules as $module) {
            $briefingPath = $this->reqDir() . "/{$module}/BRIEFING.md";
            $events = $this->gatherEvents($module, $now);
            $lastDistilledAt = $this->lastDistilledAt($briefingPath);

            $r = $distiller->destilar($module, $events, $briefingPath, $lastDistilledAt, $dryRun, $now);

            $this->reportar($module, $r, count($events));
            if (($r['status'] ?? '') === 'refused_pii') {
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, string> */
    private function resolveModules(): array
    {
        if ($m = $this->option('module')) {
            return [(string) $m];
        }
        if (! $this->option('all')) {
            return [];
        }
        // --all: módulos que JÁ têm porta (rollout: só refresca o que existe).
        $out = [];
        foreach (glob($this->reqDir() . '/*/BRIEFING.md') ?: [] as $briefing) {
            $out[] = basename(dirname($briefing));
        }
        sort($out);

        return $out;
    }

    /**
     * Coleta candidatos (impuro — FS + git). A SELEÇÃO/janela é do collector (puro).
     *
     * @return array<int, array<string, mixed>>
     */
    private function gatherEvents(string $module, string $now): array
    {
        return [
            ...$this->scanDocs($this->sessionsDir(), 'session', $module),
            ...$this->scanDocs($this->handoffsDir(), 'handoff', $module),
            ...$this->scanAudits($module),
            ...$this->scanPrs($module, $now),
        ];
    }

    /**
     * Sessions/handoffs que citam o módulo (filename ou `Modules/<Mod>` no corpo).
     * Data = prefixo YYYY-MM-DD do nome do arquivo (convenção); senão null.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scanDocs(string $dir, string $type, string $module): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $needle = mb_strtolower($module);
        $events = [];
        foreach (glob($dir . '/*.md') ?: [] as $path) {
            $name = basename($path);
            $body = (string) @file_get_contents($path);
            $cita = str_contains($body, "Modules/{$module}")
                || str_contains(mb_strtolower($name), $needle)
                || str_contains(mb_strtolower($body), mb_strtolower("resources/js/Pages/{$module}"));
            if (! $cita) {
                continue;
            }
            $date = preg_match('/^(\d{4}-\d{2}-\d{2})/', $name, $m) ? $m[1] : null;
            $events[] = ['type' => $type, 'ref' => "{$type}s/{$name}", 'date' => $date, 'modules' => [$module], 'title' => $name];
        }

        return $events;
    }

    /**
     * AUDIT/AUDITORIA/CAPTERRA do próprio módulo (proveniência local).
     *
     * @return array<int, array<string, mixed>>
     */
    private function scanAudits(string $module): array
    {
        $dir = $this->reqDir() . "/{$module}";
        if (! is_dir($dir)) {
            return [];
        }
        $events = [];
        foreach (glob($dir . '/{AUDIT,AUDITORIA,CAPTERRA}*.md', GLOB_BRACE) ?: [] as $path) {
            $name = basename($path);
            $events[] = ['type' => 'audit', 'ref' => "requisitos/{$module}/{$name}", 'date' => null, 'modules' => [$module], 'title' => $name];
        }

        return $events;
    }

    /**
     * PRs/commits mergeados tocando Modules/<Mod> ou Pages/<Mod> (best-effort via git).
     * Process::fake() nos testes → sem git real. Falha de git não derruba a destilação.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scanPrs(string $module, string $now): array
    {
        $since = ModuleTruthEventCollector::windowStart(null, ModuleTruthEventCollector::DEFAULT_WINDOW_DAYS, $now);
        try {
            $result = Process::path(base_path())->run(
                'git log --since=' . escapeshellarg($since) . ' --format=%cs|%s -n 40 -- '
                . escapeshellarg("Modules/{$module}") . ' ' . escapeshellarg("resources/js/Pages/{$module}")
            );
            if (! $result->successful()) {
                return [];
            }
            $events = [];
            foreach (preg_split('/\r?\n/', trim($result->output())) ?: [] as $line) {
                if ($line === '' || ! str_contains($line, '|')) {
                    continue;
                }
                [$date, $subject] = explode('|', $line, 2);
                $events[] = ['type' => 'pr', 'ref' => $subject, 'date' => $date, 'modules' => [$module], 'title' => $subject];
            }

            return $events;
        } catch (\Throwable) {
            return []; // git ausente/erro — best-effort, não bloqueia
        }
    }

    private function lastDistilledAt(string $briefingPath): ?string
    {
        if (! is_file($briefingPath)) {
            return null;
        }
        $content = (string) file_get_contents($briefingPath);

        return preg_match('/^distilled_at:\s*["\']?(\d{4}-\d{2}-\d{2})/m', $content, $m) ? $m[1] : null;
    }

    /** @param array<string, mixed> $r */
    private function reportar(string $module, array $r, int $candidatos): void
    {
        $status = (string) ($r['status'] ?? '?');
        $sel = $r['selected'] ?? 0;
        match ($status) {
            'written' => $this->info("✓ {$module}: porta reescrita ({$sel} eventos de {$candidatos} candidatos)."),
            'dry' => $this->line("• {$module}: dry-run ({$sel} eventos) — não escrito. Use sem --dry-run pra aplicar."),
            'refused_pii' => $this->error("✗ {$module}: RECUSADO — LLM emitiu PII (" . implode(',', array_keys($r['pii'] ?? [])) . "). Porta preservada."),
            'no_events' => $this->line("· {$module}: sem eventos recentes — porta inalterada."),
            default => $this->warn("? {$module}: status inesperado '{$status}'."),
        };
    }

    private function reqDir(): string
    {
        return (string) config('jana.requisitos_dir', base_path('memory/requisitos'));
    }

    private function sessionsDir(): string
    {
        return (string) config('jana.sessions_dir', base_path('memory/sessions'));
    }

    private function handoffsDir(): string
    {
        return (string) config('jana.handoffs_dir', base_path('memory/handoffs'));
    }
}
