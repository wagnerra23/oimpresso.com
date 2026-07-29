<?php

namespace Modules\SRS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SRS\Services\MemoryReader;

/**
 * @deprecated since 2026-07-29 (ADR 0357) — módulo em deprecação, remoção prevista em E5.
 *             Sucessor: `Modules\KB\Http\Controllers\MemoriaController` (já absorveu
 *             `/copiloto/admin/memoria`; este vira 301 em E4). DEPRECATION-PLAN §Fase 2 item 5.
 *             Não abrir feature nova aqui; segue servindo em prod até E5.
 */
class MemoriaController extends Controller
{
    public function index(Request $request, MemoryReader $reader): Response
    {
        $roots = $reader->listRoots();
        $stats = $reader->stats();

        // Se um arquivo específico foi solicitado via ?key=..., já carrega o conteúdo
        $selected = null;
        if ($key = $request->query('key')) {
            $selected = $reader->readFile($key);
        }

        return Inertia::render('MemCofre/Memoria', [
            'roots'    => $roots,
            'stats'    => $stats,
            'selected' => $selected,
            'paths'    => [
                'project_dir' => (string) config('memcofre.memory.project_dir'),
                'claude_dir'  => (string) config('memcofre.memory.claude_dir'),
            ],
        ]);
    }

    public function file(Request $request, MemoryReader $reader): JsonResponse
    {
        $key = (string) $request->query('key');
        $data = $reader->readFile($key);
        if (! $data) {
            return response()->json(['error' => 'Arquivo não encontrado ou caminho inválido.'], 404);
        }
        return response()->json($data);
    }
}
