<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Copiloto\Entities\Meta;

/**
 * STUB spec-ready: dashboard renderiza lista de metas ativas.
 * Cards com sparkline + farol entram quando as Pages React forem criadas.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->session()->get('user.business_id');

        $metas = Meta::where('ativo', true)
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                  ->orWhereNull('business_id'); // plataforma — se superadmin, global scope deixa passar
            })
            ->with(['periodoAtual', 'ultimaApuracao'])
            ->get();

        // Se não tem nenhuma meta, redireciona ao chat (ver adr/arq/0002).
        if ($metas->isEmpty()) {
            return redirect()->route('copiloto.chat.index')
                ->with('status', 'Nenhuma meta ativa. Converse com o Copiloto pra criar a primeira.');
        }

        return view('copiloto::dashboard.index', compact('metas'));
    }
}
