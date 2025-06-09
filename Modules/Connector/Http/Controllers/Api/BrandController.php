<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\Brands;


/**
 * @group Brand management
 * @authenticated
 *
 * APIs for managing brands
 */
class BrandController extends BaseApiController
{

    public function __construct()
    {
        // Mapeamento centralizado
        $fieldMapping = [
            'name' => 'descricao',
            'officeimpresso_codigo' => 'codigo',
            'officeimpresso_dt_alteracao' => 'dt_alteracao',
        ];

        parent::__construct(Brands::class, $fieldMapping);
    }

    /**
     * Get brands updated after a specific date.
     *
     * @queryParam date required The reference date to filter brands in 'Y-m-d H:i:s' format. Example: 2024-11-10 15:30:00
     * @response {
     *   "status": "success",
     *   "message": "Dados sincronizados.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "descricao": "Levis",
     *       "codigo": "123456",
     *       "dt_alteracao": "2024-11-11 12:00:00",
     *       "updated_at": "2024-11-11 12:00:00",
     *       "deleted_at": null,
     *       "officeimpresso_action": "update local",
     *       "officeimpresso_message": "Registro modificado no site. Atualização realizada no banco de dados local."
     *     }
     *   ]
     * }
     */
    public function getUntilDate(Request $request)
    {
        return $this->getData($request);
    }

    /**
     * Synchronize brands between local and server.
     *
     * @bodyParam data array required List of brands to synchronize.
     * @response {
     *   "status": "completed",
     *   "data": [
     *     {
     *       "officeimpresso_action": "updated",
     *       "officeimpresso_message": "Registro atualizado com sucesso.",
     *       "id": 1,
     *       "updated_at": "2024-11-18 12:00:00",
     *       "codigo": "IM123",
     *       "dt_alteracao": "2024-11-18 12:00:00"
     *     }
     *   ]
     * }
     */
    public function sync(Request $request)
    {
        $validationRules = [
            'data.*.codigo' => 'required|integer',
            'data.*.descricao' => 'required|string|max:255',
            'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
        ];

        return $this->syncData($request, $validationRules, $this->fieldMapping);
    }

    /**
     * Lista marcas
     * @response {
         "data": [
             {
                 "id": 1,
                 "business_id": 1,
                 "name": "Levis",
                 "description": null,
                 "created_by": 1,
                 "officeimpresso_codigo": "789011", 
                 "officeimpresso_dt_alteracao": "2024-11-11 12:00:00",  
                 "deleted_at": null,
                 "created_at": "2018-01-03 21:19:47",
                 "updated_at": "2018-01-03 21:19:47"
             },
             {
                 "id": 2,
                 "business_id": 1,
                 "name": "Espirit",
                 "description": null,
                 "created_by": 1,
                 "officeimpresso_codigo": "OI_789012", 
                 "officeimpresso_dt_alteracao": "2024-11-11 12:00:00", 
                 "deleted_at": null,
                 "created_at": "2018-01-03 21:19:58",
                 "updated_at": "2018-01-03 21:19:58"
             }
         ]
      }
     */
    public function index()
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        
        $brands = Brands::where('business_id', $business_id)
                        ->get();

        return CommonResource::collection($brands);
    }

    /**
     * Busca uma marca específica
     *
     * @urlParam brand required comma separated ids of the brands Example: 1
     * @response {
         "data": [
             {
                 "id": 1,
                 "business_id": 1,
                 "name": "Levis",
                 "description": null,
                 "created_by": 1,
                 "officeimpresso_codigo": "123456",
                 "officeimpresso_dt_alteracao": "2024-11-11 12:00:00",  
                 "deleted_at": null,
                 "created_at": "2018-01-03 21:19:47",
                 "updated_at": "2018-01-03 21:19:47"
             }
         ]
      }
     */

    public function show($brand_ids)
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        $brand_ids = explode(',', $brand_ids);

        $brands = Brands::where('business_id', $business_id)
                        ->whereIn('id', $brand_ids)
                        ->get();

        return CommonResource::collection($brands);

    }    

    /**
     * Criar uma nova marca
     *
     * @bodyParam name string required The name of the brand. Example: Nike
     * @bodyParam description string The description of the brand. Example: Popular sports brand
     * @response {
         "data": {
             "id": 3,
             "business_id": 1,
             "name": "Nike",
             "description": "Popular sports brand",
             "created_by": 1,
             "officeimpresso_codigo": "789012",  
             "officeimpresso_dt_alteracao": "2024-11-11 12:00:00",  
             "created_at": "2024-11-11 12:00:00",
             "updated_at": "2024-11-11 12:00:00"
         }
      }
     */

    public function create(Request $request)
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        // Validar os dados da marca
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        // Criar uma nova marca
        $brand = Brands::create([
            'business_id' => $business_id,
            'name' => $validatedData['name'],
            'description' => $validatedData['description'] ?? null,
            'created_by' => $user->id,
            'officeimpresso_codigo' => $validatedData['codigo'], 
            'officeimpresso_dt_alteracao' => $validatedData['dt_alteracao'],  
    
        ]);

        return new CommonResource($brand);
    }

    /**
     * Atualizar uma marca existente
     *
     * @urlParam id int required The ID of the brand to update. Example: 1
     * @bodyParam name string required The name of the brand. Example: Adidas
     * @bodyParam description string The description of the brand. Example: Global sports brand
     * @response {
          "data": {
              "id": 1,
              "business_id": 1,
              "name": "Adidas",
              "description": "Global sports brand",
              "created_by": 1,
              "officeimpresso_codigo": "654321", 
              "officeimpresso_dt_alteracao": "2024-11-11 12:00:00", 
              "updated_at": "2024-11-11 12:30:00"
            }
        }
     */

     public function update(Request $request, $id)
     {
         try {
             $user = Auth::user();
             $business_id = $user->business_id;
     
             // Validar os dados da marca
             $validatedData = $request->validate([
                 'unidade' => 'required|string|max:255',
                 'description' => 'nullable|string|max:500',
                 'codigo' => 'required|integer',
                 'dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
             ]);
     
             // Buscar a marca que pertence ao business_id do usuário
             $brand = Brands::where('business_id', $business_id)->findOrFail($id);
     
             // Comparar `updated_at` para verificar se há conflitos
             $updatedAt = $brand->updated_at->format('Y-m-d H:i:s');
             $oimpressoUpdatedAt = Carbon::parse($validatedData['dt_alteracao'])->format('Y-m-d H:i:s');
     
             if ($updatedAt !== $oimpressoUpdatedAt) {
                 // Conflito detectado
                 return response()->json([
                     'officeimpresso_action' => 'conflict',
                     'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                     'id' => $brand->id,
                     'updated_at' => $brand->updated_at->format('Y-m-d H:i:s'),
                     'deleted_at' => $brand->deleted_at,
                     'officeimpresso_codigo' => $brand->officeimpresso_codigo,
                     'officeimpresso_dt_alteracao' => $brand->officeimpresso_dt_alteracao,
                 ]);
             }
     
             // Atualizar a marca com os novos dados
             $brand->update([
                 'name' => $validatedData['name'],
                 'description' => $validatedData['description'] ?? $brand->description,
                 'officeimpresso_codigo' => $validatedData['codigo'],
                 'officeimpresso_dt_alteracao' => $validatedData['dt_alteracao'],
             ]);
     
             // Retorno de sucesso
             return response()->json([
                 'officeimpresso_action' => 'updated',
                 'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                 'id' => $brand->id,
                 'updated_at' => $brand->updated_at->format('Y-m-d H:i:s'),
                 'deleted_at' => $brand->deleted_at,
                 'officeimpresso_codigo' => $brand->officeimpresso_codigo,
                 'officeimpresso_dt_alteracao' => $brand->officeimpresso_dt_alteracao,
             ]);
         } catch (\Exception $e) {
             // Log de erro e retorno de falha
             \Log::error('Erro ao atualizar a marca: ' . $e->getMessage());
             return response()->json([
                 'status' => 'error',
                 'message' => 'Erro ao atualizar o registro. ' . $e->getMessage(),
             ], 500);
         }
     }

}
