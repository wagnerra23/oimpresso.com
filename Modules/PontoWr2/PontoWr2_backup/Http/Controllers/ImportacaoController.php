<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $importacoes = Importacao::where('business_id', $businessId)
            ->with('usuario')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('pontowr2::importacoes.index', compact('importacoes'));
    }

    public function create(): View
    {
        return view('pontowr2::importacoes.create');
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

    public function show(int $id): View
    {
        $importacao = Importacao::with('usuario')->findOrFail($id);
        return view('pontowr2::importacoes.show', compact('importacao'));
    }

    public function baixarOriginal(int $id)
    {
        $importacao = Importacao::findOrFail($id);
        return Storage::download($importacao->arquivo_path, $importacao->nome_arquivo);
    }
}
