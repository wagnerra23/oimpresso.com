<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Throwable;

/**
 * Orquestrador canônico do loop de reconciliação único — ADR 0237.
 *
 * `jana:reconcile` garante `git == índice == MCP == settings == deploy`: itera a
 * lista `config('copiloto.reconcilers')` (FQCN dos Reconcilers — merged como
 * `copiloto.*` pelo JanaServiceProvider), instancia cada um com guard
 * `class_exists` (tolera reconciler ainda-não-criado) e agrega os
 * {@see ReconcileResult}. Espelha o padrão já provado do `governance:audit`
 * (Drift Framework, ADR 0216): seleção → loop resiliente → tabela/JSON → exit code.
 *
 * Modos (mutuamente exclusivos na semântica de cura):
 *   --check    NÃO cura (ignora --heal). Exit 1 se QUALQUER drift > 0 (gate de CI).
 *   --heal     cura o que é seguro (idempotente, append-only); alerta o resto.
 *   (default)  só reporta desired × observed × drift por faceta, sem mexer.
 *
 * Flags auxiliares:
 *   --dry-run  com --heal, mostra o que CURARIA sem aplicar.
 *   --only=    filtra por name() (CSV: `--only=index,settings`).
 *   --json     imprime json_encode determinístico do array de ->toArray().
 *
 * Exit codes:
 *   0 = tudo inSync (ou modo default/heal sem gate).
 *   1 = --check com drift detectado (CI bloqueia) OU algum reconciler em erro.
 *
 * Resiliência: se um reconciler lança, captura, marca a linha como erro e segue
 * os demais — nunca derruba o comando inteiro (ADR 0237 / 0230 confiabilidade).
 *
 * Cron sugerido (NÃO editado aqui — app/Console/Kernel.php):
 *   $schedule->command('jana:reconcile --heal')->daily();
 *
 * @see Modules\Jana\Contracts\Reconciler
 * @see Modules\Jana\Services\Reconcile\ReconcileResult
 * @see memory/decisions/0237-jana-reconcile-loop-unico.md
 */
class ReconcileCommand extends Command
{
    protected $signature = 'jana:reconcile
                            {--check : Só DETECTA drift (ignora --heal). Exit 1 se qualquer drift > 0 — gate de CI}
                            {--heal : CURA o que é seguro (idempotente, append-only); alerta o resto}
                            {--dry-run : Com --heal, mostra o que curaria sem aplicar}
                            {--only= : Filtra reconcilers por name() (CSV, ex: index,settings)}
                            {--json : Output JSON determinístico em vez de tabela}';

    protected $description = 'ADR 0237 — loop de reconciliação único: git == índice == MCP == settings == deploy';

    public function handle(): int
    {
        $check = (bool) $this->option('check');
        $heal = (bool) $this->option('heal');
        $dryRun = (bool) $this->option('dry-run');
        $json = (bool) $this->option('json');

        // --check é gate de detecção pura: NUNCA cura, mesmo se --heal vier junto.
        $healEffective = $heal && ! $check;

        $only = $this->parseOnly((string) ($this->option('only') ?? ''));

        $reconcilers = $this->resolveReconcilers($only);

        if ($reconcilers === []) {
            $msg = $only !== []
                ? 'Nenhum reconciler casou com --only=' . implode(',', $only) . '.'
                : 'Nenhum reconciler registrado em config(copiloto.reconcilers).';
            if ($json) {
                $this->line($this->encodeJson([]));
            } else {
                $this->warn($msg);
            }

            return self::SUCCESS;
        }

        /** @var array<int, ReconcileResult> $results */
        $results = [];
        $hadError = false;

        foreach ($reconcilers as $reconciler) {
            [$result, $errored] = $this->runReconciler($reconciler, $healEffective, $dryRun);
            $results[] = $result;
            $hadError = $hadError || $errored;
        }

        if ($json) {
            $this->line($this->encodeJson($results));
        } else {
            $this->renderTabela($results, $check, $healEffective, $dryRun);
        }

        $totalDrift = array_sum(array_map(
            static fn (ReconcileResult $r): int => $r->driftCount,
            $results,
        ));
        $totalHealed = array_sum(array_map(
            static fn (ReconcileResult $r): int => $r->healedCount,
            $results,
        ));

        Log::channel('single')->info('jana:reconcile', [
            'mode' => $check ? 'check' : ($healEffective ? 'heal' : 'report'),
            'dry_run' => $dryRun,
            'reconcilers' => count($results),
            'total_drift' => $totalDrift,
            'total_healed' => $totalHealed,
            'had_error' => $hadError,
        ]);

        // Exit code:
        //  - erro em qualquer reconciler → 1 (não esconde falha operacional).
        //  - --check com drift → 1 (gate de CI).
        //  - resto → 0.
        if ($hadError) {
            return self::FAILURE;
        }

        if ($check && $totalDrift > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Resolve a lista de Reconcilers a partir de config(copiloto.reconcilers),
     * com guard class_exists (tolera FQCN ainda-não-criado) e filtro --only por name().
     *
     * @param array<int, string> $only
     * @return array<int, Reconciler>
     */
    private function resolveReconcilers(array $only): array
    {
        $resolved = [];

        foreach ($this->configuredFqcns() as $fqcn) {
            if (! class_exists($fqcn)) {
                // Reconciler ainda-não-criado: ADR 0237 manda tolerar (registry
                // declara os 5, alguns podem não ter aterrissado ainda).
                continue;
            }

            $instance = app()->make($fqcn);
            if (! $instance instanceof Reconciler) {
                // FQCN registrado não honra o contrato — ignora em silêncio
                // controlado (não derruba o loop; loga pra rastreio).
                Log::channel('single')->warning('jana:reconcile — FQCN não é Reconciler', [
                    'fqcn' => $fqcn,
                ]);

                continue;
            }

            if ($only !== [] && ! in_array($instance->name(), $only, true)) {
                continue;
            }

            $resolved[] = $instance;
        }

        return $resolved;
    }

    /**
     * Lê config(copiloto.reconcilers) defensivamente: só strings não-vazias.
     *
     * @return array<int, string>
     */
    private function configuredFqcns(): array
    {
        $raw = config('copiloto.reconcilers', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $fqcn) {
            if (is_string($fqcn) && $fqcn !== '') {
                $out[] = $fqcn;
            }
        }

        return $out;
    }

    /**
     * Executa 1 reconciler de forma resiliente. Devolve [resultado, errou?].
     * Exceção vira ReconcileResult de erro (1 drift sintético, metadata.error),
     * pra a linha aparecer na tabela sem derrubar os demais.
     *
     * @return array{0: ReconcileResult, 1: bool}
     */
    private function runReconciler(Reconciler $reconciler, bool $heal, bool $dryRun): array
    {
        $name = $reconciler->name();
        $start = microtime(true);

        try {
            $result = $reconciler->reconcile([
                'heal' => $heal,
                'dry_run' => $dryRun,
            ]);

            return [$result, false];
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            Log::channel('single')->error("jana:reconcile — reconciler '{$name}' lançou", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorResult = new ReconcileResult(
                name: $name,
                inSync: false,
                driftCount: 1,
                healedCount: 0,
                drifts: [],
                durationMs: $durationMs,
                metadata: ['error' => $e->getMessage()],
            );

            return [$errorResult, true];
        }
    }

    /**
     * Parse do --only CSV → lista de name() normalizados (trim, sem vazios).
     *
     * @return array<int, string>
     */
    private function parseOnly(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $raw) as $piece) {
            $name = trim($piece);
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Encode JSON determinístico do array de ReconcileResult::toArray().
     *
     * @param array<int, ReconcileResult> $results
     */
    private function encodeJson(array $results): string
    {
        $payload = array_map(
            static fn (ReconcileResult $r): array => $r->toArray(),
            $results,
        );

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Tabela humana: reconciler | in_sync | drift | healed | duration_ms + rodapé.
     *
     * @param array<int, ReconcileResult> $results
     */
    private function renderTabela(array $results, bool $check, bool $heal, bool $dryRun): void
    {
        $modo = $check ? 'CHECK (gate CI — não cura)' : ($heal ? 'HEAL (cura o seguro)' : 'REPORT (só detecta)');
        $this->info("═══ jana:reconcile — {$modo} ═══");
        if ($dryRun && $heal) {
            $this->warn('DRY-RUN — cura simulada, nada foi aplicado.');
        }

        $rows = [];
        $totalDrift = 0;
        $totalHealed = 0;
        $totalDuration = 0;
        $errados = 0;

        foreach ($results as $r) {
            $erro = is_string($r->metadata['error'] ?? null);
            if ($erro) {
                $errados++;
            }
            $rows[] = [
                $erro ? "{$r->name} ⚠ erro" : $r->name,
                $r->inSync ? 'sim' : 'não',
                (string) $r->driftCount,
                (string) $r->healedCount,
                (string) $r->durationMs,
            ];
            $totalDrift += $r->driftCount;
            $totalHealed += $r->healedCount;
            $totalDuration += $r->durationMs;
        }

        $this->table(
            ['reconciler', 'in_sync', 'drift', 'healed', 'duration_ms'],
            $rows,
        );

        $this->line('');
        $this->line(sprintf(
            'Total: %d reconcilers · %d drift · %d healed · %dms%s',
            count($results),
            $totalDrift,
            $totalHealed,
            $totalDuration,
            $errados > 0 ? " · {$errados} em erro" : '',
        ));

        if ($check && $totalDrift > 0) {
            $this->error("CHECK falhou: {$totalDrift} drift(s) detectado(s). Rode com --heal pra curar o seguro.");
        } elseif ($totalDrift === 0 && $errados === 0) {
            $this->info('Tudo em sincronia (git == índice == MCP == settings == deploy).');
        }
    }
}
