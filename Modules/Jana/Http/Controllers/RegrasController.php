<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Stub das Regras do Copiloto — placeholder pro ghost canon `/jana/regras`
 * (ADR 0182 + GUIA-SIDEBAR-V3 Wagner 2026-05-21).
 *
 * "Regras" cobre policies do PolicyEngine ADS (ALLOW_BRAIN_A / REQUIRE_HUMAN_REVIEW
 * etc.) + governance MCP cross-team. UI dedicada vem em onda futura; stub mantém
 * o ghost clicável e aponta pra `/jana/admin/governanca` enquanto isso.
 */
class RegrasController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Jana/Regras/Index', [
            'businessId' => $request->session()->get('user.business_id'),
        ]);
    }
}
