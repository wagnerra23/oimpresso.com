<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Mcp\Ct100CircuitBreaker;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * MEM-MCP-1.a (ADR 0053) — Sincroniza memory/ git → mcp_memory_documents.
 *
 * Modos:
 *   - manual: dev roda à mão depois de commit
 *   - webhook: chamado por endpoint POST /api/mcp/sync-memory (GitHub)
 *   - cron: scheduler 5min como fallback se webhook falhar
 *
 * Uso:
 *   php artisan mcp:sync-memory                    # manual padrão
 *   php artisan mcp:sync-memory --reason=cron      # registra origem no history
 *   php artisan mcp:sync-memory --user=1           # quem disparou
 *   php artisan mcp:sync-memory --base=/path/repo  # override do path
 *   php artisan mcp:sync-memory --only=briefing    # sync PARCIAL por type
 *
 * Sync robusto (handoff 2026-07-05 — deadlock + OOM no sync completo):
 *   - Lock atômico `mcp:sync-memory` (Cache::lock) impede webhook + cron
 *     concorrentes — a causa nº 1 dos deadlocks MySQL no Hostinger.
 *   - `--only=<type>` roda subconjunto barato (ex: os 73 BRIEFINGs) sem
 *     varrer os 1500 docs. Sync parcial NÃO roda a fase de soft-delete.
 */
class McpSyncMemoryCommand extends Command
{
    /**
     * TTL do lock em segundos.
     *
     * Era 900 (15min) sob a premissa "sync completo leva minutos, não horas".
     * A premissa era falsa: medido 2026-08-12 no CT 100, o run completo dos 2488
     * docs levou 1h40 — o lock expirava no meio e deixava de proteger justamente
     * a janela mais longa. 2h cobre a duração real com folga.
     *
     * ⚠️ Medido na mesma data: o CT 100 rodava com `cache.default = array`, um
     * driver por-processo — nesse cenário `Cache::lock()` não barra ninguém, e a
     * proteção descrita abaixo não existia de fato. Quem mexer aqui confira o
     * driver antes de confiar no lock (o campo git_sha tem defesa própria em
     * IndexarMemoryGitParaDb, independente deste lock).
     */
    protected const LOCK_TTL = 7200;

    protected $signature = 'mcp:sync-memory
                            {--reason=manual   : Origem da sincronização (manual|webhook|cron|fallback)}
                            {--user=           : ID do user que disparou (opcional)}
                            {--business=1      : business_id dono destes documentos (default: 1 = oimpresso dev)}
                            {--base=           : Override do path base do repo (default: base_path())}
                            {--only=           : Sync parcial: só docs deste type (briefing|adr|spec|session|...)}';

    protected $description = 'Sincroniza memory/ do filesystem com mcp_memory_documents (ADR 0053)';

    public function handle(): int
    {
        $base       = (string) ($this->option('base') ?? base_path());
        $reason     = (string) $this->option('reason');
        $userId     = $this->option('user')     ? (int) $this->option('user')     : null;
        $businessId = $this->option('business') ? (int) $this->option('business') : 1;
        $onlyType   = $this->option('only') ? (string) $this->option('only') : null;

        // Circuit breaker (2026-09-02): CT 100 fora desde ~27-28/08 fez este cron
        // gravar ~145 `exit code [1]`/dia. Falha de REDE não é erro de aplicação —
        // pergunta ANTES de trabalhar e, se o destino está mudo, sai 0 com UM
        // WARNING por janela. Respondeu mal (5xx) segue exit 1: ver Ct100CircuitBreaker.
        $probe = Ct100CircuitBreaker::probe();
        if ($probe['applicable'] && ! $probe['reachable']) {
            return $this->transporteFora((string) $probe['target'], (string) $probe['detail']);
        }

        // Lock anti-concorrência: webhook GitHub + cron 5min disparando juntos
        // era a receita do deadlock (UPSERTs simultâneos na mesma tabela).
        // get() não-bloqueante: quem chegar segundo pula o run — o próximo
        // cron (5min) reconcilia, então pular é seguro e não perde dado.
        $lock = Cache::lock('mcp:sync-memory', self::LOCK_TTL);
        if (! $lock->get()) {
            $this->warn('Sync já em andamento (lock mcp:sync-memory ativo) — pulando este run.');
            return self::SUCCESS;
        }

        try {
            $this->info("Sincronizando memory/ → mcp_memory_documents");
            $this->line("  base       : $base");
            $this->line("  reason     : $reason");
            $this->line("  business_id: $businessId");
            if ($userId) {
                $this->line("  user: $userId");
            }
            if ($onlyType) {
                $this->line("  only (parcial, sem soft-delete): $onlyType");
            }

            $service = new IndexarMemoryGitParaDb($base, $reason, $userId, $businessId, $onlyType);

            try {
                $stats = $service->run();
            } catch (\Throwable $e) {
                // Rede de segurança do probe: o CT 100 pode cair DEPOIS do probe
                // passar (janela de 5min), e o Scout estoura no meio do run.
                if (Ct100CircuitBreaker::isTransportFailure($e)) {
                    return $this->transporteFora(null, mb_substr($e->getMessage(), 0, 120));
                }

                $this->error('Sync falhou: ' . $e->getMessage());
                return self::FAILURE;
            }

            // Run fechou: encerra a série de queda (o health-check lê esse estado).
            Ct100CircuitBreaker::recordUp();

            $this->info(sprintf(
                "Concluído: %d indexados (%d novos, %d atualizados), %d removidos, %d redactions PII",
                $stats['indexados'],
                $stats['novos'],
                $stats['atualizados'],
                $stats['removidos'],
                $stats['redactions'],
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * Breaker aberto: o destino não respondeu.
     *
     * Sai SUCCESS de propósito — não é "deu certo", é "não havia o que fazer, e
     * repetir o alarme a cada 5min não acrescenta informação". Isto NÃO apaga o
     * alarme, muda QUEM alarma: o `jana:health-check` continua com os checks
     * duros `memoria_recall_backend` e `langfuse_trace_uptime_24h` paginando
     * enquanto o CT 100 estiver fora, e o `down_since` gravado aqui dá a eles a
     * data de início da queda.
     */
    private function transporteFora(?string $target, string $detail): int
    {
        $since = Ct100CircuitBreaker::recordDown();
        $desde = $since?->toDateTimeString() ?? 'agora';
        $alvo = $target ?? 'dependência CT 100';

        if (Ct100CircuitBreaker::shouldWarn()) {
            Log::channel('copiloto-ai')->warning(
                'mcp:sync-memory PULADO — transporte CT 100 fora desde ' . $desde
                . '. Sync retoma sozinho quando voltar; índice fica stale até lá. '
                . 'Diagnóstico: memory/requisitos/Infra/RUNBOOK-acesso-ct100.md',
                ['alvo' => $alvo, 'detalhe' => $detail, 'desde' => $desde, 'reason' => (string) $this->option('reason')],
            );
        }

        $this->warn("Transporte CT 100 fora desde {$desde} ({$alvo}) — sync pulado, exit 0.");

        return self::SUCCESS;
    }
}
