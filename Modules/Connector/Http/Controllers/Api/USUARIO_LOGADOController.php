<?php
namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\USUARIO_LOGADO;

class USUARIO_LOGADOController extends ApiController
{
    public function sync(Request $request)
    {
        // Your sync logic here
    }
    public function getSyncUntilDate(Request $request)
    {
        // Your getSyncUntilDate logic here
    }
}
