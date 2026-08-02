<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Serve a documentação do sistema em /documentacao (atrás de auth).
 *
 * DESENHO — por que renderiza em runtime e não serve HTML gerado:
 * a doutrina do projeto é "derivado e enforçado sobrevive; escrito e lembrado
 * apodrece" (ADR 0256). Um HTML commitado seria uma CÓPIA do documento dono e
 * drifaria dele em silêncio. Aqui a página É o dono, renderizado — não há
 * artefato intermediário que possa divergir.
 *
 * FONTE: memory/GUIA-DO-SISTEMA.md, que já se declara "a leitura humana do
 * sistema". Nenhum documento novo foi criado para esta rota.
 *
 * Sem dependência nova: league/commonmark já vem no vendor (illuminate/mail).
 */
class DocumentacaoController extends Controller
{
    /** Documento dono, relativo à raiz do repo. */
    private const FONTE = 'memory/GUIA-DO-SISTEMA.md';

    /** Base para reescrever links relativos do markdown (que apontam pra árvore do git). */
    private const BLOB = 'https://github.com/wagnerra23/oimpresso.com/blob/main/memory/';

    public function index(): View
    {
        $caminho = base_path(self::FONTE);

        if (! File::exists($caminho)) {
            // Falha honesta: diz QUAL arquivo falta, em vez de página vazia.
            abort(503, 'Documento fonte ausente no deploy: ' . self::FONTE);
        }

        $markdown = File::get($caminho);

        return view('documentacao', [
            'html' => $this->paraHtml($markdown),
            'fonte' => self::FONTE,
            'atualizadoEm' => $this->dataDoFrontmatter($markdown),
        ]);
    }

    /** Converte o markdown, já sem frontmatter e com links relativos utilizáveis na web. */
    private function paraHtml(string $markdown): string
    {
        $corpo = $this->semFrontmatter($markdown);

        $html = Str::markdown($corpo, [
            'html_input' => 'strip',          // conteúdo é do repo, mas não há razão pra injetar HTML
            'allow_unsafe_links' => false,
        ]);

        // Links do markdown apontam pra árvore do git (../decisions/0094-x.md).
        // Na web isso morre — reescreve pro blob do GitHub, que é onde a fonte vive.
        return preg_replace_callback(
            '/href="(?!https?:|#|mailto:)([^"]+\.md)(#[^"]*)?"/i',
            fn ($m) => 'href="' . self::BLOB . ltrim(str_replace('../', '', $m[1]), '/') . ($m[2] ?? '') . '" rel="noopener" target="_blank"',
            $html
        );
    }

    /** Remove o bloco YAML do topo — ele é metadado, não conteúdo de leitura. */
    private function semFrontmatter(string $markdown): string
    {
        return (string) preg_replace('/\A---\R.*?\R---\R/s', '', $markdown);
    }

    /** Lê `last_updated` do frontmatter. Retorna null se ausente — nunca inventa data. */
    private function dataDoFrontmatter(string $markdown): ?string
    {
        return preg_match('/^last_updated:\s*"?([0-9]{4}-[0-9]{2}-[0-9]{2})"?/m', $markdown, $m)
            ? $m[1]
            : null;
    }
}
