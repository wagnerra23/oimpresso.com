
<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\PessoasRepresentante;

/**
 * @group Pessoas Representante Management
 * @authenticated
 *
 * APIs para gerenciar representantes de pessoas.
 */
class PessoasRepresentanteController extends ApiController
{
    /**
     * Sincronizar representantes de pessoas.
     */
    public function syncPessoasRepresentante(Request $request)
    {
        try {
            // Validação dos dados
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
                    $representante = PessoasRepresentante::where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();

                    if ($representante) {
                        $updatedAt = $representante->updated_at->format('Y-m-d H:i:s');
                        $oimpressoUpdatedAt = $item['dt_alteracao'] ?? null;

                        if ($updatedAt !== $oimpressoUpdatedAt) {
                            $response[] = [
                                'officeimpresso_action' => 'conflict',
                                'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                                'id' => $representante->id,
                                'updated_at' => $representante->updated_at->format('Y-m-d H:i:s'),
                                'officeimpresso_codigo' => $representante->officeimpresso_codigo,
                                'officeimpresso_dt_alteracao' => $representante->officeimpresso_dt_alteracao,
                            ];
                            continue;
                        }

                        $representante->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                            'id' => $representante->id,
                            'updated_at' => $representante->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_codigo' => $representante->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $representante->officeimpresso_dt_alteracao,
                        ];
                    } else {
                        $representante = PessoasRepresentante::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'O registro foi criado com sucesso.',
                            'id' => $representante->id,
                            'updated_at' => $representante->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_codigo' => $representante->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $representante->officeimpresso_dt_alteracao,
                        ];
                    }
                } catch (\Exception $e) {
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'officeimpresso_codigo' => $item['codigo'] ?? null,
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
            Log::error('Erro na sincronização de representantes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar representantes de pessoas até uma data específica.
     */
    public function getSyncPessoasRepresentanteUntilDate(Request $request)
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
            $date = $request->input('date');

            $representantes = PessoasRepresentante::where('business_id', $business_id)
                ->where('updated_at', '>', $date)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados.',
                'data' => $representantes->map(function ($representante) {
                    return [
                        'id' => $representante->id,
                        'descricao' => $representante->descricao,
                        'officeimpresso_codigo' => $representante->officeimpresso_codigo,
                        'officeimpresso_dt_alteracao' => $representante->officeimpresso_dt_alteracao,
                        'updated_at' => $representante->updated_at->format('Y-m-d H:i:s'),
                        'officeimpresso_action' => 'update local',
                        'officeimpresso_message' => 'Registro atualizado no banco de dados local.',
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar representantes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
            ], 500);
        }
    }
}
