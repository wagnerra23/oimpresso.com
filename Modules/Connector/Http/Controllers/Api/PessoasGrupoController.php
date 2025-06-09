<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use App\PessoasGrupo;
use Carbon\Carbon;

class PessoasGrupoController extends Controller
{
    /**
     * Sincronizar registros de pessoas_grupo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncPessoasGrupo(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:150',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
            ]);

            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    $grupo = PessoasGrupo::withTrashed()
                        ->where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                  ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();

                    if ($grupo) {
                        $updatedAt = $grupo->updated_at->format('Y-m-d H:i:s');
                        $oimpressoUpdatedAt = isset($item['dt_alteracao'])
                            ? Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s')
                            : null;

                        if ($updatedAt !== $oimpressoUpdatedAt) {
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $grupo->id,
                                'updated_at' => $grupo->updated_at->format('Y-m-d H:i:s'),
                                'deleted_at' => $grupo->deleted_at,
                                'officeimpresso_codigo' => $grupo->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $grupo->officeimpresso_dt_alteracao,
                            ];
                            continue;
                        }

                        $grupo->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'] ?? null,
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                            'id' => $grupo->id,
                            'updated_at' => $grupo->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $grupo->deleted_at,
                            'officeimpresso_codigo' => $grupo->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $grupo->officeimpresso_dt_alteracao,
                        ];
                    } else {
                        $grupo = PessoasGrupo::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'] ?? null,
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'O registro foi criado com sucesso.',
                            'id' => $grupo->id,
                            'updated_at' => $grupo->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $grupo->deleted_at,
                            'officeimpresso_codigo' => $grupo->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $grupo->officeimpresso_dt_alteracao,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao processar registro: ' . $e->getMessage());
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'id' => $item['id'],
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
            Log::error('Erro na sincronização de PessoasGrupo: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar registros atualizados após uma data específica
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */             
    public function getSyncPessoasGrupoUntilDate(Request $request)
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
    
            // Buscar registros de PessoasGrupo atualizados após a data informada
            $grupos = PessoasGrupo::where('business_id', $business_id)
                                  ->where('updated_at', '>', $date)
                                  ->get();
    
            // Formatar a resposta
            $response = $grupos->map(function ($grupo) {
                return [
                    'id' => $grupo->id,
                    'descricao' => $grupo->descricao,
                    'officeimpresso_codigo' => $grupo->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $grupo->officeimpresso_dt_alteracao,
                    'updated_at' => $grupo->updated_at->format('Y-m-d H:i:s'),
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
            Log::error('Erro ao buscar registros de PessoasGrupo: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
            ], 500);
        }
    }
    
}
