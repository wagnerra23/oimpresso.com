<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\Unit;

/**
 * @group Unit management
 * @authenticated
 *
 * APIs for managing units
 */
class UnitController extends ApiController
{
    /**
     * List units
     * @response {
        "data": [
            {
                "id": 1,
                "business_id": 1,
                "actual_name": "Pieces",
                "short_name": "Pc(s)",
                "allow_decimal": 0,
                "base_unit_id": null,
                "base_unit_multiplier": null,
                "created_by": 1,
                "deleted_at": null,
                "created_at": "2018-01-03 15:15:20",
                "updated_at": "2018-01-03 15:15:20",
                "base_unit": null
            },
            {
                "id": 2,
                "business_id": 1,
                "actual_name": "Packets",
                "short_name": "packets",
                "allow_decimal": 0,
                "base_unit_id": null,
                "base_unit_multiplier": null,
                "created_by": 1,
                "deleted_at": null,
                "created_at": "2018-01-06 01:07:01",
                "updated_at": "2018-01-06 01:08:36",
                "base_unit": null
            },
            {
                "id": 15,
                "business_id": 1,
                "actual_name": "Dozen",
                "short_name": "dz",
                "allow_decimal": 0,
                "base_unit_id": 1,
                "base_unit_multiplier": "12.0000",
                "created_by": 9,
                "deleted_at": null,
                "created_at": "2020-07-20 13:11:09",
                "updated_at": "2020-07-20 13:11:09",
                "base_unit": {
                    "id": 1,
                    "business_id": 1,
                    "actual_name": "Pieces",
                    "short_name": "Pc(s)",
                    "allow_decimal": 0,
                    "base_unit_id": null,
                    "base_unit_multiplier": null,
                    "created_by": 1,
                    "deleted_at": null,
                    "created_at": "2018-01-03 15:15:20",
                    "updated_at": "2018-01-03 15:15:20"
                }
            }
        ]
    }
     */
    public function index()
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        
        $units = Unit::where('business_id', $business_id)
                    ->with(['base_unit'])
                    ->get();

        return CommonResource::collection($units);
    }

    /**
     * Get the specified unit
     *
     * @urlParam unit required comma separated ids of the units Example: 1
     * @response {
        "data": [
            {
                "id": 1,
                "business_id": 1,
                "actual_name": "Pieces",
                "short_name": "Pc(s)",
                "allow_decimal": 0,
                "base_unit_id": null,
                "base_unit_multiplier": null,
                "created_by": 1,
                "deleted_at": null,
                "created_at": "2018-01-03 15:15:20",
                "updated_at": "2018-01-03 15:15:20",
                "base_unit": null
            }
        ]
    }
     */
    public function show($unit_ids)
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        $unit_ids = explode(',', $unit_ids);

        $units = Unit::where('business_id', $business_id)
                        ->whereIn('id', $unit_ids)
                        ->with(['base_unit'])
                        ->get();

        return CommonResource::collection($units);
    }

/**
 * Listar unidades atualizadas até uma data específica
 *
 * @queryParam date required Data de referência para filtrar unidades no formato 'Y-m-d H:i:s'. Example: 2024-11-10 15:30:00
 * @response {
 *     "status": "success",
 *     "message": "Dados sincronizados.",
 *     "data": [
 *         {
 *             "id": 1,
 *             "descricao": "Pacotes",
 *             "unidade": "Pkt",
 *             "updated_at": "2024-11-11 12:00:00",
 *             "deleted_at": null,
 *             "officeimpresso_codigo": "123456",
 *             "officeimpresso_dt_alteracao": "2024-11-11 12:00:00",
 *             "exibir_comprimento": 1,
 *             "exibir_largura": 0,
 *             "exibir_espessura": 1,
 *             "calc_comprimento": 1,
 *             "calc_largura": 0,
 *             "calc_espessura": 1,
 *             "gera_lote": 1,
 *             "exibir_qtdmetricaunitaria": 0,
 *             "formula": "C*L*E",
 *             "officeimpresso_action": "update local",
 *             "officeimpresso_message": "Registro modificado no site. Atualização realizada no banco de dados local."
 *         }
 *     ]
 * }
 */
public function getUnitsUntilDate(Request $request)
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

        // Buscar unidades atualizadas após a data informada
        $units = Unit::where('business_id', $business_id)
                    ->where('updated_at', '>', $date)
                    ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Dados sincronizados.',
            'data' => $units->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'descricao' => $unit->actual_name, // Nome da unidade
                    'unidade' => $unit->short_name,    // Abreviação
                    'updated_at' => $unit->updated_at->format('Y-m-d H:i:s'),
                    'deleted_at' => $unit->deleted_at,
                    'officeimpresso_codigo' => $unit->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $unit->officeimpresso_dt_alteracao,
                    'exibir_comprimento' => $unit->exibir_comprimento,
                    'exibir_largura' => $unit->exibir_largura,
                    'exibir_espessura' => $unit->exibir_espessura,
                    'calc_comprimento' => $unit->calc_comprimento,
                    'calc_largura' => $unit->calc_largura,
                    'calc_espessura' => $unit->calc_espessura,
                    'gera_lote' => $unit->gera_lote,
                    'exibir_qtdmetricaunitaria' => $unit->exibir_qtdmetricaunitaria,
                    'formula' => $unit->formula,
                    'officeimpresso_action' => 'update local',
                    'officeimpresso_message' => 'Registro modificado no site. Atualização realizada no banco de dados local.',
                ];
            }),
        ]);
    } catch (\Exception $e) {
        \Log::error('Erro ao buscar unidades sincronizadas: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
        ], 500);
    }
}



/**
 * Sincroniza unidades entre o banco local e o servidor
 *
 * @bodyParam data array required Lista de unidades para sincronizar.
 * @bodyParam data[].id integer ID da unidade no servidor remoto. Exemplo: 1
 * @bodyParam data[].descricao string required Nome da unidade. Exemplo: Pacotes
 * @bodyParam data[].unidade string required Nome curto da unidade. Exemplo: Pct
 * @bodyParam data[].updated_at string required Data/hora da última atualização no formato 'Y-m-d H:i:s'. Exemplo: 2024-11-18 12:00:00
 * @bodyParam data[].codigo integer required Código local da unidade. Exemplo: U123
 * @bodyParam data[].dt_alteracao string required Data/hora da última alteração no sistema local. Exemplo: 2024-11-18 12:00:00
 * @bodyParam data[].exibir_comprimento smallint Exibir comprimento. Exemplo: 1
 * @bodyParam data[].exibir_largura smallint Exibir largura. Exemplo: 1
 * @bodyParam data[].exibir_espessura smallint Exibir espessura. Exemplo: 1
 * @bodyParam data[].calc_comprimento smallint Calcular comprimento. Exemplo: 1
 * @bodyParam data[].calc_largura smallint Calcular largura. Exemplo: 1
 * @bodyParam data[].calc_espessura smallint Calcular espessura. Exemplo: 1
 * @bodyParam data[].gera_lote smallint Gerar lote. Exemplo: 0
 * @bodyParam data[].exibir_qtdmetricaunitaria smallint Exibir quantidade métrica unitária. Exemplo: 1
 * @bodyParam data[].ativo string Ativo ('y' para sim, 'n' para não). Exemplo: y
 * @bodyParam data[].formula string Fórmula da unidade. Exemplo: COMPRIMENTO * LARGURA
 *
 * @response {
 *   "status": "concluido",
 *   "mensagem": "Sincronização concluída.",
 *   "dados": [
 *       {
 *           "acao": "atualizado",
 *           "mensagem": "Registro atualizado com sucesso.",
 *           "id": 1,
 *           "updated_at": "2024-11-18 12:00:00",
 *           "officeimpresso_codigo": "U123",
 *           "officeimpresso_dt_alteracao": "2024-11-18 12:00:00"
 *       }
 *   ]
 * }
 */
public function syncUnits(Request $request)
{
    try {
        $request->validate([
            'data' => 'required|array',
            'data.*.oimpresso_id' => 'nullable|integer',
            'data.*.descricao' => 'required|string|max:255',
            'data.*.unidade' => 'required|string|max:50',
            'data.*.codigo' => 'required|integer',
            'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
            'data.*.exibir_comprimento' => 'nullable|integer',
            'data.*.exibir_largura' => 'nullable|integer',
            'data.*.exibir_espessura' => 'nullable|integer',
            'data.*.calc_comprimento' => 'nullable|integer',
            'data.*.calc_largura' => 'nullable|integer',
            'data.*.calc_espessura' => 'nullable|integer',
            'data.*.gera_lote' => 'nullable|integer',
            'data.*.exibir_qtdmetricaunitaria' => 'nullable|integer',
            'data.*.formula' => 'nullable|string|max:20',
        ]);
        } catch (ValidationException $e) {
            // Captura erros detalhados
            $errors = $e->errors();

            return response()->json([
                'status' => 'error',
                'message' => 'Erros de validação encontrados nos dados.',
                'errors' => $errors, // Detalha os campos inválidos e suas mensagens
            ], 422);
        }   

    try {

        $data = $request->input('data');
        $response = [];
        $user = Auth::user();
        $business_id = $user->business_id;

        foreach ($data as $item) {
            try {
                $unit = Unit::where('business_id', $business_id)
                    ->where(function ($query) use ($item) {
                        $query->where('id', $item['oimpresso_id'])
                              ->orWhere('officeimpresso_codigo', $item['codigo']);
                    })
                    ->first();

                if ($unit) {
                    // Check for conflict in updated_at
                    $updatedAt = $unit->updated_at->format('Y-m-d H:i:s');
                    $receivedAt = Carbon::parse($item['dt_alteracao'])->format('Y-m-d H:i:s');

                    if ($updatedAt !== $receivedAt) {
                        $response[] = [
                            'action' => 'conflict',
                            'message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                            'id' => $unit->id,
                            'updated_at' => $unit->updated_at->format('Y-m-d H:i:s'),
                            'officeimpresso_codigo' => $unit->officeimpresso_codigo,
                            'officeimpresso_dt_alteracao' => $unit->officeimpresso_dt_alteracao,
                        ];
                        continue; // Skip this record and process the next one
                    }

                    // Update existing record
                    $unit->update([
                        'actual_name' => $item['descricao'],
                        'short_name' => $item['unidade'],
                        'officeimpresso_codigo' => $item['codigo'],
                        'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                        'exibir_comprimento' => $item['exibir_comprimento'] ?? 0,
                        'exibir_largura' => $item['exibir_largura'] ?? 0,
                        'exibir_espessura' => $item['exibir_espessura'] ?? 0,
                        'calc_comprimento' => $item['calc_comprimento'] ?? 0,
                        'calc_largura' => $item['calc_largura'] ?? 0,
                        'calc_espessura' => $item['calc_espessura'] ?? 0,
                        'gera_lote' => $item['gera_lote'] ?? 0,
                        'exibir_qtdmetricaunitaria' => $item['exibir_qtdmetricaunitaria'] ?? 0,
                        'formula' => $item['formula'] ?? null,
                    ]);

                    $response[] = [
                        'action' => 'updated',
                        'message' => 'Registro atualizado com sucesso.',
                        'id' => $unit->id,
                        'updated_at' => $unit->updated_at->format('Y-m-d H:i:s'),
                        'officeimpresso_codigo' => $unit->officeimpresso_codigo,
                        'officeimpresso_dt_alteracao' => $unit->officeimpresso_dt_alteracao,
                    ];
                } else {
                    // Create new record
                    $unit = Unit::create([
                        'business_id' => $business_id,
                        'allow_decimal' => 1,
                        'created_by' => $user->id,
                        'actual_name' => $item['descricao'],
                        'short_name' => $item['unidade'],
                        'officeimpresso_codigo' => $item['codigo'],
                        'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                        'exibir_comprimento' => $item['exibir_comprimento'] ?? 0,
                        'exibir_largura' => $item['exibir_largura'] ?? 0,
                        'exibir_espessura' => $item['exibir_espessura'] ?? 0,
                        'calc_comprimento' => $item['calc_comprimento'] ?? 0,
                        'calc_largura' => $item['calc_largura'] ?? 0,
                        'calc_espessura' => $item['calc_espessura'] ?? 0,
                        'gera_lote' => $item['gera_lote'] ?? 0,
                        'exibir_qtdmetricaunitaria' => $item['exibir_qtdmetricaunitaria'] ?? 0,
                        'formula' => $item['formula'] ?? null,
                    ]);

                    $response[] = [
                        'action' => 'created',
                        'message' => 'Registro criado com sucesso.',
                        'id' => $unit->id,
                        'updated_at' => $unit->updated_at->format('Y-m-d H:i:s'),
                        'officeimpresso_codigo' => $unit->officeimpresso_codigo,
                        'officeimpresso_dt_alteracao' => $unit->officeimpresso_dt_alteracao,
                    ];
                }
            } catch (\Exception $e) {
                $response[] = [
                    'action' => 'error',
                    'message' => 'Erro ao processar o registro: ' . $e->getMessage(),
                    'officeimpresso_codigo' => $item['codigo'] ?? null,
                    'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                ];
            }
        }

        // Final response
        return response()->json([
            'status' => 'completed',
            'message' => 'Sincronização concluída com sucesso.',
            'data' => $response,
        ]);
    } catch (\Exception $e) {
        \Log::error('Erro na sincronização de unidades: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
        ], 500);
    }
}


}
