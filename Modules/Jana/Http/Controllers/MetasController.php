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
 * Resource CRUD de metas (US-COPI-011 detalhe · 012 criar · 013 editar). Rotas vivas sob `/ia/metas`.
 *
 * ⚠️ O rótulo "STUB spec-ready" saiu daqui em 2026-08-27 porque era FALSO — e é a
 * mesma classe que o `JanaViewsSemAndaimeTest` impede de chegar ao cliente, só que
 * no docblock: afirmação em PRESENTE sobre o próprio estado, que apodrece
 * (§5 2026-08-17). O que o método faz hoje, medido: CRUD completo, `StoreMetaRequest`
 * e `UpdateMetaRequest` validando (D8.c Wave 14), gate Tier 0 via `findOrFail`.
 *
 * A LISTA de pendências do docblock antigo continua verdadeira, e por isso fica —
 * verificada em 2026-08-27, não herdada: `index()` não filtra (um `orderBy` só,
 * sem `Request`), não há permissão por ação (o grupo inteiro passa por
 * `can:jana.access`) e o retorno é Blade nas 4 telas, não JSON. Ver DoD no SPEC.md.
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

    /**
     * Tier 0 (ADR 0093) — o DONO da meta é decidido pelo servidor, nunca pelo payload.
     *
     * É o gate que o docblock do `StoreMetaRequest` já descrevia — *"se business_id veio
     * no payload, controller verifica que matcha session OU user é superadmin antes de
     * persistir"* — e que o controller nunca executava. Mesma família do IDOR que o
     * #4474 fechou no `PeriodosController`, aqui no eixo **ESCRITA**: `HasBusinessScope`
     * só adiciona global scope de SELECT, e nenhum scope cobre INSERT.
     *
     * DOIS defeitos medidos no CI (MySQL real) pelo baseline da F2, com a mesma raiz:
     *  1. sem `business_id` no payload, o campo não era preenchido e a coluna (que é
     *     `nullable`) ficava **NULL** — a meta nascia "de plataforma": sumia da lista do
     *     próprio dono (o scope filtra `business_id = sessão`) e aparecia para superadmin
     *     de QUALQUER tenant (o scope faz `orWhereNull`).
     *  2. com `business_id` de outro tenant no payload, criava lá. O form Blade nunca
     *     manda o campo — não havia uso legítimo pela UI.
     *
     * A regra de superadmin espelha `App\Scopes\ScopeByBusiness` (`jana.superadmin` vê o
     * próprio tenant + `NULL`), pra não existir um segundo conceito de "quem é plataforma".
     *
     * @see Modules/Jana/Tests/Feature/MetasControllerBaselineTest.php
     */
    public function store(StoreMetaRequest $request)
    {
        // D8.c (Wave 14) — FormRequest dedicado substitui validate() inline.
        // Regras endurecidas: slug regex, whitelist unidade/tipo, msgs PT-BR.
        $data = $request->validated();

        $tenant = session('user.business_id');

        if ($request->has('business_id')) {
            $pedido = $data['business_id'] ?? null;   // null explícito = meta de plataforma
            abort_unless(
                auth()->user()?->can('jana.superadmin') || (int) $pedido === (int) $tenant,
                403,
                'Sem permissão para criar meta em outro business.'
            );
            $businessId = $pedido === null ? null : (int) $pedido;
        } else {
            $businessId = $tenant !== null ? (int) $tenant : null;
        }

        $meta = Meta::create(array_merge($data, [
            'business_id'        => $businessId,
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
