<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Whatsapp\Entities\ClientFeedback;

/**
 * FeedbackIndexGenerator — produz memory/feedback/INDEX.md + archive trimestral.
 *
 * Wagner 2026-05-27: "ficar na memória apenas as mais importantes e ir retirando".
 *
 * Refs: ADR 0195 (relevance scoring + decay), ADR 0131 (tiering canon/local/segredo).
 *
 * 2 outputs:
 *   1. memory/feedback/INDEX.md — top 20 HOT por business (auto-loaded em sessão)
 *   2. memory/feedback/archive/YYYY-QN.md — digest agregado COLD/closed (não loaded)
 *
 * Path canon: D:/oimpresso.com/memory/feedback/* (base_path do projeto).
 *
 * Idempotente: regenera arquivo completo cada execução (não append).
 */
class FeedbackIndexGenerator
{
    public function __construct(protected FeedbackRelevanceService $relevance)
    {
    }

    /**
     * Gera memory/feedback/INDEX.md com top N HOT (default 20) por business.
     *
     * @return string Path absoluto do arquivo gerado.
     */
    public function generateIndex(?int $businessId = null, int $topN = 20): string
    {
        $now = Carbon::now('America/Sao_Paulo');
        $lines = [];

        $lines[] = '# Feedback HOT · top ' . $topN . ' por business';
        $lines[] = '';
        $lines[] = '> Auto-gerado por `php artisan feedback:reindex` em ' . $now->format('Y-m-d H:i') . ' BRT.';
        $lines[] = '> ADR 0195 — score ≥ 70 = HOT (carregamento automático em sessão Claude).';
        $lines[] = '> NÃO editar manualmente — alterações são sobrescritas no próximo reindex.';
        $lines[] = '';

        $businesses = $businessId
            ? collect([$businessId])
            : ClientFeedback::query()
                ->withoutGlobalScopes()
                ->select('business_id')
                ->distinct()
                ->orderBy('business_id')
                ->pluck('business_id');

        $totalHotAll = 0;
        $totalActiveAll = 0;

        foreach ($businesses as $bizId) {
            $hot = ClientFeedback::query()
                ->withoutGlobalScopes()
                ->where('business_id', $bizId)
                ->whereNull('deleted_at')
                ->hot()
                ->orderByDesc('relevance_score')
                ->limit($topN)
                ->get();

            $active = ClientFeedback::query()
                ->withoutGlobalScopes()
                ->where('business_id', $bizId)
                ->whereNull('deleted_at')
                ->whereNotIn('status', [
                    ClientFeedback::STATUS_CLOSED,
                    ClientFeedback::STATUS_RESOLVED,
                ])
                ->count();

            $totalHotAll += $hot->count();
            $totalActiveAll += $active;

            $lines[] = '## biz=' . $bizId . ' — ' . $hot->count() . ' HOT de ' . $active . ' ativos';
            $lines[] = '';

            if ($hot->isEmpty()) {
                $lines[] = '_(sem feedbacks HOT — bom sinal ou índice ainda quente)_';
                $lines[] = '';
                continue;
            }

            $lines[] = '| Score | Sig (8c) | Persona | Módulo | Sev | Freq | Last seen | Status |';
            $lines[] = '|------:|----------|---------|--------|----:|-----:|-----------|--------|';

            foreach ($hot as $fb) {
                $sigShort = $fb->signature ? substr($fb->signature, 0, 8) : '–';
                $persona = $fb->persona_slug ?: '–';
                $modulo = $fb->modulo_afetado ?: '–';
                $lastSeen = $fb->last_seen_at ? Carbon::parse($fb->last_seen_at)->diffForHumans() : '–';
                $score = number_format((float) $fb->relevance_score, 1);

                $lines[] = "| {$score} | `{$sigShort}` | {$persona} | {$modulo} | {$fb->severity_nng} | {$fb->recorrente_count} | {$lastSeen} | {$fb->status} |";
            }
            $lines[] = '';

            // Patterns emergentes: recorrente >= 3 + >= 2 personas afetadas
            $patterns = $this->emergentPatterns($bizId);
            if ($patterns->isNotEmpty()) {
                $lines[] = '### Patterns emergentes (recorrente ≥ 3 + ≥ 2 personas afetadas)';
                $lines[] = '';
                foreach ($patterns as $pat) {
                    $sigShort = substr((string) $pat->signature, 0, 8);
                    $lines[] = "- `{$sigShort}` · {$pat->modulo_afetado} · {$pat->personas_count} personas · {$pat->recorrente_count}× ocorrências";
                }
                $lines[] = '';
            }
        }

        // Footer com estatísticas globais
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Resumo global';
        $lines[] = '';
        $lines[] = '- **HOT total**: ' . $totalHotAll;
        $lines[] = '- **Ativos total** (novo/triaged/backlog/in_progress): ' . $totalActiveAll;
        $lines[] = '- **Cobertura HOT/ativos**: ' . ($totalActiveAll > 0 ? round($totalHotAll / $totalActiveAll * 100) . '%' : 'n/a');
        $lines[] = '';
        $lines[] = '_Próximo reindex: domingo 03:00 BRT (schedule)._';
        $lines[] = '';

        $content = implode("\n", $lines);
        $path = base_path('memory/feedback/INDEX.md');
        $this->ensureDir(dirname($path));
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Gera memory/feedback/archive/YYYY-QN.md com digest COLD agregado.
     *
     * Por trimestre — sumariza feedbacks com score < 30 OU closed/resolved >= 90d.
     * Não inclui literais cru (LGPD — PII fica só na DB com retention 365d).
     *
     * @return string Path absoluto do arquivo gerado.
     */
    public function generateArchive(?Carbon $forQuarter = null): string
    {
        $now = $forQuarter ?: Carbon::now('America/Sao_Paulo');
        $year = $now->year;
        $quarter = (int) ceil($now->month / 3);
        $qStart = Carbon::create($year, ($quarter - 1) * 3 + 1, 1, 0, 0, 0, 'America/Sao_Paulo');
        $qEnd = $qStart->copy()->addMonths(3)->subSecond();

        $lines = [];
        $lines[] = "# Feedback COLD archive · {$year}-Q{$quarter}";
        $lines[] = '';
        $lines[] = "> Janela: {$qStart->format('Y-m-d')} → {$qEnd->format('Y-m-d')}";
        $lines[] = '> Auto-gerado por `php artisan feedback:reindex` em ' . Carbon::now('America/Sao_Paulo')->format('Y-m-d H:i') . ' BRT.';
        $lines[] = '> Digest agregado por persona+módulo — NÃO contém literal PII (LGPD).';
        $lines[] = '';

        // Agrupa por (business, persona, modulo) os COLD
        $colds = ClientFeedback::query()
            ->withoutGlobalScopes()
            ->whereBetween('created_at', [$qStart, $qEnd])
            ->cold()
            ->whereNull('deleted_at')
            ->selectRaw('business_id, persona_slug, modulo_afetado,
                COUNT(*) as total,
                SUM(recorrente_count) as occurrences_sum,
                MAX(severity_nng) as severity_max,
                AVG(relevance_score) as score_avg')
            ->groupBy('business_id', 'persona_slug', 'modulo_afetado')
            ->orderBy('business_id')
            ->orderByDesc('total')
            ->get();

        if ($colds->isEmpty()) {
            $lines[] = '_(sem feedbacks COLD neste trimestre)_';
            $lines[] = '';
        } else {
            $byBiz = $colds->groupBy('business_id');
            foreach ($byBiz as $bizId => $rows) {
                $lines[] = "## biz={$bizId} — " . $rows->count() . ' clusters · ' . $rows->sum('total') . ' feedbacks';
                $lines[] = '';
                $lines[] = '| Persona | Módulo | Clusters | Ocorrências | Sev max | Score avg |';
                $lines[] = '|---------|--------|---------:|------------:|--------:|----------:|';
                foreach ($rows as $r) {
                    $persona = $r->persona_slug ?: '–';
                    $modulo = $r->modulo_afetado ?: '–';
                    $scoreAvg = number_format((float) $r->score_avg, 1);
                    $lines[] = "| {$persona} | {$modulo} | {$r->total} | {$r->occurrences_sum} | {$r->severity_max} | {$scoreAvg} |";
                }
                $lines[] = '';
            }
        }

        $content = implode("\n", $lines);
        $path = base_path("memory/feedback/archive/{$year}-Q{$quarter}.md");
        $this->ensureDir(dirname($path));
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Recompute relevance_score de todos feedbacks ATIVOS (não COLD/FROZEN).
     *
     * Retorna stats: [processed, rescored, ok_skipped].
     */
    public function reindexScores(?int $businessId = null): array
    {
        $stats = ['processed' => 0, 'rescored' => 0, 'skipped' => 0];

        $query = ClientFeedback::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at');

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $query->chunkById(200, function ($chunk) use (&$stats) {
            foreach ($chunk as $fb) {
                $stats['processed']++;
                $newScore = $this->relevance->computeScore($fb);
                $currentScore = (float) ($fb->relevance_score ?? 0);

                if (abs($newScore - $currentScore) >= 0.5) {
                    $fb->relevance_score = $newScore;
                    $fb->relevance_score_at = now();
                    $fb->saveQuietly(); // skip observer rescore loop
                    $stats['rescored']++;
                } else {
                    $stats['skipped']++;
                }
            }
        });

        return $stats;
    }

    /**
     * Patterns emergentes: signature com recorrente >= 3 E afetando >= 2 personas distintas.
     */
    protected function emergentPatterns(int $businessId)
    {
        return ClientFeedback::query()
            ->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNotNull('signature')
            ->whereNull('deleted_at')
            ->selectRaw('signature, modulo_afetado, MAX(recorrente_count) as recorrente_count,
                COUNT(DISTINCT persona_slug) as personas_count')
            ->groupBy('signature', 'modulo_afetado')
            ->havingRaw('MAX(recorrente_count) >= 3 AND COUNT(DISTINCT persona_slug) >= 2')
            ->orderByDesc('recorrente_count')
            ->limit(5)
            ->get();
    }

    protected function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
