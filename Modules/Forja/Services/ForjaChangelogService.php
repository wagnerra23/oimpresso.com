<?php

declare(strict_types=1);

namespace Modules\Forja\Services;

use App\Support\Privacy\PiiRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * ForjaChangelogService — aba Changelog do cockpit Forja (/forja/changelog).
 *
 * Projeta "o que shippou" SÓ a partir de fonte real no DB — ADRs/SPECs
 * (mcp_memory_documents) + sessões Claude Code do time (mcp_cc_sessions),
 * mescladas e ordenadas por data desc. PRs e Ondas NÃO têm fonte fácil/
 * confiável no DB neste recorte, então são OMITIDOS (sem dado fantasma —
 * mesma disciplina da Triagem em ForjaController).
 *
 * ── Shape (PARIDADE §11 Onda 9 · ADR 0388 "réplica primeiro") ─────────────────
 * O `ChangelogFeed` de `prototipo-ui/cowork/forja-page.jsx` desenha, por linha:
 * dot + corpo(ref · flags · data | resumo | ator + módulos). Este service serve
 * exatamente esses campos, e cada um vem de COLUNA REAL:
 *
 *   kind        <- type do doc / 'session'          (dot colorido)
 *   id          <- slug do doc / uuid[0..8]         (.fj-feed-ref)
 *   flags       <- tags ∩ {tier-0, breaking}        (.fj-flag-*)
 *   date        <- decided_at / started_at, ISO     (ordenação · tooltip)
 *   date_label  <- a MESMA data em dd/mm            (.fj-feed-when)
 *   title       <- title / summary_auto / 1º prompt (.fj-feed-resumo)
 *   actor       <- decided_by[0] / metadata.actor   (RoleBadge)
 *   modules     <- module do doc                    (.fj-mod)
 *
 * `flags` é FILTRADO pelas duas que o protótipo estiliza (`.fj-flag-tier-0` e
 * `.fj-flag-breaking`) — tag real que o design não sabe desenhar não vira selo
 * sem cor (item em memory/requisitos/Forja/INCONSISTENCIAS-replica.md). Medido
 * no corpus de 2026-09-02: 40 dos 393 ADRs trazem `tier-0`; `breaking`, zero —
 * a classe existe e fica muda até o dado aparecer, o que é o esperado.
 *
 * ── Título de sessão: derivado, nunca inventado ───────────────────────────────
 * Até 2026-09-02 a aba projetava a string fixa "Sessão Claude Code" quando
 * `summary_auto` era vazio — uma parede de linhas idênticas (medido em
 * forja-cockpit-visual-comparison.md §2026-09-02). Agora o título cai, nessa
 * ordem, em (1) `summary_auto`, (2) o PRIMEIRO prompt do usuário na sessão
 * (`mcp_cc_messages.msg_type='user'` por `ts` asc) e (3) string VAZIA — o
 * componente simplesmente não desenha o parágrafo. Sem rótulo sintético.
 *
 * LGPD/PII: o 1º prompt é texto livre. Esta rota é `can:jana.mcp.usage.all`
 * (superadmin) — MAIS estreita que a `/team-mcp/cc-sessions`, que já serve
 * `content_text` inteiro sob `jana.cc.read.team`; logo não há alargamento de
 * exposição. Ainda assim o trecho passa por {@see PiiRedactor} e é cortado em
 * self::TITULO_MAX. A derivação roda DEPOIS do corte da lista (máx. 30 linhas),
 * então são no máximo 30 leituras pontuais no índice `cc_msg_sess_ts_idx`.
 *
 * Multi-tenant Tier 0 (ADR 0093 / ADR 0070): mcp_memory_documents,
 * mcp_cc_sessions e mcp_cc_messages são REPO-WIDE cross-tenant POR DESIGN
 * (governança da plataforma, não per-business) — sem filtro business_id,
 * INTENCIONAL, igual ForjaController / ScopedScorecard / TriageController.
 */
class ForjaChangelogService
{
    /** Teto de linhas projetadas (lista mescla ADR + sessão). */
    private const MAX_ENTRIES = 30;

    /** Quanto puxar de cada fonte antes de mesclar/cortar (folga p/ ordenação). */
    private const PER_SOURCE = 40;

    /**
     * As ÚNICAS flags que o protótipo sabe desenhar (`.fj-flag-tier-0`,
     * `.fj-flag-breaking` em cowork-forja-bundle.css). Tag real fora desta lista
     * não vira selo — renderizá-la daria um `.fj-flag` sem cor nem fundo.
     */
    private const FLAGS_RENDERIZAVEIS = ['tier-0', 'breaking'];

    /** Teto do resumo derivado do 1º prompt (o `.fj-feed-resumo` é 1 parágrafo). */
    private const TITULO_MAX = 160;

    /**
     * Linhas do changelog (máx ~30), ordenadas por data desc.
     *
     * @return array<int, array{kind:string, id:string, title:string, actor:string, date:string, date_label:string, flags:array<int,string>, modules:array<int,string>}>
     */
    public function build(): array
    {
        $entries = array_merge($this->adrEntries(), $this->sessionEntries());

        // Ordena por data desc (string ISO/date ordena lexicograficamente; itens
        // sem data vão pro fim) e corta no teto.
        usort($entries, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $this->hidrataTitulosDeSessao(array_slice($entries, 0, self::MAX_ENTRIES));
    }

    /**
     * ADRs/SPECs aceitos (mcp_memory_documents type in [adr,spec]) por decided_at desc.
     *
     * @return array<int, array<string,mixed>>
     */
    private function adrEntries(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        if (! Schema::hasTable('mcp_memory_documents')) {
            return [];
        }

        return McpMemoryDocument::query()
            ->whereIn('type', ['adr', 'spec'])
            ->orderByDesc('decided_at')
            ->limit(self::PER_SOURCE)
            ->get(['slug', 'type', 'title', 'decided_at', 'decided_by', 'module', 'tags'])
            ->map(function (McpMemoryDocument $d): array {
                // decided_by é cast array (frontmatter); 1º autor vira o selo de ator.
                $by = is_array($d->decided_by) ? $d->decided_by : [];
                $actor = isset($by[0]) && trim((string) $by[0]) !== ''
                    ? (string) $by[0]
                    : strtoupper((string) $d->type);

                return [
                    'kind'       => 'adr',
                    'id'         => $this->adrId($d),
                    'title'      => (string) $d->title,
                    'actor'      => $actor,
                    'date'       => optional($d->decided_at)->toIso8601String() ?? '',
                    'date_label' => optional($d->decided_at)->format('d/m') ?? '',
                    'flags'      => $this->flagsDe($d->tags),
                    'modules'    => $this->modulesDe($d->module),
                ];
            })
            ->all();
    }

    /**
     * Sessões Claude Code do time (mcp_cc_sessions) por started_at desc.
     *
     * O título sai vazio aqui quando não há `summary_auto`; quem o deriva do 1º
     * prompt é {@see hidrataTitulosDeSessao}, DEPOIS do corte — pra não ler
     * mensagem de sessão que nem entra na lista. A chave `_session_id` é interna
     * e some antes de virar payload.
     *
     * @return array<int, array<string,mixed>>
     */
    private function sessionEntries(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        if (! Schema::hasTable('mcp_cc_sessions')) {
            return [];
        }

        return McpCcSession::query()
            ->orderByDesc('started_at')
            ->limit(self::PER_SOURCE)
            ->get(['id', 'session_uuid', 'summary_auto', 'started_at', 'git_branch', 'metadata'])
            ->map(function (McpCcSession $s): array {
                return [
                    'kind'        => 'session',
                    'id'          => $this->sessionId($s),
                    'title'       => $this->encurta((string) ($s->summary_auto ?? '')),
                    'actor'       => $this->sessionActor($s),
                    'date'        => optional($s->started_at)->toIso8601String() ?? '',
                    'date_label'  => optional($s->started_at)->format('d/m') ?? '',
                    'flags'       => [],
                    // Sessão não tem coluna de módulo — `project_path` é o caminho do
                    // repo e `git_branch` é nome de branch; nenhum dos dois É módulo.
                    // Vazio honesto (o protótipo simplesmente não desenha o chip).
                    'modules'     => [],
                    '_session_id' => (int) $s->id,
                ];
            })
            ->all();
    }

    /**
     * Preenche o título das sessões que ficaram sem `summary_auto`, usando o
     * PRIMEIRO prompt do usuário daquela sessão. Sem prompt -> título vazio (o
     * componente omite o parágrafo em vez de inventar rótulo).
     *
     * @param  array<int, array<string,mixed>>  $entries
     * @return array<int, array<string,mixed>>
     */
    private function hidrataTitulosDeSessao(array $entries): array
    {
        $temMensagens = Schema::hasTable('mcp_cc_messages');

        foreach ($entries as $i => $e) {
            $sid = $e['_session_id'] ?? null;
            unset($entries[$i]['_session_id']);

            if ($sid === null || $e['title'] !== '' || ! $temMensagens) {
                continue;
            }

            $entries[$i]['title'] = $this->encurta($this->primeiroPrompt((int) $sid));
        }

        return array_values($entries);
    }

    /**
     * 1º prompt do usuário de uma sessão (índice cc_msg_sess_ts_idx). String
     * vazia quando a sessão não tem mensagem de usuário com texto.
     */
    private function primeiroPrompt(int $sessionId): string
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        $texto = DB::table('mcp_cc_messages')
            ->where('session_id', $sessionId)
            ->where('msg_type', 'user')
            ->whereNotNull('content_text')
            ->orderBy('ts')
            ->value('content_text');

        return trim((string) ($texto ?? ''));
    }

    /**
     * Normaliza texto livre pra caber numa linha do feed: redige PII, colapsa
     * quebras e corta em self::TITULO_MAX preservando palavra.
     */
    private function encurta(string $texto): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if ($texto === '') {
            return '';
        }

        $texto = app(PiiRedactor::class)->redact($texto);

        if (mb_strlen($texto) <= self::TITULO_MAX) {
            return $texto;
        }

        $corte = mb_substr($texto, 0, self::TITULO_MAX);
        $espaco = mb_strrpos($corte, ' ');
        if ($espaco !== false && $espaco > (int) (self::TITULO_MAX * 0.6)) {
            $corte = mb_substr($corte, 0, $espaco);
        }

        return rtrim($corte) . '…';
    }

    /**
     * Flags renderizáveis do doc — interseção das `tags` reais com as que o
     * protótipo estiliza. Sem tag conhecida -> [] (nenhum selo).
     *
     * @return array<int, string>
     */
    private function flagsDe(mixed $tags): array
    {
        $out = [];

        foreach (is_array($tags) ? $tags : [] as $tag) {
            $tag = mb_strtolower(trim((string) $tag));
            if (in_array($tag, self::FLAGS_RENDERIZAVEIS, true) && ! in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }

        return $out;
    }

    /**
     * Módulos do doc — a coluna `module` guarda UM módulo (ou nada). O frontmatter
     * legado grava a string literal 'null' em parte do corpus (17 ADRs medidos em
     * 2026-09-02), que aqui conta como ausência.
     *
     * @return array<int, string>
     */
    private function modulesDe(mixed $module): array
    {
        $m = trim((string) ($module ?? ''));

        return ($m === '' || mb_strtolower($m) === 'null') ? [] : [$m];
    }

    /** ID curto da ADR/SPEC: slug se houver, senão "ADR/SPEC <título>" como fallback. */
    private function adrId(McpMemoryDocument $d): string
    {
        $slug = trim((string) ($d->slug ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        return strtoupper((string) $d->type);
    }

    /** ID curto da sessão: 8 primeiros chars do uuid (igual list_sessions/cc-search). */
    private function sessionId(McpCcSession $s): string
    {
        $uuid = (string) ($s->session_uuid ?? '');

        return $uuid !== '' ? substr($uuid, 0, 8) : 'sess';
    }

    /**
     * Selo de ator da sessão: lê do dado se houver (metadata.actor), senão 'CL'
     * (Claude — sem inventar nome de pessoa quando o dado não traz).
     */
    private function sessionActor(McpCcSession $s): string
    {
        $meta = is_array($s->metadata) ? $s->metadata : [];
        $actor = isset($meta['actor']) ? trim((string) $meta['actor']) : '';

        return $actor !== '' ? $actor : 'CL';
    }
}
