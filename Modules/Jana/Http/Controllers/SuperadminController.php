<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaPeriodo;
use App\Scopes\ScopeByBusiness;

/**
 * Visão superadmin — metas da plataforma (`business_id NULL`) e metas de clientes.
 * Ver adr/arq/0001-tenancy-hibrida.md.
 *
 * F3 do MWART (ADR 0104) em 2026-08-31: a view Blade AdminLTE virou
 * `Inertia::render('Jana/Plataforma')`. F1 = memory/requisitos/Jana/RUNBOOK-plataforma.md.
 *
 * ⚠️ A **agregação** cross-business que o docblock antigo prometia continua NÃO
 * existindo — medido em 2026-08-27 e re-medido em 2026-08-31: nenhum `sum`/`count`/
 * `groupBy` aqui. As duas coleções são listadas cruas, e isso é **deliberado**: somar
 * na tela inventaria um total de plataforma que ninguém definiu. A fonte de design toma
 * a mesma posição e escreve a razão na própria tela (`jana-telas-novas.jsx` §JmPlataforma:
 * *"Somar aqui na tela seria inventar total de plataforma no cliente"*). O que a
 * plataforma quer medir é decisão [W] — RUNBOOK-plataforma.md §6.1.
 */
class SuperadminController extends Controller
{
    public function metas()
    {
        $user = auth()->user();

        // ── Tier 0 (ADR 0093) — VAZAMENTO CROSS-TENANT FECHADO EM 2026-08-28 ──────
        //
        // O gate anterior era `abort_unless($user?->can('jana.superadmin'), 403)` e
        // NÃO protegia nada: `Gate::before` (app/Providers/AuthServiceProvider.php:34-47)
        // devolve `true` em QUALQUER ability fora de
        // ['backup','superadmin','manage_modules'] pra quem tem `Admin#{business_id}`.
        // `jana.superadmin` não está nessa allowlist — então TODO DONO DE NEGÓCIO
        // passava aqui e recebia, do `withoutGlobalScope` abaixo, as metas de TODOS
        // os tenants. E o link ainda lhe aparecia no menu
        // (Modules/Jana/Resources/menus/topnav.php:44 — `'can' => 'jana.superadmin'`).
        //
        // São DUAS portas legítimas, e nenhuma delas é o `can()` sozinho:
        //
        //  (a) `hasPermissionTo` consulta o Spatie DIRETO, sem passar pelo Gate —
        //      logo só devolve true pra quem REALMENTE recebeu a permissão, por
        //      papel ou atribuição direta. É a porta cirúrgica.
        //  (b) `user_type` é COLUNA, não ability: o `Gate::before` não a alcança.
        //      Fica como segunda porta pra NÃO trancar a tela no caso de a permissão
        //      nunca ter sido atribuída a ninguém (todos entravam pelo bypass).
        //
        // ⚠️ ERRATA 2026-09-03 — A PORTA (b) É INALCANÇÁVEL AQUI, e isto é medido:
        // o grupo `/ia` carrega o middleware `CheckUserLogin` (routes.php:50), que faz
        // `if ($request->user()->user_type != 'user' ...) abort(403)`. Ou seja, qualquer
        // `user_type` fora de 'user' leva 403 ANTES desta linha rodar. A porta (b) existe
        // no código e não pode ser exercida em nenhuma rota deste grupo.
        // Achado no 1º run real do `SuperadminMetasCrossTenantTest` (ele nunca tinha
        // executado em lane nenhuma), que afirmava o contrário e foi corrigido lá.
        // Bate com a medição de produção de 2026-08-31: ZERO usuários com esse user_type,
        // e os 5 que alcançam a tela entram todos pela porta (a).
        // ⛔ REMOVER a (b) é decisão [W] — mexe no gate de uma tela Tier 0. Ela fica, agora
        // com o alcance declarado em vez de presumido.
        //
        // Mesmo espírito da defesa que o irmão já tinha —
        // Modules/Jana/Http/Controllers/Admin/JanaProController.php:48.
        //
        // ⚠️ MEDIDO EM PRODUÇÃO em 2026-08-31 (o que o #6421 não conseguiu medir sem
        // banco, e que a F1 desta tela mediu): a porta (b) está MORTA — zero usuários
        // com `user_type` em ('superadmin','user_oimpresso'); os 130 são `user`. Quem
        // de fato alcança são 5 usuários do business_id=1, todos pela porta (a), via o
        // papel `Operacional#1`. Ou seja: **a porta (b) é rede de segurança, não a
        // porta em uso** — remover a (a) trancaria a tela pra todo mundo hoje.
        // Recibo: RUNBOOK-plataforma.md §1.1 (com controle positivo de `hasPermissionTo`).
        //
        // O `withoutGlobalScope` segue deliberado e correto para quem de fato é
        // superadmin (o caso legítimo do ADR 0093): o que estava errado era o QUEM.
        abort_unless($this->ehSuperadminDePlataforma(), 403);

        // `Inertia::defer` nas duas props: ambas são `->get()` e uma delas com eager
        // load — o gatilho literal da regra canônica (skill `inertia-defer-default`,
        // RUNBOOK-inertia-defer-pattern). O ganho aqui não é só latência: é o que dá à
        // tela um estado de CARREGANDO de verdade (`<Deferred fallback>`), em vez de
        // um spinner decorativo que nunca corresponde a nada.
        return Inertia::render('Jana/Plataforma', [
            // Contrato da Blade preservado (RUNBOOK-plataforma.md §3): a seção da
            // plataforma mostra Nome · Unidade · Origem, e a de clientes abre com
            // `#{business_id}`. As 2 colunas novas (período atual · última apuração)
            // saem do eager load que JÁ existia e que a Blade não usava.
            'metasPlataforma' => Inertia::defer(fn () => $this->payloadPlataforma()),
            'metasDeClientes' => Inertia::defer(fn () => $this->payloadDeClientes()),
        ]);
    }

    /** Metas da plataforma — `business_id NULL`, o caso legítimo do `withoutGlobalScope`. */
    private function payloadPlataforma(): \Illuminate\Support\Collection
    {
        return Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNull('business_id')
            ->get()
            ->map(fn (Meta $m) => [
                'id'      => $m->id,
                'slug'    => $m->slug,
                'nome'    => $m->nome,
                'unidade' => $m->unidade,
                'origem'  => $m->origem,
            ])
            ->values();
    }

    /**
     * Metas de clientes — cross-business por desenho.
     *
     * POR QUE AQUI NAO HA EAGER LOAD — e as duas tentativas que o run real derrubou.
     *
     * `MetaPeriodo` e `MetaApuracao` NAO tem coluna `business_id`: usam
     * `App\Concerns\BelongsToBusinessViaParent`, que aplica `ScopeByBusinessViaParent`
     * NELAS, via `meta_id`. Consequencia: tirar o escopo so da `Meta` conserta a LISTA,
     * nao as filhas.
     *
     *   1a tentativa — `->with('periodoAtual', 'ultimaApuracao')` puro. O `UC-PLATAF-03`
     *     reprovou no 1o run da lane MySQL (2026-09-03): `null is identical to '2026-09-03'`.
     *     Defeito de PRODUCAO, nao de fixture — a tela mostraria "—" e "nunca apurada" para
     *     TODOS os clientes, justamente as 2 colunas que ela existe para mostrar.
     *   2a tentativa — `->with(['ultimaApuracao' => fn ($q) => $q->withoutGlobalScopes()])`.
     *     Reprovou IGUAL no 2o run. A closure tira o escopo da query externa da relacao, mas
     *     `ultimaApuracao` e `latestOfMany('data_ref')`, e o `ofMany` monta uma SUBQUERY de
     *     agregacao com uma instancia nova do model — que nasce com os scopes de volta.
     *
     * Por isso as filhas viram DUAS queries explicitas, agregadas e sem escopo. Nao e
     * preferencia de estilo: e a forma que nao depende do que o `ofMany` faz por dentro.
     * Segue sem N+1 (uma query cada, `whereIn` nos ids ja resolvidos) e seguindo partindo
     * das `Meta` ja carregadas — tocar apuracao a partir de um `$id` cru de URL seria o
     * vazamento que o RUNBOOK-plataforma.md §6 item 5 nomeia.
     */
    private function payloadDeClientes(): \Illuminate\Support\Collection
    {
        $metas = Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNotNull('business_id')
            ->get();

        $ids = $metas->pluck('id')->all();

        // Período vigente por meta, em UMA query (sem N+1).
        //
        // O predicado é o MESMO de `Meta::periodoAtual()` — `where`, não `whereDate` —
        // de propósito: esta refatoração troca o CAMINHO (relação → query explícita), e
        // não pode trocar o RESULTADO de carona.
        // ⚠️ Achado que fica DECLARADO e não consertado: com `data_fim` sendo DATE e
        // `now()` trazendo hora, um período que termina HOJE já não casa (o MySQL lê
        // `2026-09-03 00:00:00 >= 2026-09-03 12:00:00` como falso). É borda pré-existente
        // da relação, não regressão daqui; consertar muda comportamento e pede teste
        // próprio — não entra numa refatoração de leitura.
        //
        // SUPERADMIN: visão de plataforma (ADR 0093 — caso legítimo de sair do escopo).
        $periodos = MetaPeriodo::withoutGlobalScopes()
            ->whereIn('meta_id', $ids)
            ->where('data_ini', '<=', now())
            ->where('data_fim', '>=', now())
            ->get()
            ->keyBy('meta_id');

        // SUPERADMIN: idem. `MAX(data_ref)` agregado em vez da relação `latestOfMany`.
        $ultimas = MetaApuracao::withoutGlobalScopes()
            ->whereIn('meta_id', $ids)
            ->selectRaw('meta_id, MAX(data_ref) as ultima_data_ref')
            ->groupBy('meta_id')
            ->pluck('ultima_data_ref', 'meta_id');

        return $metas
            ->map(function (Meta $m) use ($periodos, $ultimas) {
                /** @var \Modules\Jana\Entities\MetaPeriodo|null $periodo */
                $periodo = $periodos->get($m->id);
                $ultima = $ultimas->get($m->id);

                return [
                    'id'          => $m->id,
                    'business_id' => $m->business_id,
                    'slug'        => $m->slug,
                    'nome'        => $m->nome,
                    'unidade'     => $m->unidade,
                    // Datas em ISO (Y-m-d), formatadas no front. NÃO usar `format_date`:
                    // ele carrega o shift +3h preservado pra clientes legados (ADR 0066),
                    // que aqui viraria data errada por fuso numa tela de auditoria.
                    'periodo_atual' => $periodo ? [
                        'data_ini' => $periodo->data_ini?->format('Y-m-d'),
                        'data_fim' => $periodo->data_fim?->format('Y-m-d'),
                    ] : null,
                    // `MAX()` volta string do banco — normaliza pra Y-m-d sem passar por
                    // timezone (a string já É a data; `Carbon::parse` aqui só arriscaria shift).
                    'ultima_apuracao' => $ultima ? substr((string) $ultima, 0, 10) : null,
                ];
            })
            ->values();
    }

    /**
     * As DUAS portas do gate, num lugar só — porque o ghost do menu precisa fazer a
     * MESMA pergunta que a rota (RUNBOOK-plataforma.md §4).
     *
     * Sem isto, o item da faixa de abas usaria `can('jana.superadmin')` (o predicado do
     * dropdown legacy) e apareceria pra todo dono de negócio, que levaria 403 ao clicar:
     * aba visível que não abre é pior que aba ausente.
     *
     * ⛔ `can('jana.superadmin')` NÃO serve como gate — o `Gate::before` o torna `true`
     * pra qualquer `Admin#{business_id}`. Ver o bloco Tier 0 em `metas()`.
     */
    public static function podeVerPlataforma(?\App\User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            $temPermissaoReal = (bool) $user->hasPermissionTo('jana.superadmin');
        } catch (\Throwable $e) {
            // Permissão não cadastrada no guard ⇒ ninguém a tem. Fail-closed.
            $temPermissaoReal = false;
        }

        return $temPermissaoReal
            || in_array($user->user_type, ['superadmin', 'user_oimpresso'], true);
    }

    private function ehSuperadminDePlataforma(): bool
    {
        return self::podeVerPlataforma(auth()->user());
    }
}
