<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Copiloto\Contracts\MemoriaContrato;

/**
 * MemoriaController — tela "O Copiloto lembra de você" (US-COPI-MEM-012, LGPD).
 *
 * Sprint 6 do roadmap canônico (ADR 0036 + ADR 0037).
 * Multi-tenant scope obrigatório via session('user.business_id') + auth()->id().
 * Integra com MemoriaContrato (PR #25) — driver default = MeilisearchDriver.
 */
class MemoriaController extends Controller
{
    public function __construct(
        protected MemoriaContrato $memoria,
    ) {
    }

    /**
     * Lista todas as memórias ativas do user logado no business atual.
     */
    public function index(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $userId     = (int) auth()->id();

        $memorias = $this->memoria->listar($businessId, $userId);

        return Inertia::render('Copiloto/Memoria', [
            'memorias' => collect($memorias)->map(fn ($m) => $m->toArray())->values()->all(),
            'businessId' => $businessId,
            'userId' => $userId,
        ]);
    }

    /**
     * Esquece uma memória (soft delete = LGPD opt-out).
     */
    public function destroy(Request $request, int $id)
    {
        $this->memoria->esquecer($id);

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return back()->with('flash.success', 'Memória esquecida.');
        }

        return redirect()->route('copiloto.memoria.index')
            ->with('flash.success', 'Memória esquecida.');
    }

    /**
     * Atualiza fato (supersedes — append-only via valid_until + cria novo).
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'fato' => 'required|string|max:1000',
        ]);

        $this->memoria->atualizar(
            memoriaId: $id,
            novoFato: $request->input('fato'),
            metadata: $request->input('metadata', []) ?? [],
        );

        return back()->with('flash.success', 'Memória atualizada.');
    }
}
