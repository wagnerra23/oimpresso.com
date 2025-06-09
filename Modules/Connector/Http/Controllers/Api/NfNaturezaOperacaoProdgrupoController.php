<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\NfNaturezaOperacaoProdgrupo;

/**
 * @group NF Natureza Operação Prodgrupo Management
 * @authenticated
 *
 * APIs for managing NF Natureza Operação Prodgrupo.
 */
class NfNaturezaOperacaoProdgrupoController extends BaseApiController
{
    public function __construct()
    {
        // Mapeamento centralizado
        $fieldMapping = [
            'nf_natureza_operacao_id' => 'nf_natureza_operacao_id',
            'produto_grupo_id' => 'produto_grupo_id',
            'codnf_cst' => 'codnf_cst',
            'codnf_cfop' => 'codnf_cfop',
            'codnf_cfop_fora' => 'codnf_cfop_fora',
            'officeimpresso_dt_alteracao' => 'dt_alteracao',
        ];

        parent::__construct(NfNaturezaOperacaoProdgrupo::class, $fieldMapping);
    }

    /**
     * Get records updated after a specific date.
     */
    public function getUntilDate(Request $request)
    {
        return $this->getData($request);
    }

    /**
     * Synchronize NF Natureza Operação Prodgrupo
     */
    public function sync(Request $request)
    {
        $validationRules = [
            'data.*.nf_natureza_operacao_id' => 'required|integer',
            'data.*.produto_grupo_id' => 'required|integer',
            'data.*.codnf_cst' => 'nullable|string|max:4',
            'data.*.codnf_cfop' => 'nullable|string|max:9',
            'data.*.codnf_cfop_fora' => 'nullable|string|max:9',
            'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
        ];

        return $this->syncData($request, $validationRules, $this->fieldMapping, function ($model, $item, $business_id) {
            return $model::where('business_id', $business_id)
                ->where(function ($query) use ($item) {
                    $query->where('id', $item['oimpresso_id'])
                          ->orWhere(function ($subQuery) use ($item) {
                                $subQuery->where('officeimpresso_codnf_natureza_operacao', $item['codnf_natureza_operacao'])
                                ->where('officeimpresso_codproduto_grupo', $item['codproduto_grupo']);
                          });
                });
        });
    }
}





<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\NfNaturezaOperacaoProdgrupo;

/**
 * @group NF Natureza Operação Prodgrupo Management
 * @authenticated
 *
 * APIs for managing NF Natureza Operação Prodgrupo.
 */
class NfNaturezaOperacaoProdgrupoController extends ApiController
{
    /**
     * Synchronize NF Natureza Operação Prodgrupo
     */
    public function syncNfNaturezaOperacaoProdgrupo(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.nf_natureza_operacao_id' => 'required|integer',
                'data.*.produto_grupo_id' => 'required|integer',
                'data.*.business_id' => 'required|integer',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
                'data.*.codnf_cst' => 'nullable|string|max:4',
                'data.*.codnf_cfop' => 'nullable|string|max:9',
                'data.*.codnf_cfop_fora' => 'nullable|string|max:9',
            ]);

            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    $record = NfNaturezaOperacaoProdgrupo::where('business_id', $business_id)
                    ->where(function ($query) use ($item) {
                        $query->where('id', $item['oimpresso_id'])
                              ->orWhere(function ($subQuery) use ($item) {
                                  $subQuery->where('officeimpresso_codnf_natureza_operacao', $item['codnf_natureza_operacao'])
                                           ->where('officeimpresso_codproduto_grupo', $item['codproduto_grupo']);
                              });
                    })
                    ->first();
                

                    if ($record) {
                        $updatedAt = $record->updated_at->format('Y-m-d H:i:s');
                        $itemUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                        if ($updatedAt !== $itemUpdatedAt) {
                            $response[] = [
                                'action' => 'conflict',
                                'message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $record->id,
                                'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                                'officeimpresso_dt_alteracao' => $record->dt_alteracao,
                            ];
                            continue;
                        }

                        $record->update([
                            'codnf_cst' => $item['codnf_cst'] ?? $record->codnf_cst,
                            'codnf_cfop' => $item['codnf_cfop'] ?? $record->codnf_cfop,
                            'codnf_cfop_fora' => $item['codnf_cfop_fora'] ?? $record->codnf_cfop_fora,
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'action' => 'updated',
                            'message' => 'O registro foi atualizado com sucesso.',
                            'id' => $record->id,
                            'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_dt_alteracao' => $record->dt_alteracao,
                            'officeimpresso_codnf_natureza_operacao' => $item['codnf_natureza_operacao'] ?? null,
                            'officeimpresso_codproduto_grupo' => $item['codproduto_grupo'] ?? null,
                        ];
                    } else {
                        $record = NfNaturezaOperacaoProdgrupo::create([
                            'business_id' => $business_id,
                            'nf_natureza_operacao_id' => $item['nf_natureza_operacao_id'], // aqui é uma função que coloca as dus chaves e retorna o id
                            'produto_grupo_id' => $item['produto_grupo_id'],
                            'codnf_cst' => $item['codnf_cst'] ?? null,
                            'codnf_cfop' => $item['codnf_cfop'] ?? null,
                            'codnf_cfop_fora' => $item['codnf_cfop_fora'] ?? null,
                            'dt_alteracao' => $item['dt_alteracao'] ?? null,
                            'created_by' => $user->id,
                        ]);

                        $response[] = [
                            'action' => 'created',
                            'message' => 'O registro foi criado com sucesso.',
                            'id' => $record->id,
                            'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_dt_alteracao' => $record->dt_alteracao,
                            'officeimpresso_codnf_natureza_operacao' => $item['codnf_natureza_operacao'] ?? null,
                            'officeimpresso_codproduto_grupo' => $item['codproduto_grupo'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    $response[] = [
                        'action' => 'error',
                        'message' => $e->getMessage(),
                        'officeimpresso_codnf_natureza_operacao' => $item['codnf_natureza_operacao'] ?? null,
                        'officeimpresso_codproduto_grupo' => $item['codproduto_grupo'] ?? null,
                        'dt_alteracao' => $item['dt_alteracao'] ?? null,
                    ];
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => 'Sincronização finalizada.',
                'data' => $response,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'message' => 'Erro de validação nos dados enviados.',
                'data' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro na sincronização de NF Natureza Operação Prodgrupo: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Custom response formatter for this controller.
     */
    protected function formatResponse($record, $action, $message, $responseMapping, $item = [])
    {
        return [
            'officeimpresso_action' => $action,
            'officeimpresso_message' => $message,
            'id' => $record->id,
            'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
            'deleted_at' => $record->deleted_at ?? null,
            'officeimpresso_dt_alteracao' => $record->officeimpresso_dt_alteracao ?? null,
            'officeimpresso_codnf_natureza_operacao' => $item['codnf_natureza_operacao'] ?? null,
            'officeimpresso_codproduto_grupo' => $item['codproduto_grupo'] ?? null,
        ] + $responseMapping;
    }
}
