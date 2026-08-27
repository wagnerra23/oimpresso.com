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
        abort_unless(auth()->user()?->can('jana.superadmin'), 403);

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
