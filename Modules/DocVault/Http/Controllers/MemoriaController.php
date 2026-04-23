<?php

namespace Modules\DocVault\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\DocVault\Services\MemoryReader;

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

        return Inertia::render('DocVault/Memoria', [
            'roots'    => $roots,
            'stats'    => $stats,
            'selected' => $selected,
            'paths'    => [
                'project_dir' => (string) config('docvault.memory.project_dir'),
                'claude_dir'  => (string) config('docvault.memory.claude_dir'),
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
