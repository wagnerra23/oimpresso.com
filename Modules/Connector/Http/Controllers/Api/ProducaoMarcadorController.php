<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\ProducaoMarcador;

/**
 * @group Produção Marcador Management
 * @authenticated
 *
 * APIs para gerenciar Produção Marcador.
 */
class ProducaoMarcadorController extends ApiController
{
    /**
     * Sincronizar Produção Marcador
     *
     * @bodyParam data array required Lista de marcadores para sincronizar.
     * @response {
     *   "status": "completed",
     *   "message": "Sincronização finalizada.",
     *   "data": []
     * }
     */
    public function syncProducaoMarcador(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:255',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
                'data.*.oimpresso_id' => 'nullable|integer',
            ]);

            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    $marcador = ProducaoMarcador::where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();

                    if ($marcador) {
                        $updatedAt = $marcador->updated_at->format('Y-m-d H:i:s');
                        $oimpressoUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                        if ($updatedAt !== $oimpressoUpdatedAt) {
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $marcador->id,
                                'updated_at' => $marcador->updated_at->format('Y-m-d H:i:s'),
                                'deleted_at' => $marcador->deleted_at,
                                'officeimpresso_codigo' => $marcador->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $marcador->officeimpresso_dt_alteracao,
                            ];
                            continue;
                        }

                        $marcador->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                            'id' => $marcador->id,
                            'updated_at' => $marcador->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $marcador->deleted_at,
                            'officeimpresso_codigo' => $marcador->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $marcador->officeimpresso_dt_alteracao,
                        ];
                    } else {
                        $marcador = ProducaoMarcador::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                            'created_by' => $user->id,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'O registro foi criado com sucesso.',
                            'id' => $marcador->id,
                            'updated_at' => $marcador->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $marcador->deleted_at,
                            'officeimpresso_codigo' => $marcador->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $marcador->officeimpresso_dt_alteracao,
                        ];
                    }
                } catch (\Exception $e) {
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'officeimpresso_codigo' => $item['codigo'] ?? null,
                        'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
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
            Log::error('Erro na sincronização de Produção Marcador: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar Produção Marcador atualizado até uma data
     */
    public function getProducaoMarcadorUntilDate(Request $request)
    {
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

            $marcadores = ProducaoMarcador::where('business_id', $business_id)
                ->where('updated_at', '>', $date)
                ->get();

            $response = $marcadores->map(function ($marcador) {
                return [
                    'id' => $marcador->id,
                    'descricao' => $marcador->descricao,
                    'officeimpresso_codigo' => $marcador->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $marcador->officeimpresso_dt_alteracao,
                    'updated_at' => $marcador->updated_at->format('Y-m-d H:i:s'),
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
            Log::error('Erro ao buscar Produção Marcador: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
            ], 500);
        }
    }
}
