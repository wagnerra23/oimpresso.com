<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Jana\Http\Requests\UpdateAlertasConfigRequest;

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

    public function updateConfig(UpdateAlertasConfigRequest $request)
    {
        // TODO: persistir $request->validated() em business.essentials_settings
        // ou tabela dedicada. FormRequest endurece whitelist (D8.c Wave 17).
        return redirect()->route('jana.alertas.config')
            ->with('status', 'Configuração salva.');
    }
}
