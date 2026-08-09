<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Http\Requests\StoreMetaRequest;
use Modules\Jana\Http\Requests\UpdateMetaRequest;
use Modules\Jana\Jobs\ApurarMetaJob;

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
     * Enfileira a reapuração da meta na data de hoje (US-COPI-031).
     *
     * Multi-tenant Tier 0 (ADR 0093): quem garante o isolamento aqui é o
     * `Meta::findOrFail` — `MetaApuracao` NÃO tem coluna `business_id` (o scope é
     * indireto, via `meta_id`), então tocar apuração a partir do `$id` cru da URL
     * vazaria entre tenants. Carregue a Meta pelo global scope ANTES de tudo.
     *
     * ⚠️ A outra metade do DoD da US-COPI-031 — "apaga MetaApuracao do range" —
     * segue ABERTA, e não é esquecimento: a rota `POST /ia/metas/{id}/reapurar`
     * não tem parâmetro de range. Apagar todas as apurações pra reexecutar só
     * `now()` destruiria as 12 janelas que a US-COPI-011 exige na tela de detalhe.
     * Como `ApuracaoService::apurar()` é idempotente por
     * (`meta_id`, `data_ref`, `fonte_query_hash`), o dispatch abaixo já sobrescreve
     * a apuração do dia — que é o caso de uso real (correção retroativa de venda).
     * Reapurar um intervalo exige contrato de rota novo: decisão [W].
     */
    public function reapurar(Request $request, $id)
    {
        $meta = Meta::findOrFail($id);

        // businessId explícito: o worker da fila (CT 100) não tem `session()`, e o
        // docblock do próprio Job pede que callers novos passem — defesa em
        // profundidade contra scope drift entre o dispatch e a execução.
        ApurarMetaJob::dispatch($meta, now(), (int) $meta->business_id);

        return redirect()->route('jana.metas.show', $meta->id)
            ->with('status', 'Reapuração enfileirada para hoje. O valor atualiza quando a fila processar.');
    }
}
