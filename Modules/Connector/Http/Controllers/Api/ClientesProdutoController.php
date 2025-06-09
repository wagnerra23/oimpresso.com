
<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\ClientesProduto;
use Carbon\Carbon;

/**
 * @group Clientes Produto Management
 * @authenticated
 *
 * APIs para gerenciar Clientes Produto.
 */
class ClientesProdutoController extends ApiController
{
    /**
     * Sincronizar Clientes Produto
     */
    public function syncClientesProduto(Request $request)
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
                    $registro = ClientesProduto::where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();

                    if ($registro) {
                        $updatedAt = $registro->updated_at->format('Y-m-d H:i:s');
                        $oimpressoUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                        if ($updatedAt !== $oimpressoUpdatedAt) {
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado.',
                                'id' => $registro->id,
                                'updated_at' => $registro->updated_at->format('Y-m-d H:i:s'),
                                'officeimpresso_codigo' => $registro->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $registro->officeimpresso_dt_alteracao,
                            ];
                            continue;
                        }

                        $registro->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'Registro atualizado.',
                            'id' => $registro->id,
                            'updated_at' => $registro->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_codigo' => $registro->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $registro->officeimpresso_dt_alteracao,
                        ];
                    } else {
                        $registro = ClientesProduto::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                            'created_by' => $user->id,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'Registro criado.',
                            'id' => $registro->id,
                            'updated_at' => $registro->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_codigo' => $registro->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $registro->officeimpresso_dt_alteracao,
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
            Log::error('Erro na sincronização de Clientes Produto: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obter registros atualizados até uma data específica
     */
    public function getSyncClientesProdutoUntilDate(Request $request)
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

            $registros = ClientesProduto::where('business_id', $business_id)
                ->where('updated_at', '>', $date)
                ->get();

            $response = $registros->map(function ($registro) {
                return [
                    'id' => $registro->id,
                    'descricao' => $registro->descricao,
                    'officeimpresso_codigo' => $registro->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $registro->officeimpresso_dt_alteracao,
                    'updated_at' => $registro->updated_at->format('Y-m-d H:i:s'),
                    'officeimpresso_action' => 'update local',
                    'officeimpresso_message' => 'Registro atualizado localmente.',
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados.',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar dados de Clientes Produto: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
            ], 500);
        }
    }
}
