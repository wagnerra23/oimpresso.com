<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;
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
     * retratos datados. Os abaixo são os que respondem "como isto funciona".
     *
     * `feature` entrou em 2026-08-04 (pedido [W]): é o trio
     * `memory/requisitos/<Mod>/features/<slug>/{requirements,plan,tasks}.md` — o degrau
     * "spec por feature" (proposal `feature-trio-requirements-plan-tasks`). Não é retrato
     * datado: é contrato vivo de UMA feature (acceptance EARS + plug-points + DoD), a
     * mesma natureza do `spec`, um nível abaixo.
     *
     * Fora AINDA (decisão pendente [W], não esquecimento): `charter` e `casos` — entram no
     * ÍNDICE desde 2026-08-02 (B3) mas não neste filtro, de propósito. Estar na TABELA não
     * é estar no ACERVO; adicioná-los é decisão, não conserto.
     *
     * `briefing` entrou em 2026-08-05 (autorização [W]: *"pode incluir"*). Era o único tipo
     * que não estava nem na lista nem entre as exclusões justificadas acima — assinatura de
     * omissão, não de decisão. É o dono da camada de PRODUTO por módulo (`requisitos/<Mod>/
     * BRIEFING.md`, entrada da Camada A no README), já indexado com `type='briefing'` desde
     * 2026-07-22 e já buscável pela Jana (`KbAnswerService::TIPOS_VALIDOS`) — só a rota
     * humana não o enxergava. Não é retrato datado: é estado vivo do módulo, mantido por
     * PR (skill `brief-update`) e vigiado por `briefing-code-staleness.mjs`.
     */
    private const TIPOS_DOC = ['adr', 'reference', 'spec', 'runbook', 'feature', 'briefing'];

    /**
     * Rótulo humano de cada tipo — para a PROSA das views ("cobre decisões, referências…").
     *
     * Só o rótulo mora aqui; QUAIS tipos entram é sempre `TIPOS_DOC`. Tipo sem rótulo cai
     * no próprio slug (ver `escopoEmProsa()`), então um tipo novo aparece torto — nunca
     * some calado. É o inverso do defeito que motivou isto: até 2026-08-05 as views
     * enumeravam "adr · reference · spec · runbook" DIGITADO, e a lista ficou mentindo
     * duas vezes seguidas — `feature` entrou em 08-04, `briefing` em 08-05, e nenhum dos
     * quatro rótulos acompanhou. Descrição de máquina mora dentro da máquina; o que a view
     * mostra é derivado dela, nunca redigitado ([W] 2026-08-05 · ADR 0256).
     */
    private const TIPOS_DOC_ROTULO = [
        'adr' => 'decisões (ADR)',
        'reference' => 'referências',
        'spec' => 'specs',
        'runbook' => 'runbooks',
        'feature' => 'features',
        'briefing' => 'briefings de módulo',
    ];

    private const POR_PAGINA = 25;

    /** Pasta de onde a navegação é derivada. Doc sem `nav_group` fica fora do rail. */
    private const PASTA_NAV = 'memory/reference';

    /** Rótulo de cada grupo, na ordem em que o rail os apresenta. */
    private const GRUPOS_NAV = [
        'start' => 'Comece aqui',
        'dominio' => 'Domínio',
        'fluxo' => 'Fluxos',
        'tecnico' => 'Técnico',
        'governanca' => 'Governança',
    ];

    private const LENTES = ['operar' => 'Operar', 'construir' => 'Construir'];

    /**
     * Publica o escopo do acervo pra TODA view desta rota — inclusive o layout, que é
     * quem carrega o `aria-label` da busca e não recebe payload de método nenhum.
     *
     * `View::share` aqui (e não em provider) porque o alcance é exatamente este
     * controller: quem renderiza `documentacao.*` é só ele.
     */
    public function __construct()
    {
        ViewFacade::share('escopoTipos', self::TIPOS_DOC);
        ViewFacade::share('escopoProsa', self::escopoEmProsa());
    }

    /**
     * Os tipos do acervo em prosa PT-BR: "a, b, c e d".
     *
     * Deriva de `TIPOS_DOC` — a lista de tipos é dona; este método só a veste. Tipo sem
     * entrada em `TIPOS_DOC_ROTULO` sai como o próprio slug: fica feio e visível, em vez
     * de sumir em silêncio (o modo de falha que esta função existe pra impedir).
     */
    private static function escopoEmProsa(): string
    {
        $rotulos = array_map(
            static fn (string $t): string => self::TIPOS_DOC_ROTULO[$t] ?? $t,
            self::TIPOS_DOC,
        );

        if (count($rotulos) < 2) {
            return implode('', $rotulos);
        }

        $ultimo = array_pop($rotulos);

        return implode(', ', $rotulos) . ' e ' . $ultimo;
    }

    public function index(Request $request): View
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
            'nav' => $this->navegacao($this->lenteAtiva($request)),
            'atual' => null,   // a capa não é item do rail; é a rota raiz
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
                'nav' => $this->navegacao($this->lenteAtiva($request)),
                'atual' => null,
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
            'nav' => $this->navegacao($this->lenteAtiva($request)),
            'atual' => null,
        ]);
    }

    /** Abre um documento do corpus com o mesmo visual da leitura guiada. */
    public function documento(Request $request, string $slug): View
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
            'nav' => $this->navegacao($this->lenteAtiva($request)),
            'atual' => $slug,
            // A base dos links é a PASTA DO PRÓPRIO documento, não `memory/`: este acervo
            // tem doc em subpasta (`memory/reference/…`, `memory/requisitos/<Mod>/…`), e o
            // link relativo dele foi escrito a partir de onde ele mora.
            'html' => $this->paraHtml($doc->content_md, $this->pastaDe($doc->git_path)),
        ]);
    }

    /**
     * Monta o rail a partir do FRONTMATTER dos documentos — nada escrito à mão.
     *
     * POR QUE DERIVADO EM RUNTIME, e não um JSON gerado e commitado: um manifesto no
     * repositório seria mais uma cópia da estrutura, e cópia drifa (ADR 0256). É a mesma
     * razão de o sumário da página ser recalculado a cada acesso. Documento novo com
     * `nav_group` aparece sozinho; documento que perde o campo some junto.
     *
     * OPT-IN de propósito: sem `nav_group` o doc não entra. Os ~130 arquivos de referência
     * legados não viram menu porque alguém criou um rail — viram quando alguém decidir.
     *
     * O ORDINAL não sai de `nav_order`. Ele numera a ordem VISÍVEL na lente ativa; se
     * viesse do campo, filtrar a lente deixaria buracos (1, 3, 7) e o leitor pensaria
     * que sumiu conteúdo. `nav_order` só ordena.
     *
     * @return array{grupos: list<array{id:string, titulo:string, itens:list<array<string,mixed>>}>, linear: list<array<string,mixed>>, lente: ?string, lentes: array<string,string>}
     */
    private function navegacao(?string $lente): array
    {
        $docs = [];

        foreach (File::glob(base_path(self::PASTA_NAV . '/*.md')) as $arquivo) {
            $meta = $this->frontmatter(File::get($arquivo));

            $grupo = $meta['nav_group'] ?? null;
            if (! is_string($grupo) || ! isset(self::GRUPOS_NAV[$grupo])) {
                continue;
            }

            // `lente` ausente = aparece em todas. Domínio é UMA página vista por dois
            // públicos — nunca duas cópias.
            $lentes = $meta['lente'] ?? [];
            if ($lente !== null && $lentes !== [] && ! in_array($lente, $lentes, true)) {
                continue;
            }

            $titulo = $meta['name'] ?? basename($arquivo, '.md');

            $docs[] = [
                'id' => $meta['id'] ?? basename($arquivo, '.md'),
                'grupo' => $grupo,
                'ordem' => (int) ($meta['nav_order'] ?? 999),
                'titulo' => $titulo,
                // No rail o item já está sob o cabeçalho do grupo, então "Domínio — Venda"
                // vira "Venda": repetir o grupo em cada linha rouba a largura do rótulo.
                // A página em si continua com o título inteiro.
                'rotulo' => trim(Str::after($titulo, '—')) ?: $titulo,
                'descricao' => $meta['description'] ?? null,
            ];
        }

        // Ordena por (grupo na ordem do rail, nav_order, título) — determinístico.
        $posicaoGrupo = array_flip(array_keys(self::GRUPOS_NAV));
        usort($docs, fn ($a, $b) => [$posicaoGrupo[$a['grupo']], $a['ordem'], $a['titulo']]
            <=> [$posicaoGrupo[$b['grupo']], $b['ordem'], $b['titulo']]);

        $grupos = [];
        $linear = [];
        $n = 0;

        foreach (self::GRUPOS_NAV as $id => $titulo) {
            $itens = array_values(array_filter($docs, fn ($d) => $d['grupo'] === $id));
            if ($itens === []) {
                continue;   // grupo vazio não vira cabeçalho órfão
            }

            foreach ($itens as $i => $item) {
                $itens[$i]['ordinal'] = ++$n;   // ordinal = ordem VISÍVEL, não nav_order
                $linear[] = $itens[$i];
            }

            $grupos[] = ['id' => $id, 'titulo' => $titulo, 'itens' => $itens];
        }

        return ['grupos' => $grupos, 'linear' => $linear, 'lente' => $lente, 'lentes' => self::LENTES];
    }

    /**
     * Lente ativa: query string manda, cookie lembra, ausência = tudo.
     *
     * Valor fora do enum vira null em vez de lista vazia — filtro que ninguém pediu e que
     * esconde a página inteira é pior do que ignorar o parâmetro.
     */
    private function lenteAtiva(Request $request): ?string
    {
        // A escolha só se torna preferência quando é EXPLÍCITA (veio na URL). Ler o
        // cookie e regravá-lo a cada request renovaria sozinho uma escolha que o
        // usuário talvez tenha feito uma vez, meses atrás.
        if ($request->query->has('lente')) {
            $escolha = $request->query('lente');
            $valida = is_string($escolha) && isset(self::LENTES[$escolha]) ? $escolha : null;

            // 1 ano; `null` apaga (o link "Tudo" desfaz a preferência de verdade).
            Cookie::queue($valida === null
                ? Cookie::forget('doc_lente')
                : cookie('doc_lente', $valida, 60 * 24 * 365));

            return $valida;
        }

        $lembrado = $request->cookie('doc_lente');

        return is_string($lembrado) && isset(self::LENTES[$lembrado]) ? $lembrado : null;
    }

    /**
     * Frontmatter YAML — só o suficiente para a navegação (escalar e lista inline).
     *
     * Escrito à mão de propósito: o projeto não tem parser YAML no runtime da web, e a
     * alternativa seria uma dependência nova para ler 4 campos. Se um dia o frontmatter
     * usar YAML de verdade (aninhado, multilinha), isto vira insuficiente — e o caso de
     * contrato quebra, que é o sinal certo para trocar por parser real.
     *
     * @return array<string, mixed>
     */
    private function frontmatter(string $markdown): array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R/s', $markdown, $bloco)) {
            return [];
        }

        $meta = [];

        foreach (preg_split('/\R/', $bloco[1]) as $linha) {
            if (! preg_match('/^([a-z_][a-z0-9_]*):\s*(.*)$/i', $linha, $m)) {
                continue;   // indentado, comentário ou vazio — fora do escopo
            }

            $valor = trim($m[2]);

            if (str_starts_with($valor, '[') && str_ends_with($valor, ']')) {
                $itens = array_filter(array_map(
                    fn ($v) => trim($v, " \t\"'"),
                    explode(',', trim($valor, '[]'))
                ), fn ($v) => $v !== '');
                $meta[$m[1]] = array_values($itens);
                continue;
            }

            $meta[$m[1]] = trim($valor, "\"'");
        }

        return $meta;
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
     *
     * TERCEIRO DEFEITO (medido 2026-08-03): a base era a constante `memory/` para TODO
     * documento. Vale pro guia, que mora ali — mas `/documentacao/{slug}` serve o acervo
     * inteiro, e doc em subpasta tem link escrito a partir da pasta DELE. Medição em
     * `memory/reference/` (130 docs, 71 com link relativo): **482 links** resolviam pra
     * caminho inexistente — `../decisions/0275-….md` virava `decisions/0275-….md`, que
     * não existe, em vez de `memory/decisions/0275-….md`. Passou invisível porque o caso
     * de contrato varre só o guia. Agora a base vem do `git_path` do próprio documento.
     */
    private function paraHtml(string $markdown, ?string $base = null): string
    {
        $base ??= self::BASE_LINKS;

        $html = Str::markdown($this->semFrontmatter($markdown), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return preg_replace_callback(
            '/href="(?!https?:|#|mailto:|data:|\/)([^"#]+)(#[^"]*)?"/i',
            fn ($m) => 'href="' . self::BLOB . $this->resolveRelativo($base, $m[1])
                . ($m[2] ?? '') . '" rel="noopener" target="_blank"',
            $html
        );
    }

    /**
     * Pasta de um documento do acervo, para resolver os links relativos dele.
     *
     * `git_path` é relativo à raiz do repo, com `/` (ver ReindexarDocumentoJob). Documento
     * na raiz devolve string vazia — que `resolveRelativo` trata como a própria raiz.
     * Sem `git_path` (registro antigo), cai na base do guia em vez de arriscar caminho pior.
     */
    private function pastaDe(?string $gitPath): string
    {
        if (! $gitPath) {
            return self::BASE_LINKS;
        }

        $pasta = str_contains($gitPath, '/') ? dirname($gitPath) : '';

        return $pasta === '.' ? '' : $pasta;
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
