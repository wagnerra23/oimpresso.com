<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ProdutoEstoqueReserva;
use Carbon\Carbon;

class ProdutoEstoqueReservaController extends Controller
{
    public function syncPost(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:255',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
            ]);

            $data = $validatedData['data'];
            $response = [];
            $user = Auth::user();
            $businessId = $user->business_id;

            foreach ($data as $item) {
                try {
                    $record = ProdutoEstoqueReserva::updateOrCreate(
                        [
                            'business_id' => $businessId,
                            'codigo' => $item['codigo'],
                        ],
                        [
                            'descricao' => $item['descricao'],
                            'dt_alteracao' => $item['dt_alteracao'] ?? now(),
                        ]
                    );

                    $action = $record->wasRecentlyCreated ? 'created' : 'updated';

                    $response[] = [
                        'officeimpresso_action' => $action,
                        'officeimpresso_message' => "Registro $action com sucesso.",
                        'id' => $record->id,
                        'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                        'deleted_at' => $record->deleted_at,
                        'codigo' => $record->codigo,
                        'dt_alteracao' => $record->dt_alteracao,
                    ];
                } catch (\Exception $e) {
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'codigo' => $item['codigo'],
                    ];
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => 'Sincronização concluída.',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar ProdutoEstoqueReserva: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar sincronização.',
            ], 500);
        }
    }

    public function syncGet(Request $request)
    {
        try {
            $date = $request->query('date');

            if (!$date) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Parâmetro "date" é obrigatório.',
                ], 422);
            }

            $user = Auth::user();
            $businessId = $user->business_id;

            $records = ProdutoEstoqueReserva::where('business_id', $businessId)
                ->where('updated_at', '>', Carbon::parse($date))
                ->get();

            $response = $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'descricao' => $record->descricao,
                    'codigo' => $record->codigo,
                    'dt_alteracao' => $record->dt_alteracao,
                    'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                    'officeimpresso_action' => 'sync',
                    'officeimpresso_message' => 'Registro sincronizado.',
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados com sucesso.',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar ProdutoEstoqueReserva: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados.',
            ], 500);
        }
    }
}
