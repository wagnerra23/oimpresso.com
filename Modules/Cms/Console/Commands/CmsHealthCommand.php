<?php

declare(strict_types=1);

namespace Modules\Cms\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsPage;

/**
 * Daily health-check do módulo Cms (D9 Observability).
 *
 * Roda 03:30 BRT (após cron geral). Reporta pra log estruturado:
 *   - Schema canônico (`cms_pages` existe)
 *   - Páginas publicadas (cms publicado biz=1)
 *   - Último update recente (< 365 dias — sinal de site abandonado)
 *
 * Diferença vs `cms:import-wp-officeimpresso`:
 *   - import é one-shot manual (migração WP)
 *   - health é cron (silencioso, exit 0/1 pra alert)
 *
 * Multi-tenant Tier 0 (ADR 0093): commands rodam sem sessão HTTP; aceita
 * --business-id pra scope tenant ou roda em modo schema-only (default).
 *
 * Uso:
 *   php artisan cms:health
 *   php artisan cms:health --business-id=1
 *   php artisan cms:health --detail (log detalhado por check)
 *   php artisan cms:health --notify (ALERT em log se algo falhou)
 *
 * @see ADR 0155 module-grade-v3 D9 — sinal #3 health endpoint/command
 */
class CmsHealthCommand extends Command
{
    protected $signature = 'cms:health
                            {--business-id= : Business id pra scope tenant (omitir = schema-only)}
                            {--detail : Log detalhado por check}
                            {--notify : Loga ALERT em governance channel se algo falhou}';

    protected $description = 'Health-check diário do módulo Cms (schema + páginas publicadas + drift)';

    public function handle(): int
    {
        return OtelHelper::spanBiz('cms.health.run', function () {
            return $this->runChecks();
        }, ['detail' => (bool) $this->option('detail')]);
    }

    private function runChecks(): int
    {
        $checks = [
            'schema_cms_pages' => $this->checkSchema(),
            'pages_published'  => $this->checkPagesPublished(),
            'last_update_age'  => $this->checkLastUpdateAge(),
        ];

        $issues = collect($checks)->filter(fn ($result) => $result['ok'] === false);
        $allOk = $issues->isEmpty();

        $logPayload = [
            'ok' => $allOk,
            'biz' => $this->option('business-id'),
            'checks' => $checks,
        ];

        Log::channel('single')->info('cms:health', $logPayload);

        if ($this->option('detail')) {
            foreach ($checks as $name => $result) {
                $icon = $result['ok'] ? 'OK' : 'FAIL';
                $this->line("[{$icon}] {$name}: {$result['message']}");
            }
        }

        if ($this->option('notify') && ! $allOk) {
            $detail = $issues->map(fn ($r, $name) => "{$name}={$r['message']}")->implode(' | ');
            Log::channel('single')->error("cms:health ALERT — issues: {$detail}");
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Schema check — tabela canônica cms_pages existe.
     */
    private function checkSchema(): array
    {
        $ok = Schema::hasTable('cms_pages')
            && Schema::hasColumn('cms_pages', 'title')
            && Schema::hasColumn('cms_pages', 'is_enabled');

        return [
            'ok' => $ok,
            'message' => $ok ? 'cms_pages presente com colunas canônicas' : 'cms_pages ausente ou schema drift',
        ];
    }

    /**
     * Páginas publicadas — espera ao menos 1 página enabled.
     * Modo schema-only conta global (sem business_id scope; CmsPage não tem
     * business_id Tier 0 — site público é global).
     */
    private function checkPagesPublished(): array
    {
        try {
            $count = CmsPage::where('is_enabled', 1)->count();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'erro consultando cms_pages: '.substr($e->getMessage(), 0, 80),
            ];
        }

        $ok = $count >= 1;

        return [
            'ok' => $ok,
            'message' => $ok ? "{$count} páginas publicadas" : 'nenhuma página publicada (site vazio?)',
            'count' => $count,
        ];
    }

    /**
     * Idade do último update — alerta se > 365 dias (site abandonado).
     */
    private function checkLastUpdateAge(): array
    {
        try {
            $last = CmsPage::orderByDesc('updated_at')->value('updated_at');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'erro lendo updated_at: '.substr($e->getMessage(), 0, 80),
            ];
        }

        if (! $last) {
            return ['ok' => true, 'message' => 'sem páginas pra medir idade (skip)'];
        }

        $days = (int) $last->diffInDays(now());
        $ok = $days <= 365;

        return [
            'ok' => $ok,
            'message' => $ok
                ? "última edição há {$days} dias"
                : "última edição há {$days} dias (>365 — site abandonado?)",
            'days' => $days,
        ];
    }
}
