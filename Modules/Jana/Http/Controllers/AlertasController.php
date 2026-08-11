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

    /**
     * A gravação NÃO está implementada — e a mensagem de retorno não pode dizer que
     * está. Persistir (em `business.essentials_settings` ou tabela dedicada) e ligar
     * no `AlertaService` é a US-COPI-061; até lá o formulário fica desabilitado na
     * view e esta rota devolve o que de fato aconteceu: nada.
     *
     * O FormRequest continua validando a whitelist (D8.c Wave 17) de propósito —
     * o contrato de entrada não deve regredir enquanto a persistência não chega.
     */
    public function updateConfig(UpdateAlertasConfigRequest $request)
    {
        return redirect()->route('jana.alertas.config')
            ->with('status', 'Ainda não é possível salvar a configuração de alertas — nada foi alterado.');
    }
}
