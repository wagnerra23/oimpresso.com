<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\ProductBom;
use App\Http\Controllers\Controller;
use App\Product;
use App\Variation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * ProductBomController — endpoints admin pra cadastro de Bill of Materials (US-INV-001).
 *
 * Multi-tenant Tier 0 (ADR 0093): toda query usa business_id de session('user.business_id').
 * Permission: product.update (mesma usada pra editar produto).
 *
 * UI Inertia drag-drop fica em US-INV-002 — esta US entrega só CRUD API.
 */
class ProductBomController extends Controller
{
    /**
     * GET /api/products/{id}/bom — lista componentes do produto pai.
     */
    public function index(int $productId): JsonResponse
    {
        $this->authorizeAccess();
        $businessId = $this->businessId();

        // Garante produto pertence ao business (Tier 0).
        Product::query()
            ->where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $rows = ProductBom::query()
            ->where('parent_product_id', $productId)
            ->with(['component:id,name,sku,type', 'componentVariation:id,name,sub_sku'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $rows,
            'count' => $rows->count(),
        ]);
    }

    /**
     * POST /api/products/{id}/bom — adiciona componente ao produto pai.
     *
     * Body:
     *   component_id (required, int)
     *   component_variation_id (optional, int)
     *   parent_variation_id (optional, int) — se kit definido per variação
     *   qty_required (required, decimal)
     *   is_optional (optional, bool)
     *   allow_substitution (optional, bool)
     *   notes (optional, string)
     *   sort_order (optional, int)
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        $this->authorizeAccess();
        $businessId = $this->businessId();

        // Tier 0: pai existe E pertence ao business.
        Product::query()
            ->where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $data = $request->validate([
            'component_id' => 'required|integer',
            'component_variation_id' => 'nullable|integer',
            'parent_variation_id' => 'nullable|integer',
            'qty_required' => 'required|numeric|min:0.0001',
            'is_optional' => 'nullable|boolean',
            'allow_substitution' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Tier 0: componente também precisa pertencer ao business.
        Product::query()
            ->where('id', $data['component_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        // UC-PBOM-02 (Tier 0 multi-tenant · ADR 0093) — as VARIAÇÕES passavam só por
        // `nullable|integer` e iam direto pro `ProductBom::create`, enquanto os PRODUTOS
        // acima já eram validados. Mesma família do UC-PTAB-04 (#4300), UC-PBULK-03 e
        // UC-PQCK-02: id que chega CRU do request e é gravado sem checar de quem é. O SDD
        // já avisava — "o próximo model pendurado em Product nasce com o mesmo buraco".
        //
        // A checagem é por PRODUTO-DONO, não por business: `variations` não tem coluna
        // `business_id` (liga por `product_id`), e amarrar a variação ao produto ao qual
        // ela pertence é ESTRITAMENTE MAIS FORTE — os dois produtos já foram provados do
        // business acima, então isto fecha o cross-tenant E ainda pega variação de outro
        // produto do MESMO business (que também corromperia o kit).
        //
        // `firstOrFail()` = mesmo idioma dos dois guards Tier 0 acima (404), não 422.
        if (! empty($data['component_variation_id'])) {
            Variation::query()
                ->where('id', $data['component_variation_id'])
                ->where('product_id', $data['component_id'])
                ->firstOrFail();
        }
        if (! empty($data['parent_variation_id'])) {
            Variation::query()
                ->where('id', $data['parent_variation_id'])
                ->where('product_id', $productId)
                ->firstOrFail();
        }

        // Guard: produto NÃO pode ser componente de si mesmo (proteção mínima
        // contra circular — BomResolver pega ciclos transitivos em runtime).
        if ($data['component_id'] === $productId) {
            return response()->json([
                'message' => 'Produto não pode ser componente de si mesmo (auto-referência proibida).',
            ], 422);
        }

        $bom = ProductBom::create([
            'business_id' => $businessId,
            'parent_product_id' => $productId,
            'parent_variation_id' => $data['parent_variation_id'] ?? null,
            'component_product_id' => $data['component_id'],
            'component_variation_id' => $data['component_variation_id'] ?? null,
            'qty_required' => $data['qty_required'],
            'is_optional' => (bool) ($data['is_optional'] ?? false),
            'allow_substitution' => (bool) ($data['allow_substitution'] ?? false),
            'notes' => $data['notes'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json(['data' => $bom], 201);
    }

    /**
     * DELETE /api/products/{id}/bom/{bom_id} — remove componente do kit.
     */
    public function destroy(int $productId, int $bomId): JsonResponse
    {
        $this->authorizeAccess();
        $businessId = $this->businessId();

        // Tier 0: row existe E pertence ao business E ao produto pai.
        $bom = ProductBom::query()
            ->where('id', $bomId)
            ->where('business_id', $businessId)
            ->where('parent_product_id', $productId)
            ->firstOrFail();

        $bom->delete();

        return response()->json(['data' => ['id' => $bomId, 'deleted' => true]]);
    }

    /**
     * business_id do usuário autenticado (Tier 0 — NUNCA hardcode).
     *
     * Pattern UltimatePOS: session('user.business_id') populado em SetSessionData
     * middleware. ScopeByBusiness usa o mesmo source.
     */
    private function businessId(): int
    {
        $bizId = session('user.business_id');
        if (empty($bizId) || ! is_numeric($bizId)) {
            abort(403, 'business_id de sessão ausente — autenticação ou middleware setData faltando.');
        }

        return (int) $bizId;
    }

    /**
     * Permission gate — product.update (ADR 0093 + ProductController pattern).
     */
    private function authorizeAccess(): void
    {
        if (auth()->guest() || ! auth()->user()->can('product.update')) {
            abort(403, 'Permissão product.update obrigatória pra editar BOM.');
        }
    }
}
