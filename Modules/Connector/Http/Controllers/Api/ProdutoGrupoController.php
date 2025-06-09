<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\ProdutoGrupo;

/**
 * @group Produto Grupo Management
 * @authenticated
 *
 * APIs para gerenciar Produto Grupo.
 */
class ProdutoGrupoController extends BaseApiController
{
    public function __construct()
    {
        // Mapeamento centralizado para Produto Grupo
        $fieldMapping = [
            'descricao' => 'descricao',
            'referencia' => 'referencia',
            'codplanocontas' => 'codplanocontas',
            'officeimpresso_codigo' => 'codigo',
            'officeimpresso_dt_alteracao' => 'dt_alteracao',
        ];

        parent::__construct(ProdutoGrupo::class, $fieldMapping);
    }

    /**
     * Sincronizar Produto Grupo
     */
    public function sync(Request $request)
    {
        $validationRules = [
            'data.*.codigo' => 'required|string|max:15',
            'data.*.descricao' => 'required|string|max:40',
            'data.*.referencia' => 'nullable|string|max:15',
            'data.*.codplanocontas' => 'nullable|string|max:15',
            'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
        ];

        return $this->syncData($request, $validationRules, $this->fieldMapping);
    }

    /**
     * Listar Produto Grupo atualizado até uma data.
     */
    public function getUntilDate(Request $request)
    {
        return $this->getData($request);
    }
}
