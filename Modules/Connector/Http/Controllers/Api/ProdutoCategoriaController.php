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
            // 'business_id' => 'business_id',
            // 'category_type' => 'category_type',
            // 'officeimpresso_codigo' => 'codigo',
            // 'officeimpresso_dt_alteracao' => 'dt_alteracao',
        ];

        parent::__construct(Category::class, $fieldMapping); // Passa a model e o mapeamento para a base
    }

    /**
     * Get categories updated after a specific date.
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
            'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
            'data.*.oimpresso_id' => 'nullable|integer',
        ];

        return $this->syncData($request, $validationRules, $this->fieldMapping);
    }


}
