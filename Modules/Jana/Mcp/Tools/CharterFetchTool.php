<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * GAP-ANALYSIS-91-100-2026-05-13 (C1 P0 Onda 4) — Ativa Page Charters S4.
 *
 * 26 charters .charter.md viviam ao lado dos .tsx mas sem tool MCP de leitura
 * — eram peso-morto. Esta tool entrega o "contrato vivo" da Constituição V2
 * princípio #3 (Charter > Spec, [ADR 0094](memory/decisions/0094...) + ADR 0101).
 *
 * Resolve `page_id` (path .tsx ou slug curto) → `<path>.charter.md` ao lado.
 * Lê frontmatter (status/page/owner/tier/related_adrs) + body markdown, e
 * retorna seções canônicas (Mission, Goals, Non-Goals, UX targets, Automation
 * hooks/Anti-hooks). Se `status: draft|rascunho`, anexa WARNING no header.
 *
 * Multi-tenant: charters são repo-wide (sem business_id) — convenção
 * consistente com handoffs, decisions, weekly-digest.
 *
 * Skill `charter-first` (Tier A) consome essa tool ANTES de qualquer Edit/Write
 * em Pages/<Mod>/<Tela>.tsx que tenha .charter.md irmão.
 */
class CharterFetchTool extends Tool
{
    protected string $name = 'charter-fetch';

    protected string $title = 'Carrega Page Charter (contrato vivo da tela)';

    protected string $description = 'Lê .charter.md ao lado de uma Page Inertia (ex resources/js/Pages/Sells/Index.tsx → Index.charter.md). Retorna Mission/Goals/Non-Goals/UX targets/Automation hooks/Anti-hooks + frontmatter (status, owner, related_adrs). Sempre chame ANTES de Edit/Write em .tsx que tenha charter irmão (skill charter-first Tier A — princípio Constituição V2 #3).';

    /**
     * Seções canônicas que o output highlight tenta extrair.
     * Sinônimos cobrem variação PT/EN observada nos 26 charters em prod.
     *
     * @var array<string, array<int, string>>
     */
    protected array $sectionAliases = [
        'Mission' => ['Mission', 'Missão', 'Missao'],
        'Goals' => ['Goals', 'Goals — Features (faz)', 'Goals — objetivos de produto mensuráveis', 'Objetivos'],
        'Non-Goals' => ['Non-Goals', 'Non-Goals — Features (NÃO faz)', 'Non-Goals — o que **NÃO** é responsabilidade do módulo', 'Não-objetivos'],
        'UX targets' => ['UX targets', 'UX Targets', 'UX targets (estado-da-arte, calibragem Cockpit V2)'],
        'Automation hooks' => ['Automation hooks', 'Automation Hooks', 'Automation hooks (faz)'],
        'Anti-hooks' => ['Anti-hooks', 'UX Anti-patterns', 'Anti-hooks (NÃO faz automaticamente)', 'Automation Anti-hooks'],
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->string()
                ->required()
                ->description('Path do .tsx (`resources/js/Pages/Sells/Index.tsx`), do `.charter.md` direto, ou rota canônica (`/sells`, `/admin`, `/repair/job-sheet`). Resolve case-insensitive.'),
            'format' => $schema->string()
                ->enum(['markdown', 'json'])
                ->default('markdown')
                ->description('`markdown` (default — body completo formatado pra LLM) ou `json` (frontmatter parsed + seções extraídas estruturadas).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $pageId = trim((string) $request->get('page_id', ''));
        $format = (string) $request->get('format', 'markdown');

        if ($pageId === '') {
            return Response::text("[charter-fetch] ❌ Parâmetro `page_id` é obrigatório. Ex: `page_id:resources/js/Pages/Sells/Index.tsx` ou `page_id:/sells`.");
        }

        $charterPath = $this->resolveCharterPath($pageId);

        if ($charterPath === null) {
            return Response::text($this->charter404($pageId));
        }

        $raw = @file_get_contents($charterPath);
        if ($raw === false) {
            return Response::text("[charter-fetch] ❌ Erro lendo charter `{$charterPath}`. Filesystem indisponível ou permissão negada.");
        }

        [$frontmatter, $body] = $this->parseFrontmatter($raw);
        $sections = $this->extractSections($body);
        $status = strtolower((string) ($frontmatter['status'] ?? 'unknown'));
        $relativePath = $this->relativeFromBase($charterPath);

        if ($format === 'json') {
            return Response::text($this->renderJson($relativePath, $frontmatter, $sections, $status));
        }

        return Response::text($this->renderMarkdown($pageId, $relativePath, $frontmatter, $sections, $status, $body));
    }

    /**
     * Resolve `page_id` (path .tsx, path .charter.md, ou rota /xxx) → caminho absoluto do .charter.md.
     */
    protected function resolveCharterPath(string $pageId): ?string
    {
        $base = base_path();

        // Caso 1 — já é path .charter.md direto
        if (str_ends_with($pageId, '.charter.md')) {
            $abs = $this->absolutePath($pageId);

            return is_file($abs) ? $abs : null;
        }

        // Caso 2 — path .tsx → trocar extensão por .charter.md
        if (str_ends_with($pageId, '.tsx')) {
            $charter = substr($pageId, 0, -4) . '.charter.md';
            $abs = $this->absolutePath($charter);

            return is_file($abs) ? $abs : null;
        }

        // Caso 3 — rota canônica `/sells`, `/repair/job-sheet`, `/admin` → buscar charter com `page: <rota>` no frontmatter
        if (str_starts_with($pageId, '/')) {
            $found = $this->findCharterByRoute($pageId, $base);
            if ($found !== null) {
                return $found;
            }
        }

        // Caso 4 — heurística: tenta `resources/js/Pages/{pageId}/Index.charter.md` ou
        // `resources/js/Pages/{pageId}.charter.md` (PascalCase módulo)
        $candidates = [
            "resources/js/Pages/{$pageId}/Index.charter.md",
            "resources/js/Pages/{$pageId}.charter.md",
        ];
        foreach ($candidates as $rel) {
            $abs = $this->absolutePath($rel);
            if (is_file($abs)) {
                return $abs;
            }
        }

        return null;
    }

    /**
     * Busca `*.charter.md` cujo frontmatter `page:` bate com a rota dada.
     */
    protected function findCharterByRoute(string $route, string $base): ?string
    {
        $needleRoute = rtrim($route, '/');

        $patterns = [
            $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Pages',
        ];
        $found = null;

        foreach ($patterns as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = $this->globRecursiveCharters($dir);
            foreach ($files as $file) {
                $head = $this->readHead($file, 2048);
                if ($head === null) {
                    continue;
                }
                if (preg_match('/^page:\s*(\S+)/m', $head, $m)) {
                    $page = rtrim(trim($m[1], "\"' \t"), '/');
                    if (strcasecmp($page, $needleRoute) === 0) {
                        $found = $file;
                        break 2;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Lista recursiva de *.charter.md num diretório (sem usar `**` glob — compatível Windows).
     *
     * @return array<int, string>
     */
    protected function globRecursiveCharters(string $dir): array
    {
        $out = [];
        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iter as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.charter.md')) {
                    $out[] = $file->getPathname();
                }
            }
        } catch (\Throwable $e) {
            // diretório inacessível — silencia
        }

        return $out;
    }

    /**
     * Resolve path relativo → absoluto sob base_path().
     */
    protected function absolutePath(string $rel): string
    {
        $rel = ltrim($rel, '/\\');

        return base_path($rel);
    }

    /**
     * Retorna path relativo ao base_path() (forward slashes, p/ display).
     */
    protected function relativeFromBase(string $abs): string
    {
        $base = rtrim(base_path(), '/\\');
        $abs = str_replace('\\', '/', $abs);
        $base = str_replace('\\', '/', $base);
        if (str_starts_with($abs, $base)) {
            return ltrim(substr($abs, strlen($base)), '/');
        }

        return $abs;
    }

    /**
     * Lê primeiros N bytes (otimização — não carrega o body inteiro só pra checar frontmatter).
     */
    protected function readHead(string $path, int $bytes): ?string
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }
        $head = @fread($fp, $bytes);
        @fclose($fp);

        return $head === false ? null : $head;
    }

    /**
     * Parser de YAML frontmatter MINIMALISTA — só `key: value` flat + listas inline.
     *
     * Não usa symfony/yaml pra evitar adicionar dep transitiva e não-determinismo
     * em testes. Suficiente pros campos canon (page, status, owner, tier, related_adrs).
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function parseFrontmatter(string $raw): array
    {
        $raw = ltrim($raw, "\xef\xbb\xbf"); // BOM UTF-8
        if (! str_starts_with($raw, '---')) {
            return [[], $raw];
        }
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        if ($lines === false || ! isset($lines[0]) || trim($lines[0]) !== '---') {
            return [[], $raw];
        }

        $front = [];
        $endIdx = -1;
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '---') {
                $endIdx = $i;
                break;
            }
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_-]*):\s*(.*)$/', $lines[$i], $m)) {
                $key = $m[1];
                $val = trim($m[2]);
                // lista inline `[a, b, c]`
                if (preg_match('/^\[(.*)\]$/', $val, $lm)) {
                    $items = array_map('trim', explode(',', $lm[1]));
                    $items = array_filter($items, fn ($x) => $x !== '');
                    $front[$key] = array_values($items);
                } elseif ($val === '') {
                    // valor multilinha ignorado (raro em charters do oimpresso)
                    $front[$key] = '';
                } else {
                    $front[$key] = trim($val, "\"'");
                }
            }
        }

        $body = $endIdx > 0
            ? implode("\n", array_slice($lines, $endIdx + 1))
            : $raw;

        return [$front, $body];
    }

    /**
     * Extrai seções canon do body. Retorna map `Mission` → texto (string vazia se ausente).
     *
     * @return array<string, string>
     */
    protected function extractSections(string $body): array
    {
        $result = [];
        // Quebra o body em blocos `## <heading>` (level 2 markdown)
        // Pattern: captura heading + conteúdo até próximo `## ` ou fim
        $regex = '/^##\s+(?<heading>[^\n]+)\n(?<content>.*?)(?=^##\s+|\z)/sm';
        preg_match_all($regex, $body . "\n", $matches, PREG_SET_ORDER);

        foreach ($this->sectionAliases as $canonical => $aliases) {
            $found = '';
            foreach ($matches as $m) {
                $h = trim($m['heading']);
                foreach ($aliases as $alias) {
                    if (strcasecmp($h, $alias) === 0) {
                        $found = trim($m['content']);
                        break 2;
                    }
                }
            }
            $result[$canonical] = $found;
        }

        return $result;
    }

    /**
     * Output markdown — formato principal pra Claude consumir.
     */
    protected function renderMarkdown(
        string $pageId,
        string $relativePath,
        array $frontmatter,
        array $sections,
        string $status,
        string $body
    ): string {
        $title = $frontmatter['page'] ?? $pageId;
        $warning = '';
        if (in_array($status, ['draft', 'rascunho', 'proposto'], true)) {
            $warning = "\n> ⚠️ **CHARTER STATUS: {$status}** — contrato AINDA NÃO aprovado por Wagner. Não vincula completamente; use com julgamento. Para virar `status: live`, Wagner precisa revisar Non-Goals + Anti-hooks (parte sensível anti-alucinação).\n";
        } elseif ($status === 'unknown' || $status === '') {
            $warning = "\n> ⚠️ **CHARTER SEM STATUS** declarado no frontmatter — trate como `draft`.\n";
        }

        $owner = $frontmatter['owner'] ?? '—';
        $tier = $frontmatter['tier'] ?? '—';
        $lastValidated = $frontmatter['last_validated'] ?? $frontmatter['last_review'] ?? '—';
        $relatedAdrs = $frontmatter['related_adrs'] ?? [];
        $adrsStr = is_array($relatedAdrs) ? implode(', ', $relatedAdrs) : (string) $relatedAdrs;

        $out = "# Charter: {$title} (status: {$status})\n";
        $out .= "{$warning}\n";
        $out .= "**Arquivo:** `{$relativePath}`\n";
        $out .= "**Owner:** {$owner} · **Tier:** {$tier} · **Last validated:** {$lastValidated}";
        if ($adrsStr !== '') {
            $out .= " · **Related ADRs:** {$adrsStr}";
        }
        $out .= "\n\n---\n\n";

        // Seções canônicas em ordem
        foreach (array_keys($this->sectionAliases) as $section) {
            $content = $sections[$section] ?? '';
            $out .= "## {$section}\n\n";
            if (trim($content) === '') {
                $out .= "_(seção ausente ou vazia neste charter)_\n\n";
            } else {
                $out .= rtrim($content) . "\n\n";
            }
        }

        $out .= "---\n\n";
        $out .= "**Lembrete (skill charter-first Tier A):** Mission + Goals definem o que FAZER. Non-Goals + Anti-hooks definem o que NUNCA fazer (anti-alucinação). Edits em `.tsx` que violem qualquer um devem ter justificativa explícita no PR ou virar nova versão do charter (charter_version bump).";

        return $out;
    }

    /**
     * Output JSON — formato estruturado pra consumers programáticos (CI, charter:audit).
     */
    protected function renderJson(string $relativePath, array $frontmatter, array $sections, string $status): string
    {
        $payload = [
            'file' => $relativePath,
            'status' => $status,
            'frontmatter' => $frontmatter,
            'sections' => $sections,
            'warnings' => [],
        ];
        if (in_array($status, ['draft', 'rascunho', 'proposto'], true)) {
            $payload['warnings'][] = "Charter status `{$status}` — não aprovado por Wagner ainda.";
        }
        foreach (array_keys($this->sectionAliases) as $section) {
            if (trim($sections[$section] ?? '') === '') {
                $payload['warnings'][] = "Seção `{$section}` ausente ou vazia.";
            }
        }

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Mensagem 404 amigável quando charter não existe.
     */
    protected function charter404(string $pageId): string
    {
        $hint = '';
        if (str_ends_with($pageId, '.tsx')) {
            $expected = substr($pageId, 0, -4) . '.charter.md';
            $hint = "\nEsperado: `{$expected}` (ao lado do .tsx).";
        } elseif (str_starts_with($pageId, '/')) {
            $hint = "\nNenhum charter com `page: {$pageId}` no frontmatter encontrado em `resources/js/Pages/`.";
        }

        return "[charter-fetch] ❌ Charter não encontrado para `page_id: {$pageId}`.{$hint}\n\n"
            . "Se essa tela ainda NÃO tem contrato, rode a skill `/charter-write {$pageId}` pra gerar draft (Wagner aprova Non-Goals + Anti-hooks antes de virar live).\n\n"
            . 'Lista de charters disponíveis: rode `Glob pattern:**/*.charter.md` ou veja [memory/requisitos/_DesignSystem/RUNBOOK-charters-s4-ativacao.md](../../memory/requisitos/_DesignSystem/RUNBOOK-charters-s4-ativacao.md).';
    }
}
