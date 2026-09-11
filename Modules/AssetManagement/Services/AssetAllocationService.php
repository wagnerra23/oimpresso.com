<?php

namespace Modules\AssetManagement\Services;

use App\Util\OtelHelper;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Exceptions\SaldoInsuficienteException;
use Modules\AssetManagement\Utils\AssetUtil;

/**
 * Service de alocacao de asset (asset_transactions transaction_type=allocate).
 *
 * Wave 16 governance D4 Architecture: extraido de AssetAllocationController.
 * Wave 25 D9.a: spans OtelHelper::spanBiz em criar/atualizar/remover.
 * Multi-tenant Tier 0 (ADR 0093) — business_id obrigatorio.
 */
class AssetAllocationService
{
    public function __construct(
        private Util $commonUtil,
        private AssetUtil $assetUtil,
    ) {
    }

    /**
     * Cria alocacao em transacao. Wave 25 D9.a: span `assetmanagement.allocation.criar`.
     */
    public function criar(Request $request, int $businessId, int $userId): AssetTransaction
    {
        return OtelHelper::spanBiz('assetmanagement.allocation.criar', function () use ($request, $businessId, $userId): AssetTransaction {
            $input = $request->only(
                'ref_no', 'asset_id', 'quantity', 'receiver',
                'transaction_datetime', 'reason', 'allocated_upto'
            );
            $input['transaction_type'] = 'allocate';
            $input['business_id'] = $businessId;
            $input['created_by'] = $userId;

            DB::beginTransaction();

            // A normalizacao subiu para ANTES da trava (thread 02): a quantidade so pode ser
            // comparada com o saldo depois de passar por `num_uf`, senao a comparacao seria
            // com a string crua. Nada aqui depende do `ref_no`, entao a ordem e segura — e o
            // `ref_no` passou a ser gerado DEPOIS da trava, para que uma alocacao recusada
            // nao queime um numero de referencia.
            $input = $this->normalizarCampos($input);

            $this->garantirSaldo(
                (int) ($input['asset_id'] ?? 0),
                $businessId,
                (float) ($input['quantity'] ?? 0)
            );

            if (empty($input['ref_no'])) {
                $ref_count = $this->commonUtil->setAndGetReferenceCount('allocation_code', $businessId);
                $asset_settings = $this->assetUtil->getAssetSettings($businessId);
                $prefix = $asset_settings['allocation_code_prefix'] ?? null;
                $input['ref_no'] = $this->commonUtil->generateReferenceNumber('allocation_code', $ref_count, null, $prefix);
            }

            $trans = AssetTransaction::create($input);

            DB::commit();

            return $trans;
        }, ['business_id' => $businessId, 'user_id' => $userId]);
    }

    /**
     * Atualiza alocacao existente (scopado a business_id).
     * Wave 25 D9.a: span `assetmanagement.allocation.atualizar`.
     */
    public function atualizar(Request $request, int $id, int $businessId): AssetTransaction
    {
        return OtelHelper::spanBiz('assetmanagement.allocation.atualizar', function () use ($request, $id, $businessId): AssetTransaction {
            $input = $request->only(
                'asset_id', 'quantity', 'receiver',
                'transaction_datetime', 'reason', 'allocated_upto'
            );

            DB::beginTransaction();

            $input = $this->normalizarCampos($input);

            $trans = AssetTransaction::where('business_id', $businessId)->findOrFail($id);
            $trans->update($input);

            DB::commit();

            return $trans;
        }, ['business_id' => $businessId, 'allocation_id' => $id]);
    }

    /**
     * Remove alocacao (scopado a business_id).
     * Wave 25 D9.a: span `assetmanagement.allocation.remover`.
     */
    public function remover(int $id, int $businessId): void
    {
        OtelHelper::spanBiz('assetmanagement.allocation.remover', function () use ($id, $businessId): void {
            $trans = AssetTransaction::where('business_id', $businessId)->findOrFail($id);
            $trans->delete();
        }, ['business_id' => $businessId, 'allocation_id' => $id]);
    }

    /**
     * Quantidade do asset que esta NA MAO das pessoas (alocado menos devolvido).
     *
     * ⚠️ O NOME ENGANA e fica como esta de proposito: ele e consumido pelo
     * `AssetAllocationController::edit()` e pelo `asset_allocation/edit.blade.php`, e
     * renomea-lo seria outro intent. MEDIDO no CT 100 em 2026-09-08 com bem de 10
     * unidades, 10 alocadas, 0 devolvidas: o retorno e **10** (o alocado), nao **0** (o
     * que sobra). Quem precisa do que SOBRA usa `saldoLivre()`.
     *
     * O `(int)` tambem fica: `asset_transactions.quantity` e DECIMAL(22,4), entao este
     * cast TRUNCA fracao — mexer nele muda o `max` do formulario legado, que e quantidade,
     * e quantidade e REGRA MESTRE. Declarado, nao consertado. A trava usa float.
     */
    public function quantidadeDisponivel(AssetTransaction $allocated): int
    {
        return (int) $this->alocadoLiquido((int) $allocated->asset_id, (int) $allocated->business_id);
    }

    /**
     * UM dono para a contagem. A expressao SQL e a mesma que vivia em
     * `quantidadeDisponivel()` — preservada byte-a-byte, so parametrizada por
     * (asset, business) em vez de ler de uma transacao existente, porque `criar()`
     * precisa do numero ANTES de existir transacao. Duas contagens para o mesmo numero
     * e como o bug renasce (thread 02 §B).
     */
    private function alocadoLiquido(int $assetId, int $businessId): float
    {
        $asset = Asset::leftJoin('asset_transactions as AT', function ($join) {
            $join->on('assets.id', '=', 'AT.asset_id')
                // O GEMEO que a thread 01 nao pegou. Ela pos o predicado de tenant na
                // subconsulta de `revoke` (`AR.business_id=assets.business_id`, logo abaixo)
                // e o lado `allocate` ficou sem — entao o alocado somava transacao de
                // QUALQUER empresa, e o saldo do dono caia. Achado pelo teste Tier 0 desta
                // thread, que era o unico vermelho dos 5.
                //
                // Nao e cosmetico: a trava LE este numero. Sem o predicado ela recusaria
                // alocacao legitima por causa de dado de outro tenant — que e exatamente o
                // que a thread 02 avisa ao exigir a 01 primeiro ("a trava usaria um numero
                // contaminado"). A 01 corrigiu metade do calculo; esta fecha a outra.
                //
                // ANTES->DEPOIS medido no CT 100 (2026-09-08): 308 bens varridos,
                // **0** mudam de valor, **0** transacoes `allocate` cross-tenant existem
                // nesta base. A correcao nao altera nenhum registro — so fecha a porta.
                ->on('AT.business_id', '=', 'assets.business_id')
                ->where('transaction_type', 'allocate');
        })
            ->where('assets.business_id', $businessId)
            ->where('assets.id', $assetId)
            ->select(
                'assets.id as id',
                DB::raw('SUM(COALESCE(AT.quantity, 0)) as allocated_qty'),
                DB::raw('(SELECT SUM(COALESCE(AR.quantity, 0)) FROM asset_transactions AS AR WHERE(AR.asset_id=assets.id AND AR.business_id=assets.business_id AND AR.transaction_type=\'revoke\')) as revoked_qty')
            )
            ->first();

        // `first()` sobre agregacao sem GROUP BY sempre devolve UMA linha; quando o asset
        // nao e do business (ou nao existe), ela vem com os dois campos nulos — e o saldo
        // resultante e 0, que e o que `saldoLivre()` precisa para recusar.
        if (! $asset) {
            return 0.0;
        }

        return (float) $asset->allocated_qty - (float) $asset->revoked_qty;
    }

    /**
     * Quanto do bem AINDA PODE ser alocado: o que a empresa tem menos o que ja saiu.
     *
     * O predicado de business esta nos DOIS lados (aqui e em `alocadoLiquido`), entao
     * asset de outro tenant devolve saldo 0 e qualquer pedido e recusado — o que fecha,
     * de lado, o buraco de `criar()` nunca ter verificado o dono do asset.
     */
    private function saldoLivre(int $assetId, int $businessId): float
    {
        $asset = Asset::where('id', $assetId)
            ->where('business_id', $businessId)
            ->first();

        if (! $asset) {
            return 0.0;
        }

        return (float) $asset->quantity - $this->alocadoLiquido($assetId, $businessId);
    }

    /**
     * A TRAVA (thread 02). Recusa antes de gravar — se a linha nascesse e so depois fosse
     * rejeitada, o saldo ja teria mentido.
     *
     * Mora aqui, e nao no `StoreAssetAllocationRequest`, porque aquele Request e ORFAO: o
     * controller recebe `Illuminate\Http\Request` cru e tem 0 chamadas de validacao, entao
     * regra escrita la passa no CI e e inerte em producao (`_saida-04.md §5`).
     */
    private function garantirSaldo(int $assetId, int $businessId, float $pedido): void
    {
        $disponivel = $this->saldoLivre($assetId, $businessId);

        if ($pedido > $disponivel) {
            // Rollback explicito: nao delegar ao `catch` do controller. A transacao foi
            // aberta por `criar()`, entao e `criar()` que a fecha — deixar aberta para
            // alguem la em cima resolver e como a conexao vaza.
            DB::rollBack();

            throw new SaldoInsuficienteException($assetId, $pedido, $disponivel);
        }
    }

    /**
     * Normaliza campos numericos/data.
     */
    private function normalizarCampos(array $input): array
    {
        if (! empty($input['transaction_datetime'])) {
            $input['transaction_datetime'] = $this->commonUtil->uf_date($input['transaction_datetime'], true);
        }
        if (! empty($input['allocated_upto'])) {
            $input['allocated_upto'] = $this->commonUtil->uf_date($input['allocated_upto']);
        }
        if (! empty($input['quantity'])) {
            $input['quantity'] = $this->commonUtil->num_uf($input['quantity']);
        }
        return $input;
    }
}
