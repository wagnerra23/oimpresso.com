<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Utils\AssetUtil;

class RevokeAllocatedAssetController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $commonUtil;

    protected $assetUtil;

    /**
     * Constructor
     */
    public function __construct(ModuleUtil $moduleUtil, Util $commonUtil, AssetUtil $assetUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->assetUtil = $assetUtil;
    }

    /**
     * Devoluções: redireciona para a tela de Alocações (thread 16, decisão [W] 2026-09-30,
     * `_saida-16b` opção b).
     *
     * A lista de devoluções não tem mais tela própria. O grão dela é 1 alocação : N devoluções
     * (`_saida-16`), e ele já aparece em dois lugares: o drawer do BEM, aba Alocações (cada
     * alocação com todas as suas devoluções), e o drawer Devolver da tela de Alocações, que
     * lista as devoluções da alocação com Excluir (thread 18). Excluir era a única capacidade
     * que só a lista Blade tinha; por isso o redirecionamento esperou a 18.
     *
     * Até 2026-09-30 este método devolvia a lista Blade de devoluções e, sob
     * `ajax()`, o JSON do DataTables que ela consumia. O ramo `ajax()` saiu junto: o cliente
     * Inertia manda `X-Requested-With` em toda visita, então quem chegava aqui pelo menu
     * recebia JSON cru. O arquivo `.blade.php` fica no disco; retirá-lo é outra onda.
     *
     * A rota `GET asset/revocation` segue registrada (menu e links antigos continuam
     * resolvendo) e o gate de assinatura é o mesmo de antes.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        return redirect()->action([AssetAllocationController::class, 'index']);
    }

    /**
     * Devolver: a tela de Alocacoes com o drawer de devolucao aberto (thread 18, [W] 2026-09-30).
     *
     * Ate 2026-09-30 devolvia o fragmento de modal `asset_revocation.create` so sob `ajax()` —
     * que nao convive com a tela React (toda visita Inertia e ajax). O drawer mostra o que ja
     * voltou desta alocacao, uma linha por devolucao (grao 1 : N, `_saida-16`), com EXCLUIR:
     * ate aqui so a lista Blade `/asset/revocation` desfazia uma devolucao errada.
     *
     * Tier 0: a alocacao e as devolucoes saem escopadas por `business_id` — as DUAS pontas.
     * `asset_transactions` nao tem global scope, entao o filtro e escrito em cada query.
     *
     * @return \Inertia\Response
     */
    public function create(Request $request)
    {
        $business_id = (int) request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        $alocacao = $this->alocacaoDoBusiness((int) $request->get('id'), $business_id);

        $devolucoes = $this->devolucoesDaAlocacao($alocacao, $business_id);
        $devolvido = (float) $devolucoes->sum('quantidade');

        return app(AssetAllocationController::class)->renderAlocacoes($request, $business_id, ['formulario' => [
            'modo' => 'devolver',
            'alocacao' => [
                'id' => (int) $alocacao->id,
                'ref_no' => (string) $alocacao->ref_no,
                'bem' => (string) $alocacao->getAttribute('bem_nome'),
                'recebido_por' => trim((string) $alocacao->getAttribute('receiver_name')),
                'quantidade' => (float) $alocacao->quantity,
                'devolvido' => $devolvido,
                'restante' => max(0.0, (float) $alocacao->quantity - $devolvido),
            ],
            'devolucoes' => $devolucoes->values()->all(),
        ]]);
    }

    /**
     * A alocacao, escopada por business E por tipo. Id de outra empresa ou de uma devolucao
     * da 404 — antes, `findOrFail` sem tipo aceitava QUALQUER transacao como "pai".
     */
    private function alocacaoDoBusiness(int $id, int $business_id): AssetTransaction
    {
        // Join, nao `with('asset')`: o nome do bem sai da MESMA linha escopada, e o Larastan
        // enxerga a coluna (a relacao `asset` nao tem tipo de retorno no Model).
        return AssetTransaction::leftJoin('assets as bem', 'asset_transactions.asset_id', '=', 'bem.id')
            ->leftJoin('users as receiver_u', 'asset_transactions.receiver', '=', 'receiver_u.id')
            ->where('asset_transactions.business_id', $business_id)
            ->where('asset_transactions.transaction_type', 'allocate')
            ->select('asset_transactions.*', 'bem.name as bem_nome', DB::raw("CONCAT(COALESCE(receiver_u.surname, ''),' ',COALESCE(receiver_u.first_name, ''),' ',COALESCE(receiver_u.last_name,'')) as receiver_name"))
            ->findOrFail($id);
    }

    /**
     * As devolucoes de UMA alocacao, da mais recente pra mais antiga. `business_id` filtrado
     * na DEVOLUCAO, nao so na alocacao (Tier 0 — `_saida-16b`): linha filha de outro tenant
     * apontando pro mesmo `parent_id` nao entra na lista nem na soma.
     */
    private function devolucoesDaAlocacao(AssetTransaction $alocacao, int $business_id)
    {
        return AssetTransaction::leftJoin('users as autor', 'asset_transactions.created_by', '=', 'autor.id')
            ->where('asset_transactions.business_id', $business_id)
            ->where('asset_transactions.transaction_type', 'revoke')
            ->where('asset_transactions.parent_id', $alocacao->id)
            ->orderByDesc('asset_transactions.transaction_datetime')
            ->select(
                'asset_transactions.id',
                'asset_transactions.ref_no',
                'asset_transactions.quantity',
                'asset_transactions.transaction_datetime',
                'asset_transactions.reason',
                DB::raw("CONCAT(COALESCE(autor.surname, ''),' ',COALESCE(autor.first_name, ''),' ',COALESCE(autor.last_name,'')) as autor")
            )
            ->get()
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'ref_no' => (string) $d->ref_no,
                'quantidade' => (float) $d->quantity,
                'data' => $d->transaction_datetime ? $this->commonUtil->format_date($d->transaction_datetime, true) : null,
                'autor' => trim((string) $d->getAttribute('autor')),
                'motivo' => $d->reason,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        // O PAI vem do business e do tipo certo, e o `asset_id` vem DELE — nao do formulario.
        // Antes os dois eram lidos crus do POST: uma devolucao podia nascer pendurada numa
        // alocacao de outra empresa, ou apontando pra outro bem (thread 18, Tier 0).
        $alocacao = $this->alocacaoDoBusiness((int) $request->input('parent_id'), (int) $business_id);

        // A trava do lado da devolucao: nao se devolve mais do que falta voltar. Antes nao havia
        // nenhuma — o `max` do Blade era so atributo HTML. Recusa ANTES de gravar, com a
        // mensagem no campo, como a trava de alocacao (thread 02).
        $pedido = (float) $this->commonUtil->num_uf((string) $request->input('quantity', ''));
        $restante = (float) $alocacao->quantity - (float) $this->devolucoesDaAlocacao($alocacao, (int) $business_id)->sum('quantidade');
        if ($pedido <= 0) {
            return redirect()->back()->withErrors(['quantity' => 'Informe a quantidade devolvida.']);
        }
        if ($pedido > $restante) {
            return redirect()->back()->withErrors(['quantity' => sprintf(
                'So falta devolver %s unidade(s) desta alocacao.',
                rtrim(rtrim(number_format(max(0, $restante), 4, ',', '.'), '0'), ',')
            )]);
        }

        try {
            $input = $request->only('ref_no', 'quantity', 'transaction_datetime', 'reason');
            $input['parent_id'] = $alocacao->id;
            $input['asset_id'] = $alocacao->asset_id;
            $input['transaction_type'] = 'revoke';
            $input['business_id'] = $business_id;
            $input['created_by'] = request()->session()->get('user.id');

            DB::beginTransaction();

            if (empty($input['ref_no'])) {
                $ref_count = $this->commonUtil->setAndGetReferenceCount('revoke_code', $business_id);
                $asset_settings = $this->assetUtil->getAssetSettings($business_id);

                $revoke_code_prefix = $asset_settings['revoke_code_prefix'] ?? null;
                $input['ref_no'] = $this->commonUtil->generateReferenceNumber('revoke_code', $ref_count, null, $revoke_code_prefix);
            }

            if (! empty($input['transaction_datetime'])) {
                $input['transaction_datetime'] = $this->commonUtil->uf_date($input['transaction_datetime'], true);
            }

            if (! empty($input['quantity'])) {
                $input['quantity'] = $this->commonUtil->num_uf($input['quantity']);
            }

            AssetTransaction::create($input);

            DB::commit();

            // Volta pra tela de onde o drawer saiu. O `index` de devolucoes (Blade) nao tem mais
            // formulario que poste aqui — o unico chamador vivo e o drawer (thread 18).
            return redirect()
                ->action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'index'])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

            return redirect()->back()
                ->with('status', ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('assetmanagement::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * Inertia (drawer) recebe redirect; o ajax legado da lista Blade segue recebendo o array.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse|array<string, mixed>|null
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        // Tipo `revoke` no filtro: sem ele este endpoint apagava QUALQUER transacao do business
        // pelo id — inclusive uma ALOCACAO, sem passar pelo `AssetAllocationService`.
        $asset_revoked = AssetTransaction::where('business_id', $business_id)
            ->where('transaction_type', 'revoke')
            ->findOrFail($id);

        // Drawer de devolucao (Inertia, thread 18): resposta Inertia, nao JSON. O cliente manda
        // `X-Requested-With` sempre, entao o teste e `inertia()` — `ajax()` sozinho nao distingue.
        if (request()->inertia()) {
            $asset_revoked->delete();

            return redirect()->back()->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
        }

        if (request()->ajax()) {
            try {
                $asset_revoked->delete();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }

        // Nem Inertia nem ajax: o mesmo corpo vazio de sempre, agora escrito (antes era o
        // "return statement is missing" que o baseline do PHPStan tolerava).
        return null;
    }

    /**
     * Get total revoked qty
     * of an allocated asset
     *
     * @return int
     */
    protected function _getRevokedQtyOfAllocatedAsset($allocated_asset)
    {
        $asset_transaction = AssetTransaction::where('business_id', $allocated_asset->business_id)
                                ->where('parent_id', $allocated_asset->id)
                                ->select(DB::raw('SUM(COALESCE(quantity, 0)) as quantity'))
                                ->first();

        return $asset_transaction->quantity;
    }
}
