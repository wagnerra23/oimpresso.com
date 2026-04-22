<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\Importacao;
use Modules\PontoWr2\Http\Requests\ImportacaoAfdRequest;
use Modules\PontoWr2\Services\AfdParserService;

class ImportacaoController extends Controller
{
    protected $parser;

    public function __construct(AfdParserService $parser)
    {
        $this->parser = $parser;
    }

    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $paginated = Importacao::where('business_id', $businessId)
            ->with('usuario:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($i) => [
            'id'               => $i->id,
            'tipo'             => $i->tipo,
            'nome_arquivo'     => $i->nome_arquivo,
            'tamanho_bytes'    => (int) $i->tamanho_bytes,
            'estado'           => $i->estado,
            'linhas_processadas' => (int) ($i->linhas_processadas ?? 0),
            'linhas_criadas'   => (int) ($i->linhas_criadas ?? 0),
            'created_at'       => optional($i->created_at)->format('Y-m-d H:i'),
            'created_at_human' => optional($i->created_at)->diffForHumans(),
            'usuario'          => optional($i->usuario)->first_name,
        ]);

        return Inertia::render('Ponto/Importacoes/Index', ['importacoes' => $paginated]);
    }

    public function create(): Response
    {
        return Inertia::render('Ponto/Importacoes/Create');
    }

    public function store(ImportacaoAfdRequest $request): RedirectResponse
    {
        $arquivo = $request->file('arquivo');
        $hash = hash_file('sha256', $arquivo->getRealPath());
        $businessId = session('business.id') ?: $request->user()->business_id;

        // Dedup por hash
        $existente = Importacao::where('business_id', $businessId)
            ->where('hash_arquivo', $hash)
            ->first();

        if ($existente) {
            return back()->withErrors([
                'arquivo' => "Arquivo já foi importado em {$existente->created_at} (ID #{$existente->id}).",
            ]);
        }

        $path = $arquivo->store("ponto/importacoes/{$businessId}", 'local');

        $importacao = Importacao::create([
            'business_id'    => $businessId,
            'tipo'           => $request->input('tipo', 'AFD'),
            'nome_arquivo'   => $arquivo->getClientOriginalName(),
            'arquivo_path'   => $path,
            'hash_arquivo'   => $hash,
            'tamanho_bytes'  => $arquivo->getSize(),
            'usuario_id'     => $request->user()->id,
        ]);

        // Dispara processamento assíncrono
        \Modules\PontoWr2\Jobs\ProcessarImportacaoAfdJob::dispatch($importacao->id);

        return redirect()
            ->route('ponto.importacoes.show', $importacao->id)
            ->with('success', 'Arquivo enfileirado para processamento.');
    }

    public function show(int $id): Response
    {
        $i = Importacao::with('usuario:id,first_name,last_name')->findOrFail($id);

        return Inertia::render('Ponto/Importacoes/Show', [
            'importacao' => [
                'id'                 => $i->id,
                'tipo'               => $i->tipo,
                'nome_arquivo'       => $i->nome_arquivo,
                'hash_arquivo'       => $i->hash_arquivo,
                'tamanho_bytes'      => (int) $i->tamanho_bytes,
                'estado'             => $i->estado,
                'linhas_processadas' => (int) ($i->linhas_processadas ?? 0),
                'linhas_criadas'     => (int) ($i->linhas_criadas ?? 0),
                'linhas_ignoradas'   => (int) ($i->linhas_ignoradas ?? 0),
                'erro_mensagem'      => $i->erro_mensagem,
                'created_at'         => optional($i->created_at)->format('Y-m-d H:i'),
                'updated_at'         => optional($i->updated_at)->format('Y-m-d H:i'),
                'usuario'            => optional($i->usuario)->first_name,
            ],
        ]);
    }

    public function baixarOriginal(int $id)
    {
        $importacao = Importacao::findOrFail($id);
        return Storage::download($importacao->arquivo_path, $importacao->nome_arquivo);
    }
}
