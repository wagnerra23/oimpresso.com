<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Backlinks;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * AdrGraphBuilder — Constrói grafo bidirecional de relações entre ADRs.
 *
 * Gap G5 (P1) auditoria 2026-05-13: Obsidian/Roam têm backlinks automáticos
 * bidirecionais; oimpresso só tem `related_adrs:` manual unidirecional →
 * orfãs/quebradas/assimétricas passam despercebidas.
 *
 * Esta classe varre memory/decisions/*.md, extrai frontmatter YAML +
 * menções inline ("ADR 0XXX"), e devolve grafo + 4 tipos de detecção:
 *
 *   1. Orfãs       — ADR aceita sem NENHUM inbound link (perdida no tempo)
 *   2. Broken      — ref a ADR que não existe (ex: related_adrs:[9999])
 *   3. Assimétricas — X.supersedes=[Y] mas Y.superseded_by não cita X
 *   4. SPEC cross-refs — SPEC.md menciona "ADR 0XXX" mas ADR não cita o SPEC
 *
 * Frontmatter YAML aceita 6 chaves de relação (ver _SCHEMA.md):
 *   - related_adrs, related   (cross-ref semântico)
 *   - supersedes, supersedes_partially  (esta substitui aquelas)
 *   - superseded_by           (esta foi substituida por aquela)
 *   - cites                   (referências externas/citações)
 *
 * Cada valor pode ser:
 *   - inteiro (94)            — número da ADR
 *   - string '0094'           — número zero-padded
 *   - slug '0094-constituicao' — slug completo
 *   Normalizado pra int via extractNumber().
 */
class AdrGraphBuilder
{
    /**
     * Chaves YAML que representam relações entre ADRs.
     * Ordem importa pra reverse-map (supersedes ↔ superseded_by).
     *
     * @var array<string, string>
     */
    protected const RELATION_KEYS = [
        'related_adrs' => 'related',
        'related' => 'related',
        'supersedes' => 'supersedes',
        'supersedes_partially' => 'supersedes',
        'superseded_by' => 'superseded_by',
        'cites' => 'cites',
    ];

    /**
     * Reverse map pra detectar assimetria.
     *
     * @var array<string, string>
     */
    protected const REVERSE_MAP = [
        'supersedes' => 'superseded_by',
        'superseded_by' => 'supersedes',
    ];

    protected string $decisionsPath;

    protected string $requisitosPath;

    /**
     * Estado interno (preenchido por build()).
     */
    protected array $nodes = [];      // [number => ['number'=>, 'slug'=>, 'path'=>, 'title'=>, 'status'=>]]

    protected array $outbound = [];   // [number => ['supersedes'=>[N,M], 'related'=>[...], ...]]

    protected array $inbound = [];    // [number => [from_number, ...]]  — agregado de todos tipos

    protected array $inlineRefs = []; // [number => [referenced_number, ...]] — extraído do corpo (ADR 0XXX)

    public function __construct(?string $decisionsPath = null, ?string $requisitosPath = null)
    {
        $this->decisionsPath = $decisionsPath ?? base_path('memory/decisions');
        $this->requisitosPath = $requisitosPath ?? base_path('memory/requisitos');
    }

    /**
     * Constrói o grafo. Retorna self pra chain.
     */
    public function build(): self
    {
        // D9.a (Wave 18 SATURATION) — span construção grafo ADRs; admin op cross-tenant.
        return OtelHelper::span('jana.adr_graph.build', [], fn () => $this->buildInternal());
    }

    private function buildInternal(): self
    {
        $files = File::glob($this->decisionsPath . '/[0-9]*.md');

        foreach ($files as $path) {
            $this->parseFile($path);
        }

        // Agrega inbound a partir de outbound (todos tipos)
        foreach ($this->outbound as $from => $relations) {
            foreach ($relations as $type => $targets) {
                foreach ($targets as $to) {
                    $this->inbound[$to][] = $from;
                }
            }
        }

        // Inline refs também contam como inbound implícito
        foreach ($this->inlineRefs as $from => $targets) {
            foreach ($targets as $to) {
                $this->inbound[$to][] = $from;
            }
        }

        // Dedup inbound
        foreach ($this->inbound as $to => $froms) {
            $this->inbound[$to] = array_values(array_unique($froms));
        }

        return $this;
    }

    /**
     * Parse 1 arquivo ADR: extrai frontmatter + inline refs.
     */
    protected function parseFile(string $path): void
    {
        $basename = basename($path);

        if (! preg_match('/^(\d{4})-/', $basename, $m)) {
            return; // arquivo não-numerado (README, _SCHEMA, etc) — ignora
        }

        $number = (int) $m[1];
        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        $frontmatter = $this->extractFrontmatter($contents);
        $body = $this->stripFrontmatter($contents);

        // Pega título do frontmatter ou da primeira linha # do body
        $rawTitle = $frontmatter['title'] ?? $this->extractTitleFromBody($body) ?? "ADR $number";

        // Decodifica binary YAML (Symfony Yaml retorna binary decoded como string raw — pode ser binário)
        // Fallback: se não for UTF-8 válido, usa título do body ou genérico
        if (is_string($rawTitle) && ! mb_check_encoding($rawTitle, 'UTF-8')) {
            $fromBody = $this->extractTitleFromBody($body);
            $rawTitle = ($fromBody && mb_check_encoding($fromBody, 'UTF-8')) ? $fromBody : "ADR $number";
        }
        if (is_string($rawTitle) && str_starts_with($rawTitle, '!!binary')) {
            $fromBody = $this->extractTitleFromBody($body);
            $rawTitle = ($fromBody && mb_check_encoding($fromBody, 'UTF-8')) ? $fromBody : "ADR $number";
        }
        $title = is_string($rawTitle) ? trim($rawTitle) : "ADR $number";

        $this->nodes[$number] = [
            'number' => $number,
            'slug' => $this->sanitize($frontmatter['slug'] ?? $basename),
            'path' => $path,
            'title' => $this->sanitize($title),
            'status' => $this->sanitize($frontmatter['status'] ?? 'desconhecido'),
            'authority' => $this->sanitize($frontmatter['authority'] ?? 'desconhecida'),
            'lifecycle' => $this->sanitize($frontmatter['lifecycle'] ?? 'desconhecido'),
        ];

        $this->outbound[$number] = [
            'related' => [],
            'supersedes' => [],
            'superseded_by' => [],
            'cites' => [],
        ];

        // Extrai relações do frontmatter
        foreach (self::RELATION_KEYS as $key => $bucket) {
            if (! isset($frontmatter[$key])) {
                continue;
            }
            $raw = $frontmatter[$key];

            if (! is_array($raw)) {
                continue;
            }

            foreach ($raw as $ref) {
                $num = $this->extractNumber($ref);
                if ($num !== null && $num !== $number) {
                    $this->outbound[$number][$bucket][] = $num;
                }
            }
            $this->outbound[$number][$bucket] = array_values(array_unique($this->outbound[$number][$bucket]));
        }

        // Extrai menções inline do corpo: "ADR 0094", "ADR0094", "[ADR 0094]"
        $inline = [];
        if (preg_match_all('/ADR\s*0?(\d{2,4})/i', $body, $matches)) {
            foreach ($matches[1] as $found) {
                $n = (int) $found;
                if ($n !== $number) {
                    $inline[] = $n;
                }
            }
        }
        // Menções via link markdown [...](0XXX-slug.md)
        if (preg_match_all('/\((\d{4})-[a-z0-9\-]+\.md\)/i', $body, $matches)) {
            foreach ($matches[1] as $found) {
                $n = (int) $found;
                if ($n !== $number) {
                    $inline[] = $n;
                }
            }
        }
        $this->inlineRefs[$number] = array_values(array_unique($inline));
    }

    /**
     * Extrai frontmatter YAML. Retorna [] se ausente/inválido.
     *
     * @return array<string, mixed>
     */
    protected function extractFrontmatter(string $contents): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $contents, $m)) {
            return [];
        }

        try {
            $parsed = Yaml::parse($m[1], Yaml::PARSE_DATETIME);

            return is_array($parsed) ? $parsed : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function stripFrontmatter(string $contents): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $contents, 1) ?? $contents;
    }

    protected function extractTitleFromBody(string $body): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Garante string UTF-8 válida. Drop bytes inválidos com substitute.
     */
    protected function sanitize(mixed $value): string
    {
        if (! is_string($value)) {
            return is_scalar($value) ? (string) $value : '';
        }
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Tenta detectar e converter; senão usa substitute
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return is_string($converted) ? $converted : '';
    }

    /**
     * Normaliza ref pra número int.
     * Aceita 94, '0094', '0094-slug', 'ADR 0094'.
     */
    public function extractNumber(mixed $ref): ?int
    {
        if (is_int($ref)) {
            return $ref;
        }
        if (! is_string($ref)) {
            return null;
        }

        if (preg_match('/(\d{1,4})/', $ref, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    // ─── Detecções ──────────────────────────────────────────────────────────

    /**
     * ADRs accepted que NÃO aparecem em nenhum inbound link.
     * Sinal de "perdida no tempo" — possível knowledge debt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findOrphans(): array
    {
        $orphans = [];
        foreach ($this->nodes as $num => $node) {
            // só considera aceitas/ativas
            if (! in_array($node['status'], ['aceito', 'accepted'], true)) {
                continue;
            }
            if (in_array($node['lifecycle'], ['arquivado', 'substituido'], true)) {
                continue;
            }
            if (empty($this->inbound[$num] ?? [])) {
                $orphans[$num] = $node;
            }
        }

        return $orphans;
    }

    /**
     * Refs (frontmatter ou inline) apontando pra ADR inexistente.
     *
     * @return array<int, array{from:int, to:int, type:string, from_title:string}>
     */
    public function findBrokenLinks(): array
    {
        $broken = [];
        foreach ($this->outbound as $from => $rels) {
            foreach ($rels as $type => $targets) {
                foreach ($targets as $to) {
                    if (! isset($this->nodes[$to])) {
                        $broken[] = [
                            'from' => $from,
                            'to' => $to,
                            'type' => $type,
                            'from_title' => $this->nodes[$from]['title'] ?? "ADR $from",
                        ];
                    }
                }
            }
        }
        // Inline também
        foreach ($this->inlineRefs as $from => $targets) {
            foreach ($targets as $to) {
                if (! isset($this->nodes[$to])) {
                    $broken[] = [
                        'from' => $from,
                        'to' => $to,
                        'type' => 'inline',
                        'from_title' => $this->nodes[$from]['title'] ?? "ADR $from",
                    ];
                }
            }
        }

        return $broken;
    }

    /**
     * X.supersedes=[Y] mas Y.superseded_by NÃO cita X (ou vice-versa).
     *
     * @return array<int, array{from:int, to:int, type:string, expected_reverse:string, missing_in:int}>
     */
    public function findAsymmetric(): array
    {
        $asymmetric = [];
        foreach ($this->outbound as $from => $rels) {
            foreach (self::REVERSE_MAP as $type => $reverse) {
                foreach ($rels[$type] ?? [] as $to) {
                    if (! isset($this->nodes[$to])) {
                        continue; // broken já cobre
                    }
                    $reverseTargets = $this->outbound[$to][$reverse] ?? [];
                    if (! in_array($from, $reverseTargets, true)) {
                        $asymmetric[] = [
                            'from' => $from,
                            'to' => $to,
                            'type' => $type,
                            'expected_reverse' => $reverse,
                            'missing_in' => $to,
                        ];
                    }
                }
            }
        }

        return $asymmetric;
    }

    /**
     * SPEC.md menciona "ADR 0XXX" mas ADR não cita o SPEC.
     * Heurística leve — só lista pares possíveis pra revisão humana.
     *
     * @return array<int, array{spec:string, adr:int, adr_title:string}>
     */
    public function findSpecCrossRefs(): array
    {
        $crossRefs = [];

        if (! is_dir($this->requisitosPath)) {
            return [];
        }

        $specs = File::glob($this->requisitosPath . '/*/SPEC.md');

        foreach ($specs as $specPath) {
            $contents = @file_get_contents($specPath);
            if (! $contents) {
                continue;
            }

            $module = basename(dirname($specPath));
            if (preg_match_all('/ADR\s*0?(\d{2,4})/i', $contents, $matches)) {
                $mentioned = array_values(array_unique(array_map('intval', $matches[1])));
                foreach ($mentioned as $adrNum) {
                    if (! isset($this->nodes[$adrNum])) {
                        continue;
                    }
                    $adrContents = @file_get_contents($this->nodes[$adrNum]['path']);
                    if (! $adrContents) {
                        continue;
                    }
                    // ADR cita o SPEC do módulo?
                    if (! str_contains($adrContents, "requisitos/{$module}") &&
                        ! str_contains(strtolower($adrContents), strtolower($module))) {
                        $crossRefs[] = [
                            'spec' => "{$module}/SPEC.md",
                            'adr' => $adrNum,
                            'adr_title' => $this->nodes[$adrNum]['title'],
                        ];
                    }
                }
            }
        }

        return $crossRefs;
    }

    /**
     * Top N ADRs mais "centrais" (mais inbound links).
     *
     * @return array<int, array{number:int, title:string, inbound_count:int}>
     */
    public function topCentral(int $n = 5): array
    {
        $ranked = [];
        foreach ($this->inbound as $num => $froms) {
            if (! isset($this->nodes[$num])) {
                continue;
            }
            $ranked[] = [
                'number' => $num,
                'title' => $this->nodes[$num]['title'],
                'inbound_count' => count($froms),
            ];
        }
        usort($ranked, fn ($a, $b) => $b['inbound_count'] <=> $a['inbound_count']);

        return array_slice($ranked, 0, $n);
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function outbound(): array
    {
        return $this->outbound;
    }

    public function inbound(): array
    {
        return $this->inbound;
    }

    public function inlineRefs(): array
    {
        return $this->inlineRefs;
    }

    /**
     * Snapshot serializável (pra JSON).
     */
    public function toArray(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'total_adrs' => count($this->nodes),
            'nodes' => $this->nodes,
            'outbound' => $this->outbound,
            'inbound' => $this->inbound,
            'inline_refs' => $this->inlineRefs,
            'stats' => [
                'orphans' => count($this->findOrphans()),
                'broken' => count($this->findBrokenLinks()),
                'asymmetric' => count($this->findAsymmetric()),
            ],
        ];
    }
}
