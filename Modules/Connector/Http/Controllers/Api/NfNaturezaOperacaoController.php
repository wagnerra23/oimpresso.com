<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\NfNaturezaOperacao;

/**
 * @group NF Natureza Operacao Management
 * @authenticated
 *
 * APIs for managing NF Natureza Operacao.
 */
class NfNaturezaOperacaoController extends BaseApiController
{
    public function __construct()
    {
        // Mapeamento centralizado
        $fieldMapping = [
            'descricao' => 'descricao',
            'tipo_nf' => 'tipo_nf',
            'nfse_codigo' => 'nfse_codigo',
            'consumidor_final' => 'consumidor_final',
            'entrada_saida' => 'entrada_saida',
            'operacao' => 'operacao',
            'tem_tributacao_padrao' => 'tem_tributacao_padrao',
            'officeimpresso_codigo' => 'codigo',
            'officeimpresso_dt_alteracao' => 'dt_alteracao',
        ];

        parent::__construct(NfNaturezaOperacao::class, $fieldMapping); // Passando os dois argumentos
    }

    /**
     * Get records updated after a specific date.
     */
    public function getUntilDate(Request $request)
    {
        return $this->getData($request);
    }

    /**
     * Synchronize NF Natureza Operacao 
     */
    public function sync(Request $request)
    {
        $validationRules = [
            'data.*.codigo' => 'required|integer',
            'data.*.descricao' => 'required|string|max:255',
            'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
            'data.*.tipo_nf' => 'nullable|string|max:10',
            // 'data.*.nfse_codigo' => 'nullable|string|max:50',
            // 'data.*.consumidor_final' => 'nullable|boolean',
            // 'data.*.entrada_saida' => 'nullable|string|max:10',
            // 'data.*.operacao' => 'nullable|string|max:100',
            // 'data.*.tem_tributacao_padrao' => 'nullable|boolean',
        ];

        return $this->syncData($request, $validationRules, $this->fieldMapping);
    }
}
