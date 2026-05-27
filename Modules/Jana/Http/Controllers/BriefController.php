<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Stub do Brief diário — placeholder pro ghost canon `/jana/brief` (ADR 0182
 * + GUIA-SIDEBAR-V3 Wagner 2026-05-21).
 *
 * Brief executive diário do business é gerado pelo BriefDiarioAgent (ver
 * Modules/Jana/Ai/Agents/BriefDiarioAgent.php) e exposto via MCP tool
 * `brief-fetch`. UI dedicada pra esse brief será implementada em onda futura;
 * stub mantém o ghost clicável e explica onde achar o brief enquanto isso.
 */
class BriefController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Jana/Brief/Index', [
            'businessId' => $request->session()->get('user.business_id'),
        ]);
    }
}
