<?php

namespace Modules\NFSe\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * NfseController
 *
 * Stub — implementação completa nas US-NFSE-006/008/009/010.
 *
 * Rotas:
 *   GET  /nfse            → index()   (US-008: Pages/Nfse/Index.tsx via Inertia)
 *   GET  /nfse/emitir     → create()  (US-009: Pages/Nfse/Emitir.tsx via Inertia)
 *   POST /nfse/emitir     → store()   (US-006: dispara EmitirNfseJob)
 *   GET  /nfse/{id}       → show()    (US-006: detalhe + status)
 *   POST /nfse/{id}/cancelar → cancelar() (US-010)
 *   GET  /nfse/{id}/pdf   → pdf()     (US-010: proxy DANFSE)
 */
class NfseController extends Controller
{
    public function index()
    {
        // TODO US-008: return Inertia::render('Nfse/Index', [...])
        return response()->json(['message' => 'NFSe — em implementação (US-NFSE-008)'], 501);
    }

    public function create()
    {
        // TODO US-009: return Inertia::render('Nfse/Emitir', [...])
        return response()->json(['message' => 'NFSe — em implementação (US-NFSE-009)'], 501);
    }

    public function store(Request $request)
    {
        // TODO US-006: validar payload + EmitirNfseJob::dispatch()
        return response()->json(['message' => 'NFSe — em implementação (US-NFSE-006)'], 501);
    }

    public function show(int $nfse)
    {
        // TODO US-006: buscar NfseEmissao + retornar status
        return response()->json(['message' => 'NFSe — em implementação (US-NFSE-006)'], 501);
    }

    public function cancelar(Request $request, int $nfse)
    {
        // TODO US-010: NfseEmissaoService::cancelar($nfse, $request->motivo)
        return response()->json(['message' => 'NFSe — em implementação (US-NFSE-010)'], 501);
    }

    public function pdf(int $nfse)
    {
        // TODO US-010: proxy DANFSE do provider
        return response()->json(['message' => 'NFSe — em implementação (US-NFSE-010)'], 501);
    }
}
