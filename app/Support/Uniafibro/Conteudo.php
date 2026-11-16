<?php

declare(strict_types=1);

namespace App\Support\Uniafibro;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Páginas "Seus Direitos" e "Guias Práticos" da Uniafibro, lidas de Markdown versionado em
 * resources/uniafibro/direitos/*.md. Não lê banco, sessão nem tenant.
 *
 * Frontmatter obrigatório: title, resumo, secao (direitos|guias), ordem, updated_at,
 * disclaimer_version e fontes (lista de {titulo, url}).
 *
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md §6.
 */
final class Conteudo
{
    public const SECOES = [
        'direitos' => 'Seus Direitos',
        'guias' => 'Guias Práticos',
    ];

    /** Só minúsculas, números e hífen: arquivo começado por "_" ou com ponto nunca vira página. */
    public const SLUG = '[a-z0-9][a-z0-9-]*';

    public static function pasta(): string
    {
        // config só existe para o teste apontar uma pasta de exemplo; em produção é sempre o default.
        return (string) config('uniafibro.pasta_conteudo', resource_path('uniafibro/direitos'));
    }

    public static function existe(string $slug): bool
    {
        return preg_match('/^'.self::SLUG.'$/', $slug) === 1 && is_file(self::pasta()."/{$slug}.md");
    }

    /**
     * @return array{slug: string, meta: array<string, mixed>, html: string, indice: list<array{id: string, titulo: string}>, texto: string}
     */
    public static function pagina(string $slug): array
    {
        $md = (string) file_get_contents(self::pasta()."/{$slug}.md");
        $saida = self::conversor()->convert($md);
        $meta = $saida instanceof RenderedContentWithFrontMatter ? (array) $saida->getFrontMatter() : [];

        // Cada <h2> ganha id estável para o índice lateral.
        $indice = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function (array $m) use (&$indice): string {
            $titulo = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $id = Str::slug($titulo) ?: 'secao-'.(count($indice) + 1);
            $indice[] = ['id' => $id, 'titulo' => $titulo];

            return '<h2 id="'.e($id).'">'.$m[1].'</h2>';
        }, $saida->getContent());

        // Link externo abre na mesma aba, mas sem enviar a origem.
        $html = preg_replace('/<a href="(https?:\/\/[^"]+)"/', '<a href="$1" rel="noopener noreferrer"', (string) $html);

        return [
            'slug' => $slug,
            'meta' => $meta,
            'html' => (string) $html,
            'indice' => $indice,
            'texto' => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ];
    }

    /**
     * Todas as páginas, agrupadas pela seção e ordenadas por "ordem".
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function todas(): array
    {
        $grupos = array_fill_keys(array_keys(self::SECOES), []);
        foreach (glob(self::pasta().'/*.md') ?: [] as $arquivo) {
            $slug = basename($arquivo, '.md');
            if (! self::existe($slug)) {
                continue;
            }
            $p = self::pagina($slug);
            $secao = (string) ($p['meta']['secao'] ?? '');
            if (isset($grupos[$secao])) {
                $grupos[$secao][] = $p;
            }
        }
        foreach ($grupos as &$lista) {
            usort($lista, fn ($a, $b) => ((int) ($a['meta']['ordem'] ?? 99)) <=> ((int) ($b['meta']['ordem'] ?? 99)));
        }

        return $grupos;
    }

    /**
     * Busca simples por palavra (sem acento, sem maiúscula). Todas as palavras precisam aparecer.
     *
     * @return list<array{pagina: array<string, mixed>, trecho: string}>
     */
    public static function buscar(string $consulta): array
    {
        $termos = array_values(array_filter(explode(' ', self::normalizar($consulta)), fn ($t) => mb_strlen($t) >= 2));
        if ($termos === []) {
            return [];
        }
        $achados = [];
        foreach (self::todas() as $lista) {
            foreach ($lista as $p) {
                $texto = preg_replace('/\s+/', ' ', $p['meta']['title'].' '.$p['texto']);
                $norm = self::normalizar($texto);
                if (array_filter($termos, fn ($t) => ! str_contains($norm, $t)) !== []) {
                    continue;
                }
                // Em português, Str::ascii troca letra acentuada por uma letra só, então a posição
                // no texto sem acento serve para recortar o texto original, com acento.
                $pos = max(0, (int) mb_strpos(mb_strtolower(Str::ascii($texto)), $termos[0]) - 60);
                $achados[] = ['pagina' => $p, 'trecho' => '…'.trim(mb_substr($texto, $pos, 180)).'…'];
            }
        }

        return $achados;
    }

    public static function normalizar(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9 ]+/', ' ', mb_strtolower(Str::ascii($s))) ?? '');
    }

    private static function conversor(): MarkdownConverter
    {
        $env = new Environment([
            // Conteúdo é nosso, mas HTML cru no .md fica proibido: só Markdown.
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new TableExtension);
        $env->addExtension(new FrontMatterExtension);

        return new MarkdownConverter($env);
    }
}
