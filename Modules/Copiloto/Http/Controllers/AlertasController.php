<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** STUB spec-ready: listar + configurar alertas. */
class AlertasController extends Controller
{
    public function index()
    {
        return view('copiloto::alertas.index');
    }

    public function config()
    {
        return view('copiloto::alertas.config');
    }

    public function updateConfig(Request $request)
    {
        // TODO: persistir config em business.essentials_settings ou tabela dedicada.
        return redirect()->route('copiloto.alertas.config')
            ->with('status', 'Configuração salva.');
    }
}
