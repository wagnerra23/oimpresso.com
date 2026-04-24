<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BaseApiController extends ApiController
{
    protected $model;
    protected $fieldMapping; // Centralização do mapeamento

    public function __construct($model, array $fieldMapping)
    {
        parent::__construct();
        $this->model = $model;
        $this->fieldMapping = $fieldMapping;
    }

    public function syncData(Request $request, array $validationRules, array $responseMapping, callable $uniqueQueryCallback = null)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
            ] + $validationRules);

            $data = $validatedData['data'];
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    // Usa o callback personalizado ou aplica o padrão
                    $query = $uniqueQueryCallback
                        ? call_user_func($uniqueQueryCallback, $this->model, $item, $business_id)
                        : $this->defaultQuery($item, $business_id);

                    $record = $query->first();

                    if ($record) {
                        // Verificar conflito
                        $updatedAt = $record->updated_at->format('Y-m-d H:i:s');
                        $itemUpdatedAt = isset($item['oimpresso_updated_at']) ? Carbon::parse($item['oimpresso_updated_at'])->format('Y-m-d H:i:s') : null;

                        if ($updatedAt !== $itemUpdatedAt) {
                            $response[] = $this->formatResponse($record, 'conflict', 'Conflito detectado. O registro foi modificado após a última sincronização.', $responseMapping, $item);
                            continue;
                        }

                        // Atualizar registro existente
                        $record->update($this->mapData($item));

                        $response[] = $this->formatResponse($record, 'updated', 'O registro foi atualizado com sucesso.', $responseMapping, $item);
                    } else {
                        // Criar novo registro
                        $newRecord = $this->model::create(array_merge($this->mapData($item), [
                            'business_id' => $business_id,
                            'created_by' => $user->id,  // $item['codusuario'] ?? null, nem toda tabela(modelo) tem esse campo, e para funcionar teria que converter o codusuario para 
                        ]));

                        $response[] = $this->formatResponse($newRecord, 'created', 'O registro foi criado com sucesso.', $responseMapping, $item);
                    }
                } catch (\Exception $e) {
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                        'data' => $item,
                    ];
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => 'Sincronização finalizada.',
                'data' => $response,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'message' => 'Erro de validação nos dados enviados.',
                'data' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro na sincronização: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar a sincronização. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getData(Request $request, callable $queryCallback = null)
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
    
            // Consulta padrão ou personalizada
            $query = $queryCallback
                ? call_user_func($queryCallback, $this->model, $business_id, $date)
                : $this->model::where('business_id', $business_id)
                      ->where('updated_at', '>', $date);
    
            $records = $query->get();
    

            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados.',
                'data' => $records->map(function ($record) {
                    // Aplica o mapeamento inverso ao registro
                    $mappedData = $this->mapData($record->toArray(), true); // Mapeia de volta (inverso)
            
                    return array_merge($mappedData, [
                        'id' => $record->id,
                        'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                        'deleted_at' => $record->deleted_at ?? null,
                        'officeimpresso_codigo' => $record->officeimpresso_codigo ?? null,
                        'officeimpresso_dt_alteracao' => $record->officeimpresso_dt_alteracao ?? null,
                        'officeimpresso_action' => 'update local',
                        'officeimpresso_message' => 'Registro modificado no site. Atualização realizada no banco de dados local.',
                    ]);
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar dados sincronizados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados. ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function defaultQuery($item, $business_id)
    {
        return $this->model::where('business_id', $business_id)
            ->where(function ($query) use ($item) {
                $query->where('id', $item['oimpresso_id'])
                    ->orWhere('officeimpresso_codigo', $item['codigo']);
            });
    }

    /**
     * Mapeia os dados entre entrada e modelo.
     */
    private function mapData(array $data, bool $reverse = false)
    {
        $mapping = $reverse ? array_flip($this->fieldMapping) : $this->fieldMapping;
        $mappedData = [];
        foreach ($mapping as $modelField => $inputField) {
            $mappedData[$modelField] = $data[$inputField] ?? null;
        }
        return $mappedData;
    }

    private function formatResponse($record, $action, $message, $responseMapping, $item = [])
    {
        return [
            'officeimpresso_action' => $action,
            'officeimpresso_message' => $message,
            'id' => $record->id,
            'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
            'deleted_at' => $record->deleted_at ?? null,
            'officeimpresso_codigo' => $record->officeimpresso_codigo ?? null,
            'officeimpresso_dt_alteracao' => $record->officeimpresso_dt_alteracao ?? null,
        ] + $responseMapping;
    }

    /**
     * Resolve a chave estrangeira com base no código.
     *
     * @param string $model - O modelo (ex.: App\Brand)
     * @param string $codeField - O campo de código (ex.: codproduto_marca)
     * @param string $codeValue - O valor do código
     * @param mixed $defaultValue - Valor padrão caso não encontre
     * @return int|null - Retorna o ID correspondente ou null
     */
    private function resolveForeignKeyByCode($model, $codeValue, $codeField = 'officeimpresso_codigo', $defaultValue = null)
    {
        $record = $model::where($codeField, $codeValue)->first();
        return $record ? $record->id : $defaultValue;
    }
}
