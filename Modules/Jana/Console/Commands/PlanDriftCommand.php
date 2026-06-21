<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * jana:plan-drift — sentinela de DRIFT plano↔tasks (ADR 0294 Onda 2).
 *
 * Fecha a metade que faltava do plan-health: a sentinela Node
 * (scripts/governance/plan-health.mjs, Onda 1) valida o Índice de Planos Vivos +
 * o bloco `## Status vivo`, mas NÃO consegue checar drift porque é "node puro, sem
 * rede" e as tasks moram no MCP ([ADR 0070](memory/decisions/0070-...)). Esta é a
 * outra ponta: PHP com acesso Eloquent direto a `mcp_tasks`, compara o **status
 * declarado** de cada plano contra a **realidade das tasks** ligadas por `parent_plan`.
 *
 * TRANSPORTE (decisão documentada — ADR 0294 Onda 2):
 *   Escolhido (ii) "comando PHP que o agregador chama", NÃO (i) "artisan exporta JSON
 *   que o .mjs lê". Razões:
 *     - plan-health.mjs é determinístico/sem-infra (roda em qualquer CI); injetar
 *       spawn→php→JSON quebra essa propriedade e cria janela de staleness — um
 *       sentinela node lendo um JSON possivelmente velho é exatamente o anti-padrão
 *       "ghost canary / teatro" que a auditoria de sentinelas (PR #3098) acabou de matar.
 *     - As tasks vivem no DB que ESTE app hospeda (Modules/Jana McpTask). PHP lê ao vivo,
 *       sem etapa de export, sem janela de drift do próprio drift-check.
 *     - O agregador `governance-audit.mjs` já tem slot de 1ª classe pra sentinelas PHP
 *       advisory que pulam gracioso quando php/app/DB não bootam — drift é infra-dependente
 *       por natureza e cai exatamente ali.
 *
 * CONTRATO `parent_plan` (alinhado com backlog-34 / feat/backlog-plano-perdido-34):
 *   - slug = kebab-case minúsculo (ex: `plano-atendimento-automatico`).
 *   - Lado PLANO: declarado no bloco `## Status vivo` como `execução: parent_plan=<slug>`.
 *   - Lado TASK (precedência ao resolver — ver resolveParentPlan()):
 *       1. custom_fields['parent_plan']  — ALVO CANÔNICO, alimentado pelo TaskParserService
 *          a partir de uma meta-line `> parent_plan: <slug>` (chave não-canônica cai no
 *          default→custom_fields; o parser aceita `:` e `=`).
 *       2. label `parent_plan:<slug>`     — fluxos que rotulam em vez de custom-field.
 *       3. regex na description           — PONTE TRANSITÓRIA pro formato atual do backlog-34
 *          (`\`parent_plan: <slug>\`` em texto de corpo, fora da meta-line — logo NÃO chega
 *          em custom_fields hoje). Coordenação: mover esses 22 US pra meta-line `>` fecha a
 *          ponte e torna a ligação 100% estruturada.
 *
 * Flaga (os 3 casos do escopo Onda 2 + 2 inversos):
 *   - 🔴 em-execução mas 0 tasks com o parent_plan (ligação fantasma / nunca materializou)
 *   - 🟡 em-execução mas 0 tasks em todo/doing (parou?)
 *   - 🟡 concluído mas tasks ainda abertas
 *   - 🟡 proposto/ativo mas já tem tasks abertas (cruzou a membrana sem virar em-execução — ADR 0294)
 *   - 🟡 órfão reverso: tasks apontam pra slug que nenhum plano registrado declara
 *
 * SKIP GRACIOSO (requer MCP online — estava offline em 2026-06-20):
 *   - `mcp_tasks` ausente → skip (DB/MCP offline ou fresh).
 *   - PLANS-INDEX ausente → skip.
 *   - 0 tasks com qualquer parent_plan → skip (evita "ligação fantasma" em massa quando o
 *     backlog ainda não materializou/sincronizou).
 *
 * USO (raiz do repo):
 *   php artisan jana:plan-drift            # relatório (exit 0; exit 1 só em 🔴)
 *   php artisan jana:plan-drift --json     # pro agregador governance-audit / Daily Brief
 *   php artisan jana:plan-drift --check    # exit 1 em qualquer achado (ratchet futuro)
 */
class PlanDriftCommand extends Command
{
    protected $signature = 'jana:plan-drift
                            {--index= : Caminho do PLANS-INDEX.md (default: memory/requisitos/_processo/PLANS-INDEX.md)}
                            {--json : Output JSON (agregador governance-audit / Daily Brief)}
                            {--check : Exit 1 se houver QUALQUER achado (ratchet futuro)}';

    protected $description = 'Detecta DRIFT entre o status declarado de um plano e a realidade das tasks MCP (parent_plan). ADR 0294 Onda 2 + ADR 0070.';

    public const INDEX_REL = 'memory/requisitos/_processo/PLANS-INDEX.md';

    /** Estados "abertos" (a US ainda não fechou). */
    public const OPEN = ['backlog', 'todo', 'doing', 'review', 'blocked'];

    /** Estados "em movimento" (alguém está realmente tocando agora). */
    public const MOVING = ['todo', 'doing'];

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $check = (bool) $this->option('check');
        $indexPath = $this->option('index') ?: base_path(self::INDEX_REL);

        if (! Schema::hasTable('mcp_tasks')) {
            return $this->skip('mcp_tasks ausente (MCP/DB offline?) — drift não verificável', $asJson);
        }
        if (! is_file($indexPath)) {
            return $this->skip("PLANS-INDEX ausente ({$indexPath})", $asJson);
        }

        $plans = $this->parsePlans($indexPath);
        $taskCounts = $this->taskCountsByParentPlan();

        $totalLinked = array_sum(array_map(fn ($c) => $c['total'], $taskCounts));
        if ($totalLinked === 0) {
            $total = McpTask::count();
            return $this->skip(
                $total === 0
                    ? 'mcp_tasks vazia (MCP offline / não sincronizado) — drift não verificável'
                    : "nenhuma das {$total} tasks carrega parent_plan ainda (sync/format pendente — coordenar backlog-34)",
                $asJson,
            );
        }

        $findings = [];
        $declaredSlugs = [];

        foreach ($plans as $plan) {
            if ($plan['slug'] === null) {
                continue; // sem parent_plan declarado: é o check de órfão da plan-health, não drift
            }
            $declaredSlugs[$plan['slug']] = true;
            $c = $taskCounts[$plan['slug']] ?? self::emptyCounts();
            $f = self::classifyDrift($plan['status'], $c);
            if ($f !== null) {
                $findings[] = array_merge([
                    'plan' => $plan['label'], 'slug' => $plan['slug'], 'status' => $plan['status'], 'counts' => $c,
                ], $f);
            }
        }

        // Órfão reverso: tasks apontam pra um slug que nenhum plano registrado declara.
        foreach ($taskCounts as $slug => $c) {
            if (isset($declaredSlugs[$slug])) {
                continue;
            }
            $findings[] = [
                'plan' => '(sem plano registrado)', 'slug' => $slug, 'status' => null, 'counts' => $c,
                'level' => 'warn',
                'issue' => "{$c['total']} task(s) com parent_plan=$slug, mas nenhum plano declara esse slug no Índice (órfão reverso — registrar no PLANS-INDEX?)",
            ];
        }

        return $this->report($findings, count($plans), count($taskCounts), $asJson, $check);
    }

    /**
     * Regra de drift — PURA, testável sem filesystem/DB.
     *
     * @param  array{total:int,open:int,moving:int,done:int,cancelled:int}  $c
     * @return array{level:string,issue:string}|null
     */
    public static function classifyDrift(?string $status, array $c): ?array
    {
        $s = strtolower(trim((string) $status));
        $emExec = str_starts_with($s, 'em-execu');
        $concluido = str_starts_with($s, 'conclu');

        if ($emExec && $c['total'] === 0) {
            return ['level' => 'fail', 'issue' => 'em-execução mas 0 tasks com esse parent_plan (ligação fantasma / nunca materializou no MCP)'];
        }
        if ($emExec && $c['moving'] === 0) {
            return ['level' => 'warn', 'issue' => "em-execução mas 0 tasks em todo/doing ({$c['done']} done · {$c['open']} abertas) — parou de verdade?"];
        }
        if ($concluido && $c['open'] > 0) {
            return ['level' => 'warn', 'issue' => "concluído mas {$c['open']} task(s) ainda aberta(s) no MCP"];
        }
        if (($s === 'proposto' || $s === 'ativo') && $c['open'] > 0) {
            return ['level' => 'warn', 'issue' => "status=$s mas {$c['open']} task(s) aberta(s) com parent_plan — deveria estar em-execução? (membrana ADR 0294)"];
        }

        return null;
    }

    /** @return array{total:int,open:int,moving:int,done:int,cancelled:int} */
    public static function emptyCounts(): array
    {
        return ['total' => 0, 'open' => 0, 'moving' => 0, 'done' => 0, 'cancelled' => 0];
    }

    /**
     * Agrega contagem de tasks por slug parent_plan.
     *
     * @return array<string, array{total:int,open:int,moving:int,done:int,cancelled:int}>
     */
    public function taskCountsByParentPlan(): array
    {
        $out = [];
        foreach (McpTask::query()->get(['status', 'labels', 'custom_fields', 'description']) as $t) {
            $slug = $this->resolveParentPlan($t);
            if ($slug === null) {
                continue;
            }
            $out[$slug] ??= self::emptyCounts();
            $st = strtolower((string) $t->status);
            $out[$slug]['total']++;
            if (in_array($st, self::OPEN, true)) {
                $out[$slug]['open']++;
            }
            if (in_array($st, self::MOVING, true)) {
                $out[$slug]['moving']++;
            }
            if ($st === 'done') {
                $out[$slug]['done']++;
            }
            if ($st === 'cancelled') {
                $out[$slug]['cancelled']++;
            }
        }

        return $out;
    }

    /**
     * Resolve o slug parent_plan de uma task (precedência do contrato — ver docblock da classe).
     */
    public function resolveParentPlan(McpTask $t): ?string
    {
        $cf = $t->custom_fields;
        if (is_array($cf) && ! empty($cf['parent_plan'])) {
            return $this->normalizeSlug((string) $cf['parent_plan']);
        }
        foreach ((array) $t->labels as $label) {
            if (preg_match('/^parent_plan\s*[:=]\s*(.+)$/i', (string) $label, $m)) {
                return $this->normalizeSlug($m[1]);
            }
        }
        if ($t->description && preg_match('/parent_plan\s*[:=]\s*`?([a-z0-9][a-z0-9-]*)/i', (string) $t->description, $m)) {
            return $this->normalizeSlug($m[1]);
        }

        return null;
    }

    /** Primeiro token kebab-case minúsculo; descarta sufixos tipo "(etapa E2 ...)". */
    public function normalizeSlug(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^[a-z0-9][a-z0-9-]*/i', $raw, $m)) {
            return strtolower($m[0]);
        }

        return strtolower($raw);
    }

    /**
     * Lê o Índice de Planos Vivos e, pra cada plano linkado, extrai status + slug do
     * bloco `## Status vivo` do PRÓPRIO plano (fonte da verdade — ADR 0294, não a coluna
     * do índice que pode driftar).
     *
     * @return list<array{label:string,rel:string,abs:string|false,status:?string,slug:?string}>
     */
    public function parsePlans(string $indexPath): array
    {
        $indexDir = dirname($indexPath);
        $body = (string) file_get_contents($indexPath);
        $plans = [];

        foreach (preg_split('/\r?\n/', $body) as $line) {
            if (! isset($line[0]) || $line[0] !== '|' || ! str_contains($line, '](')) {
                continue;
            }
            if (! preg_match('/\[([^\]]+)\]\(([^)]+)\)/', $line, $m)) {
                continue;
            }
            $rel = trim($m[2]);
            $abs = realpath($indexDir . DIRECTORY_SEPARATOR . $rel);
            [$status, $slug] = $this->readStatusVivo($abs ?: '');
            $plans[] = ['label' => trim($m[1]), 'rel' => $rel, 'abs' => $abs, 'status' => $status, 'slug' => $slug];
        }

        return $plans;
    }

    /**
     * Extrai [status, parent_plan-slug] do bloco `## Status vivo` (heading ancorado em
     * início de linha — espelha plan-health.mjs pra não divergir do parser Node).
     *
     * @return array{0:?string,1:?string}
     */
    public function readStatusVivo(string $abs): array
    {
        if ($abs === '' || ! is_file($abs)) {
            return [null, null];
        }
        $lines = preg_split('/\r?\n/', (string) file_get_contents($abs));
        $hi = null;
        foreach ($lines as $i => $l) {
            if (preg_match('/^##\s+Status vivo\b/i', trim($l))) {
                $hi = $i;
                break;
            }
        }
        if ($hi === null) {
            return [null, null];
        }

        $raw = '';
        for ($i = $hi + 1, $n = count($lines); $i < $n; $i++) {
            if (preg_match('/^##\s/', trim($lines[$i]))) {
                break;
            }
            $raw .= $lines[$i] . "\n";
        }
        // Normaliza markdown (igual plan-health.mjs): tira comentários HTML, negrito, backticks.
        $bloco = preg_replace('/<!--.*?-->/s', '', $raw);
        $bloco = str_replace(['**', '`'], '', (string) $bloco);

        $status = null;
        if (preg_match('/status:\s*([^\n·]+)/i', $bloco, $m)) {
            $status = strtolower(trim((string) preg_split('/\s+/', trim($m[1]))[0]));
        }
        $slug = null;
        if (preg_match('/parent_plan\s*[:=]\s*([a-z0-9][a-z0-9-]*)/i', $bloco, $m)) {
            $slug = strtolower($m[1]);
        }

        return [$status, $slug];
    }

    /** @param list<array<string,mixed>> $findings */
    protected function report(array $findings, int $planos, int $linked, bool $asJson, bool $check): int
    {
        $fails = array_values(array_filter($findings, fn ($f) => $f['level'] === 'fail'));
        $warns = array_values(array_filter($findings, fn ($f) => $f['level'] === 'warn'));
        $ok = $check ? count($findings) === 0 : count($fails) === 0;

        Log::channel('single')->info('jana:plan-drift', [
            'planos' => $planos, 'linked' => $linked, 'fail' => count($fails), 'warn' => count($warns),
        ]);

        if ($asJson) {
            $this->line(json_encode([
                'ok' => $ok,
                'planos' => $planos,
                'linked' => $linked,
                'fail' => count($fails),
                'warn' => count($warns),
                'findings' => array_values($findings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->line("\n  jana:plan-drift — {$planos} planos · {$linked} com tasks · " . count($fails) . ' 🔴 · ' . count($warns) . " 🟡\n");
        foreach ($fails as $f) {
            $this->line("  🔴 {$f['plan']} [{$f['slug']}]: {$f['issue']}");
        }
        foreach ($warns as $f) {
            $this->line("  🟡 {$f['plan']} [{$f['slug']}]: {$f['issue']}");
        }
        if (! $findings) {
            $this->line('  ✓ sem drift: status declarado coerente com as tasks MCP.');
        }
        $this->line('');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    protected function skip(string $reason, bool $asJson): int
    {
        if ($asJson) {
            $this->line(json_encode(
                ['ok' => true, 'skipped' => true, 'reason' => $reason, 'planos' => 0, 'fail' => 0, 'warn' => 0, 'findings' => []],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->line("\n  jana:plan-drift — ⊘ {$reason}\n");
        }

        return self::SUCCESS;
    }
}
