<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria;

/**
 * PR-1 da estação de ingestão de design (plano vectorized-badger · pós-adversário).
 *
 * MONTA o "dossiê de tela" — uma READ-VIEW efêmera que reúne a memória de decisões
 * que JÁ existe curada (charter+casos+decisoes+RUNBOOK+visual-comparison+persona+
 * feedback) num só doc, com proveniência. É o contexto que o handoff padrão NÃO
 * carrega e a IA precisa ANTES de aplicar um zip de design.
 *
 * NÃO é canon, NÃO compete com o charter (a lei segue sendo o charter — ADR 0270 D-2):
 * o dossiê só LÊ e CONCATENA o curado, não re-destila com LLM (o adversário matou isso).
 *
 * DETERMINÍSTICO por contrato: dado o mesmo conteúdo das fontes, devolve o mesmo
 * markdown — SEM timestamp no corpo (re-run idêntico = a âncora do PR-1). Função PURA
 * (não toca FS); o comando faz a resolução de paths e injeta os conteúdos.
 */
final class DesignDossieAssembler
{
    /** Limite de linhas por excerto de fonte (mantém o dossiê em ~1-2 páginas). */
    public const EXCERPT_LINES = 18;

    /**
     * @param array{
     *   tela:string,
     *   module:string,
     *   page_id?:?string,
     *   sources: array<string, array{path:string, content:?string}>,
     *   personas?: array{primary?:?string, secondary?:array<int,string>, files?:array<string,string>},
     *   feedback?: array<int, array{path:string, content:?string}>,
     *   padroes?: array<int, string>
     * } $ctx
     */
    public static function assemble(array $ctx): string
    {
        $tela = (string) ($ctx['tela'] ?? '?');
        $module = (string) ($ctx['module'] ?? '?');
        $pageId = $ctx['page_id'] ?? null;
        $src = $ctx['sources'] ?? [];

        $charter = $src['charter']['content'] ?? null;
        $out = [];

        $out[] = "---";
        $out[] = "tela: {$module}/{$tela}";
        $out[] = 'page_id: ' . ($pageId ?? '?');
        $out[] = "gerado_por: design:dossie";
        $out[] = "tipo: read-view efemera (NAO-canon — a lei e o charter)";
        $out[] = "---";
        $out[] = "";
        $out[] = "# DOSSIÊ DE TELA — {$module}/{$tela}";
        $out[] = "";
        $out[] = "> Read-view montada das fontes curadas que já existem. **Não é canon** e não substitui o charter (a porta única da verdade-de-tela · ADR 0270 D-2). É o contexto pra IA ler ANTES de aplicar.";

        // 1. O que já foi decidido (charter: Mission + Goals + Non-Goals)
        $out[] = self::block('1. O que já foi decidido (charter)', $charter === null
            ? self::ausente($src['charter']['path'] ?? 'charter')
            : self::join([
                self::section($charter, 'Mission'),
                self::section($charter, 'Goals'),
                self::section($charter, 'Non-Goals'),
            ]) . self::prov($src['charter']['path'] ?? null));

        // 2. Anti-hooks / Tier 0 (charter)
        $out[] = self::block('2. Anti-hooks / Tier 0', $charter === null
            ? '_(charter ausente)_'
            : (self::section($charter, 'Automation Anti-hooks') ?? '_(sem seção Anti-hooks)_') . self::prov($src['charter']['path'] ?? null));

        // 3. Regras / contrato (casos)
        $out[] = self::block('3. Regras / contrato (casos)', self::excerptOrAusente($src['casos'] ?? null));

        // 4. Em-debate (decisoes / Register)
        $out[] = self::block('4. Em-debate (Register)', self::excerptOrAusente($src['decisoes'] ?? null));

        // 5. Últimas conversões (RUNBOOK + visual-comparison + UX targets)
        $conv = [
            self::prov($src['runbook']['path'] ?? null, 'RUNBOOK'),
            self::prov($src['visual_comparison']['path'] ?? null, 'visual-comparison'),
            self::prov($src['briefing']['path'] ?? null, 'BRIEFING'),
        ];
        if ($charter !== null) {
            $conv[] = self::section($charter, 'UX Targets');
        }
        $out[] = self::block('5. Últimas conversões & alvos', self::join($conv));

        // 6. Padronizações DS aplicáveis (related_adrs do charter + PTs passados)
        $adrs = $charter !== null ? self::frontmatterList($charter, 'related_adrs') : [];
        $padroes = array_values(array_unique(array_merge($adrs, $ctx['padroes'] ?? [])));
        $out[] = self::block('6. Padronizações DS aplicáveis', $padroes === []
            ? '_(nenhuma ADR/PT resolvida)_'
            : implode("\n", array_map(static fn ($p) => "- `{$p}`", $padroes)));

        // 7. Reclamações / feedback
        $fb = $ctx['feedback'] ?? [];
        $out[] = self::block('7. Reclamações / feedback', $fb === []
            ? '_(nenhum feedback encontrado pra esta tela)_'
            : implode("\n", array_map(static fn ($f) => self::prov($f['path'] ?? null, basename((string) ($f['path'] ?? '?'))), $fb)));

        // 8. Persona(s)
        $p = $ctx['personas'] ?? [];
        $linhas = [];
        if (! empty($p['primary'])) {
            $linhas[] = "- **primary:** `{$p['primary']}`";
        }
        foreach (($p['secondary'] ?? []) as $s) {
            $linhas[] = "- secondary: `{$s}`";
        }
        $out[] = self::block('8. Persona(s) da tela', $linhas === [] ? '_(persona não resolvida)_' : implode("\n", $linhas));

        // Proveniência consolidada
        $prov = ['## Proveniência (fontes lidas)', ''];
        foreach ($src as $key => $s) {
            $marca = ($s['content'] ?? null) !== null ? 'presente' : 'AUSENTE';
            $prov[] = "- {$key}: `" . ($s['path'] ?? '?') . "` ({$marca})";
        }
        $out[] = "\n" . implode("\n", $prov);

        return implode("\n", $out) . "\n";
    }

    /** Extrai o bloco de uma seção `## Heading` até o próximo `## ` ou o fim. */
    public static function section(string $md, string $heading): ?string
    {
        // para no próximo `## ` OU num `---` HR separador de seções (charter real usa HR)
        $pattern = '/^##\s+' . preg_quote($heading, '/') . '\s*$(.*?)(?=^##\s|^---\s*$|\z)/ms';
        if (preg_match($pattern, $md, $m)) {
            $body = trim($m[1]);

            return "**{$heading}**\n\n" . $body;
        }

        return null;
    }

    /** Lê uma lista do frontmatter YAML (ex: related_adrs) — parser simples por linha. */
    public static function frontmatterList(string $md, string $field): array
    {
        if (! preg_match('/^---\s*$(.*?)^---\s*$/ms', $md, $fm)) {
            return [];
        }
        $block = $fm[1];
        // captura `field:` seguido de linhas `  - item`
        if (! preg_match('/^' . preg_quote($field, '/') . ':\s*$(.*?)(?=^\S|\z)/ms', $block, $m)) {
            return [];
        }
        preg_match_all('/^\s*-\s*(.+?)\s*$/m', $m[1], $items);

        return array_map('trim', $items[1] ?? []);
    }

    private static function excerptOrAusente(?array $s): string
    {
        $content = $s['content'] ?? null;
        if ($content === null) {
            return self::ausente($s['path'] ?? '?');
        }
        $lines = preg_split('/\r?\n/', trim($content)) ?: [];
        $excerpt = implode("\n", array_slice($lines, 0, self::EXCERPT_LINES));
        if (count($lines) > self::EXCERPT_LINES) {
            $excerpt .= "\n…";
        }

        return $excerpt . self::prov($s['path'] ?? null);
    }

    private static function block(string $title, string $body): string
    {
        return "\n## {$title}\n\n" . $body;
    }

    private static function join(array $parts): string
    {
        $parts = array_filter($parts, static fn ($p) => $p !== null && trim((string) $p) !== '');

        return $parts === [] ? '_(sem conteúdo)_' : implode("\n\n", $parts);
    }

    private static function ausente(string $path): string
    {
        return "_(ausente: `{$path}` — fonte ainda não existe pra esta tela)_";
    }

    private static function prov(?string $path, ?string $label = null): string
    {
        if ($path === null || $path === '') {
            return $label !== null ? "- {$label}: _(ausente)_" : '';
        }
        $label ??= 'fonte';

        return $label === 'fonte'
            ? "\n\n_proveniência:_ `{$path}`"
            : "- {$label}: `{$path}`";
    }
}
