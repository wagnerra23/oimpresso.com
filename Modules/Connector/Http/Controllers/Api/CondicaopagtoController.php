<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\CondicaoPagto;

/**
 * @group Condição de Pagamento Management
 * @authenticated
 *
 * APIs para gerenciar condições de pagamento.
 */
class CondicaoPagtoController extends ApiController
{
    /**
     * Lista condições de pagamento
     */
    public function index()
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $condicoes = CondicaoPagto::where('business_id', $business_id)->get();

        return CommonResource::collection($condicoes);
    }

    /**
     * Busca uma condição específica
     */
    public function show($condicao_ids)
    {
        $user = Auth::user();
        $business_id = $user->business_id;
        $condicao_ids = explode(',', $condicao_ids);

        $condicoes = CondicaoPagto::where('business_id', $business_id)
                                  ->whereIn('id', $condicao_ids)
                                  ->get();

        return CommonResource::collection($condicoes);
    }

    /**
     * Criar uma nova condição de pagamento
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $validatedData = $request->validate([
            'descricao' => 'required|string|max:255',
            'parcelas' => 'required|integer',
            'officeimpresso_codigo' => 'required|string|max:50',
            'officeimpresso_dt_alteracao' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $condicao = CondicaoPagto::create([
            'business_id' => $business_id,
            'descricao' => $validatedData['descricao'],
            'parcelas' => $validatedData['parcelas'],
            'officeimpresso_codigo' => $validatedData['officeimpresso_codigo'],
            'officeimpresso_dt_alteracao' => $validatedData['officeimpresso_dt_alteracao'],
            'created_by' => $user->id,
        ]);

        return new CommonResource($condicao);
    }

    /**
     * Atualizar uma condição existente
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $business_id = $user->business_id;

            $validatedData = $request->validate([
                'descricao' => 'required|string|max:255',
                'parcelas' => 'required|integer',
                'officeimpresso_codigo' => 'required|string|max:50',
                'officeimpresso_dt_alteracao' => 'required|date_format:Y-m-d H:i:s',
            ]);

            $condicao = CondicaoPagto::where('business_id', $business_id)->findOrFail($id);

            $updatedAt = $condicao->updated_at->format('Y-m-d H:i:s');
            $oimpressoUpdatedAt = Carbon::parse($validatedData['officeimpresso_dt_alteracao'])->format('Y-m-d H:i:s');

            if ($updatedAt !== $oimpressoUpdatedAt) {
                return response()->json([
                    'officeimpresso_action' => 'conflict',
                    'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                    'id' => $condicao->id,
                    'updated_at' => $condicao->updated_at->format('Y-m-d H:i:s'),
                    'officeimpresso_codigo' => $condicao->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $condicao->officeimpresso_dt_alteracao,
                ]);
            }

            $condicao->update([
                'descricao' => $validatedData['descricao'],
                'parcelas' => $validatedData['parcelas'],
                'officeimpresso_codigo' => $validatedData['officeimpresso_codigo'],
                'officeimpresso_dt_alteracao' => $validatedData['officeimpresso_dt_alteracao'],
            ]);

            return new CommonResource($condicao);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar condição de pagamento: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar o registro. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar condições de pagamento atualizadas até uma data específica
     *
     * @queryParam date required A data de referência no formato 'Y-m-d H:i:s'. Example: 2024-11-10 15:30:00
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "descricao": "Parcelamento em 3x",
     *       "parcelas": 3,
     *       "officeimpresso_codigo": "789012",
     *       "officeimpresso_dt_alteracao": "2024-11-11 12:00:00",
     *       "updated_at": "2024-11-10 15:30:00"
     *     },
     *     {
     *       "id": 2,
     *       "descricao": "Parcelamento em 5x",
     *       "parcelas": 5,
     *       "officeimpresso_codigo": "654321",
     *       "officeimpresso_dt_alteracao": "2024-11-11 12:00:00",
     *       "updated_at": "2024-11-10 15:45:00"
     *     }
     *   ]
     * }
     */
    public function getCondicaopagtoUntilDate(Request $request)
    {
        // Validação do parâmetro `date`
        if (!$request->has('date') || !$request->filled('date')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parâmetro "date" está ausente ou vazio.',
            ], 422);
        }

        try {
            $user = Auth::user();
            $business_id = $user->business_id;
            $date = Carbon::parse($request->input('date'))->format('Y-m-d H:i:s');

            // Buscar condições de pagamento atualizadas até a data informada
            $condicoes = CondicaoPagto::where('business_id', $business_id)
                                      ->where('updated_at', '>', $date)
                                      ->get();

            // Formatar a resposta
            $response = $condicoes->map(function ($condicao) {
                return [
                    'id' => $condicao->id,
                    'descricao' => $condicao->descricao,
                    'parcelas' => $condicao->parcelas,
                    'officeimpresso_codigo' => $condicao->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $condicao->officeimpresso_dt_alteracao,
                    'updated_at' => $condicao->updated_at->format('Y-m-d H:i:s'),
                    'officeimpresso_action' => 'update local',
                    'officeimpresso_message' => 'Registro modificado no site. Atualização realizada no banco de dados local.',
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados.',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar condições de pagamento: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Sincronizar condições de pagamento
     */
    public function syncCondicaopagto(Request $request)
    {
        try {
            // Validação dos dados
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:30',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
                'data.*.oimpresso_id' => 'nullable|integer',
            ]);
    
            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;
    
            foreach ($data as $item) {
                try {
                    // Localiza registro existente (ID ou código)
                    $condicao = CondicaoPagto::where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();
    
                    if ($condicao) {
                        $updatedAt = $condicao->updated_at->format('Y-m-d H:i:s');
                        $oimpressoUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');
    
                        if ($updatedAt !== $oimpressoUpdatedAt) {
                            // Conflito detectado
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $condicao->id,
                                'updated_at' => $condicao->updated_at->format('Y-m-d H:i:s'),
                                'deleted_at' => $condicao->deleted_at,
                                'officeimpresso_codigo' => $condicao->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $condicao->officeimpresso_dt_alteracao,
                            ];
                        }
    
                        // Atualizar registro existente
                        $condicao->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);
    
                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                            'id' => $condicao->id,
                            'updated_at' => $condicao->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $condicao->deleted_at,
                            'officeimpresso_codigo' => $condicao->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $condicao->officeimpresso_dt_alteracao,
                        ];
                    } else {
                        // Inserir novo registro
                        $condicao = CondicaoPagto::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                            'created_by' => $user->id,
                        ]);
    
                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'O registro foi criado com sucesso.',
                            'id' => $condicao->id,
                            'updated_at' => $condicao->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $condicao->deleted_at,
                            'officeimpresso_codigo' => $condicao->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $condicao->officeimpresso_dt_alteracao,
                        ];
                    }
                } catch (\Exception $e) {
                    // Adiciona erro ao retorno
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'officeimpresso_codigo' => $item['codigo'] ?? null,
                        'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                    ];
                }
            }
    
            // Retorna a resposta geral
            return response()->json([
                'status' => 'completed',
                'message' => 'Sincronização finalizada.',
                'data' => $response,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Retornar erros de validação no formato JSON
            return response()->json([
                'status' => 'validation_error',
                'message' => 'Erro de validação nos dados enviados.'. $e->errors(),
                'data' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Retornar erro geral
            \Log::error('Erro na sincronização das Condicoes de pagamento: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }
      
}
