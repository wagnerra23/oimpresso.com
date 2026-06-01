<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

/**
 * Charter health checks (ADVISORY) — extensão do `jana:health-check` (PROTOCOL §6).
 *
 * Origem: COWORK_NOTES item 2 / handoff design 2026-05-31 — "quem cobra a falta
 * dos arquivos? de quem é a função de saber se falta ou tem que atualizar?".
 * Princípio (AUTOMAÇÃO §2): se [W] cobra, faltou gate. A função "tem/falta/velho"
 * é de check automático ([CL] constrói; tooling = Tier 0, [W] autoriza). O conteúdo
 * do charter é do [CC]; o gate só checa existência + frescor + integridade de refs.
 *
 * Lógica isolada do Command (SoC — Constituição v2 §5) pra ser testável sem DB:
 * recebe `basePath` no construtor (default = base_path()), lê só filesystem.
 *
 * Todos os checks são ADVISORY (`advisory => true`): reportam mas NÃO falham o
 * exit code do comando nem disparam o ALERT de cron. Viram gate-ratchet depois
 * que o baseline de charters existir (mesmo modelo do `ds/*`).
 *
 * Checks:
 *   - charter_missing          — página .tsx (rota) sem `.charter.md` ao lado
 *   - charter_stale            — `last_validated` > 90 dias
 *   - charter_refs_broken      — refs (component/runbook/parent_capterra + links) inexistentes
 *   - charter_method_missing   — charter tier A sem referência a método/bench (leve)
 *   - readme_handoff_block_missing — prototipo-ui/README.md sem `<!-- HANDOFF-ENTRY -->` (L-18)
 *   - design_return_skipped    — HANDOFF.md atrás do último merge no SYNC_LOG (retorno §10.2 pulou o canal HANDOFF · G4 / COWORK_NOTES #1)
 */
class CharterHealthChecker
{
    /** Dias sem revalidar até virar "stale". */
    private const STALE_DAYS = 90;

    public function __construct(private readonly string $basePath) {}

    public static function fromApp(): self
    {
        return new self(base_path());
    }

    /**
     * @return list<array{name:string,ok:bool,value:mixed,threshold:mixed,message:string,advisory:bool}>
     */
    public function checks(): array
    {
        $charters = $this->charterFiles();

        return [
            $this->charterMissing(),
            $this->charterStale($charters),
            $this->charterRefsBroken($charters),
            $this->charterMethodMissing($charters),
            $this->readmeHandoffBlockMissing(),
            $this->designReturnSkipped(),
        ];
    }

    // ── Checks ────────────────────────────────────────────────────────────

    private function charterMissing(): array
    {
        $missing = [];
        foreach ($this->pageTsxFiles() as $tsx) {
            $charter = substr($tsx, 0, -strlen('.tsx')) . '.charter.md';
            if (! is_file($this->pagesDir() . '/' . $charter)) {
                $missing[] = $tsx;
            }
        }

        $n = count($missing);

        return $this->row(
            'charter_missing',
            $n === 0,
            $n,
            $n === 0
                ? 'Toda página .tsx tem .charter.md ao lado'
                : "{$n} página(s) .tsx sem charter (baseline advisory): " . $this->sample($missing),
        );
    }

    /**
     * @param  list<string>  $charters
     *
     * NOTA: detecção adicional "tela .tsx tocada (git commit) DEPOIS do last_validated"
     * fica como evolução — exige git por-arquivo (flaky em CI/clone raso). A regra
     * dos 90 dias é o piso robusto e determinístico.
     */
    private function charterStale(array $charters): array
    {
        $stale = [];
        $cutoff = time() - self::STALE_DAYS * 86400;

        foreach ($charters as $rel) {
            $lv = $this->frontmatter($this->pagesDir() . '/' . $rel)['last_validated'] ?? null;
            if ($lv === null || $lv === '') {
                continue;
            }
            $ts = strtotime($lv);
            if ($ts !== false && $ts < $cutoff) {
                $days = (int) floor((time() - $ts) / 86400);
                $stale[] = "{$rel} ({$days}d)";
            }
        }

        $n = count($stale);

        return $this->row(
            'charter_stale',
            $n === 0,
            $n,
            $n === 0
                ? 'Nenhum charter com last_validated > ' . self::STALE_DAYS . 'd'
                : "{$n} charter(s) > " . self::STALE_DAYS . 'd sem revalidar: ' . $this->sample($stale),
            '<= ' . self::STALE_DAYS . 'd',
        );
    }

    /**
     * @param  list<string>  $charters
     */
    private function charterRefsBroken(array $charters): array
    {
        $broken = [];

        foreach ($charters as $rel) {
            $abs = $this->pagesDir() . '/' . $rel;
            [$fm, $body] = $this->splitFrontmatter((string) @file_get_contents($abs));

            // Refs estruturados do frontmatter (repo-relative ao root).
            foreach (['component', 'runbook', 'parent_capterra'] as $key) {
                if (preg_match('/^' . $key . ':\s*["\']?(\S+?)["\']?\s*$/m', $fm, $m)) {
                    $p = $m[1];
                    if ($this->isRepoRelativePath($p) && ! is_file($this->basePath . '/' . ltrim($p, '/'))) {
                        $broken[] = "{$rel} → {$key}: {$p}";
                    }
                }
            }

            // Links markdown relativos (`](../x)` / `](./x)`) no corpo.
            if (preg_match_all('/\]\((\.\.?\/[^)\s]+)\)/', $body, $mm)) {
                foreach (array_unique($mm[1]) as $link) {
                    $clean = (string) preg_replace('/[#:].*$/', '', $link); // tira #anchor e :linha
                    if ($clean === '') {
                        continue;
                    }
                    $target = $this->resolveRelative(dirname($abs), $clean);
                    if ($target !== null && ! file_exists($target)) {
                        $broken[] = "{$rel} → {$clean}";
                    }
                }
            }
        }

        $n = count($broken);

        return $this->row(
            'charter_refs_broken',
            $n === 0,
            $n,
            $n === 0
                ? 'Todas as refs citadas nos charters existem'
                : "{$n} ref(s) quebrada(s): " . $this->sample($broken),
        );
    }

    /**
     * @param  list<string>  $charters
     */
    private function charterMethodMissing(array $charters): array
    {
        $missing = [];

        foreach ($charters as $rel) {
            $abs = $this->pagesDir() . '/' . $rel;
            if (($this->frontmatter($abs)['tier'] ?? '') !== 'A') {
                continue;
            }
            if (! preg_match('/m[eé]todo|bench|benchmark|golden/iu', (string) @file_get_contents($abs))) {
                $missing[] = $rel;
            }
        }

        $n = count($missing);

        return $this->row(
            'charter_method_missing',
            $n === 0,
            $n,
            $n === 0
                ? 'Todo charter tier A referencia método/bench'
                : "{$n} charter(s) tier A sem ref a método/bench: " . $this->sample($missing),
        );
    }

    private function readmeHandoffBlockMissing(): array
    {
        $readme = $this->basePath . '/prototipo-ui/README.md';

        if (! is_file($readme)) {
            return $this->row(
                'readme_handoff_block_missing',
                true,
                'n/a',
                'prototipo-ui/README.md ausente — handoff entry n/a',
                'present',
            );
        }

        $has = str_contains((string) file_get_contents($readme), '<!-- HANDOFF-ENTRY -->');

        return $this->row(
            'readme_handoff_block_missing',
            $has,
            $has ? 'present' : 'MISSING',
            $has
                ? 'Bloco <!-- HANDOFF-ENTRY --> presente no README do Handoff'
                : 'ALERTA: prototipo-ui/README.md sem <!-- HANDOFF-ENTRY --> (L-18) — Handoff entrega mas Code não acha a fila',
            'present',
        );
    }

    /**
     * design_return_skipped — o retorno §10.2 ([CL]→[CC]) pulou o canal HANDOFF.
     *
     * Sinal filesystem-only e determinístico (sem git por-arquivo, flaky em CI/clone
     * raso — mesma escolha do charter_stale): o HANDOFF.md ("sobrescreve" a cada
     * retorno) deve refletir data ≥ a do último merge logado no SYNC_LOG.md ("append").
     * Se HANDOFF < último SYNC_LOG, o canal HANDOFF ficou pra trás → retorno parcial
     * (origem do incidente "HANDOFF 15d stale", PROTOCOL §10). Gêmeo do workflow
     * design-return-gate.yml (pós-merge, pega o skip TOTAL via git diff do merge).
     */
    private function designReturnSkipped(): array
    {
        $handoff = $this->basePath . '/prototipo-ui/HANDOFF.md';
        $syncLog = $this->basePath . '/prototipo-ui/SYNC_LOG.md';

        if (! is_file($handoff) || ! is_file($syncLog)) {
            return $this->row('design_return_skipped', true, 'n/a',
                'Canais §10.2 (HANDOFF/SYNC_LOG) ausentes — n/a', 'sincronizado');
        }

        $syncDate = $this->maxLineLeadingIsoDate((string) @file_get_contents($syncLog));
        preg_match('/Estado atual:\s*(\d{4}-\d{2}-\d{2})/u',
            (string) @file_get_contents($handoff), $hm);
        $handoffDate = $hm[1] ?? null;

        if ($syncDate === null || $handoffDate === null) {
            return $this->row('design_return_skipped', true, 'n/a',
                'Sem data ISO parseável em HANDOFF/SYNC_LOG — n/a', 'sincronizado');
        }

        // ISO YYYY-MM-DD: comparação lexicográfica == cronológica.
        $skipped = $handoffDate < $syncDate;

        return $this->row(
            'design_return_skipped',
            ! $skipped,
            $skipped ? "HANDOFF {$handoffDate} < SYNC_LOG {$syncDate}" : 'sincronizado',
            $skipped
                ? "ALERTA §10.2: HANDOFF.md ({$handoffDate}) atrás do último SYNC_LOG ({$syncDate}) — retorno [CL]→[CC] pulou o canal HANDOFF"
                : "HANDOFF.md ({$handoffDate}) em dia com o SYNC_LOG ({$syncDate})",
            'sincronizado',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Maior data ISO (YYYY-MM-DD) em início de linha; null se nenhuma. */
    private function maxLineLeadingIsoDate(string $text): ?string
    {
        if (! preg_match_all('/^(\d{4}-\d{2}-\d{2})\b/m', $text, $m)) {
            return null;
        }
        $dates = $m[1];
        sort($dates);

        return end($dates) ?: null;
    }

    private function pagesDir(): string
    {
        return $this->basePath . '/resources/js/Pages';
    }

    /** Páginas .tsx (PascalCase, fora de dirs `_*`, sem `.test.tsx`). */
    private function pageTsxFiles(): array
    {
        return $this->scan(fn (string $rel) => str_ends_with($rel, '.tsx')
            && ! str_ends_with($rel, '.test.tsx')
            && ! $this->hasUnderscoreSegment($rel)
            && $this->basenameStartsUpper($rel));
    }

    /** @return list<string> */
    private function charterFiles(): array
    {
        return $this->scan(fn (string $rel) => str_ends_with($rel, '.charter.md')
            && ! $this->hasUnderscoreSegment($rel));
    }

    /**
     * @param  callable(string):bool  $accept
     * @return list<string>  caminhos relativos a pagesDir(), separador "/"
     */
    private function scan(callable $accept): array
    {
        $dir = $this->pagesDir();
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            if ($accept($rel)) {
                $out[] = $rel;
            }
        }

        sort($out);

        return $out;
    }

    private function hasUnderscoreSegment(string $rel): bool
    {
        foreach (explode('/', $rel) as $seg) {
            if ($seg !== '' && $seg[0] === '_') {
                return true;
            }
        }

        return false;
    }

    private function basenameStartsUpper(string $rel): bool
    {
        $base = basename($rel);

        return $base !== '' && $base[0] >= 'A' && $base[0] <= 'Z';
    }

    /**
     * @return array{0:string,1:string}  [frontmatter, body]
     */
    private function splitFrontmatter(string $content): array
    {
        if (preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $content, $m)) {
            return [$m[1], $m[2]];
        }

        return ['', $content];
    }

    /** @return array<string,string> scalars do frontmatter (sem aspas). */
    private function frontmatter(string $path): array
    {
        [$fm] = $this->splitFrontmatter((string) @file_get_contents($path));
        $out = [];
        foreach (preg_split('/\R/', $fm) ?: [] as $line) {
            if (preg_match('/^([a-zA-Z_][\w-]*):\s*(.*)$/', $line, $m)) {
                $out[$m[1]] = trim(trim($m[2]), '"\'');
            }
        }

        return $out;
    }

    private function isRepoRelativePath(string $p): bool
    {
        return ! preg_match('#^https?://#', $p) && (bool) preg_match('#^[A-Za-z0-9._-]+/#', $p);
    }

    /** Resolve `../` manualmente (realpath falha quando o alvo não existe — é o que detectamos). */
    private function resolveRelative(string $dir, string $rel): ?string
    {
        if ($rel === '' || preg_match('#^https?://#', $rel)) {
            return null;
        }

        $path = str_replace('\\', '/', $dir . '/' . $rel);
        $isAbsRoot = str_starts_with($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $seg;
        }

        return ($isAbsRoot ? '/' : '') . implode('/', $parts);
    }

    /**
     * @return array{name:string,ok:bool,value:mixed,threshold:mixed,message:string,advisory:bool}
     */
    private function row(string $name, bool $ok, mixed $value, string $message, mixed $threshold = 0): array
    {
        return [
            'name' => $name,
            'ok' => $ok,
            'value' => $value,
            'threshold' => $threshold,
            'message' => $message,
            'advisory' => true,
        ];
    }

    /** @param list<string> $items */
    private function sample(array $items, int $max = 3): string
    {
        $head = array_slice($items, 0, $max);
        $more = count($items) - count($head);

        return implode(' · ', $head) . ($more > 0 ? " (+{$more})" : '');
    }
}
