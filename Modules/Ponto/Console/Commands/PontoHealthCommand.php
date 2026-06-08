<?php

declare(strict_types=1);

namespace Modules\Ponto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ponto:health — Wave 16 D9 (governance v3 — observabilidade módulo Ponto).
 *
 * Dashboard de saúde do módulo Ponto WR2. Equivalente ao jana:health-check (5
 * checks SQL) mas focado nos sinais de compliance trabalhista + integridade
 * append-only Portaria MTP 671/2021.
 *
 * 5 checks obrigatórios (READ-ONLY — nenhum INSERT/UPDATE/DELETE):
 *   1. trigger_imutabilidade   — triggers UPDATE/DELETE BEFORE em ponto_marcacoes ativas
 *   2. ultima_marcacao_recente — última marcação por business < 24h (REPs vivos)
 *   3. hash_chain_integro      — sample 50 REPs: zero hash_anterior quebrado
 *   4. apuracao_pendente_lag   — apuracoes_dia em estado PENDENTE > 48h (cron parado?)
 *   5. nsr_sequencial          — gaps de NSR por REP (skip count > 0 = REP rebootou)
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - Sem --business-id: admin global view (agrega todos businesses)
 *   - Com --business-id: filtra um tenant específico
 *
 * Exit code:
 *   - Sem --alert: sempre 0 (info-only)
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se todos OK
 *
 * Uso:
 *   php artisan ponto:health
 *   php artisan ponto:health --business-id=1
 *   php artisan ponto:health --detail
 *   php artisan ponto:health --json
 *   php artisan ponto:health --alert
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Portaria MTP 671/2021 Art. 85 (imutabilidade marcações)
 */
class PontoHealthCommand extends Command
{
    /**
     * `--detail` (não `--verbose` que é reservado Symfony Console — ver
     * .claude/rules/commands.md, handoff 2026-05-14 18:34 PR #851).
     */
    protected $signature = 'ponto:health
        {--business-id= : Filtra checks por business_id (default: todos)}
        {--alert : Exit code 2 se FAIL, 1 se WARN, 0 se OK (cron + monitoring)}
        {--json : Output JSON estruturado (integração dashboard)}
        {--detail : Mostra linhas individuais dos checks (debug)}';

    protected $description = 'Dashboard de saúde do módulo Ponto WR2 — 5 sinais compliance Portaria 671 + integridade hash chain.';

    /** Threshold WARN (horas) — última marcação por business. */
    private const ULTIMA_MARCACAO_WARN_HORAS = 24;

    /** Threshold FAIL (horas). */
    private const ULTIMA_MARCACAO_FAIL_HORAS = 72;

    /** Threshold WARN (horas) — apuração pendente lag. */
    private const APURACAO_LAG_WARN_HORAS = 24;

    /** Threshold FAIL. */
    private const APURACAO_LAG_FAIL_HORAS = 48;

    public function handle(): int
    {
        $businessId = $this->option('business-id') !== null
            ? (int) $this->option('business-id')
            : null;

        $checks = [
            'trigger_imutabilidade'   => $this->checkTriggerImutabilidade(),
            'ultima_marcacao_recente' => $this->checkUltimaMarcacaoRecente($businessId),
            'hash_chain_integro'      => $this->checkHashChainIntegro($businessId),
            'apuracao_pendente_lag'   => $this->checkApuracaoPendenteLag($businessId),
            'nsr_sequencial'          => $this->checkNsrSequencial($businessId),
        ];

        if ($this->option('json')) {
            $this->line(json_encode([
                'module'      => 'Ponto',
                'business_id' => $businessId,
                'checked_at'  => now()->toIso8601String(),
                'checks'      => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($checks);
        }

        // Log estruturado pra alerting (ELK/Loki/CloudWatch).
        $this->emitLog($businessId, $checks);

        if (! $this->option('alert')) {
            return self::SUCCESS;
        }

        $hasFail = collect($checks)->contains(fn ($c) => ($c['status'] ?? '') === 'FAIL');
        $hasWarn = collect($checks)->contains(fn ($c) => ($c['status'] ?? '') === 'WARN');

        return $hasFail ? 2 : ($hasWarn ? 1 : 0);
    }

    /**
     * Check 1 — triggers MySQL de imutabilidade ATIVAS em ponto_marcacoes.
     * Portaria 671/2021 Art. 85: marcações são append-only no banco.
     *
     * @return array{status:string, valor:mixed, mensagem:string}
     */
    private function checkTriggerImutabilidade(): array
    {
        try {
            $triggers = collect(DB::select(
                'SELECT TRIGGER_NAME, EVENT_MANIPULATION
                 FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = DATABASE()
                   AND EVENT_OBJECT_TABLE = ?
                   AND EVENT_MANIPULATION IN (?, ?)
                   AND ACTION_TIMING = ?',
                ['ponto_marcacoes', 'UPDATE', 'DELETE', 'BEFORE']
            ));

            $hasUpdate = $triggers->contains(fn ($t) => $t->EVENT_MANIPULATION === 'UPDATE');
            $hasDelete = $triggers->contains(fn ($t) => $t->EVENT_MANIPULATION === 'DELETE');

            if ($hasUpdate && $hasDelete) {
                return [
                    'status'   => 'OK',
                    'valor'    => $triggers->count(),
                    'mensagem' => "{$triggers->count()} trigger(s) BEFORE UPDATE+DELETE ativas.",
                ];
            }

            return [
                'status'   => 'FAIL',
                'valor'    => $triggers->count(),
                'mensagem' => 'Triggers de imutabilidade AUSENTES — violação Portaria 671/2021 Art. 85.',
            ];
        } catch (\Throwable $e) {
            return [
                'status'   => 'WARN',
                'valor'    => null,
                'mensagem' => 'Não foi possível verificar triggers: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check 2 — última marcação por business < 24h. REPs parados = bug ou
     * empresa fechada/feriado (operador investiga).
     */
    private function checkUltimaMarcacaoRecente(?int $businessId): array
    {
        if (! Schema::hasTable('ponto_marcacoes')) {
            return ['status' => 'WARN', 'valor' => null, 'mensagem' => 'Tabela ponto_marcacoes ausente.'];
        }

        $query = DB::table('ponto_marcacoes')
            ->select('business_id', DB::raw('MAX(created_at) as ultima'))
            ->groupBy('business_id');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Nenhum business com marcações ainda.'];
        }

        $worst = null;
        foreach ($rows as $r) {
            $idade = $r->ultima ? Carbon::parse($r->ultima)->diffInHours(now()) : PHP_INT_MAX;
            if ($worst === null || $idade > $worst['idade']) {
                $worst = ['business_id' => $r->business_id, 'idade' => $idade];
            }
        }

        $status = $worst['idade'] >= self::ULTIMA_MARCACAO_FAIL_HORAS
            ? 'FAIL'
            : ($worst['idade'] >= self::ULTIMA_MARCACAO_WARN_HORAS ? 'WARN' : 'OK');

        return [
            'status'   => $status,
            'valor'    => $worst['idade'],
            'mensagem' => "Business mais atrasado: biz={$worst['business_id']} há {$worst['idade']}h sem marcação.",
        ];
    }

    /**
     * Check 3 — sample 50 REPs ativos; conta quantos têm hash_anterior NULL no
     * meio da cadeia (≠ primeira marcação). NÃO recalcula hash (caro) — só
     * estrutural. Verificação completa via MarcacaoService::verificarIntegridade.
     */
    private function checkHashChainIntegro(?int $businessId): array
    {
        if (! Schema::hasTable('ponto_marcacoes') || ! Schema::hasTable('ponto_reps')) {
            return ['status' => 'WARN', 'valor' => null, 'mensagem' => 'Tabelas Ponto ausentes.'];
        }

        $repsQuery = DB::table('ponto_reps')->where('ativo', true);
        if ($businessId !== null) {
            $repsQuery->where('business_id', $businessId);
        }
        $reps = $repsQuery->orderByDesc('id')->limit(50)->pluck('id');

        if ($reps->isEmpty()) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Nenhum REP ativo (sample vazio).'];
        }

        // Conta marcações com hash_anterior NULL excluindo a primeira (nsr mínimo) por REP.
        $quebrados = 0;
        foreach ($reps as $repId) {
            $minNsr = DB::table('ponto_marcacoes')->where('rep_id', $repId)->min('nsr');
            if ($minNsr === null) {
                continue;
            }
            $orfas = DB::table('ponto_marcacoes')
                ->where('rep_id', $repId)
                ->whereNull('hash_anterior')
                ->where('nsr', '>', $minNsr)
                ->count();
            $quebrados += $orfas;
        }

        $status = $quebrados === 0 ? 'OK' : ($quebrados <= 3 ? 'WARN' : 'FAIL');

        return [
            'status'   => $status,
            'valor'    => $quebrados,
            'mensagem' => "Sample {$reps->count()} REPs: {$quebrados} marcação(ões) com hash_anterior NULL no meio da cadeia.",
        ];
    }

    /**
     * Check 4 — apuracao_dia PENDENTE > 48h indica que cron de apuração parou
     * ou ReapurarDiaJob enfileirado mas não processado.
     */
    private function checkApuracaoPendenteLag(?int $businessId): array
    {
        if (! Schema::hasTable('ponto_apuracao_dia')) {
            return ['status' => 'WARN', 'valor' => null, 'mensagem' => 'Tabela ponto_apuracao_dia ausente.'];
        }

        $query = DB::table('ponto_apuracao_dia')->where('estado', 'PENDENTE');
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $oldest = $query->min('created_at');
        if (! $oldest) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Nenhuma apuração pendente.'];
        }

        $idadeHoras = Carbon::parse($oldest)->diffInHours(now());

        $status = $idadeHoras >= self::APURACAO_LAG_FAIL_HORAS
            ? 'FAIL'
            : ($idadeHoras >= self::APURACAO_LAG_WARN_HORAS ? 'WARN' : 'OK');

        return [
            'status'   => $status,
            'valor'    => $idadeHoras,
            'mensagem' => "Apuração pendente mais antiga: há {$idadeHoras}h.",
        ];
    }

    /**
     * Check 5 — gaps de NSR por REP. NSR é sequencial pela Portaria; gap
     * significa marcação perdida ou banco corrompido.
     */
    private function checkNsrSequencial(?int $businessId): array
    {
        if (! Schema::hasTable('ponto_marcacoes')) {
            return ['status' => 'WARN', 'valor' => null, 'mensagem' => 'Tabela ponto_marcacoes ausente.'];
        }

        $repsQuery = DB::table('ponto_marcacoes')
            ->select('rep_id', DB::raw('MIN(nsr) as min_n'), DB::raw('MAX(nsr) as max_n'), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('rep_id')
            ->groupBy('rep_id');

        if ($businessId !== null) {
            $repsQuery->where('business_id', $businessId);
        }

        $gaps = 0;
        $sample = 0;
        foreach ($repsQuery->orderByDesc('cnt')->limit(20)->get() as $r) {
            $sample++;
            $esperado = (int) $r->max_n - (int) $r->min_n + 1;
            $diff = $esperado - (int) $r->cnt;
            if ($diff > 0) {
                $gaps += $diff;
            }
        }

        $status = $gaps === 0 ? 'OK' : ($gaps <= 10 ? 'WARN' : 'FAIL');

        return [
            'status'   => $status,
            'valor'    => $gaps,
            'mensagem' => "Sample {$sample} REPs com mais marcações: {$gaps} gap(s) de NSR detectado(s).",
        ];
    }

    private function renderTable(array $checks): void
    {
        $rows = [];
        foreach ($checks as $name => $c) {
            $rows[] = [
                $name,
                $c['status'] ?? 'UNKNOWN',
                $c['valor'] ?? '-',
                $c['mensagem'] ?? '',
            ];
        }
        $this->table(['Check', 'Status', 'Valor', 'Mensagem'], $rows);

        if ($this->option('detail')) {
            $this->line('');
            $this->line('Detalhes JSON:');
            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function emitLog(?int $businessId, array $checks): void
    {
        $worstStatus = 'OK';
        foreach ($checks as $c) {
            if (($c['status'] ?? '') === 'FAIL') { $worstStatus = 'FAIL'; break; }
            if (($c['status'] ?? '') === 'WARN') { $worstStatus = 'WARN'; }
        }

        Log::info('ponto.health.check.executado', [
            'business_id'  => $businessId,
            'worst_status' => $worstStatus,
            'checks'       => array_map(fn ($c) => [
                'status' => $c['status'] ?? null,
                'valor'  => $c['valor'] ?? null,
            ], $checks),
        ]);
    }
}
