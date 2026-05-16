<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Http\Requests\StoreMetaRequest;
use Modules\Jana\Http\Requests\UpdateMetaRequest;

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

    public function store(StoreMetaRequest $request)
    {
        // D8.c (Wave 14) — FormRequest dedicado substitui validate() inline.
        // Regras endurecidas: slug regex, whitelist unidade/tipo, msgs PT-BR.
        $data = $request->validated();

        $meta = Meta::create(array_merge($data, [
            'ativo'              => true,
            'criada_por_user_id' => auth()->id(),
            'origem'             => 'manual',
        ]));

        return redirect()->route('jana.metas.show', $meta->id);
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

    public function update(UpdateMetaRequest $request, $id)
    {
        // D8.c (Wave 14) — FormRequest valida partial update (sometimes) +
        // whitelist nos enums. Antes era `only([...])` sem validação alguma.
        $meta = Meta::findOrFail($id);
        $meta->update($request->validated());
        return redirect()->route('jana.metas.show', $meta->id);
    }

    public function destroy($id)
    {
        Meta::findOrFail($id)->update(['ativo' => false]);
        return redirect()->route('jana.metas.index');
    }

    /**
     * Força reapuração do range — apaga MetaApuracao do período e reexecuta driver.
     * STUB: implementação real dispara ApurarMetaJob.
     */
    public function reapurar(Request $request, $id)
    {
        // TODO: dispatch(new ApurarMetaJob(Meta::find($id), now()));
        return redirect()->route('jana.metas.show', $id)
            ->with('status', 'Reapuração agendada.');
    }
}
