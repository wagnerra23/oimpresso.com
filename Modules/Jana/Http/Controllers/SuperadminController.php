<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Scopes\ScopeByBusiness;
use App\System;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaPeriodo;

/**
 * Visão superadmin — metas da plataforma (`business_id NULL`) e metas de clientes.
 * Ver adr/arq/0001-tenancy-hibrida.md.
 *
 * 2026-09-02: virou a aba **Plataforma** da área Jana (Inertia `Jana/Plataforma`),
 * 6ª aba da âncora `jana-merge.jsx` §JmTabs — o Blade AdminLTE cru saiu. O gate
 * abaixo é o do P0 #6421 (2026-08-28), intacto. O que de fato NÃO existe é a
 * **agregação** cross-business que o docblock antigo prometia — medido em
 * 2026-08-27: nenhum `sum`/`count`/`groupBy` aqui; as duas coleções são listadas
 * cruas, e a tela diz isso em letra (âncora `jana-telas-novas.jsx` §JmPlataforma).
 */
class SuperadminController extends Controller
{
    public function metas(Request $request)
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
        // os tenants. E o link ainda lhe aparecia no menu.
        //
        // São DUAS portas legítimas, e nenhuma delas é o `can()` sozinho:
        //  (a) `hasPermissionTo` consulta o Spatie DIRETO, sem passar pelo Gate.
        //  (b) `user_type` é COLUNA, não ability: o `Gate::before` não a alcança.
        //      ⚠️ ERRATA 2026-09-03 (medida, não lida): esta porta é INALCANÇÁVEL aqui. O
        //      grupo `/ia` carrega o middleware `CheckUserLogin` (Modules/Jana/Http/routes.php),
        //      que faz `if ($request->user()->user_type != 'user' ...) abort(403)` — qualquer
        //      `user_type` fora de 'user' leva 403 ANTES desta linha rodar. Achado no 1º run
        //      real do `SuperadminMetasCrossTenantTest` (nunca tinha rodado em lane nenhuma),
        //      que afirmava o contrário e foi corrigido lá. Em prod (2026-08-31): ZERO usuários
        //      com esse user_type; os 5 que alcançam a tela entram pela porta (a). Removê-la
        //      é decisão [W] — mexe no gate de uma tela Tier 0. Fica, com o alcance declarado.
        // Mesmo espírito de Admin/JanaProController.php:48. A aba do `DataController`
        // (`podeVerPlataforma`) espelha estas duas portas — menu e rota concordam.
        //
        // O `withoutGlobalScope` segue deliberado e correto para quem de fato é
        // superadmin (o caso legítimo do ADR 0093): o que estava errado era o QUEM.
        try {
            $temPermissaoReal = (bool) $user?->hasPermissionTo('jana.superadmin');
        } catch (\Throwable $e) {
            // Permissão não cadastrada no guard ⇒ ninguém a tem. Fail-closed.
            $temPermissaoReal = false;
        }

        $ehSuperadmin = in_array($user?->user_type, ['superadmin', 'user_oimpresso'], true);

        abort_unless($temPermissaoReal || $ehSuperadmin, 403);

        // SUPERADMIN: visão da plataforma — o caso legítimo do ADR 0093 (gate acima).
        $metasPlataforma = Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNull('business_id')
            ->orderBy('nome')
            ->get();

        // SUPERADMIN: cross-business por desenho — listagem CRUA, sem agregação.
        $metasDeClientes = Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNotNull('business_id')
            ->orderBy('business_id')->orderBy('nome')
            ->get();

        // POR QUE NÃO HÁ EAGER LOAD de `periodoAtual`/`ultimaApuracao` — bug de PRODUÇÃO,
        // achado por teste (UC-PLAT-03, lane MySQL, 2026-09-03), não por leitura:
        // `MetaPeriodo` e `MetaApuracao` não têm `business_id`; usam `BelongsToBusinessViaParent`,
        // que aplica `ScopeByBusinessViaParent` NELAS. Tirar o escopo só da `Meta` conserta a
        // lista, mas o `->with(...)` seguia filtrando as filhas pela sessão: para toda meta de
        // OUTRO tenant, `periodo` e `ultima` voltavam null — as duas colunas que a tela existe
        // para mostrar chegariam vazias ("—" / "nunca apurada"), sem erro nenhum.
        // E `->with(['ultimaApuracao' => fn ($q) => $q->withoutGlobalScopes()])` também cai:
        // `ultimaApuracao` é `latestOfMany`, e o `ofMany` monta a subquery com uma instância
        // NOVA do model, que nasce com o escopo de volta. Por isso as filhas viram duas
        // queries explícitas, agregadas, sem N+1, partindo das `Meta` já resolvidas — tocar
        // apuração a partir de id cru de URL seria o vazamento que o RUNBOOK §6 nomeia.
        $ids = $metasDeClientes->pluck('id')->all();

        // Predicado IGUAL ao de `Meta::periodoAtual()` (`where`, não `whereDate`): a mudança é
        // de caminho, não de resultado. A borda "período que termina HOJE não casa" (DATE vs
        // now() com hora) é pré-existente na relação e fica declarada, não consertada aqui.
        // SUPERADMIN: visão de plataforma (ADR 0093 — o caso legítimo de sair do escopo).
        $periodos = MetaPeriodo::withoutGlobalScopes()
            ->whereIn('meta_id', $ids)
            ->where('data_ini', '<=', now())
            ->where('data_fim', '>=', now())
            ->get()
            ->keyBy('meta_id');

        // SUPERADMIN: idem — `MAX(data_ref)` agregado no lugar da relação `latestOfMany`.
        $ultimas = MetaApuracao::withoutGlobalScopes()
            ->whereIn('meta_id', $ids)
            ->selectRaw('meta_id, MAX(data_ref) as ultima_data_ref')
            ->groupBy('meta_id')
            ->pluck('ultima_data_ref', 'meta_id');

        // SUPERADMIN: nome das empresas — só o que a tabela da tela mostra.
        $empresas = \App\Business::whereIn('id', $metasDeClientes->pluck('business_id')->unique()->all())
            ->pluck('name', 'id');

        $data = fn ($d) => $d ? substr((string) $d, 0, 10) : null;

        return Inertia::render('Jana/Plataforma', [
            'metasPlataforma' => $metasPlataforma->map(fn (Meta $m) => [
                'id' => $m->id, 'nome' => $m->nome, 'slug' => $m->slug, 'unidade' => $m->unidade, 'origem' => $m->origem,
            ])->values(),
            'metasDeClientes' => $metasDeClientes->map(function (Meta $m) use ($empresas, $data, $periodos, $ultimas) {
                /** @var MetaPeriodo|null $periodo */
                $periodo = $periodos->get($m->id);
                // `MAX()` volta string do banco — a string já É a data; `$data` só corta Y-m-d.
                $ultima = $ultimas->get($m->id);

                return [
                    'id'          => $m->id,
                    'business_id' => $m->business_id,
                    'empresa'     => $empresas->get($m->business_id),
                    'nome'        => $m->nome,
                    'unidade'     => $m->unidade,
                    'periodo'     => $periodo ? ['data_ini' => $data($periodo->data_ini), 'data_fim' => $data($periodo->data_fim)] : null,
                    'ultima'      => $data($ultima),
                ];
            })->values(),
            // Bloco "Instalação do módulo" — contagens DERIVADAS do disco/registry, não
            // digitadas (a âncora carrega "21 · 4 · 24" fixos; o 24 já estava errado: são 22).
            'instalacao' => [
                'migrations'  => count(glob(module_path('Jana', 'Database/Migrations/*.php')) ?: []),
                'seeders'     => count(glob(module_path('Jana', 'Database/Seeders/*.php')) ?: []),
                'permissoes'  => count((include module_path('Jana', 'Resources/permissions.php'))['permissions'] ?? []),
                'versao'      => System::getProperty('jana_version'),
                // As ações de instalação são `can('superadmin')` REAL (BaseModuleInstallController) —
                // mais estreito que `jana.superadmin`. Sem ele, os botões não nascem.
                'podeOperar'  => (bool) $user?->can('superadmin'),
            ],
            'janaContext' => [
                'businessId'   => (int) $request->session()->get('user.business_id'),
                'businessName' => (string) ($request->session()->get('business.name') ?? ''),
                'userName'     => optional($user)->name,
            ],
        ]);
    }
}
