<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;
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
     * ⚠️ O eager load de `periodoAtual`/`ultimaApuracao` NÃO é conveniência: `MetaApuracao`
     * e `MetaPeriodo` não têm coluna `business_id` (o escopo deles é indireto, via `meta_id`).
     * Partir daqui — da `Meta` já resolvida — é o que mantém a leitura correta; tocar apuração
     * a partir de um `$id` cru vazaria entre tenants (RUNBOOK-plataforma.md §6 item 5).
     */
    private function payloadDeClientes(): \Illuminate\Support\Collection
    {
        return Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNotNull('business_id')
            ->with('periodoAtual', 'ultimaApuracao')
            ->get()
            ->map(fn (Meta $m) => [
                'id'          => $m->id,
                'business_id' => $m->business_id,
                'slug'        => $m->slug,
                'nome'        => $m->nome,
                'unidade'     => $m->unidade,
                // Datas em ISO (Y-m-d), formatadas no front. NÃO usar `format_date`:
                // ele carrega o shift +3h preservado pra clientes legados (ADR 0066),
                // que aqui viraria data errada por fuso numa tela de auditoria.
                'periodo_atual' => $m->periodoAtual ? [
                    'data_ini' => $m->periodoAtual->data_ini?->format('Y-m-d'),
                    'data_fim' => $m->periodoAtual->data_fim?->format('Y-m-d'),
                ] : null,
                'ultima_apuracao' => $m->ultimaApuracao?->data_ref?->format('Y-m-d'),
            ])
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
