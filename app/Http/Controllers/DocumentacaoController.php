<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * Documentação do sistema em /documentacao (atrás de auth).
 *
 * DESENHO — por que renderiza em runtime e não serve HTML gerado:
 * a doutrina do projeto é "derivado e enforçado sobrevive; escrito e lembrado
 * apodrece" (ADR 0256). Um HTML commitado seria uma CÓPIA do documento dono e
 * drifaria dele em silêncio. Aqui a página É o dono, renderizado.
 *
 * A BUSCA não constrói índice novo: usa o FULLTEXT que já existe em
 * `mcp_memory_documents` (índice `mcp_md_fulltext_idx` sobre title+content_md),
 * a mesma tabela que o webhook do git sincroniza e que a Jana consulta.
 *
 * Sem dependência nova: league/commonmark já vem no vendor (illuminate/mail).
 */
class DocumentacaoController extends Controller
{
    /** Documento dono da leitura guiada, relativo à raiz do repo. */
    private const FONTE = 'memory/GUIA-DO-SISTEMA.md';

    /** Base para reescrever links relativos do markdown (que apontam pra árvore do git). */
    private const BLOB = 'https://github.com/wagnerra23/oimpresso.com/blob/main/memory/';

    /**
     * Tipos que são DOCUMENTAÇÃO — decisão [W] 2026-08-02.
     *
     * Fora de propósito: `session` e `handoff` (diário de bordo, não guia — misturá-los
     * piora a busca: procurar "financeiro" traria 40 handoffs antes do briefing do
     * módulo), e `audit`/`changelog`/`comparativo`/`current`/`tasks`/`other`, que são
     * retratos datados. Os 4 abaixo são os que respondem "como isto funciona".
     */
    private const TIPOS_DOC = ['adr', 'reference', 'spec', 'runbook'];

    private const POR_PAGINA = 25;

    public function index(): View
    {
        $caminho = base_path(self::FONTE);

        if (! File::exists($caminho)) {
            // Falha honesta: diz QUAL arquivo falta, em vez de página vazia.
            abort(503, 'Documento fonte ausente no deploy: ' . self::FONTE);
        }

        $markdown = File::get($caminho);

        return view('documentacao.index', [
            'html' => $this->paraHtml($markdown),
            'fonte' => self::FONTE,
            'atualizadoEm' => $this->dataDoFrontmatter($markdown),
            'buscaDisponivel' => $this->corpusDisponivel(),
        ]);
    }

    /** Busca full-text no corpus sincronizado do git. */
    public function buscar(Request $request): View
    {
        $termo = trim((string) $request->query('q', ''));

        if (! $this->corpusDisponivel()) {
            // Não finge resultado vazio: diz que o índice não está acessível.
            return view('documentacao.busca', [
                'termo' => $termo,
                'resultados' => collect(),
                'indisponivel' => true,
            ]);
        }

        $resultados = collect();

        if (Str::length($termo) >= 2) {
            $resultados = $this->consultaBase()
                ->when(true, function ($q) use ($termo) {
                    // FULLTEXT ignora palavra < 4 chars (ft_min_word_len) e stopwords —
                    // por isso o LIKE no título entra como rede de segurança, não como
                    // substituto: sem ele, buscar "NFe" ou "MCP" devolveria vazio.
                    $q->where(function ($sub) use ($termo) {
                        $sub->whereRaw(
                            'MATCH(title, content_md) AGAINST (? IN NATURAL LANGUAGE MODE)',
                            [$termo]
                        )->orWhere('title', 'like', '%' . $termo . '%');
                    });
                })
                ->orderByRaw(
                    'MATCH(title, content_md) AGAINST (? IN NATURAL LANGUAGE MODE) DESC',
                    [$termo]
                )
                ->limit(self::POR_PAGINA)
                ->get(['slug', 'type', 'module', 'title', 'git_path', 'content_md'])
                ->map(fn (McpMemoryDocument $d) => [
                    'slug' => $d->slug,
                    'type' => $d->type,
                    'module' => $d->module,
                    'title' => $d->title,
                    'git_path' => $d->git_path,
                    'trecho' => $this->trecho($d->content_md, $termo),
                ]);
        }

        return view('documentacao.busca', [
            'termo' => $termo,
            'resultados' => $resultados,
            'indisponivel' => false,
        ]);
    }

    /** Abre um documento do corpus com o mesmo visual da leitura guiada. */
    public function documento(string $slug): View
    {
        if (! $this->corpusDisponivel()) {
            abort(503, 'Índice de documentação indisponível no momento.');
        }

        $doc = $this->consultaBase()->where('slug', $slug)->first();

        if (! $doc) {
            // 404 honesto: ou não existe, ou é de um tipo fora da documentação
            // (session/handoff), ou exige permissão que este usuário não tem.
            abort(404, 'Documento não encontrado na documentação.');
        }

        return view('documentacao.doc', [
            'doc' => $doc,
            'html' => $this->paraHtml($doc->content_md),
        ]);
    }

    /**
     * Base de TODA consulta ao corpus — o filtro de acesso mora aqui, num lugar só.
     *
     * `admin_only` e `scope_required` são colunas do próprio schema (o corpus já
     * nasceu com modelo de acesso). Conservador de propósito: documento com
     * `scope_required` fica fora até alguém mapear a permissão Spatie correspondente
     * — melhor não listar do que listar o que o usuário não deveria ver.
     */
    private function consultaBase()
    {
        return McpMemoryDocument::query()
            ->whereIn('type', self::TIPOS_DOC)
            ->where('admin_only', false)
            ->whereNull('scope_required');
    }

    /** O corpus vive numa tabela sincronizada por webhook; pode não existir no ambiente. */
    private function corpusDisponivel(): bool
    {
        try {
            return Schema::hasTable('mcp_memory_documents');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Trecho ao redor da primeira ocorrência — dá contexto sem abrir o documento. */
    private function trecho(?string $conteudo, string $termo): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', (string) $this->semFrontmatter((string) $conteudo)));
        if ($texto === '') {
            return '';
        }

        $pos = stripos($texto, $termo);
        $inicio = $pos === false ? 0 : max(0, $pos - 90);

        return ($inicio > 0 ? '…' : '') . Str::limit(substr($texto, $inicio), 230);
    }

    /** Converte o markdown, já sem frontmatter e com links relativos utilizáveis na web. */
    private function paraHtml(string $markdown): string
    {
        $html = Str::markdown($this->semFrontmatter($markdown), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

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
