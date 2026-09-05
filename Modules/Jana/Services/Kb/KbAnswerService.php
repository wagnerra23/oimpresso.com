<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Kb;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\KbAnswerAgent;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Support\RetrievalStatus;

/**
 * KbAnswerService — pipeline reutilizável de Q&A sobre a KB do oimpresso.
 *
 * Extraído de {@see \Modules\Jana\Mcp\Tools\KbAnswerTool} (2026-07-01) pra que o
 * mesmo pipeline retrieval → síntese seja reusado por:
 *   - a tool MCP `kb-answer` (fachada user-facing);
 *   - o RAGAS real-eval (`jana:ragas-real-eval`) — mede a saída REAL da Jana
 *     em vez de `answer = ground_truth` (tautologia banida por ADR 0271 +
 *     memory/proibicoes.md §"Teste que deriva do código").
 *
 * SoC brutal (Constituição v2 §5) + reuse-check (rule reuse-check.md): a lógica
 * de retrieval e síntese vive AQUI; quem precisa de resposta/contexto reais
 * (Tool ou eval) chama este service. Zero duplicação.
 *
 * Multi-tenant Tier 0 (ADR 0093): `acessiveisPara($user)` aplica permissões
 * Spatie; `doBusiness($businessId)` aplica scope (NULL = global/compartilhado).
 *
 * @see Modules/Jana/Mcp/Tools/KbAnswerTool.php
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class KbAnswerService
{
    /**
     * Tipos canônicos persistidos em `mcp_memory_documents.type`.
     *
     * `charter`/`casos` entram com o B3 (2026-08-02): o trio de tela mora colado ao
     * `.tsx` e passou a ser indexado in-place. Sem estar AQUI, o `kb-answer` rejeita
     * `categoria:charter` na entrada e o doc fica no corpus sem porta de filtro —
     * a mesma meia-indexação que deixou os BRIEFINGs com `type=''` por 18 dias.
     */
    public const TIPOS_VALIDOS = ['adr', 'spec', 'session', 'handoff', 'briefing', 'surface', 'charter', 'casos', 'all'];

    /**
     * Orcamento de caracteres por fonte no bloco FONTES.
     *
     * Config-as-code SEM env() E SEM config() de proposito. Sem env() pelo mesmo
     * criterio do `docs_query_instruction` (Modules/Jana/Config/config.php): mexer
     * aqui muda a QUALIDADE do retrieval, logo e PR medido contra o gold-set, nunca
     * ajuste de .env. Sem config() porque `renderFontes` e PURA (roda sem container)
     * — o teste `renderiza bloco FONTES` quebrou com `Target class [config] does not
     * exist` quando isto passou por config(); a constante e o dono unico do numero.
     */
    public const EXCERPT_CHARS = 1200;

    /** Teto de posicoes varridas por doc — evita O(n^2) em doc gigante (max medido: 815k chars). */
    protected const MAX_POSICOES_JANELA = 400;

    /** Stopwords PT-BR de >=5 chars — sem elas o "centro" da janela vira ruido. */
    protected const STOPWORDS = [
        'para', 'como', 'esse', 'essa', 'isso', 'pelo', 'pela', 'mais', 'deve', 'sobre',
        'entre', 'quando', 'porque', 'qual', 'quais', 'onde', 'esta', 'estao', 'cada',
        'todo', 'toda', 'todos', 'todas', 'pode', 'fazer', 'antes', 'depois', 'apenas',
        'tambem', 'primeiro', 'segundo', 'existe', 'existem', 'precisa', 'devem',
    ];

    /** Retrieval veio do hybrid (semântico + lexical) — caminho pleno. */
    public const RETRIEVAL_HYBRID = 'hybrid';

    /** FULLTEXT por desenho: flag desligada, OU hybrid respondeu vazio (2ª opinião legítima). */
    public const RETRIEVAL_FULLTEXT = 'fulltext';

    /** FULLTEXT porque o hybrid CAIU (infra) — recall menor que o normal. É o caso visível. */
    public const RETRIEVAL_FULLTEXT_DEGRADADO = 'fulltext_degradado';

    /**
     * Frase única de degradação — mesma redação pra toda superfície (tool, eval, log).
     *
     * O texto MUDOU DE CASA (2026-07-28, 2ª onda): mora em {@see RetrievalStatus},
     * porque `decisions-search` e `memoria-search` emitem a mesma frase e o service de
     * Q&A não é dono dela. Mantido aqui como alias — os consumidores existentes
     * (`KbAnswerTool`, testes) seguem byte-a-byte.
     */
    public const AVISO_DEGRADADO = RetrievalStatus::AVISO;

    /**
     * FASE 1 — Retrieval híbrido determinístico (Princípio 2 Constituição v2:
     * tiered cost — SQL primeiro, IA só na síntese).
     *
     * Reusa exatamente os mesmos scopes de DecisionsSearchTool / MemoriaSearchTool —
     * invariante "MCP server (Proxmox) só lê de mcp_memory_documents" (ADR 0053).
     *
     * O fallback pro FULLTEXT acontece em TODOS os casos (disponibilidade acima de tudo —
     * degradação silenciosa foi escolha deliberada). O que o `$status` acrescenta é
     * VISIBILIDADE: o chamador passa a distinguir "o hybrid caiu, isto aqui é o plano B"
     * de "a busca rodou inteira e a KB não tem o assunto" — duas coisas que produziam
     * exatamente a mesma resposta ao usuário ("não encontrei nada · confiança: baixa").
     *
     * @param  string|null  $status  saída por referência: RETRIEVAL_HYBRID|RETRIEVAL_FULLTEXT|RETRIEVAL_FULLTEXT_DEGRADADO
     * @return Collection<int,McpMemoryDocument>
     */
    public function retrieve(
        ?User $user,
        string $pergunta,
        string $categoria = 'all',
        string $module = '',
        int $topK = 10,
        ?string &$status = null,
    ): Collection {
        $businessId = (int) data_get($user, 'business_id', 0);
        $status = self::RETRIEVAL_FULLTEXT;

        // Gap #2 (US-RET-001) — recall HYBRID atrás de flag, fallback FULLTEXT.
        // Corpus MCP é GLOBAL (sem filtro business_id — verificado no índice CT 100).
        if (config('copiloto.mcp_search.docs_pipeline', false)) {
            $statusHybrid = null;
            try {
                $hybrid = McpMemoryDocument::buscarHybrid(
                    $pergunta,
                    $topK,
                    $user,
                    $categoria !== 'all' ? $categoria : null,
                    $module !== '' ? $module : null,
                    $businessId, // Tier 0 — simétrico ao FULLTEXT (revisão 2026-05-29)
                    $statusHybrid,
                );
                if ($hybrid->isNotEmpty()) {
                    $status = self::RETRIEVAL_HYBRID;

                    return $hybrid;
                }
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->warning('kb-answer: hybrid falhou, fallback FULLTEXT: '.$e->getMessage());
                $statusHybrid = McpMemoryDocument::HYBRID_INDISPONIVEL;
            }

            // Vazio com o índice VIVO não é degradação — o FULLTEXT abaixo é 2ª opinião
            // legítima. Só marca degradado quando o hybrid não pôde responder.
            if ($statusHybrid === McpMemoryDocument::HYBRID_INDISPONIVEL) {
                $status = self::RETRIEVAL_FULLTEXT_DEGRADADO;
            }
        }

        $query = McpMemoryDocument::query()
            ->acessiveisPara($user)
            ->porStatusAtivo(false)   // só docs ativos
            ->buscarTexto($pergunta);

        if ($businessId > 0) {
            $query->doBusiness($businessId);
        }

        if ($categoria !== 'all') {
            $query->doTipo($categoria);
        }

        if ($module !== '') {
            $query->doModulo($module);
        }

        return $query->limit($topK)->get([
            'id', 'slug', 'title', 'type', 'module', 'content_md', 'git_path',
        ]);
    }

    /**
     * O retrieval rodou capenga? (única leitura autorizada do status — ninguém compara
     * a string crua por aí).
     */
    public static function degradado(?string $status): bool
    {
        return $status === self::RETRIEVAL_FULLTEXT_DEGRADADO;
    }

    /**
     * Sufixo a anexar na resposta — string vazia quando o retrieval foi pleno.
     *
     * Pura de propósito, pelo mesmo motivo do `JanaRagasRealEvalCommand::avisoDeCorte`:
     * a regra "degradação tem que ser VISÍVEL" fica testável sem DB, sem Meilisearch e
     * sem LLM. Testar por grep do texto no fonte seria presence-gate — mede a FORMA, não
     * o COMPORTAMENTO (classe LC-11, banida em proibicoes.md §5 2026-07-27).
     */
    public static function avisoDegradacao(?string $status): string
    {
        return RetrievalStatus::aviso(self::degradado($status));
    }

    /**
     * Renderiza bloco "FONTES" pro prompt do LLM. Cada doc vira bloco markdown
     * numerado com slug + title + path + trecho do corpo.
     *
     * O TRECHO e a janela que CONTEM O MATCH (nao mais os primeiros 400 chars).
     * Medido no CT 100 staging contra o gold-set (N=51, corpus 2555 docs, 2026-09-04):
     * o doc certo JA ESTAVA no top-10 em 98% dos casos (cobertura lexica do
     * ground_truth = 0,9805 contra o doc inteiro), mas o excerpt de 400 chars pegava
     * o CABECALHO — titulo + preambulo — e entregava 0,3311 ao juiz. O retriever nao
     * era o gargalo; a MONTAGEM do contexto era. Curva medida:
     *
     *   orcamento | head (antigo) | janela centrada
     *   400       | 0,3311        | 0,4850
     *   800       | 0,4871        | 0,6039
     *   1200      | 0,6034        | 0,6992
     *
     * Centrar rende ~1,5-2x por token: centrada@400 (0,485) empata com head@800
     * (0,487) pela metade do orcamento. Mesma tecnica que o irmao
     * {@see \Modules\KB\Services\KbCorpusBuilder} ja usa via Meilisearch
     * (`attributesToCrop`/`cropLength`/`_formatted`) — trazida pra ca, nao inventada.
     *
     * `$pergunta` vazia mantem o comportamento antigo (head) byte-a-byte, pra quem
     * chama sem contexto de query.
     *
     * @param  Collection<int,McpMemoryDocument>  $docs
     */
    public function renderFontes(Collection $docs, string $pergunta = '', ?int $maxLen = null): string
    {
        $maxLen ??= self::EXCERPT_CHARS;
        $blocos = [];
        $i = 1;

        foreach ($docs as $doc) {
            $path = $doc->git_path ?: "memory/{$doc->type}s/{$doc->slug}.md";
            $excerpt = $pergunta === ''
                ? $this->extrairExcerpt($doc->content_md ?? '', $maxLen)
                : $this->extrairJanela($doc->content_md ?? '', $pergunta, $maxLen);

            $blocos[] = sprintf(
                "### Fonte #%d — `%s`\n**%s** _(tipo: %s · módulo: %s)_\nPath: `%s`\n\n%s",
                $i++,
                $doc->slug,
                $doc->title ?? $doc->slug,
                $doc->type,
                $doc->module ?? 'core',
                $path,
                $excerpt,
            );
        }

        return implode("\n\n---\n\n", $blocos);
    }

    /**
     * FASE 2 — Síntese IA (laravel/ai SDK, gpt-4o-mini, ADR 0035). Single-shot,
     * sem tools. Devolve o markdown cru do agent ("Resposta:/Citações:/Confiança:").
     *
     * Lança exceção do provider IA pra cima — o CHAMADOR decide fallback. A Tool
     * cai em `fallbackSemIa`; o RAGAS eval conta como falha (NUNCA tautologia).
     */
    public function synthesize(string $pergunta, string $fontes, int $maxCitacoes = 5): string
    {
        $agent = new KbAnswerAgent(
            pergunta: $pergunta,
            fontes: $fontes,
            maxCitacoes: $maxCitacoes,
        );

        return trim((string) $agent->prompt($agent->montarPrompt()));
    }

    /**
     * Extrai excerpt pulando frontmatter YAML (mesmo critério do
     * `McpMemoryDocument::toSearchableArray`).
     */
    public function extrairExcerpt(string $body, int $maxLen): string
    {
        $semFrontmatter = preg_replace('/^\s*---\n.*?\n---\n?/s', '', $body);
        $clean = trim($semFrontmatter ?? '');

        if (mb_strlen($clean) <= $maxLen) {
            return $clean;
        }

        return mb_substr($clean, 0, $maxLen).'...';
    }

    /**
     * Dobra pra busca: minusculas + acentos removidos, PRESERVANDO a contagem de
     * caracteres — cada char acentuado vira exatamente 1 char ASCII.
     *
     * O alinhamento 1:1 e o que torna o offset da janela confiavel: `mb_strpos` na
     * string dobrada devolve indice de CARACTERE valido pra `mb_substr` no original.
     * (Fold que muda o comprimento desalinha o corte — o texto sai deslocado.)
     */
    protected function foldParaBusca(string $texto): string
    {
        $de = 'áàâãäéèêëíìîïóòôõöúùûüçñÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇÑ';
        $para = 'aaaaaeeeeiiiiooooouuuucnAAAAAEEEEIIIIOOOOOUUUUCN';
        $mapa = [];
        $dc = mb_str_split($de);
        $pc = mb_str_split($para);
        foreach ($dc as $i => $ch) {
            $mapa[$ch] = $pc[$i];
        }

        return mb_strtolower(strtr($texto, $mapa), 'UTF-8');
    }

    /**
     * Termos uteis da pergunta (>=5 chars, sem stopword PT-BR), ja dobrados.
     *
     * @return list<string>
     */
    protected function termosDaPergunta(string $pergunta): array
    {
        $partes = preg_split('/[^a-z0-9]+/', $this->foldParaBusca($pergunta), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($partes as $parte) {
            if (mb_strlen($parte) < 5 || in_array($parte, self::STOPWORDS, true)) {
                continue;
            }
            $out[$parte] = true;
        }

        return array_keys($out);
    }

    /**
     * Extrai a janela de `$maxLen` chars que MAIS cobre os termos da pergunta.
     *
     * Sem termo casado (ou pergunta sem termo util) cai no `extrairExcerpt` — o
     * comportamento antigo continua sendo o piso, nunca uma janela pior que ele.
     *
     * @param  string  $pergunta  query do usuario; os termos dela definem o centro
     */
    public function extrairJanela(string $body, string $pergunta, int $maxLen): string
    {
        $semFrontmatter = preg_replace('/^\s*---\n.*?\n---\n?/s', '', $body);
        $clean = trim($semFrontmatter ?? '');

        if ($clean === '' || mb_strlen($clean) <= $maxLen) {
            return $clean;
        }

        $termos = $this->termosDaPergunta($pergunta);
        if ($termos === []) {
            return $this->extrairExcerpt($body, $maxLen);
        }

        $hay = $this->foldParaBusca($clean);
        $posicoes = [];
        foreach ($termos as $termo) {
            $off = 0;
            while (($p = mb_strpos($hay, $termo, $off)) !== false) {
                $posicoes[] = $p;
                $off = $p + 1;
                if (count($posicoes) >= self::MAX_POSICOES_JANELA) {
                    break 2;
                }
            }
        }

        if ($posicoes === []) {
            return $this->extrairExcerpt($body, $maxLen);
        }

        sort($posicoes);

        // Janela deslizante: comeca em cada match e conta quantos cabem adiante.
        $melhorInicio = $posicoes[0];
        $melhorCnt = -1;
        foreach ($posicoes as $inicio) {
            $cnt = 0;
            foreach ($posicoes as $pos) {
                if ($pos >= $inicio && $pos < $inicio + $maxLen) {
                    $cnt++;
                }
            }
            if ($cnt > $melhorCnt) {
                $melhorCnt = $cnt;
                $melhorInicio = $inicio;
            }
        }

        // Recua um quarto do orcamento pra dar contexto ANTES do match.
        $ini = max(0, $melhorInicio - (int) ($maxLen * 0.25));
        $trecho = mb_substr($clean, $ini, $maxLen);

        return ($ini > 0 ? '...' : '').$trecho.($ini + $maxLen < mb_strlen($clean) ? '...' : '');
    }

    /**
     * Fallback determinístico quando IA falha — markdown estruturado só com
     * snippets recuperados (sem síntese). Garante que a Tool nunca crasha por
     * provider IA indisponível. NÃO usado pelo RAGAS eval (lá, falha = sinal).
     *
     * @param  Collection<int,McpMemoryDocument>  $docs
     */
    public function fallbackSemIa(string $pergunta, Collection $docs, int $maxCitacoes): string
    {
        $topDocs = $docs->take($maxCitacoes);

        $out = "Resposta: Síntese IA indisponível no momento — devolvo os {$topDocs->count()} docs mais relevantes pra \"{$pergunta}\". Confira manualmente.\n\n";
        $out .= "Citações:\n";

        foreach ($topDocs as $doc) {
            $path = $doc->git_path ?: "memory/{$doc->type}s/{$doc->slug}.md";
            $quote = mb_substr(trim(preg_replace('/\s+/', ' ', $doc->content_md ?? '')), 0, 120);
            $out .= "- [{$doc->slug}]({$path}) — {$quote}\n";
        }

        $out .= "\nConfiança: baixa";

        return $out;
    }
}
