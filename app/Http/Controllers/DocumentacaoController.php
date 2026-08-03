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
    private const BLOB = 'https://github.com/wagnerra23/oimpresso.com/blob/main/';

    /** Pasta a partir da qual os links relativos do markdown se resolvem. */
    private const BASE_LINKS = 'memory';

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

        [$html, $sumario] = $this->comSumario($this->paraHtml($markdown));

        return view('documentacao.index', [
            'html' => $html,
            'sumario' => $sumario,
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

    /**
     * Converte o markdown, já sem frontmatter e com links relativos utilizáveis na web.
     *
     * O markdown foi escrito para ser lido na árvore do git, então os links são relativos
     * a `memory/`. Na web isso não resolve sozinho — cada um vira link pro blob do GitHub.
     *
     * DOIS DEFEITOS CORRIGIDOS AQUI (medidos no guia em 2026-08-03, 9 de 56 links):
     *   1. `../` era APAGADO da string em vez de subir um nível, então `../README.md`
     *      virava `memory/README.md` — caminho que não existe (o README é da raiz).
     *   2. só `.md` era reescrito, então `../Modules/Jana/` saía como href relativo cru
     *      e dava 404 na própria página.
     * Agora o caminho é normalizado segmento a segmento, e qualquer alvo relativo entra —
     * o que também é o que faz *apontar pro código* funcionar de dentro do texto.
     */
    private function paraHtml(string $markdown): string
    {
        $html = Str::markdown($this->semFrontmatter($markdown), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return preg_replace_callback(
            '/href="(?!https?:|#|mailto:|data:|\/)([^"#]+)(#[^"]*)?"/i',
            fn ($m) => 'href="' . self::BLOB . $this->resolveRelativo(self::BASE_LINKS, $m[1])
                . ($m[2] ?? '') . '" rel="noopener" target="_blank"',
            $html
        );
    }

    /**
     * Resolve um caminho relativo contra a pasta base, tratando `..` como subir um nível.
     *
     * Feito à mão de propósito: `realpath()` resolveria contra o disco do servidor e
     * devolveria caminho absoluto de máquina; aqui o alvo é uma URL do GitHub, então a
     * normalização é puramente textual — e não pode escapar acima da raiz do repo.
     */
    private function resolveRelativo(string $base, string $href): string
    {
        $partes = [];

        foreach (explode('/', $base . '/' . trim($href)) as $segmento) {
            if ($segmento === '' || $segmento === '.') {
                continue;
            }
            if ($segmento === '..') {
                array_pop($partes);   // pop em array vazio é no-op: não sobe acima da raiz
                continue;
            }
            $partes[] = $segmento;
        }

        return implode('/', $partes) . (str_ends_with(trim($href), '/') ? '/' : '');
    }

    /**
     * Injeta `id` nos títulos e devolve o sumário — DERIVADO do HTML, nunca escrito à mão.
     *
     * POR QUE ASSIM: uma lista de links escrita no markdown seria uma CÓPIA da estrutura
     * do documento e drifaria dele no primeiro título novo — ninguém lembra de sincronizar
     * um sumário (ADR 0256). Aqui o trilho é recalculado a cada acesso: título que nasce
     * aparece; título que some, some junto.
     *
     * ÂNCORA ESTÁVEL: quando o título começa por `A3.`/`B6.`, o id é `a3`/`b6` — esses
     * códigos são referenciados de fora do documento (o próprio guia manda "ver B6.1"),
     * então o link precisa sobreviver a reescrita do resto do título. Sem código, cai no
     * slug do texto.
     *
     * @return array{0: string, 1: list<array{id:string, nivel:int, codigo:?string, rotulo:string}>}
     */
    private function comSumario(string $html): array
    {
        $sumario = [];
        $usados = [];

        $html = preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/su',
            function (array $m) use (&$sumario, &$usados): string {
                $nivel = (int) $m[1];

                // strip_tags porque o título pode conter link e <code>; decode porque o
                // conversor escapa `—`, aspas e afins.
                $texto = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($texto === '') {
                    return $m[0];
                }

                $codigo = preg_match('/^([AB]\d{1,2})\./u', $texto, $c) ? $c[1] : null;

                $id = $codigo !== null ? Str::lower($codigo) : Str::slug($texto);
                if ($id === '') {
                    $id = 'secao';
                }

                // Título repetido não pode roubar a âncora do primeiro.
                $usados[$id] = ($usados[$id] ?? 0) + 1;
                if ($usados[$id] > 1) {
                    $id .= '-' . $usados[$id];
                }

                // O rótulo do trilho corta o aparte entre parênteses — neste documento ele
                // é sempre explicação, não identidade ("A4. Onde roda (Tier 0 …)" → "Onde
                // roda"). O título da página continua inteiro; só o trilho encurta.
                $rotulo = trim(preg_replace('/\s*\(.*$/us', '', $texto));
                if ($codigo !== null) {
                    $rotulo = trim(Str::after($rotulo, $codigo . '.'));
                }
                if ($rotulo === '') {
                    $rotulo = $texto;
                }

                $sumario[] = [
                    'id' => $id,
                    'nivel' => $nivel,
                    'codigo' => $codigo,
                    'rotulo' => $rotulo,
                ];

                return '<h' . $nivel . ' id="' . $id . '">' . $m[2] . '</h' . $nivel . '>';
            },
            $html
        );

        return [$html, $sumario];
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
