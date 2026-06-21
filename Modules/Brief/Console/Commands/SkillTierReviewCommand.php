<?php

declare(strict_types=1);

namespace Modules\Brief\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * skills:tier-review — loop telemetria → promoção/rebaixamento de tier (Parte B T7).
 *
 * Fecha o ciclo da Parte A (PR #3067, que gravou `tier:` nas ~70 .claude/skills/):
 * parseia o tier de cada SKILL.md, lê uso em `mcp_skill_telemetry` e aplica as 4
 * regras da ADR 0095 — B→A uso ≥80% 30d (exige ADR, nunca auto), B→C uso <10% 60d
 * (trivial, auto), C→arquivar sem uso >90d (exige ADR HISTORICAL), A→B só Wagner.
 * Default READ-ONLY; --apply-suggestions aplica SÓ B→C.
 *
 * Relatório APPEND-ONLY em memory/governance/skill-tier-review-AAAA-QN.md — SÓ
 * agregados; `context_payload` NUNCA é gravado (pode ter PII — LGPD/ADR 0093).
 *
 * Tier 0: `mcp_skill_telemetry` é GOVERNANÇA cross-tenant — sem business_id (exceção
 * superadmin, ADR 0093); queries rodam sem global scope. Hostinger ≠ CT 100 (ADR
 * 0062): só artisan + schedule. "Sessão" = par distinto (agent_id, dia) — proxy
 * honesto, a tabela não tem session_id.
 *
 * @see memory/decisions/0095-skills-tiers-convencao-interna.md
 * @see Modules\Brief\Mcp\Tools\BriefFetchTool::logSkillTelemetry (produtor)
 */
class SkillTierReviewCommand extends Command
{
    protected $signature = 'skills:tier-review
                            {--since=90 : Janela de telemetria em dias (default cobre as 3 regras)}
                            {--apply-suggestions : Aplica SÓ rebaixamentos triviais B→C (nunca promove/arquiva)}';

    protected $description = 'Loop telemetria→tier (ADR 0095): relatório de promoção/rebaixamento de skills. Read-only por default.';

    /** Mínimo de sessões-proxy na janela pra uma % ser confiável (anti-ruído "0 sessões → 0%"). */
    public const MIN_SESSIONS = 10;

    /** Colunas lidas de mcp_skill_telemetry. `context_payload` NUNCA entra (LGPD). */
    public static function telemetrySelectColumns(): array
    {
        return ['skill_name', 'agent_id', 'triggered_at', 'success', 'tokens_saved_estimate'];
    }

    public function handle(): int
    {
        $since = max(1, (int) $this->option('since'));
        $apply = (bool) $this->option('apply-suggestions');

        $skills = $this->loadSkills(base_path('.claude/skills'));
        $semTier = array_keys(array_filter($skills, static fn ($s) => $s['tier'] === null));

        // CATRACA (Parte A): 0 skills sem tier. Se a Parte A regredir, falha aqui.
        if ($semTier !== []) {
            $this->error('CATRACA FALHOU — '.count($semTier).' skill(s) sem tier: '.implode(', ', $semTier));

            return self::FAILURE;
        }

        $metrics = $this->loadTelemetryMetrics($since);
        $suggestions = self::evaluateRules($this->mergeMetrics($skills, $metrics), self::MIN_SESSIONS);
        $applied = $apply ? $this->applyTrivialDemotions($suggestions, $skills) : [];

        $tierCounts = array_count_values(array_map(static fn ($s) => $s['tier'], $skills));
        $reportPath = base_path('memory/governance/skill-tier-review-'.self::quarterLabel(now()).'.md');
        $this->appendReport($reportPath, self::renderReportSection([
            'now_str' => now()->format('Y-m-d H:i'), 'quarter' => self::quarterLabel(now()),
            'since' => $since, 'apply' => $apply, 'total_skills' => count($skills), 'tier_counts' => $tierCounts,
            'total_sessions_30d' => $metrics['total_sessions_30d'], 'total_sessions_60d' => $metrics['total_sessions_60d'],
            'events' => $metrics['events'], 'skills_with_use' => count($metrics['per_skill']),
            'suggestions' => $suggestions, 'applied' => $applied,
        ]));

        $this->renderConsole($tierCounts, $suggestions, $applied, $reportPath, $apply);

        return self::SUCCESS;
    }

    /** Carrega skills ativas (glob `*\/SKILL.md` exclui `_archive/`). */
    private function loadSkills(string $dir): array
    {
        $out = [];
        foreach (glob($dir.'/*/SKILL.md') ?: [] as $path) {
            $name = basename(dirname($path));
            $out[$name] = ['name' => $name, 'path' => $path, 'tier' => self::parseTier((string) file_get_contents($path))];
        }
        ksort($out);

        return $out;
    }

    /** Extrai `tier: A|B|C` do frontmatter. null = sem tier (a catraca pega). PURO. */
    public static function parseTier(string $content): ?string
    {
        $front = preg_match('/\A---\s*\n(.*?)\n---/s', $content, $m) ? $m[1] : $content;

        return preg_match('/^tier:[ \t]*([ABC])\b/mi', $front, $t) ? strtoupper($t[1]) : null;
    }

    private function loadTelemetryMetrics(int $since): array
    {
        try {
            $rows = DB::table('mcp_skill_telemetry')
                ->where('triggered_at', '>=', now()->subDays($since))
                ->select(self::telemetrySelectColumns()) // NUNCA context_payload (LGPD)
                ->orderBy('triggered_at')->get();
        } catch (Throwable $e) {
            $this->warn('Telemetria indisponível ('.$e->getMessage().') — relatório sem métricas.');
            $rows = collect();
        }

        return self::aggregate($rows, now()->timestamp, min($since, 30), min($since, 60), min($since, 90))
            + ['events' => $rows->count()];
    }

    /**
     * Agrega telemetria crua em métricas por skill. PURO — testável sem DB.
     * "Sessão" = par distinto (agent_id, dia). `context_payload` nunca é passado aqui.
     *
     * @param  iterable<object>  $rows  cada um com skill_name, agent_id, triggered_at
     * @return array{total_sessions_30d:int, total_sessions_60d:int, per_skill:array<string, array{sessions_30d:int, sessions_60d:int, triggers_90d:int}>}
     */
    public static function aggregate(iterable $rows, int $nowTs, int $w30 = 30, int $w60 = 60, int $w90 = 90): array
    {
        [$cut30, $cut60, $cut90] = [$nowTs - $w30 * 86400, $nowTs - $w60 * 86400, $nowTs - $w90 * 86400];
        $tot30 = $tot60 = $per = [];

        foreach ($rows as $r) {
            $name = (string) $r->skill_name;
            $ts = is_int($r->triggered_at) ? $r->triggered_at : (int) strtotime((string) $r->triggered_at);
            if ($ts <= 0) {
                continue;
            }
            $day = is_int($r->triggered_at) ? gmdate('Y-m-d', $ts) : substr((string) $r->triggered_at, 0, 10);
            $sess = ((string) $r->agent_id).'|'.$day;
            $per[$name] ??= ['s30' => [], 's60' => [], 't90' => 0];
            if ($ts >= $cut90) {
                $per[$name]['t90']++;
            }
            if ($ts >= $cut60) {
                $per[$name]['s60'][$sess] = $tot60[$sess] = true;
            }
            if ($ts >= $cut30) {
                $per[$name]['s30'][$sess] = $tot30[$sess] = true;
            }
        }

        $perOut = [];
        foreach ($per as $n => $v) {
            $perOut[$n] = ['sessions_30d' => count($v['s30']), 'sessions_60d' => count($v['s60']), 'triggers_90d' => $v['t90']];
        }

        return ['total_sessions_30d' => count($tot30), 'total_sessions_60d' => count($tot60), 'per_skill' => $perOut];
    }

    /** Junta tier (do SKILL.md) + métricas (telemetria) → input da régua de regras. */
    private function mergeMetrics(array $skills, array $agg): array
    {
        [$t30, $t60] = [$agg['total_sessions_30d'], $agg['total_sessions_60d']];
        $out = [];
        foreach ($skills as $name => $s) {
            $m = $agg['per_skill'][$name] ?? ['sessions_30d' => 0, 'sessions_60d' => 0, 'triggers_90d' => 0];
            $out[] = [
                'name' => $name, 'tier' => $s['tier'],
                'sessions_30d' => $m['sessions_30d'], 'sessions_60d' => $m['sessions_60d'], 'triggers_90d' => $m['triggers_90d'],
                'total_sessions_30d' => $t30, 'total_sessions_60d' => $t60,
                'usage_30d_pct' => $t30 > 0 ? round($m['sessions_30d'] / $t30 * 100, 1) : 0.0,
                'usage_60d_pct' => $t60 > 0 ? round($m['sessions_60d'] / $t60 * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Aplica as 4 regras da ADR 0095. PURO — testável sem DB nem filesystem.
     *
     * @param  array<int, array<string, mixed>>  $skills  rows de mergeMetrics()
     * @return array<int, array<string, mixed>>  sugestões
     */
    public static function evaluateRules(array $skills, int $minSessions = self::MIN_SESSIONS): array
    {
        $out = [];
        foreach ($skills as $s) {
            if ($s['tier'] === 'B' && $s['total_sessions_30d'] >= $minSessions && $s['usage_30d_pct'] >= 80.0) {
                $out[] = self::suggestion($s['name'], 'B', 'A', 'B→A uso ≥80% 30d',
                    sprintf('uso %s%% em 30d (%d/%d sessões)', self::pct($s['usage_30d_pct']), $s['sessions_30d'], $s['total_sessions_30d']),
                    auto: false, needsAdr: true, needsHistorical: false);
            } elseif ($s['tier'] === 'B' && $s['total_sessions_60d'] >= $minSessions && $s['usage_60d_pct'] < 10.0) {
                $out[] = self::suggestion($s['name'], 'B', 'C', 'B→C uso <10% 60d',
                    sprintf('uso %s%% em 60d (%d/%d sessões)', self::pct($s['usage_60d_pct']), $s['sessions_60d'], $s['total_sessions_60d']),
                    auto: true, needsAdr: false, needsHistorical: false);
            } elseif ($s['tier'] === 'C' && $s['triggers_90d'] === 0) {
                $out[] = self::suggestion($s['name'], 'C', 'arquivar', 'C→arquivar sem uso >90d',
                    '0 eventos em 90d (verificar que nenhum charter de mission referencia)',
                    auto: false, needsAdr: false, needsHistorical: true);
            }
            // tier A: A→B só por regressão consciente do Wagner (manual — nunca auto).
        }

        return $out;
    }

    private static function suggestion(string $skill, string $from, string $to, string $rule, string $reason, bool $auto, bool $needsAdr, bool $needsHistorical): array
    {
        return compact('skill', 'from', 'to', 'rule', 'reason', 'auto', 'needsAdr', 'needsHistorical');
    }

    private static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    /** Aplica SÓ B→C (auto). Promoção/arquivamento NUNCA são auto-aplicados. */
    private function applyTrivialDemotions(array $suggestions, array $skills): array
    {
        $applied = [];
        foreach ($suggestions as $sg) {
            $path = ($sg['auto'] ?? false) ? ($skills[$sg['skill']]['path'] ?? null) : null;
            if ($path === null || ! is_file($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            $new = self::applyDemotion($content, $sg['from'], $sg['to']);
            if ($new !== $content) {
                file_put_contents($path, $new);
                $applied[] = $sg['skill'];
            }
        }

        return $applied;
    }

    /** Reescreve só a linha `tier: <from>` → `tier: <to>` no frontmatter. PURO + idempotente. */
    public static function applyDemotion(string $content, string $from, string $to): string
    {
        return preg_replace('/^(tier:[ \t]*)'.preg_quote($from, '/').'[ \t]*$/m', '${1}'.$to, $content, 1) ?? $content;
    }

    /** Rótulo de trimestre AAAA-QN a partir de uma data. PURO. */
    public static function quarterLabel(\DateTimeInterface $d): string
    {
        return $d->format('Y').'-Q'.(int) ceil(((int) $d->format('n')) / 3);
    }

    /**
     * Renderiza a seção markdown da run. PURO. SÓ agregados — `context_payload`
     * NUNCA é recebido nem emitido (LGPD · hook pii-redactor).
     */
    public static function renderReportSection(array $ctx): string
    {
        $tc = $ctx['tier_counts'];
        $withTier = ($tc['A'] ?? 0) + ($tc['B'] ?? 0) + ($tc['C'] ?? 0);
        $semTier = $ctx['total_skills'] - $withTier;

        $l = [];
        $l[] = sprintf('## Run %s (%s) — --since=%d · apply=%s', $ctx['now_str'], $ctx['quarter'], $ctx['since'], $ctx['apply'] ? 'sim' : 'não');
        $l[] = '';
        $l[] = sprintf('- Skills analisadas: **%d** (com tier: %d · sem tier: %d %s catraca)', $ctx['total_skills'], $withTier, $semTier, $semTier === 0 ? '✅' : '❌');
        $l[] = sprintf('- Distribuição de tier: A=%d · B=%d · C=%d', $tc['A'] ?? 0, $tc['B'] ?? 0, $tc['C'] ?? 0);
        $l[] = sprintf('- Telemetria: %d eventos · %d skills com uso · sessões-proxy 30d=%d · 60d=%d', $ctx['events'], $ctx['skills_with_use'], $ctx['total_sessions_30d'], $ctx['total_sessions_60d']);
        $l[] = '';
        $l[] = sprintf('### Sugestões (%d)', count($ctx['suggestions']));
        if ($ctx['suggestions'] === []) {
            $l[] = '_Nenhuma — distribuição de tier saudável pra esta janela._';
        } else {
            $l[] = '| Skill | De | Para | Regra | Motivo | Ação |';
            $l[] = '|---|---|---|---|---|---|';
            foreach ($ctx['suggestions'] as $s) {
                $acao = $s['needsAdr'] ? '⚠️ EXIGE ADR (nunca auto)' : ($s['needsHistorical'] ? 'requer ADR HISTORICAL + Wagner' : ($s['auto'] ? 'auto-aplicável (B→C)' : 'manual'));
                $l[] = sprintf('| %s | %s | %s | %s | %s | %s |', $s['skill'], $s['from'], $s['to'], $s['rule'], $s['reason'], $acao);
            }
        }
        $l[] = '';
        $l[] = '### Aplicado nesta run';
        $l[] = $ctx['applied'] === [] ? '- (nenhum rebaixamento aplicado)' : '- B→C aplicado: '.implode(', ', $ctx['applied']);
        $l[] = '';
        $l[] = '> ⚠️ Apenas agregados. `context_payload` NUNCA é gravado aqui (LGPD · ADR 0093 + hook pii-redactor).';

        return implode("\n", $l)."\n";
    }

    private function appendReport(string $path, string $section): void
    {
        if (! is_dir($dir = dirname($path))) {
            mkdir($dir, 0775, true);
        }
        $header = is_file($path) ? '' : (
            "# Skill Tier Review — loop telemetria→tier (ADR 0095)\n\n"
            ."> Append-only. Cada run de `php artisan skills:tier-review` adiciona uma seção.\n"
            ."> SÓ agregados; `context_payload` NUNCA é gravado (pode conter PII). Parte B do T7.\n\n"
        );
        file_put_contents($path, $header.$section, FILE_APPEND);
    }

    private function renderConsole(array $tierCounts, array $suggestions, array $applied, string $reportPath, bool $apply): void
    {
        $this->newLine();
        $this->info(sprintf('Skill Tier Review — A=%d B=%d C=%d', $tierCounts['A'] ?? 0, $tierCounts['B'] ?? 0, $tierCounts['C'] ?? 0));
        if ($suggestions === []) {
            $this->line('Nenhuma sugestão pra esta janela.');
        } else {
            $this->table(['Skill', 'De', 'Para', 'Motivo', 'Ação'], array_map(static fn ($s) => [
                $s['skill'], $s['from'], $s['to'], $s['reason'],
                $s['needsAdr'] ? 'EXIGE ADR' : ($s['needsHistorical'] ? 'ADR HISTORICAL' : ($s['auto'] ? 'auto B→C' : 'manual')),
            ], $suggestions));
        }
        $this->line($apply
            ? ($applied === [] ? 'Nada aplicado.' : 'Aplicado (B→C): '.implode(', ', $applied))
            : 'Read-only. Use --apply-suggestions pra aplicar SÓ rebaixamentos triviais (B→C).');
        $this->line('Relatório: '.$reportPath);
        $this->newLine();
    }
}
