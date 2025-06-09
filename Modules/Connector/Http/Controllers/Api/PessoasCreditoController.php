
<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\PessoasCredito;

class PessoasCreditoController extends ApiController
{
    public function syncPessoasCredito(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:255',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
            ]);

            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    $record = PessoasCredito::where('business_id', $business_id)
                        ->where('codigo', $item['codigo'])
                        ->first();

                    if ($record) {
                        $updatedAt = $record->updated_at->format('Y-m-d H:i:s');
                        $itemUpdatedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                        if ($updatedAt !== $itemUpdatedAt) {
                            $response[] = [
                                'action' => 'conflict',
                                'message' => 'Registro modificado após a última sincronização.',
                                'id' => $record->id,
                                'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                                'codigo' => $record->codigo,
                                'dt_alteracao' => $record->dt_alteracao,
                            ];
                            continue;
                        }

                        $record->update([
                            'descricao' => $item['descricao'],
                            'dt_alteracao' => $item['dt_alteracao'],
                        ]);

                        $response[] = [
                            'action' => 'updated',
                            'message' => 'Registro atualizado com sucesso.',
                            'id' => $record->id,
                            'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                        ];
                    } else {
                        $newRecord = PessoasCredito::create([
                            'business_id' => $business_id,
                            'codigo' => $item['codigo'],
                            'descricao' => $item['descricao'],
                            'dt_alteracao' => $item['dt_alteracao'],
                        ]);

                        $response[] = [
                            'action' => 'created',
                            'message' => 'Registro criado com sucesso.',
                            'id' => $newRecord->id,
                            'updated_at' => $newRecord->updated_at->format('Y-m-d H:i:s'),
                        ];
                    }
                } catch (\Exception $e) {
                    $response[] = [
                        'action' => 'error',
                        'message' => $e->getMessage(),
                        'codigo' => $item['codigo'] ?? null,
                    ];
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => 'Sincronização concluída.',
                'data' => $response,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'message' => 'Erro de validação.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar PessoasCredito: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização.',
            ], 500);
        }
    }
}
