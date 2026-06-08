<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use DateTimeImmutable;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * DesignDocsFreshnessChecker — "proteção contra o tempo" dos docs de design.
 *
 * ADR 0236 (máquina 4 "freshness gate") manda estender o freshness-checker do
 * framework de drift (ADR 0220 / 0216) PROS DOCS DE DESIGN — os mesmos docs que
 * apodreceram em silêncio (o CLAUDE_COWORK_PRIMER envelheceu 3 semanas sem que
 * DS v3→v4 / golden / pré-flight chegassem nele). Sem mecanismo, o índice mestre
 * repete o apodrecimento.
 *
 * Diferença pro ChartersFreshnessChecker (ADR 0220): aquele é ADAPTER ao comando
 * `charter:audit` e cobre `*.charter.md`. Este é FILE-BASED puro (sem DB, sem rede,
 * sem comando) e cobre os docs de design narrativos:
 *   - `prototipo-ui/*.md`               (loop Cowork ↔ Code, briefings, golden)
 *   - `memory/requisitos/_DesignSystem/*.md` (+ subdir `padroes-tela/`)
 *
 * Categorias de drift detectadas:
 *   1. dead_adr_ref     — doc cita um ADR cujo lifecycle é superseded/deprecated
 *                         COMO SE vigente (severity medium). É o caso central:
 *                         ex. um briefing cita "ADR 0190" mas 0190 foi superseded
 *                         por 0235. Resolve `ADR NNNN` contra memory/decisions/ e
 *                         `ADR UI-NNNN` contra _DesignSystem/adr/ui/ (namespaces
 *                         separados — não confunde 0013 com UI-0013).
 *   2. stale_review     — frontmatter declara `next_review:` e a data já passou
 *                         relativa a "hoje" (severity low). Espelha a semântica de
 *                         TTL do ChartersFreshnessChecker, mas a fonte da verdade é
 *                         o próprio doc (next_review explícito), não um TTL por tier.
 *
 * Severity baseline: medium · enforcement: warn (não bloqueia merge, só Brief Jana
 * + CI comment) · cadence: daily.
 * Tags: tier_2, compliance, design_system, ui_governance.
 *
 * Invariantes (herdados ADR 0230 / 0236 §Invariantes):
 *   - Idempotente + determinístico: rodar 2× = mesmo resultado (ordena os docs;
 *     "hoje" é injetável — não usa wall-clock dentro da lógica pura).
 *   - Sem business_id (drift repo-wide), sem rede, sem DB.
 *   - "Hoje" injetável via $opts['now'] (DateTimeImmutable|string) — default now().
 *     Mesmo padrão dos métodos puros de DeployDriftChecker::analisar /
 *     MeilisearchSettingsDriftChecker::driftsDoIndice (testáveis sem I/O real).
 *
 * Refs:
 * - ADR 0236 mãe deste checker (máquina 4 freshness gate)
 * - ADR 0220 freshness gate de charters (irmão)
 * - ADR 0216 DriftChecker framework (interface canônica)
 * - ADR 0219 AdrLinksChecker (mesmo padrão file-based + parse frontmatter + lifecycle)
 * - memory/decisions/_INDEX-LIFECYCLE.md (estados de lifecycle canônicos)
 */
final class DesignDocsFreshnessChecker implements DriftChecker
{
    /**
     * Globs (relativos a base_path) dos docs de design varridos.
     * Esqueleto: muda só via ADR (ADR 0236 §G1 gatilho INCREMENTAL aponta exatamente
     * pra `prototipo-ui/*.md` e `_DesignSystem/*.md`).
     */
    private const DESIGN_DOC_GLOBS = [
        'prototipo-ui/*.md',
        'memory/requisitos/_DesignSystem/*.md',
        'memory/requisitos/_DesignSystem/padroes-tela/*.md',
    ];

    /**
     * Valores de lifecycle/status que significam "ADR NÃO vigente" (PT + EN).
     * Fonte: distribuição real medida no repo + memory/decisions/_INDEX-LIFECYCLE.md.
     *   status:    superseded | deprecated
     *   lifecycle: substituido (PT superseded) | arquivado (PT deprecated/archived)
     */
    private const DEAD_LIFECYCLE_VALUES = [
        'superseded',
        'deprecated',
        'substituido',
        'arquivado',
        'descontinuado',
    ];

    public function name(): string
    {
        return 'design_docs_freshness';
    }

    public function description(): string
    {
        return 'Detecta docs de design citando ADR superseded/deprecated como vigente + next_review vencido';
    }

    public function tags(): array
    {
        return ['tier_2', 'compliance', 'design_system', 'ui_governance'];
    }

    public function severity(): string
    {
        return 'medium';
    }

    public function enforcement(): string
    {
        return 'warn';
    }

    public function cadence(): string
    {
        return 'daily';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);
        $base = base_path();

        $hoje = $this->resolveHoje($opts['now'] ?? null);

        // Mapa ADR-key (normalizado) → lifecycle-string. Cruza frontmatter dos ADRs
        // (fonte primária, igual AdrLinksChecker) + lookup-table do _INDEX-LIFECYCLE.
        $lifecycleMap = $this->buildAdrLifecycleMap($base);

        $docs = $this->collectDesignDocs($base);

        if (count($docs) === 0) {
            return DriftCheckResult::clean($this->name(), 0, [
                'skipped' => 'nenhum doc de design encontrado (prototipo-ui/ + _DesignSystem/ ausentes)',
            ]);
        }

        $findings = [];
        foreach ($docs as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            // Relativiza o path de forma slash-agnóstica (Windows/Unix): normaliza
            // ambos pra '/' e remove o prefixo base — target sempre POSIX-relative.
            $relPath = ltrim(
                str_replace(str_replace('\\', '/', $base), '', str_replace('\\', '/', $file)),
                '/',
            );

            foreach ($this->analisarDoc($relPath, $content, $lifecycleMap, $hoje) as $finding) {
                $findings[] = $finding;
            }
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $metadata = [
            'docs_scanned' => count($docs),
            'adrs_indexed' => count($lifecycleMap),
            'today' => $hoje->format('Y-m-d'),
        ];

        if (count($findings) === 0) {
            return DriftCheckResult::clean($this->name(), $durationMs, $metadata);
        }

        $metadata['category_counts'] = [
            'dead_adr_ref' => $this->countByCategory($findings, 'dead_adr_ref'),
            'stale_review' => $this->countByCategory($findings, 'stale_review'),
        ];

        return DriftCheckResult::drifted(
            name: $this->name(),
            findings: $findings,
            duration_ms: $durationMs,
            metadata: $metadata,
        );
    }

    /**
     * Núcleo PURO + determinístico — sem I/O. Recebe o conteúdo do doc + mapa de
     * lifecycle + "hoje" injetados e devolve os findings. Testável sem tocar disco/rede
     * (mesmo contrato de DeployDriftChecker::analisar / Meilisearch::driftsDoIndice).
     *
     * @param array<string, string> $lifecycleMap key normalizado → lifecycle-string
     * @return array<int, DriftFinding>
     */
    public function analisarDoc(
        string $relPath,
        string $content,
        array $lifecycleMap,
        DateTimeImmutable $hoje,
    ): array {
        $findings = [];

        // ── Categoria 1: refs a ADR morto citado como VIGENTE ──────────────────
        // Agrupa por key. SÓ flaga se houver ≥1 menção FORA de contexto histórico.
        // Citar ADR morto rotulado "(superseded)" / "substituído por X" / na coluna
        // "aposentado" de um índice de reconciliação é uso CORRETO — NÃO flaga
        // (senão o próprio INDEX-DESIGN-MEMORIAS vira falso-positivo diário).
        $deadByKey = []; // key => array{label: string, lifecycle: string, vigente: bool}
        foreach ($this->extractAdrRefs($content) as $ref) {
            $key = $ref['key'];
            $lifecycle = $lifecycleMap[$key] ?? null;
            if ($lifecycle === null || ! $this->isDeadLifecycle($lifecycle)) {
                continue;
            }
            if (! isset($deadByKey[$key])) {
                $deadByKey[$key] = ['label' => $ref['label'], 'lifecycle' => $lifecycle, 'vigente' => false];
            }
            if (! $this->mencaoEhHistorica($ref['context'])) {
                $deadByKey[$key]['vigente'] = true;
                $deadByKey[$key]['label'] = $ref['label'];
            }
        }
        foreach ($deadByKey as $key => $info) {
            if ($info['vigente'] !== true) {
                continue; // todas as menções são históricas → uso correto, não flaga
            }
            $findings[] = new DriftFinding(
                target: $relPath,
                target_type: 'design_doc',
                severity: 'medium',
                message: sprintf(
                    'Doc de design cita ADR %s como vigente, mas o lifecycle dele é "%s" (não-vigente). ' .
                    'Ação: trocar a referência pela ADR que o substitui OU marcar a menção como histórica/superseded.',
                    $info['label'],
                    $info['lifecycle'],
                ),
                evidence: [
                    'category' => 'dead_adr_ref',
                    'adr_ref' => $info['label'],
                    'adr_key' => (string) $key,
                    'lifecycle' => $info['lifecycle'],
                ],
            );
        }

        // ── Categoria 2: next_review vencido ───────────────────────────────────
        $frontmatter = $this->parseFrontmatter($content);
        $nextReviewRaw = $frontmatter['next_review'] ?? $frontmatter['next_review_at'] ?? null;
        if (is_string($nextReviewRaw) && $nextReviewRaw !== '') {
            $nextReview = $this->parseDate($nextReviewRaw);
            if ($nextReview !== null && $nextReview < $hoje) {
                $diasVencido = (int) $hoje->diff($nextReview)->days;
                $findings[] = new DriftFinding(
                    target: $relPath,
                    target_type: 'design_doc',
                    severity: 'low',
                    message: sprintf(
                        'Doc de design com next_review vencido: %s (venceu há %d dias, hoje %s). ' .
                        'Ação: revalidar o conteúdo e atualizar next_review no frontmatter.',
                        $nextReview->format('Y-m-d'),
                        $diasVencido,
                        $hoje->format('Y-m-d'),
                    ),
                    evidence: [
                        'category' => 'stale_review',
                        'next_review' => $nextReview->format('Y-m-d'),
                        'today' => $hoje->format('Y-m-d'),
                        'days_overdue' => $diasVencido,
                    ],
                );
            }
        }

        return $findings;
    }

    /**
     * Extrai referências a ADR do corpo do doc, COM o contexto (a linha) de cada
     * menção — o contexto permite distinguir "citado como vigente" de "citado como
     * histórico/superseded" (ex. "ADR 0190 (superseded)"). Captura:
     *   - "ADR 0190" / "ADR-0190"          → key "0190"   (namespace memory/decisions)
     *   - "ADR UI-0013" / "ADR UI 0013"    → key "UI-0013" (namespace _DesignSystem/adr/ui)
     * Markdown link `[ADR 0190](path)` também casa (o texto "ADR 0190" está no label).
     *
     * @return array<int, array{key: string, label: string, context: string}>
     */
    private function extractAdrRefs(string $content): array
    {
        $refs = [];
        $linhas = preg_split('/\r\n|\r|\n/', $content) ?: [$content];
        foreach ($linhas as $linha) {
            if (! preg_match_all('/\bADR[ -]?(UI-?)?(\d{3,4})\b/i', $linha, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $m) {
                $isUi = trim($m[1]) !== '';
                $key = $isUi ? 'UI-' . $m[2] : $m[2];
                $refs[] = ['key' => $key, 'label' => $key, 'context' => $linha];
            }
        }

        return $refs;
    }

    private function isDeadLifecycle(string $lifecycle): bool
    {
        return in_array(strtolower(trim($lifecycle)), self::DEAD_LIFECYCLE_VALUES, true);
    }

    /**
     * Uma menção a ADR morto é "histórica" (uso CORRETO, não-flagável) quando a LINHA
     * carrega um marcador de aposentadoria — PT+EN. Ex.: "ADR 0190 (superseded)",
     * "substituído por 0235", linha da coluna "Aposentado / não-usar" de um índice de
     * reconciliação. Sem marcador na linha = citado como vigente = flagável.
     */
    private function mencaoEhHistorica(string $context): bool
    {
        $marcadores = [
            'superseded', 'supersede', 'deprecated', 'aposentad', 'substitu',
            'descontinuad', 'arquivad', 'obsolet', 'revogad',
            'não-usar', 'nao-usar', 'não usar', 'nao usar',
        ];
        $ctx = mb_strtolower($context);
        foreach ($marcadores as $marca) {
            if (str_contains($ctx, $marca)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Monta o mapa key-normalizado → lifecycle a partir de:
     *   (a) frontmatter de cada ADR em memory/decisions/*.md   → keys numéricas "0190"
     *   (b) frontmatter de cada ADR UI em _DesignSystem/adr/ui/*.md → keys "UI-0013"
     *   (c) lookup-table do _INDEX-LIFECYCLE.md (enriquecimento secundário) — linhas
     *       `NNNN | S | ...` / `NNNN | D | ...` mapeiam pra superseded/deprecated.
     * Frontmatter é fonte primária; o index só preenche o que faltar (não sobrescreve).
     *
     * @return array<string, string>
     */
    private function buildAdrLifecycleMap(string $base): array
    {
        $map = [];

        // (a) ADRs canônicos numéricos
        foreach (glob("{$base}/memory/decisions/*.md") ?: [] as $file) {
            $num = $this->numFromFilename(basename($file));
            if ($num === null) {
                continue;
            }
            $fm = $this->parseFrontmatter((string) file_get_contents($file));
            $lifecycle = $this->lifecycleFromFrontmatter($fm);
            if ($lifecycle !== null) {
                $map[$num] = $lifecycle;
            }
        }

        // (b) ADRs UI (namespace separado — key prefixada "UI-")
        foreach (glob("{$base}/memory/requisitos/_DesignSystem/adr/ui/*.md") ?: [] as $file) {
            $num = $this->numFromFilename(basename($file));
            if ($num === null) {
                continue;
            }
            $fm = $this->parseFrontmatter((string) file_get_contents($file));
            $lifecycle = $this->lifecycleFromFrontmatter($fm);
            if ($lifecycle !== null) {
                $map['UI-' . $num] = $lifecycle;
            }
        }

        // (c) Enriquecimento via _INDEX-LIFECYCLE.md (só preenche o que faltar)
        $indexFile = "{$base}/memory/decisions/_INDEX-LIFECYCLE.md";
        if (is_file($indexFile)) {
            foreach ($this->parseLifecycleIndex((string) file_get_contents($indexFile)) as $key => $lifecycle) {
                if (! isset($map[$key])) {
                    $map[$key] = $lifecycle;
                }
            }
        }

        return $map;
    }

    /**
     * Normaliza "0190", "0190-primary-button.md", "190" → "0190" (4 dígitos zero-pad).
     */
    private function numFromFilename(string $filename): ?string
    {
        if (preg_match('/^(\d{3,4})/', $filename, $m)) {
            return str_pad($m[1], 4, '0', STR_PAD_LEFT);
        }

        return null;
    }

    /**
     * Devolve a string de lifecycle "efetiva" do ADR: prioriza o campo cujo valor é
     * "morto" (PT ou EN), pra não perder o sinal quando status e lifecycle divergem
     * (ex. 0190: status=superseded + lifecycle=substituido — qualquer um já é morto;
     *  mas há casos status=aceito + lifecycle=substituido). Caso nenhum seja morto,
     * devolve o lifecycle (ou status) cru pra metadata.
     *
     * @param array<string, mixed> $fm
     */
    private function lifecycleFromFrontmatter(array $fm): ?string
    {
        $lifecycle = is_string($fm['lifecycle'] ?? null) ? trim($fm['lifecycle']) : null;
        $status = is_string($fm['status'] ?? null) ? trim($fm['status']) : null;

        // Se qualquer um é "morto", devolve esse (o sinal que importa pro checker).
        if ($lifecycle !== null && $this->isDeadLifecycle($lifecycle)) {
            return $lifecycle;
        }
        if ($status !== null && $this->isDeadLifecycle($status)) {
            return $status;
        }

        return $lifecycle ?? $status;
    }

    /**
     * Parse da lookup-table do _INDEX-LIFECYCLE.md. Linhas no formato
     * `| NNNN | S | ... |` (ou `| NNNN | D | ... |`) onde S=superseded, D=deprecated.
     * Ignora A/AH (vigentes). Tolera espaços e o ID com/sem zero-pad.
     *
     * @return array<string, string>
     */
    private function parseLifecycleIndex(string $content): array
    {
        $out = [];
        foreach (explode("\n", $content) as $line) {
            // | 0190 | S | 0235 | nota   — primeira célula = número, segunda = estado
            if (! preg_match('/^\s*\|\s*(\d{3,4})\s*\|\s*([A-Za-z]+)\s*\|/', $line, $m)) {
                continue;
            }
            $key = str_pad($m[1], 4, '0', STR_PAD_LEFT);
            $estado = strtoupper($m[2]);
            if ($estado === 'S') {
                $out[$key] = 'superseded';
            } elseif ($estado === 'D') {
                $out[$key] = 'deprecated';
            }
        }

        return $out;
    }

    /**
     * Coleta os docs de design varridos pelos globs, ordenados (determinismo).
     *
     * @return array<int, string>
     */
    private function collectDesignDocs(string $base): array
    {
        $files = [];
        foreach (self::DESIGN_DOC_GLOBS as $glob) {
            foreach (glob("{$base}/{$glob}") ?: [] as $file) {
                if (is_file($file)) {
                    $files[$file] = true;
                }
            }
        }
        $out = array_keys($files);
        sort($out); // determinístico: mesma ordem em toda execução

        return $out;
    }

    /**
     * Parse minimalista de frontmatter YAML — mesma abordagem de AdrLinksChecker
     * (suficiente pra status/lifecycle/next_review escalares + listas inline).
     *
     * @return array<string, mixed>
     */
    private function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\R(.*?)\R---/s', $content, $m)) {
            return [];
        }
        $out = [];
        $currentKey = null;
        foreach (explode("\n", $m[1]) as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            if (preg_match('/^([a-z_]+):\s*(.*)$/i', $line, $m2)) {
                $key = strtolower($m2[1]);
                // Remove comentário inline (ex. "2026-08-09  # trimestral")
                $val = trim(preg_replace('/\s+#.*$/', '', $m2[2]) ?? $m2[2]);
                if ($val === '' || $val === '[]') {
                    $out[$key] = [];
                    $currentKey = $key;

                    continue;
                }
                if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                    $inner = trim(substr($val, 1, -1));
                    $out[$key] = $inner === '' ? [] : array_map('trim', explode(',', $inner));
                } else {
                    $out[$key] = trim($val, '"\'');
                }
                $currentKey = $key;
            } elseif ($currentKey !== null && preg_match('/^\s+-\s+(.+)$/', $line, $m3)) {
                if (! is_array($out[$currentKey] ?? null)) {
                    $out[$currentKey] = [];
                }
                $out[$currentKey][] = trim($m3[1], '"\'');
            }
        }

        return $out;
    }

    /**
     * "Hoje" injetável e robusto: aceita DateTimeImmutable, string YYYY-MM-DD, ou null
     * (default = now()). Normaliza pra meia-noite pra comparação date-only estável.
     */
    private function resolveHoje(mixed $now): DateTimeImmutable
    {
        if ($now instanceof DateTimeImmutable) {
            return $now->setTime(0, 0);
        }
        if (is_string($now) && $now !== '') {
            $parsed = $this->parseDate($now);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return (new DateTimeImmutable('today'))->setTime(0, 0);
    }

    /**
     * Parse de data tolerante a "2026-08-09", "2026-08-09T00:00", "2026/08/09".
     * Normaliza pra meia-noite (comparação date-only). Null se ininteligível.
     */
    private function parseDate(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw, "\"' \t");
        if (preg_match('/(\d{4})[-\/](\d{2})[-\/](\d{2})/', $raw, $m)) {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', "{$m[1]}-{$m[2]}-{$m[3]}");

            return $dt instanceof DateTimeImmutable ? $dt : null;
        }

        return null;
    }

    /**
     * @param array<int, DriftFinding> $findings
     */
    private function countByCategory(array $findings, string $category): int
    {
        $count = 0;
        foreach ($findings as $f) {
            if (($f->evidence['category'] ?? null) === $category) {
                $count++;
            }
        }

        return $count;
    }
}
