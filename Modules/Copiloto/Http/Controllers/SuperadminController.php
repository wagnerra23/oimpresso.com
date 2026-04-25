<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Scopes\ScopeByBusiness;

/**
 * STUB spec-ready: visão superadmin — metas da plataforma (business_id NULL)
 * + agregação cross-business. Ver adr/arq/0001-tenancy-hibrida.md.
 */
class SuperadminController extends Controller
{
    public function metas()
    {
        abort_unless(auth()->user()?->can('copiloto.superadmin'), 403);

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
