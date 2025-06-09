<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\Cidades;

class CidadesController extends ApiController
{
    public function index()
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $cidades = Cidades::where('business_id', $business_id)->get();

        return CommonResource::collection($cidades);
    }

    public function show($cidades_ids)
    {
        $user = Auth::user();
        $business_id = $user->business_id;
        $cidades_ids = explode(',', $cidades_ids);

        $cidades = Cidades::where('business_id', $business_id)
                        ->whereIn('id', $cidades_ids)
                        ->get();

        return CommonResource::collection($cidades);
    }

    public function getCidadesUntilDate(Request $request)
    {
        if (!$request->has('date') || !$request->filled('date')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parâmetro date está ausente ou vazio.',
            ], 422);
        }

        try {
            $user = Auth::user();
            $business_id = $user->business_id;
            $date = Carbon::parse($request->input('date'))->format('Y-m-d H:i:s');

            $cidades = Cidades::where('business_id', $business_id)
                            ->where('updated_at', '>', $date) // Correção feita para “>”
                            ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados.',
                'data' => $cidades->map(function ($cidade) {
                    return [
                        'id' => $cidade->id,
                        'descricao' => $cidade->descricao,
                        'uf' => $cidade->uf,
                        'updated_at' => $cidade->updated_at->format('Y-m-d H:i:s'),
                        'deleted_at' => $cidade->deleted_at,
                        'officeimpresso_codigo' => $cidade->officeimpresso_codigo,
                        'officeimpresso_dt_alteracao' => $cidade->officeimpresso_dt_alteracao,
                        'officeimpresso_action' => 'update local',
                        'officeimpresso_message' => 'Registro modificado no site. Atualização realizada no banco de dados local.',
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar cidades atualizadas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar as cidades. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function syncCidades(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:40',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
                'data.*.uf' => 'nullable|string|max:2',
            ]);

            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    $cidade = Cidades::where('business_id', $business_id)
                        ->where('officeimpresso_codigo', $item['codigo'])
                        ->first();

                    if ($cidade) {
                        $updatedAt = $cidade->updated_at->format('Y-m-d H:i:s');
                        $itemUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                        if ($updatedAt !== $itemUpdatedAt) {
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $cidade->id,
                                'updated_at' => $cidade->updated_at->format('Y-m-d H:i:s'),
                                'deleted_at' => $cidade->deleted_at,
                                'officeimpresso_codigo' => $cidade->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $cidade->officeimpresso_dt_alteracao,
                            ];
                            continue;
                        }

                        $cidade->update([
                            'descricao' => $item['descricao'],
                            'uf' => $item['uf'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'Registro atualizado com sucesso.',
                            'id' => $cidade->id,
                            'updated_at' => $cidade->updated_at->format('Y-m-d H:i:s'),
                        ];
                    } else {
                        $cidade = Cidades::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'uf' => $item['uf'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'Registro criado com sucesso.',
                            'id' => $cidade->id,
                            'updated_at' => $cidade->updated_at->format('Y-m-d H:i:s'),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao sincronizar cidade: ' . $e->getMessage());
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'officeimpresso_codigo' => $item['codigo'],
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
            Log::error('Erro geral na sincronização: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }
}
