<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Social\DadosSociais;

/**
 * Dados sociais do paciente na ficha (US-FISIO-061). Todos opcionais. Gravar exige
 * `fisio.editar`; paciente de outra empresa responde 404 pelo escopo do model (ADR 0093).
 */
class DadosSociaisController extends Controller
{
    public function update(Request $request, int $id, DadosSociais $social): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        // Filtro de empresa explícito além do escopo do model (Tier 0, ADR 0093): outra empresa → 404.
        $businessId = (int) $request->session()->get('user.business_id');
        $paciente = Patient::query()->where('business_id', $businessId)->findOrFail($id);

        $dados = $request->validate($social->regras());
        $social->gravar($paciente, $dados, (int) $request->user()->id);

        return back()->with('success', 'Dados sociais atualizados.');
    }
}
