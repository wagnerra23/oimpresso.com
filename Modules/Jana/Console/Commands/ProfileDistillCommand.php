<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Memoria\ProfileDistiller;

/**
 * jana:profile-distill — regenera o perfil compacto (`jana_business_profile`)
 * de cada business via {@see ProfileDistiller} (MEM-S8-3, ADR 0037).
 *
 * RAIZ COPI-26 (incidente 2026-06-20): o `ProfileDistiller` existia desde abr/2026
 * mas NUNCA foi agendado — `->destilar()` tinha ZERO call sites no codebase. As 3
 * únicas linhas em prod (biz 1 WR2, 4 ROTA LIVRE, 164 MARTINHO) eram seeds one-off
 * que envelheceram >7d e acendiam o check `profile_distiller_drift` no
 * `jana:health-check`. A sentinela (graduada em L-OP-002) estava CORRETA — vigiava
 * um job de manutenção que nunca rodava. Este comando é o job que faltava.
 *
 * O perfil é consumido por `ContextSnapshotService::montar()` (injeta `profile_text`
 * como `observacoes` no system prompt da Jana) — perfil stale = assistente respondendo
 * com narrativa comercial velha. Por isso o check é DURO.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): itera business by business via loop
 * EXPLÍCITO, passando `$businessId` pro distiller (que filtra por `business_id`).
 * NUNCA cross-tenant. Mesmo padrão de `jana:retention-purge`.
 *
 * Custo: 1 chamada LLM/business (~200 tokens out @ gpt-4o-mini). ~76 biz ≈ $0,02/dia.
 * Não escreve em `jana_mensagens`, então não conta pro check `custo_brain_b_24h`.
 *
 * Robustez: falha de UM business (LLM timeout, ctx vazio) NÃO aborta o batch — é
 * contabilizada e logada; o próximo run diário (bem dentro da janela de 7d) re-tenta.
 *
 * Uso:
 *   php artisan jana:profile-distill                  # todos os businesses
 *   php artisan jana:profile-distill --business=4     # só ROTA LIVRE (backfill manual)
 *   php artisan jana:profile-distill --only-stale     # só ausentes/stale (>7d)
 *
 * Schedule canon: daily 04:50 BRT (app/Console/Kernel.php) — antes do
 * `jana:health-check` (06:00) reavaliar o check.
 *
 * @see Modules\Jana\Services\Memoria\ProfileDistiller
 * @see Modules\Jana\Console\Commands\HealthCheckCommand::checkProfileDrift (check 5)
 * @see Modules/Jana/LICOES-OPERACAO.md L-OP-002
 */
class ProfileDistillCommand extends Command
{
    protected $signature = 'jana:profile-distill
                            {--business= : Limitar a business_id específico (default: itera todos)}
                            {--only-stale : Regenera só profiles ausentes ou >7d sem regenerar}';

    protected $description = 'Regenera jana_business_profile via ProfileDistiller (COPI-26 — job que faltava no scheduler)';

    public function handle(ProfileDistiller $distiller): int
    {
        $businessIdArg = $this->option('business');
        $onlyStale = (bool) $this->option('only-stale');

        $businesses = $this->resolveBusinesses($businessIdArg !== null ? (string) $businessIdArg : null, $onlyStale);

        if ($businesses->isEmpty()) {
            $this->info('Nenhum business a regenerar' . ($onlyStale ? ' (--only-stale: tudo fresco <7d).' : '.'));

            return self::SUCCESS;
        }

        $this->info('jana:profile-distill — ' . now()->toDateTimeString());
        $this->line('  businesses : ' . $businesses->count() . ($onlyStale ? ' (só ausentes/stale)' : ''));
        $this->newLine();

        $rows = [];
        $ok = 0;
        $vazios = 0;
        $falhas = 0;

        foreach ($businesses as $bizId) {
            $bizId = (int) $bizId;

            try {
                $r = $distiller->destilar($bizId);
                $chars = mb_strlen((string) ($r['profile_text'] ?? ''));
                $erro = $r['error'] ?? null;

                if ($erro !== null) {
                    $falhas++;
                    $status = 'ERR: ' . mb_substr((string) $erro, 0, 26);
                } elseif ($chars === 0) {
                    // ctx null OU LLM devolveu vazio: o distiller NÃO bumpa gerado_em
                    // nesse caminho, então a sentinela seguirá vendo este biz como
                    // stale (correto — não escondemos falha persistente).
                    $vazios++;
                    $status = 'VAZIO (sem ctx/output)';
                } else {
                    $ok++;
                    $status = 'OK';
                }

                $rows[] = [
                    $bizId,
                    (int) ($r['tokens_estimated'] ?? 0),
                    (int) ($r['raw_context_tokens'] ?? 0),
                    $chars,
                    $status,
                ];
            } catch (\Throwable $e) {
                $falhas++;
                $rows[] = [$bizId, 0, 0, 0, 'EXC: ' . mb_substr($e->getMessage(), 0, 26)];
                Log::channel('copiloto-ai')->error('jana:profile-distill — exceção num business', [
                    'business_id' => $bizId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->table(['business_id', 'tok_est', 'raw_tok', 'chars', 'status'], $rows);
        $this->newLine();
        $this->info(sprintf(
            'Total: %d ok · %d vazios · %d falhas · %d businesses',
            $ok,
            $vazios,
            $falhas,
            $businesses->count(),
        ));

        Log::channel('copiloto-ai')->info('jana:profile-distill — resumo', [
            'ok' => $ok,
            'vazios' => $vazios,
            'falhas' => $falhas,
            'total' => $businesses->count(),
        ]);

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Multi-tenant Tier 0: resolve a lista de business_ids via loop EXPLÍCITO.
     *
     * @return Collection<int,int>
     */
    protected function resolveBusinesses(?string $businessIdArg, bool $onlyStale): Collection
    {
        if ($businessIdArg !== null && $businessIdArg !== '') {
            return collect([(int) $businessIdArg]);
        }

        /** @var Collection<int,int> $todos */
        $todos = Business::query()->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v);

        if (! $onlyStale) {
            return $todos;
        }

        // Só os que NÃO têm profile fresco (<7d). Ausente do profile = stale.
        // Espelha o critério de checkProfileDrift (gerado_em >= now-7d = fresco).
        $frescos = DB::table('jana_business_profile')
            ->where('gerado_em', '>=', now()->subDays(7))
            ->pluck('business_id')
            ->map(fn ($v) => (int) $v)
            ->flip();

        return $todos->reject(fn ($id) => $frescos->has($id))->values();
    }
}
