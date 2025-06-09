
<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\UsuarioLog;

class UsuarioLogController extends ApiController
{
    public function syncUsuarioLog(Request $request)
    {
        // Implementação da lógica de sincronização para UsuarioLog
    }

    public function getSyncUsuarioLogUntilDate(Request $request)
    {
        // Implementação da lógica de sincronização incremental para UsuarioLog
    }
}
