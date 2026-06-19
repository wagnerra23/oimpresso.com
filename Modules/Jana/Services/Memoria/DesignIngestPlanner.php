<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria;

/**
 * PR-2a da estação de ingestão de design ([plano] vectorized-badger · pós-adversário).
 *
 * Núcleo PURO da ingestão de um zip de design (pré-aplicação): roteia os arquivos
 * extraídos contra o `cowork-map.json` (tela→destino), parseia o diff vs o commitado,
 * e renderiza os dois entregáveis — o PLANO-MUDANCAS (o que muda por arquivo, ANTES
 * de aplicar) e a memória de sessão da ingestão.
 *
 * Tudo PURO/determinístico (sem FS/git/zip/LLM/timestamp gerado): o comando (PR-2b)
 * faz unzip via ZipArchive + `git diff --no-index` via Process e injeta as strings aqui.
 * `now` é injetado (determinismo no teste).
 */
final class DesignIngestPlanner
{
    /**
     * Roteia os arquivos extraídos contra o map (tela→destino). Extra = sem rota → reportado.
     *
     * @param  array<string, mixed>  $map  cowork-map.json decodificado
     * @param  list<string>  $files  paths relativos extraídos (ex: "vendas-page.jsx")
     * @return array{routed: list<array{from:string, to:string}>, extras: list<string>}
     */
    public static function route(array $map, array $files, string $tela): array
    {
        $screens = (array) ($map['screens'] ?? []);
        $screen = (array) ($screens[$tela] ?? []);
        $routes = (array) ($screen['routes'] ?? []);

        $routed = [];
        $extras = [];
        foreach ($files as $f) {
            $base = basename($f);
            $to = null;
            foreach ($routes as $r) {
                $r = (array) $r;
                $glob = (string) ($r['glob'] ?? '');
                if ($glob !== '' && fnmatch($glob, $base)) {
                    $dest = (string) ($r['to'] ?? '');
                    // destino diretório (termina em /) → preserva o nome do arquivo
                    $to = str_ends_with($dest, '/') ? $dest . $base : $dest;
                    break;
                }
            }
            if ($to !== null && $to !== '') {
                $routed[] = ['from' => $f, 'to' => $to];
            } else {
                $extras[] = $f;
            }
        }

        return ['routed' => $routed, 'extras' => $extras];
    }

    /**
     * Parseia a saída de `git diff --no-index --name-status`. Puro.
     *
     * @return array{added: list<string>, modified: list<string>, removed: list<string>}
     */
    public static function parseDiff(string $nameStatus): array
    {
        $added = $modified = $removed = [];
        foreach (preg_split('/\r?\n/', trim($nameStatus)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = preg_split('/\t/', $line) ?: [];
            $code = (string) ($parts[0] ?? '');
            $path = (string) ($parts[1] ?? '');
            if ($path === '') {
                continue;
            }
            if (str_starts_with($code, 'A')) {
                $added[] = $path;
            } elseif (str_starts_with($code, 'M')) {
                $modified[] = $path;
            } elseif (str_starts_with($code, 'D')) {
                $removed[] = $path;
            }
        }

        return ['added' => $added, 'modified' => $modified, 'removed' => $removed];
    }

    /**
     * PLANO-MUDANCAS — o entregável destacado: o que muda por arquivo, ANTES de aplicar.
     *
     * @param  array{routed: list<array{from:string, to:string}>, extras: list<string>}  $routing
     * @param  array{added: list<string>, modified: list<string>, removed: list<string>}  $diff
     */
    public static function renderPlano(string $tela, array $routing, array $diff): string
    {
        $rows = [];
        foreach (['added' => 'add', 'modified' => 'mod', 'removed' => 'del'] as $key => $label) {
            foreach ($diff[$key] as $path) {
                $rows[] = "| `{$path}` | {$label} |";
            }
        }
        $tabela = $rows === []
            ? '_(sem mudanças vs a tela commitada)_'
            : "| arquivo | status |\n|---|---|\n" . implode("\n", $rows);

        $extras = $routing['extras'] === []
            ? '_(nenhum)_'
            : implode("\n", array_map(static fn ($e) => "- ⚠️ `{$e}` — **fora do cowork-map** (avaliar antes de aplicar)", $routing['extras']));

        return "# PLANO-MUDANCAS — {$tela}\n\n"
            . "> **STATUS: PROPOSTA — nada aplicado.** Aplicar só via `design:ingest-zip --apply` (gate Wagner/CT100).\n\n"
            . "## Mudanças por arquivo (vs tela commitada)\n\n{$tabela}\n\n"
            . "## Arquivos extras (não-autorizados no map)\n\n{$extras}\n\n"
            . "## Roteamento (map → destino)\n\n"
            . ($routing['routed'] === []
                ? '_(nenhum arquivo roteado)_'
                : implode("\n", array_map(static fn ($r) => "- `{$r['from']}` → `{$r['to']}`", $routing['routed'])))
            . "\n";
    }

    /**
     * Memória de sessão da ingestão (schema sessions). `now` injetado (determinismo).
     *
     * @param  array{routed: list<array{from:string, to:string}>, extras: list<string>}  $routing
     * @param  array{added: list<string>, modified: list<string>, removed: list<string>}  $diff
     */
    public static function renderSession(string $tela, array $routing, array $diff, string $now): string
    {
        $nAdd = count($diff['added']);
        $nMod = count($diff['modified']);
        $nDel = count($diff['removed']);
        $nExtra = count($routing['extras']);
        $nRoute = count($routing['routed']);

        return "---\n"
            . "date: \"{$now}\"\n"
            . "topic: \"Ingestão de design-zip da tela {$tela}: {$nRoute} arquivos roteados, diff {$nAdd}+/{$nMod}~/{$nDel}- vs commitado, {$nExtra} extra(s). Prepare-only (nada aplicado).\"\n"
            . "authors: [C]\n"
            . "related_adrs:\n  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento\n"
            . "prs: []\n"
            . "---\n\n"
            . "# Ingestão de design — {$tela}\n\n"
            . "> Estação prepare-only (plano vectorized-badger PR-2). Unzip → map-roteamento → diff vs commitado → PLANO-MUDANCAS. **Nada aplicado** — a aplicação é gate Wagner/CT100.\n\n"
            . "## Resumo\n\n"
            . "- Roteados: **{$nRoute}** · Extras (fora do map): **{$nExtra}**\n"
            . "- Diff vs tela commitada: **{$nAdd}** add · **{$nMod}** mod · **{$nDel}** del\n\n"
            . "## Próximo passo\n\n"
            . "Ler o `PLANO-MUDANCAS-{$tela}.md` + o `DOSSIE-{$tela}.md` (design:dossie) ANTES de aplicar. Resolver extras. Só então `--apply` sob gate.\n";
    }
}
