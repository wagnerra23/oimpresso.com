<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Governance\Services\Concerns\PersistsDriftAlert;
use Modules\Governance\Services\DriftFinding;

/**
 * governance:staging-freshness-alert — persiste em mcp_alertas_eventos o veredito de
 * staleness do checkout de STAGING detectado FORA da app pela sentinela de host
 * (docker/oimpresso-staging/staging-freshness-sentinel.sh).
 *
 * POR QUE um comando (e não um DriftChecker plugado no governance:audit): o audit roda
 * DENTRO de um container e lê `base_path()` do PRÓPRIO env — não enxerga o disco do
 * checkout de staging (bind noutro container) nem tem endpoint HTTP são pra consultar
 * (o /api/mcp/version do staging responde 500). Só o HOST vê ao mesmo tempo o
 * `.git/HEAD` do staging E o main-SHA fresco do self-update. Então a DETECÇÃO fica no
 * host (sentinela bash) e a PERSISTÊNCIA (idempotente + escalação canônicas) reusa a
 * régua daqui — chamado via `docker exec oimpresso-mcp php artisan governance:staging-freshness-alert ...`.
 *
 * Reusa PersistsDriftAlert (ADR 0216): idempotência DIÁRIA (hourly → 1 alerta/dia, não
 * spam) + escalonamento >3d (warn→high→critical + [ESCALADO]) + insert em
 * mcp_alertas_eventos, que o brief/inbox do time LÊ. System-level, business_id null
 * (ADR 0093 §Exceção repo-wide — igual McpServedDriftChecker).
 *
 * Só persiste quando STALE (`--verdict stale:*`). Fresco/tolerado/indeterminado = no-op;
 * o ack/resolve dos alertas abertos é MANUAL via UI Governance (convenção dos demais
 * checkers — nenhum auto-resolve). @see docker/oimpresso-staging/README.md
 */
class RecordStagingFreshnessAlertCommand extends Command
{
    use PersistsDriftAlert;

    protected $signature = 'governance:staging-freshness-alert
                            {--verdict= : Veredito da sentinela (stale:Nd | fresco | atras-recente:Nd | indeterminado:...)}
                            {--head= : SHA do HEAD do checkout de staging}
                            {--main= : SHA de main comparado}
                            {--age= : Idade em dias do HEAD}';

    protected $description = 'Persiste em mcp_alertas_eventos o staleness do checkout de staging detectado pela sentinela de host';

    public function handle(): int
    {
        $verdict = trim((string) $this->option('verdict'));

        // Só STALE escala. Fresco / atras-recente / nao-aplicavel / indeterminado = no-op
        // (idem convenção dos checkers: não emitir quando limpo; abertos são resolvidos à mão).
        if (! str_starts_with($verdict, 'stale:')) {
            $this->info("Veredito '{$verdict}' — nada a persistir (só stale escala pra mcp_alertas).");

            return self::SUCCESS;
        }

        $head = trim((string) $this->option('head'));
        $main = trim((string) $this->option('main'));
        $age = trim((string) $this->option('age'));

        $finding = new DriftFinding(
            target: 'staging.oimpresso.com',
            target_type: 'env',
            severity: 'high',
            message: "Checkout de STAGING apodreceu ({$verdict}): HEAD {$head} != main {$main}. "
                .'Sincronizar com fetch + merge --ff-only (ou descartar edições conscientemente e reset) — '
                .'NUNCA pull cego (staging carrega trabalho de teste em voo). '
                .'Sentinela: docker/oimpresso-staging/staging-freshness-sentinel.sh',
            evidence: [
                'verdict' => $verdict,
                'head' => $head,
                'main' => $main,
                'age_days' => is_numeric($age) ? (int) $age : $age,
                'source' => 'staging-freshness-sentinel.sh (host CT100)',
            ],
        );

        $id = $this->persistirDriftAlert('staging_freshness', $finding);

        if ($id === null) {
            $this->error('Falha ao persistir alerta staging_freshness (ver log channel single).');

            return self::FAILURE;
        }

        $this->info("Alerta staging_freshness persistido/idempotente (mcp_alertas_eventos id={$id}).");

        return self::SUCCESS;
    }
}
