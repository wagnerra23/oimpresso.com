<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Scopes\ScopeByBusiness;
use App\System;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;

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
            ->with('periodoAtual', 'ultimaApuracao')
            ->orderBy('business_id')->orderBy('nome')
            ->get();

        // SUPERADMIN: nome das empresas — só o que a tabela da tela mostra.
        $empresas = \App\Business::whereIn('id', $metasDeClientes->pluck('business_id')->unique()->all())
            ->pluck('name', 'id');

        $data = fn ($d) => $d ? substr((string) $d, 0, 10) : null;

        return Inertia::render('Jana/Plataforma', [
            'metasPlataforma' => $metasPlataforma->map(fn (Meta $m) => [
                'id' => $m->id, 'nome' => $m->nome, 'slug' => $m->slug, 'unidade' => $m->unidade, 'origem' => $m->origem,
            ])->values(),
            'metasDeClientes' => $metasDeClientes->map(fn (Meta $m) => [
                'id'          => $m->id,
                'business_id' => $m->business_id,
                'empresa'     => $empresas->get($m->business_id),
                'nome'        => $m->nome,
                'unidade'     => $m->unidade,
                'periodo'     => $m->periodoAtual ? ['data_ini' => $data($m->periodoAtual->data_ini), 'data_fim' => $data($m->periodoAtual->data_fim)] : null,
                'ultima'      => $m->ultimaApuracao ? $data($m->ultimaApuracao->data_ref) : null,
            ])->values(),
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
