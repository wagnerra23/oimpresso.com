<?php

namespace App\Services\Documentacao;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * Leva cada caso de uso do catálogo de fluxos (UC-FLX-NN) aos artefatos que o documentam:
 * o SPEC onde mora cada US citada e os `.casos.md` de tela que citam o caso.
 *
 * DERIVADO, NÃO ESCRITO (ADR 0256): nenhuma lista de "caso → arquivo" existe à mão. O mapa sai
 * da árvore do repo a cada janela de cache — US nova num SPEC, ou tela nova citando o caso,
 * aparece sozinha; arquivo que sai, some junto.
 *
 * O LINK aponta para `/documentacao/{slug}` quando o arquivo está no acervo indexado (lookup por
 * `git_path` em `mcp_memory_documents`, o mesmo corpus que a rota de documento serve — com o
 * mesmo filtro de acesso). Fora do acervo, aponta para o arquivo no git. Hoje o indexador só lê
 * os `.casos.md` de `resources/js/Pages`; os de `Modules/<X>/Resources/js/Pages` caem no git.
 *
 * COLISÃO DE ID (lição §5 2026-09-04): um `.casos.md` que DECLARA casos próprios com o prefixo
 * `UC-FLX-` (título de seção começando pelo id) usa o mesmo nome para outra coisa — medido em
 * 2026-10-20: `Financeiro/Fluxo/Index.casos.md` declara UC-FLX-01..07 do fluxo de caixa. Esse
 * arquivo não conta como "documenta o caso do catálogo"; contá-lo seria crédito por coincidência
 * de string.
 */
class RastroDosCasos
{
    public const RE_US = '/US-[A-Z]+(?:-NEW)?-\d+/';

    private const RE_UC = '/\bUC-FLX-\d+\b/';

    private const TTL = 600;

    /**
     * @param  list<array{id:string, us:string}>  $casos
     * @param  list<string>  $tiposDoc  tipos que a rota de documento serve (dono: DocumentacaoController)
     * @param  string  $blob  base de link para o que não está no acervo (dono: DocumentacaoController)
     * @return array<string, array{us: list<array{id:string, docs: list<array{rotulo:string, path:string, href:string, interno:bool}>}>, documentado: list<array{rotulo:string, path:string, href:string, interno:bool}>}>
     */
    public function paraCasos(array $casos, array $tiposDoc, string $blob): array
    {
        $specs = $this->specsPorUs();
        $citacoes = $this->casosPorUc();

        $paths = [];
        $saida = [];
        foreach ($casos as $c) {
            preg_match_all(self::RE_US, $c['us'], $m);
            $us = [];
            foreach (array_values(array_unique($m[0])) as $id) {
                $us[] = ['id' => $id, 'paths' => $specs[$id] ?? []];
                array_push($paths, ...($specs[$id] ?? []));
            }
            $docs = $citacoes[$c['id']] ?? [];
            array_push($paths, ...$docs);
            $saida[$c['id']] = ['us' => $us, 'documentado' => $docs];
        }

        $slugs = $this->slugsNoAcervo(array_values(array_unique($paths)), $tiposDoc);
        $link = fn (string $p): array => [
            'rotulo' => $this->rotulo($p),
            'path' => $p,
            'href' => isset($slugs[$p]) ? '/documentacao/' . $slugs[$p] : $blob . $p,
            'interno' => isset($slugs[$p]),
        ];

        foreach ($saida as $id => $r) {
            $saida[$id] = [
                'us' => array_map(fn (array $u) => ['id' => $u['id'], 'docs' => array_map($link, $u['paths'])], $r['us']),
                'documentado' => array_map($link, $r['documentado']),
            ];
        }

        return $saida;
    }

    /** @return array<string, list<string>> US-id → SPECs que têm `### <id>` */
    public function specsPorUs(): array
    {
        return Cache::remember('doc:rastro:specs', self::TTL, function (): array {
            $mapa = [];
            foreach (File::glob(base_path('memory/requisitos/*/SPEC.md')) as $arquivo) {
                $rel = 'memory/requisitos/' . basename(dirname($arquivo)) . '/SPEC.md';
                preg_match_all('/^###\s+(US-[A-Z]+(?:-NEW)?-\d+)\b/m', File::get($arquivo), $m);
                foreach (array_unique($m[1]) as $id) {
                    $mapa[$id][] = $rel;
                }
            }

            return $mapa;
        });
    }

    /** @return array<string, list<string>> UC-FLX-id → `.casos.md` de tela que o citam */
    public function casosPorUc(): array
    {
        return Cache::remember('doc:rastro:casos', self::TTL, function (): array {
            $raizes = [base_path('resources/js/Pages'), ...File::glob(base_path('Modules/*/Resources/js/Pages'))];
            $mapa = [];
            foreach ($raizes as $raiz) {
                if (! is_dir($raiz)) {
                    continue;
                }
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if (! str_ends_with($f->getFilename(), '.casos.md')) {
                        continue;
                    }
                    $texto = File::get($f->getPathname());
                    // Declara casos próprios com o prefixo → outro namespace, não cita o catálogo.
                    if (preg_match('/^#+\s+UC-FLX-\d+\b/m', $texto) === 1) {
                        continue;
                    }
                    preg_match_all(self::RE_UC, $texto, $m);
                    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(base_path()) + 1));
                    foreach (array_unique($m[0]) as $uc) {
                        $mapa[$uc][] = $rel;
                    }
                }
            }
            foreach ($mapa as &$lista) {
                sort($lista);
            }
            unset($lista);

            return $mapa;
        });
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $tiposDoc
     * @return array<string, string> git_path → slug
     */
    private function slugsNoAcervo(array $paths, array $tiposDoc): array
    {
        if ($paths === []) {
            return [];
        }
        try {
            if (! Schema::hasTable('mcp_memory_documents')) {
                return [];
            }

            // Mesmo filtro de acesso da rota de documento: o link interno só existe se ela abre.
            return McpMemoryDocument::query()
                ->whereIn('type', $tiposDoc)
                ->where('admin_only', false)
                ->whereNull('scope_required')
                ->whereIn('git_path', $paths)
                ->pluck('slug', 'git_path')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** "memory/requisitos/Producao/SPEC.md" → "SPEC Producao"; ".../Pages/Producao/Apontamento/Index.casos.md" → "Producao/Apontamento/Index". */
    private function rotulo(string $path): string
    {
        if (preg_match('#^memory/requisitos/([^/]+)/SPEC\.md$#', $path, $m) === 1) {
            return 'SPEC ' . $m[1];
        }

        return (string) preg_replace(['#^.*?/js/Pages/#', '#\.casos\.md$#'], '', $path);
    }
}
