<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\BusinessLocation;
use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ProductUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Venda rápida do app das lojas (tela 11). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §2.2.
 *
 * Este PR traz só a LEITURA (busca de produtos). A criação da venda vem em PR separado, pela
 * regra mestre de valor/estoque (dupla prova + antes→depois + ok do [W]).
 *
 * Regras medidas na web (2026-10-02), espelhadas aqui:
 *  - Local de venda: o ERP não tem "local padrão do usuário". A web (SellController::create,
 *    BusinessLocation::forDropdown) usa o 1º local ATIVO que o usuário pode acessar. Mesmo aqui.
 *  - Preço: o da variação (`sell_price_inc_tax`) ou, se o local tem grupo de preço padrão, o do
 *    grupo pelo MESMO cálculo do PDV Blade (ProductUtil::getVariationGroupPrice, usado quando
 *    não-vazio). O POST vai recalcular por esta mesma função — o número que o app mostra é o
 *    que o ERP confere.
 *  - Estoque: o do local de venda; `null` quando o produto não controla estoque OU o business
 *    permite vender sem estoque (aí o ERP não barra, e o app não deve pôr teto).
 *
 * Tier 0 (ADR 0093): business_id do usuário do token, nunca da requisição.
 */
class VendaRapidaController extends Controller
{
    private const LIMITE = 20;

    public function produtos(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $this->podeVender($user)) {
            return $this->semPermissao();
        }
        $local = self::localDeVenda($user);
        if ($local === null) {
            return $this->semLocal();
        }

        $bizId = (int) $user->business_id;
        $busca = trim((string) $request->query('q', ''));
        // Business que vende sem estoque (pos_settings.allow_overselling): o ERP aceita a venda
        // mesmo com saldo zero ou negativo, então o app não deve pôr teto — estoque vai `null`.
        $semTeto = self::permiteVenderSemEstoque($bizId);

        $q = DB::table('products as p')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->join('product_locations as pl', function ($j) use ($local) {
                $j->on('pl.product_id', '=', 'p.id')->where('pl.location_id', '=', $local->id);
            })
            ->leftJoin('variation_location_details as vld', function ($j) use ($local) {
                $j->on('vld.variation_id', '=', 'v.id')->where('vld.location_id', '=', $local->id);
            })
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.business_id', $bizId)
            ->where('p.is_inactive', 0)
            ->where('p.not_for_selling', 0)
            // Combo e modificador ficam fora da v1: a baixa de combo e o preço de modificador
            // seguem outros caminhos no ERP e não estão no contrato da tela 11.
            ->whereIn('p.type', ['single', 'variable'])
            ->whereNull('v.deleted_at');

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $q->where(fn ($w) => $w->where('p.name', 'like', $like)
                ->orWhere('v.name', 'like', $like)
                ->orWhere('p.sku', 'like', $like)
                ->orWhere('v.sub_sku', 'like', $like)
                ->orWhere('c.name', 'like', $like));
        }

        $linhas = $q->orderBy('p.name')->orderBy('v.id')
            ->limit(self::LIMITE)
            ->get([
                'v.id as variacao_id', 'p.name as produto', 'p.type', 'v.name as variacao',
                'c.name as categoria', 'p.enable_stock', 'p.tax as tax_id',
                'v.sell_price_inc_tax', 'vld.qty_available',
            ]);

        return response()->json([
            'itens' => $linhas->map(fn ($l) => [
                'id' => (int) $l->variacao_id,
                'nome' => $l->type === 'variable' && $l->variacao !== null && $l->variacao !== 'DUMMY'
                    ? $l->produto . ' — ' . $l->variacao
                    : $l->produto,
                'categoria' => $l->categoria,
                'preco' => self::precoDeVenda($local, (int) $l->variacao_id, (float) $l->sell_price_inc_tax, $l->tax_id),
                'estoque' => (int) $l->enable_stock === 1 && ! $semTeto ? round((float) ($l->qty_available ?? 0), 4) : null,
            ])->values(),
        ]);
    }

    /** `pos_settings.allow_overselling` do business — a mesma chave que o mapPurchaseSell lê. */
    public static function permiteVenderSemEstoque(int $bizId): bool
    {
        $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true);

        return ! empty($pos['allow_overselling']);
    }

    /** 1º local ativo que o usuário pode acessar — a regra do SellController::create. */
    public static function localDeVenda(User $user): ?BusinessLocation
    {
        $q = BusinessLocation::where('business_id', (int) $user->business_id)->active()->orderBy('id');
        $permitidos = $user->permitted_locations((int) $user->business_id);
        if ($permitidos !== 'all') {
            $q->whereIn('id', (array) $permitidos);
        }

        return $q->first();
    }

    /**
     * Preço que a venda usa, em reais com 2 casas. Grupo de preço padrão do local quando há preço
     * não-vazio para a variação (mesmo teste `!empty` do PDV Blade); senão o preço da variação.
     */
    public static function precoDeVenda(BusinessLocation $local, int $variacaoId, float $base, mixed $taxId): float
    {
        $preco = $base;
        if (! empty($local->selling_price_group_id)) {
            // O docblock do getVariationGroupPrice declara `decimal`, mas ele devolve array
            // ['price_inc_tax', 'price_exc_tax']; o (array) só torna isso explícito para o PHPStan.
            $grupo = (array) app(ProductUtil::class)->getVariationGroupPrice($variacaoId, (int) $local->selling_price_group_id, $taxId);
            $doGrupo = $grupo['price_inc_tax'] ?? null;
            if (! empty($doGrupo)) {
                $preco = (float) $doGrupo;
            }
        }

        return round($preco, 2);
    }

    private function podeVender(?User $user): bool
    {
        // As mesmas da criação de venda direta na web (SellPosController::store), sem `so.create`
        // (pedido de venda, que não baixa estoque nem é venda).
        return $user !== null && ($user->can('sell.create') || $user->can('direct_sell.access'));
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não pode registrar vendas.',
        ], 403);
    }

    private function semLocal(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_local',
            'mensagem' => 'Seu usuário não tem acesso a nenhum local de venda ativo.',
        ], 403);
    }
}
