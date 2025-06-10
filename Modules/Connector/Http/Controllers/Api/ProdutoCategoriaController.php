<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\Category;

/**
 * @group Taxonomy management
 * @authenticated
 *
 * APIs for managing taxonomies
 */
class ProdutoCategoriaController extends BaseApiController
{
    public function __construct()
    {
        // Mapeamento centralizado
        $fieldMapping = [
            'name' => 'descricao',
            // 'category_type' => 'category_type',
            'officeimpresso_codigo' => 'codigo',
            'officeimpresso_dt_alteracao' => 'dt_alteracao',    
        ];

        parent::__construct(Category::class, $fieldMapping); // Passa a model e o mapeamento para a base
    }

    /**
     * Listar Produto Categoria atualizado até uma data.
     */
    public function getUntilDate(Request $request)
    {
        return $this->getData($request);
    }

    /**
     * Synchronize categories between the local database and the server.
     */
    public function sync(Request $request)
    {
        $validationRules = [
            'data' => 'required|array',
            'data.*.codigo' => 'required|integer',
            'data.*.descricao' => 'required|string|max:255',
            'data.*.oimpresso_id' => 'nullable|integer',
        ];

        // Valide os dados recebidos
        $validatedData = $request->validate($validationRules);

        // Processa os dados validados
        $processedData = collect($validatedData['data'])->map(function ($item) {
            return $this->processItem($item);
        })->toArray();

        // Substitui os dados no request para passar para syncData
        $request->merge(['data' => $processedData]);

        return $this->syncData($request, $validationRules, $this->fieldMapping);
    }

    /**
     * Process individual item for sync.
     */
    private function processItem(array $item): array
    {
        // Valores padrão para esta classe
        $defaultValues = [
            'category_type' => 'product',
            'parent_id' => 0,
            // 'category_id' => $this->resolveForeignKeyByCode(\App\Category::class, $item['codproduto_categoria'] ?? null),
            // 'sub_category_id' => $this->resolveForeignKeyByCode(\App\SubCategory::class, $item['codproduto_subcategoria'] ?? null),
        ];

        // Mescla os valores padrão com os valores específicos do item
        return array_merge($defaultValues, $item);
    }
}
