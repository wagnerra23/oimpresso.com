<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Jana\Entities\Meta;
use App\Scopes\ScopeByBusiness;

/**
 * Visão superadmin — metas da plataforma (`business_id NULL`) e metas de clientes.
 * Ver adr/arq/0001-tenancy-hibrida.md.
 *
 * ⚠️ "STUB spec-ready" saiu em 2026-08-27: o método está implementado (gate
 * `jana.superadmin` + `withoutGlobalScope` deliberado, que é o caso legítimo do
 * ADR 0093). O que de fato NÃO existe é a **agregação** cross-business prometida no
 * docblock antigo — medido em 2026-08-27: nenhum `sum`/`count`/`groupBy` aqui; as
 * duas coleções são listadas cruas. Essa metade fica como pendência declarada.
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

        $metasPlataforma = Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNull('business_id')
            ->get();

        $metasDeClientes = Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNotNull('business_id')
            ->with('periodoAtual', 'ultimaApuracao')
            ->get();

        return view('copiloto::superadmin.metas', compact('metasPlataforma', 'metasDeClientes'));
    }
}
