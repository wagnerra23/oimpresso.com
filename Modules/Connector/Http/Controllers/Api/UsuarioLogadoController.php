
<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\UsuarioLogado;

class UsuarioLogadoController extends ApiController
{
    public function syncUsuarioLogado(Request $request)
    {
        // Implementação da lógica de sincronização para UsuarioLogado
    }

    public function getSyncUsuarioLogadoUntilDate(Request $request)
    {
        // Implementação da lógica de sincronização incremental para UsuarioLogado
    }
}
