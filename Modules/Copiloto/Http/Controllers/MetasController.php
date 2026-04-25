<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaApuracao;

/**
 * STUB spec-ready: resource CRUD de metas. Lógica de filtros, permissões
 * granulares e shape JSON-friendly (ver DoD no SPEC.md) a preencher.
 */
class MetasController extends Controller
{
    public function index(Request $request)
    {
        $metas = Meta::orderByDesc('ativo')->orderBy('nome')->get();
        return view('copiloto::metas.index', compact('metas'));
    }

    public function create()
    {
        return view('copiloto::metas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'           => 'required|string|max:80',
            'nome'           => 'required|string|max:150',
            'unidade'        => 'required|in:R$,qtd,%,dias',
            'tipo_agregacao' => 'required|in:soma,media,ultimo,contagem',
            'business_id'    => 'nullable|integer',
        ]);

        $meta = Meta::create(array_merge($data, [
            'ativo'              => true,
            'criada_por_user_id' => auth()->id(),
            'origem'             => 'manual',
        ]));

        return redirect()->route('copiloto.metas.show', $meta->id);
    }

    public function show($id)
    {
        $meta       = Meta::findOrFail($id);
        $apuracoes  = MetaApuracao::where('meta_id', $id)
            ->orderByDesc('data_ref')
            ->limit(12)
            ->get();

        return view('copiloto::metas.show', compact('meta', 'apuracoes'));
    }

    public function edit($id)
    {
        return view('copiloto::metas.edit', ['meta' => Meta::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $meta = Meta::findOrFail($id);
        $meta->update($request->only(['nome', 'unidade', 'tipo_agregacao']));
        return redirect()->route('copiloto.metas.show', $meta->id);
    }

    public function destroy($id)
    {
        Meta::findOrFail($id)->update(['ativo' => false]);
        return redirect()->route('copiloto.metas.index');
    }

    /**
     * Força reapuração do range — apaga MetaApuracao do período e reexecuta driver.
     * STUB: implementação real dispara ApurarMetaJob.
     */
    public function reapurar(Request $request, $id)
    {
        // TODO: dispatch(new ApurarMetaJob(Meta::find($id), now()));
        return redirect()->route('copiloto.metas.show', $id)
            ->with('status', 'Reapuração agendada.');
    }
}
