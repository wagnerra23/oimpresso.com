<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Console\Commands\HealthCheckCommand;
use Modules\Jana\Services\CharterHealthChecker;
use Throwable;

/**
 * `governanca:ciclo-diario` — orquestrador diário da governança [CC]×Jana.
 *
 * Por quê ([W], 2026-06-03): *"não quero repetir 2x nem ficar pedindo pra
 * organizar — resolve e gradua todo dia."* Hoje os checks existem soltos
 * (`jana:health-check`, `governanca:scorecard`, `review-freshness`) e o placar
 * do Cowork é mantido à mão. Este ciclo regenera o estado, roda o frescor,
 * gradua o inbox de [W] (`COWORK_NOTES.md`) e emite UM digest/dia — [W] lê 1
 * coisa e só toca Tier 0.
 *
 * NÃO cria daemon novo nem 7º motor de score: ORQUESTRA o que já existe
 * (anti-G1 do AUDITORIA_ROTINAS_DESIGN). Estende o cron `jana:health-check`
 * (Kernel 06:00) rodando depois dele (06:50) sobre o estado já fresco.
 *
 * ADVISORY: nada derruba o cron, nada auto-mergeia Tier 0, não numera ADR
 * (soberania [W], ADR 0238). O digest é informativo; o irreversível é de [W].
 *
 * Defensivo: as pontes irmãs (`governanca:scorecard` já em main #2151;
 * `protocol_freshness` chega no lote UC-guards) são lidas se presentes e
 * marcadas como "ponte pendente" se ainda não landaram — o ciclo nunca quebra
 * por uma ponte ausente.
 *
 * @see Modules/Governance/Console/Commands/GovernancaScorecardCommand.php
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php  (graduation_ratio)
 * @see prototipo-ui/COWORK_NOTES.md  (inbox [W]→[CC]/[CD])
 */
class CicloDiarioGovernancaCommand extends Command
{
    protected $signature = 'governanca:ciclo-diario
                            {--json : imprime o ciclo como JSON em vez de tabela}
                            {--code-notes : anexa o digest do dia ao prototipo-ui/CODE_NOTES.md (idempotente por data)}';

    protected $description = 'Ciclo diário de governança: regenera estado + frescor + gradua inbox [W] + emite 1 digest/dia (advisory).';

    private const STATE_PATH = 'reports/governanca-state.json';
    private const DIGEST_PATH = 'reports/governanca-digest.md';
    private const INBOX_PATH = 'prototipo-ui/COWORK_NOTES.md';
    private const CODE_NOTES_PATH = 'prototipo-ui/CODE_NOTES.md';

    public function handle(): int
    {
        $scorecard = $this->safeScorecard();
        $frescor = $this->runFrescor($scorecard);
        $inbox = $this->graduateInbox();
        $state = $this->buildState($scorecard, $frescor, $inbox);

        $this->writeJson(storage_path(self::STATE_PATH), $state);

        $digest = $this->buildDigest($state);
        $this->writeText(storage_path(self::DIGEST_PATH), $digest);

        if ($this->option('code-notes')) {
            $this->appendCodeNotes($digest, $state['date']);
        }

        if ($this->option('json')) {
            $this->line(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($digest);
        }

        Log::channel('single')->info('governanca:ciclo-diario', [
            'graduou' => $state['frescor']['graduacao'],
            'acendeu' => $state['frescor']['acendeu'],
            'inbox_pendentes' => $state['inbox']['pendentes'],
            'espera_w' => $state['espera_w'],
        ]);

        // Advisory — sempre SUCCESS. Drift de governança não pagina à noite.
        return Command::SUCCESS;
    }

    // ── 1. Estado (scorecard agregado) ──────────────────────────────────────

    /**
     * Reusa GovernancaScorecardCommand::buildScorecard (já em main #2151).
     * Defensivo: se a ponte não estiver presente, devolve estrutura mínima.
     *
     * @return array<string, mixed>
     */
    private function safeScorecard(): array
    {
        try {
            $cmd = $this->getLaravel()->make(GovernancaScorecardCommand::class);
            if (method_exists($cmd, 'buildScorecard')) {
                return $cmd->buildScorecard();
            }
        } catch (Throwable $e) {
            $this->warn("scorecard indisponível ({$e->getMessage()}) — ciclo segue advisory");
        }

        return ['_ponte_ausente' => 'governanca:scorecard', 'ledgers' => [], 'mecanizado' => []];
    }

    // ── 2. Frescor (orquestra checks existentes) ────────────────────────────

    /**
     * @param  array<string, mixed>  $scorecard
     * @return array<string, mixed>
     */
    private function runFrescor(array $scorecard): array
    {
        $graduacao = [];
        $acendeu = [];

        // (a) graduação dos 2 ledgers — do scorecard já calculado.
        foreach (($scorecard['ledgers'] ?? []) as $key => $l) {
            if (! ($l['presente'] ?? false)) {
                continue;
            }
            $pct = (int) round((float) $l['graduation_ratio'] * 100);
            $graduacao[$key] = "{$l['graduadas']}/{$l['total']} ({$pct}%)";
            if ($l['graduation_ratio'] < 1.0 || ($l['pendentes'] ?? 0) > 0) {
                $acendeu[] = "graduacao:{$key} {$pct}%";
            }
        }

        // (b) cobertura de charter (advisory) — reusa CharterHealthChecker.
        try {
            foreach (CharterHealthChecker::fromApp()->checks() as $c) {
                if (! ($c['ok'] ?? true)) {
                    $acendeu[] = "{$c['name']}: {$c['value']}";
                }
            }
        } catch (Throwable) {
            // checker indisponível em dev — segue advisory.
        }

        // (c) review-freshness — lê o baseline (ratchet, ADR 0209) sem bootar node.
        $reviewMissing = $this->reviewFreshnessMissing();
        if ($reviewMissing > 0) {
            $acendeu[] = "review-freshness: {$reviewMissing} missing (baseline)";
        }

        // (d) protocol_freshness — ponte irmã (lote UC-guards). Defensivo.
        $protocol = $this->protocolFreshnessSignal();
        if ($protocol !== null) {
            $acendeu[] = $protocol;
        }

        return [
            'graduacao' => $graduacao,
            'review_freshness_missing' => $reviewMissing,
            'acendeu' => array_values(array_unique($acendeu)),
        ];
    }

    private function reviewFreshnessMissing(): int
    {
        $path = base_path('prototipo-ui/audit/review-freshness-baseline.json');
        if (! is_file($path)) {
            return 0;
        }
        $j = json_decode((string) file_get_contents($path), true);

        return is_array($j) && isset($j['missing']) && is_array($j['missing']) ? count($j['missing']) : 0;
    }

    /**
     * Sinal do protocol_freshness (ponte irmã do lote UC-guards). Lê o JSON que
     * o check emite se já landou; senão devolve marcador de ponte pendente.
     */
    private function protocolFreshnessSignal(): ?string
    {
        $report = storage_path('reports/protocol-freshness.json');
        if (is_file($report)) {
            $j = json_decode((string) file_get_contents($report), true);
            $n = is_array($j) && isset($j['acende']) && is_array($j['acende']) ? count($j['acende']) : 0;

            return $n > 0 ? "protocol_freshness: {$n} acende(m)" : null;
        }

        $script = base_path('prototipo-ui/audit/protocol-freshness.mjs');

        return is_file($script) ? null : 'protocol_freshness: ponte pendente (lote UC-guards)';
    }

    // ── 3. Graduação do inbox COWORK_NOTES ──────────────────────────────────

    /**
     * "Não repetir 2x": toda decisão de [W] no inbox tem que receber `Graduação:`
     * em ≤1 ciclo (MEC→check / JULG→regra). Sem isso = pendente (amarelo).
     *
     * @return array{total:int, graduadas:int, pendentes:int, pendentes_ids:list<string>}
     */
    private function graduateInbox(): array
    {
        $path = base_path(self::INBOX_PATH);
        if (! is_file($path)) {
            return ['total' => 0, 'graduadas' => 0, 'pendentes' => 0, 'pendentes_ids' => []];
        }

        $content = (string) file_get_contents($path);
        // Cada decisão = bloco iniciado por `## ` cujo cabeçalho marca autoria [W].
        $blocos = preg_split('/^##\s+/m', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $total = 0;
        $graduadas = 0;
        $pendentes = [];

        foreach ($blocos as $bloco) {
            $linhas = explode("\n", $bloco, 2);
            $cabecalho = trim($linhas[0] ?? '');
            $corpo = $linhas[1] ?? '';

            // Pula o template-modelo (linha "YYYY-MM-DD HH:MM [W] → [CC]" copiável).
            if (str_contains($cabecalho, 'YYYY-MM-DD')) {
                continue;
            }

            // Só decisões de [W] (autoria + seta). Pedidos de [CC]→[CL] não graduam aqui.
            $ehDecisaoW = preg_match('/\[W\b/u', $cabecalho) === 1
                && preg_match('/→|\[W\s*→/u', $cabecalho) === 1;
            if (! $ehDecisaoW) {
                continue;
            }

            $total++;
            if (preg_match('/Gradua[çc][ãa]o:/u', $corpo) === 1) {
                $graduadas++;
            } else {
                $pendentes[] = mb_substr($cabecalho, 0, 60);
            }
        }

        return [
            'total' => $total,
            'graduadas' => $graduadas,
            'pendentes' => count($pendentes),
            'pendentes_ids' => $pendentes,
        ];
    }

    // ── 4. State + 5. Digest ────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $scorecard
     * @param  array<string, mixed>  $frescor
     * @param  array<string, mixed>  $inbox
     * @return array<string, mixed>
     */
    private function buildState(array $scorecard, array $frescor, array $inbox): array
    {
        // Tier 0 / irreversível que ESPERA [W]: por ora o pipe único [CC]+Jana
        // (flag manual da condição 9.7). Drift de processo NÃO entra aqui — só o
        // que é genuinamente decisão de [W]. O ciclo nunca auto-resolve Tier 0.
        $esperaW = [];
        $cond = $scorecard['condicao_9_7'] ?? [];
        if (($cond['ambos_ledgers_100'] ?? false) && ! ($cond['pipe_unico_cc_jana'] ?? false)) {
            $esperaW[] = 'confirmar pipe único [CC]+Jana (condição 9.7 · flag manual)';
        }

        return [
            'generator' => 'governanca:ciclo-diario',
            'version' => '1.0.0',
            'date' => now()->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'measured_against_sha' => $this->currentSha(),
            'aprovado' => [
                'ledgers_100' => array_keys(array_filter(
                    $scorecard['ledgers'] ?? [],
                    fn ($l) => ($l['presente'] ?? false) && ($l['graduation_ratio'] ?? 0) >= 1.0
                )),
                'enforcement_score' => $scorecard['mecanizado']['enforcement_score'] ?? null,
            ],
            'frescor' => $frescor,
            'inbox' => $inbox,
            'espera_w' => $esperaW,
            'scorecard_ref' => self::STATE_PATH,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function buildDigest(array $state): string
    {
        $grad = $state['frescor']['graduacao'] ?? [];
        $gradStr = $grad === []
            ? '— (nenhum ledger presente)'
            : implode(' · ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($grad), $grad));

        $acendeu = $state['frescor']['acendeu'] ?? [];
        $acendeuStr = $acendeu === [] ? 'nada' : implode(' · ', $acendeu);

        $inbox = $state['inbox'];
        $espera = $state['espera_w'] === [] ? 'nada' : implode(' · ', $state['espera_w']);
        $enf = $state['aprovado']['enforcement_score'] ?? 'n/a';

        return <<<MD
# Governança — Digest diário ({$state['date']})

> Saída do ciclo `governanca:ciclo-diario` (advisory). [W] lê 1 coisa/dia e só toca Tier 0.
> measured_against_sha: `{$state['measured_against_sha']}` · enforcement_score: {$enf}/10

- **Graduou:** {$gradStr}
- **Acendeu (advisory):** {$acendeuStr}
- **Inbox [W]:** {$inbox['graduadas']} graduada(s) · {$inbox['pendentes']} pendente(s) sem `Graduação:`
- **Espera [W] (Tier 0):** {$espera}

MD;
    }

    /**
     * Anexa o digest do dia ao CODE_NOTES.md — idempotente por data (não duplica
     * se já rodou hoje). Usado pro marco/deliverable, NÃO pelo cron (que só grava
     * o storage/digest pra não sujar arquivo versionado todo dia).
     */
    private function appendCodeNotes(string $digest, string $date): void
    {
        $path = base_path(self::CODE_NOTES_PATH);
        if (! is_file($path)) {
            $this->warn('CODE_NOTES.md ausente — pulo o append.');

            return;
        }
        $atual = (string) file_get_contents($path);
        $marcador = "Digest diário ({$date})";
        if (str_contains($atual, $marcador)) {
            $this->line("CODE_NOTES já tem o digest de {$date} — idempotente, não duplico.");

            return;
        }
        file_put_contents($path, $atual . "\n---\n\n## ⟳ " . $marcador . " — `governanca:ciclo-diario`\n\n" . $digest . "\n");
        $this->info("digest {$date} anexado a CODE_NOTES.md");
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $this->ensureDir(dirname($path));
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function writeText(string $path, string $text): void
    {
        $this->ensureDir(dirname($path));
        file_put_contents($path, $text);
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function currentSha(): ?string
    {
        try {
            $out = @shell_exec('git rev-parse --short HEAD 2>&1');
            $sha = is_string($out) ? trim($out) : '';

            return preg_match('/^[0-9a-f]{7,40}$/', $sha) === 1 ? $sha : null;
        } catch (Throwable) {
            return null;
        }
    }
}
