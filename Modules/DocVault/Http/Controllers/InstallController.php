<?php

namespace Modules\DocVault\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InstallController extends Controller
{
    public function index(Request $request)
    {
        return response('DocVault instalado. Tabelas docs_* já migradas via module:migrate.', 200);
    }
}
