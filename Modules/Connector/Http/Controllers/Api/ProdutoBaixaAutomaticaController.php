
<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\ProdutoBaixaAutomatica;

/**
 * @group Produto Baixa Automática Management
 * @authenticated
 *
 * APIs para gerenciar Produto Baixa Automática.
 */
class ProdutoBaixaAutomaticaController extends ApiController
{
    /**
     * Lista de Produtos Baixa Automática
     */
    public function index()
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $produtos = ProdutoBaixaAutomatica::where('business_id', $business_id)->get();

        return CommonResource::collection($produtos);
    }

    /**
     * Sincronizar Produtos Baixa Automática
     */
    public function syncProdutoBaixaAutomatica(Request $request)
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
                    $produto = ProdutoBaixaAutomatica::where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();

                    if ($produto) {
                        $updatedAt = $produto->updated_at->format('Y-m-d H:i:s');
                        $oimpressoUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                        if ($updatedAt !== $oimpressoUpdatedAt) {
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $produto->id,
                                'updated_at' => $produto->updated_at->format('Y-m-d H:i:s'),
                                'deleted_at' => $produto->deleted_at,
                                'officeimpresso_codigo' => $produto->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $produto->officeimpresso_dt_alteracao,
                            ];
                            continue;
                        }

                        $produto->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                            'id' => $produto->id,
                            'updated_at' => $produto->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $produto->deleted_at,
                            'officeimpresso_codigo' => $produto->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $produto->officeimpresso_dt_alteracao,
                        ];
                    } else {
                        $produto = ProdutoBaixaAutomatica::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'O registro foi criado com sucesso.',
                            'id' => $produto->id,
                            'updated_at' => $produto->updated_at->format('Y-m-d H:i:s'),
                            'deleted_at' => $produto->deleted_at,
                            'officeimpresso_codigo' => $produto->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $produto->officeimpresso_dt_alteracao,
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
            Log::error('Erro na sincronização de Produto Baixa Automática: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }
}
