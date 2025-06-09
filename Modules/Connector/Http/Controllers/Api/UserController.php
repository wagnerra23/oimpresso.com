<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Connector\Transformers\CommonResource;
use App\Utils\Util;
use App\User;
use App\Business;
use Modules\Connector\Utils\Cryptography;
use Carbon\Carbon;

/**
 * @group User management
 * @authenticated
 *
 * APIs for managing users
 */
class UserController extends ApiController
{
    /**
     * All Utils instance.
     *
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * List users
     *
     * @queryParam service_staff boolean Filter service staffs from users list (0, 1)
     *
     * @response {
            "data": [
                {
                    "id": 1,
                    "user_type": "user",
                    "surname": "Mr",
                    "first_name": "Admin",
                    "last_name": null,
                    "username": "admin",
                    "email": "admin@example.com",
                    "language": "en",
                    "contact_no": null,
                    "address": null,
                    "business_id": 1,
                    "max_sales_discount_percent": null,
                    "allow_login": 1,
                    "essentials_department_id": null,
                    "essentials_designation_id": null,
                    "status": "active",
                    "crm_contact_id": null,
                    "is_cmmsn_agnt": 0,
                    "cmmsn_percent": "0.00",
                    "selected_contacts": 0,
                    "dob": null,
                    "gender": null,
                    "marital_status": null,
                    "blood_group": null,
                    "contact_number": null,
                    "fb_link": null,
                    "twitter_link": null,
                    "social_media_1": null,
                    "social_media_2": null,
                    "permanent_address": null,
                    "current_address": null,
                    "guardian_name": null,
                    "custom_field_1": null,
                    "custom_field_2": null,
                    "custom_field_3": null,
                    "custom_field_4": null,
                    "bank_details": null,
                    "id_proof_name": null,
                    "id_proof_number": null,
                    "deleted_at": null,
                    "created_at": "2018-01-04 02:15:19",
                    "updated_at": "2018-01-04 02:15:19"
                }
            ]
        }
     */
    public function index()
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        
        if (!empty(request()->service_staff) && request()->service_staff == 1) {
            $users = $this->commonUtil->getServiceStaff($business_id);
        } else {
            $users = User::where('business_id', $business_id)
                        ->get();
        }

        return CommonResource::collection($users);
    }

    /**
     * Get the specified user
     * 
     * @response {
            "data": [
                {
                    "id": 1,
                    "user_type": "user",
                    "surname": "Mr",
                    "first_name": "Admin",
                    "last_name": null,
                    "username": "admin",
                    "email": "admin@example.com",
                    "language": "en",
                    "contact_no": null,
                    "address": null,
                    "business_id": 1,
                    "max_sales_discount_percent": null,
                    "allow_login": 1,
                    "essentials_department_id": null,
                    "essentials_designation_id": null,
                    "status": "active",
                    "crm_contact_id": null,
                    "is_cmmsn_agnt": 0,
                    "cmmsn_percent": "0.00",
                    "selected_contacts": 0,
                    "dob": null,
                    "gender": null,
                    "marital_status": null,
                    "blood_group": null,
                    "contact_number": null,
                    "fb_link": null,
                    "twitter_link": null,
                    "social_media_1": null,
                    "social_media_2": null,
                    "permanent_address": null,
                    "current_address": null,
                    "guardian_name": null,
                    "custom_field_1": null,
                    "custom_field_2": null,
                    "custom_field_3": null,
                    "custom_field_4": null,
                    "bank_details": null,
                    "id_proof_name": null,
                    "id_proof_number": null,
                    "deleted_at": null,
                    "created_at": "2018-01-04 02:15:19",
                    "updated_at": "2018-01-04 02:15:19"
                }
            ]
        }
     * @urlParam user required comma separated ids of the required users Example: 1
     */
    public function show($user_ids)
    {
        $user = Auth::user();

        $business_id = $user->business_id;
        $user_ids = explode(',', $user_ids);

        $users = User::where('business_id', $business_id)
                    ->whereIn('id', $user_ids)
                    ->get();

        return CommonResource::collection($users);
    }

    /**
     * Get the loggedin user details.
     * 
     * @response {
            "data":{
                "id": 1,
                "user_type": "user",
                "surname": "Mr",
                "first_name": "Admin",
                "last_name": null,
                "username": "admin",
                "email": "admin@example.com",
                "language": "en",
                "contact_no": null,
                "address": null,
                "business_id": 1,
                "max_sales_discount_percent": null,
                "allow_login": 1,
                "essentials_department_id": null,
                "essentials_designation_id": null,
                "status": "active",
                "crm_contact_id": null,
                "is_cmmsn_agnt": 0,
                "cmmsn_percent": "0.00",
                "selected_contacts": 0,
                "dob": null,
                "gender": null,
                "marital_status": null,
                "blood_group": null,
                "contact_number": null,
                "fb_link": null,
                "twitter_link": null,
                "social_media_1": null,
                "social_media_2": null,
                "permanent_address": null,
                "current_address": null,
                "guardian_name": null,
                "custom_field_1": null,
                "custom_field_2": null,
                "custom_field_3": null,
                "custom_field_4": null,
                "bank_details": null,
                "id_proof_name": null,
                "id_proof_number": null,
                "deleted_at": null,
                "created_at": "2018-01-04 02:15:19",
                "updated_at": "2018-01-04 02:15:19"
            }
        }
     */
    public function loggedin()
    {
        $user = Auth::user();
        return new CommonResource($user);
    }

    /**
     * Update user password.
     * @bodyParam current_password string required Current password of the user
     * @bodyParam new_password string required New password of the user
     * 
     * @response {
            "success":1,
            "msg":"Password updated successfully"
        }
     */
    public function updatePassword(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!empty($request->input('current_password')) && !empty($request->input('new_password'))) {
                if (Hash::check($request->input('current_password'), $user->password)) {
                    $user->password = Hash::make($request->input('new_password'));
                    $user->save();
                    $output = ['success' => 1,
                                'msg' => __('lang_v1.password_updated_successfully')
                            ];
                } else {
                    $output = ['success' => 0,
                                'msg' => __('lang_v1.u_have_entered_wrong_password')
                            ];
                }
            } else {
                $output = ['success' => 0,
                            'msg' => __('messages.something_went_wrong')
                        ];
            }

        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
            $output = ['success' => 0,
                            'msg' => __('messages.something_went_wrong')
                        ];
        }

        if ($output['success']) {
            return $this->respond($output);
        } else {
            return $this->otherExceptions($output['msg']);
        }
    }

    /**
     * Cria e retorna os detalhes de um usuário proprietário gerado automaticamente.
     *
     * @return array Os detalhes do usuário criado.
     */
    public static function createOwnerUser(): array
    {
        $first_name = 'Sistema';
        $last_name = 'OfficeLocal';

        // Gera um username único
        do {
            $username_base = 'officelocal' . substr(md5(uniqid()), 0, 5);
        } while (User::where('username', $username_base)->exists());

        // Gera um email único
        do {
            $email_base = $username_base . '@wr2.com.br';
        } while (User::where('email', $email_base)->exists());

        // Define a senha padrão
        $password = bcrypt('wr2.01');

        // Detalhes do usuário
        $owner_details = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'username' => $username_base,
            'email' => $email_base,
            'password' => $password,
            'language' => 'pt', // Ou use config('app.locale') para pegar o idioma padrão.
            'officeimpresso_codigo' => 1,
        ];

        // Cria o usuário no banco de dados
        $user = User::create($owner_details);

        // Retorna os detalhes completos do usuário criado
        return $user->toArray();
    }


    /**
     * Sync GET method
     * 
     * @queryParam filter optional Provide filters for syncing data
     * @response {
     *    "success": 1,
     *    "data": []
     * }
     */
    
    public function syncGet(Request $request)
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
    
            $usuarios = User::where('business_id', $business_id)
                            ->where('updated_at', '>', $date) // Filtra todos alterados depois da data informada
                            ->get();       
    
            return response()->json([
                'status' => 'success',
                'message' => 'Dados sincronizados.',
                'data' => $usuarios->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'usuario' => $user->usuario,
                        'email' => $user->email,
                        'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                        'officeimpresso_codigo' => $user->officeimpresso_codigo,
                        'officeimpresso_dt_alteracao' => $user->officeimpresso_dt_alteracao,
                        'officeimpresso_action' => 'update local',
                        'officeimpresso_message' => 'Registro modificado no site. Atualização realizada no banco de dados local.',
                    ];
                }),
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar dados sincronizados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar os dados.'. $e->getMessage(),
            ], 500);
        }
    }  

    

    /**
     * Sync POST method
     * 
     * @bodyParam data array required Provide data to be synced
     * @response {
     *    "success": 1,
     *    "msg": "Data synced successfully"
     * }
     */

     public function syncPost(Request $request)
     {
         $response = [];
         try {
             // Validação básica do payload
             $request->validate([
                 'data' => 'required|array',
                 'data.*.codigo' => 'required|integer',
                 'data.*.usuario' => 'required|string|max:255',
                 'data.*.email' => 'nullable|email',
                 'data.*.senha' => 'nullable|string|min:6',
                 'data.*.login' => 'nullable|string|max:255',
                 'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d\TH:i:s.v\Z',
                 'data.*.oimpresso_id' => 'nullable|integer',
             ]);
     
             $user = Auth::user();
             $business_id = $user->business_id;
     
             foreach ($request->input('data') as $item) {
                 try {
                     // Processar cada item
                     $result = $this->processItem($item, $business_id);
                     $response[] = array_merge(['officeimpresso_status' => 'success'], $result);
                 } catch (\Exception $e) {
                     // Retornar erro específico para o item
                     $response[] = [
                         'officeimpresso_action' => 'error',
                         'officeimpresso_message' => $e->getMessage(),
                         'officeimpresso_codigo' => $item['codigo'] ?? null,
                         'officeimpresso_dt_alteracao' => $item['dt_alteracao'],
                     ];
                 }
             }
     
             return response()->json([
                 'status' => 'completed',
                 'message' => 'Sincronização finalizada.',
                 'data' => $response,
             ]);
     
         } catch (\Exception $e) {
             // Retornar erro geral
             \Log::error('Erro na sincronização: ' . $e->getMessage());
             return response()->json([
                 'status' => 'error',
                 'message' => 'Erro ao processar a sincronização.'. $e->getMessage(),
             ], 500);
         }
     }
     
     /**
      * Processa um único item, atualizando ou criando no banco de dados.
      */
     private function processItem(array $item, int $business_id)
     {
        $userRecord = User::where('business_id', $business_id)
        ->where(function ($query) use ($item) {
            $query->where('id', $item['oimpresso_id'])
                  ->orWhere('officeimpresso_codigo', $item['codigo']);
        })
        ->first();
    
             
         $usernameBusinnesID = $this->generateUsername($item['usuario'], $business_id);
         $email = $this->generateEmail($item['email'], $business_id);
         $senha = $this->decryptPassword($item['senha']);                  
      
         if ($userRecord) {

            $updatedAt = $userRecord->updated_at->format('Y-m-d H:i:s');
            $oimpressoUpdatedAt = Carbon::parse($item['oimpresso_updated_at'])->format('Y-m-d H:i:s');
            
            if ($updatedAt !== $oimpressoUpdatedAt) {   //  if  ($userRecord->updated_at->diffInSeconds(Carbon::parse($item['oimpresso_updated_at'])) > 0) {  não usar assim 
                // Aqui deve retornar conflito, Vai ter baixar resolver os conflitos para depois postar            
                // Retorna um status de conflito, sem atualizar o registro
                return [
                    'officeimpresso_action' => 'conflict',
                    'officeimpresso_message' => 'Conflito detectado. O registro foi modificado após a última sincronização.',
                    'id' => $userRecord->id,
                    'updated_at' => $userRecord->fresh()->updated_at->format('Y-m-d H:i:s'),
                    'deleted_at'=> $userRecord->deleted_at,
                    'officeimpresso_codigo' => $userRecord->officeimpresso_codigo,
                    'officeimpresso_dt_alteracao' => $userRecord->officeimpresso_dt_alteracao,
                ];
            }

             // Atualizar usuário existente
             $userRecord->update([
                'first_name' => $item['login'] ?? $usernameBusinnesID,
                'last_name' => $usernameBusinnesID ?? null,
                'email' => $email,
                'username' => $usernameBusinnesID ?? $userRecord->username,
                'password' => isset($senha) ? bcrypt($senha) : $userRecord->password,
                'officeimpresso_senha' => isset($item['senha']) ? $item['senha'] : null,
                'language' => $item['language'] ?? $userRecord->language, 
                'officeimpresso_codigo' => $item['codigo'],
                'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
             ]);
             return [
                 'officeimpresso_action' => 'updated',
                 'officeimpresso_message' => 'O registro foi modificado com sucesso.',
                 'id' => $userRecord->id,
                 'updated_at' => $userRecord->fresh()->updated_at->format('Y-m-d H:i:s'),
                 'deleted_at'=> $userRecord->deleted_at,
                 'officeimpresso_codigo' => $userRecord->officeimpresso_codigo,
                 'officeimpresso_dt_alteracao' => $userRecord->officeimpresso_dt_alteracao,
             ];
         } else {
             // Criar novo usuário
             $newUser = User::create([
                'business_id' => $business_id,
                'first_name' => $item['login'] ?? $usernameBusinnesID,
                'last_name' => $usernameBusinnesID ?? null,
                'email' => $email,
                'username' => $usernameBusinnesID,
                'password' => isset($senha) ? bcrypt($senha) : $newUser->password,
                'officeimpresso_senha' => isset($item['senha']) ? $item['senha'] : null,
                'language' => $item['language'] ?? 'pt',
                'officeimpresso_codigo' => $item['codigo'],
                'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
             ]);
             return [
                 'officeimpresso_action' => 'created',
                 'officeimpresso_message' => 'O registro foi criado com sucesso.',
                 'id' => $newUser->id,
                 'updated_at' => $newUser->fresh()->updated_at->format('Y-m-d H:i:s'),
                 'deleted_at'=> $newUser->deleted_at,
                 'officeimpresso_codigo' => $newUser->officeimpresso_codigo,
                 'officeimpresso_dt_alteracao' => $newUser->officeimpresso_dt_alteracao,
             ];
         }
     }

    
    public function getUserByBusinessCNPJ(Request $request)
    {
        $cnpj = $request->input('cnpj');

        $business = Business::where('cnpj', $cnpj)->first();

        if ($business) {
            // Busca o usuário com email no padrão, ignorando case sensitivity
            $user = User::where('business_id', $business->id)
                ->where('email', 'LIKE', '%@wr2.com.br')
                ->first();
        
            return response()->json([
                'business_id' => $business->id,
                'username' => $user ? $user->username : null,
                'senha' => $user ? $user->password : null,
            ]);
        }

        return response()->json(['error' => 'Business not found'. $cnpj], 404);
    }

    private function generateEmail($emailInput, $business_id)
    {
        // Se o email estiver vazio ou inválido, cria um placeholder
        if (empty($emailInput) || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $emailBase = 'geraroffice' . str_pad($business_id, 2, '0', STR_PAD_LEFT) . '@office.com';
        } else {
            $emailBase = strtolower($emailInput); // Normaliza o email fornecido
        }
    
        // Garante que o email seja único
        while (User::where('email', $emailBase)->exists()) {
            $uniqueSuffix = substr(md5(uniqid()), 0, 5); // Gera sufixo único
            $emailBase = 'geraroffice' . str_pad($business_id, 2, '0', STR_PAD_LEFT) . $uniqueSuffix . '@office.com';
        }
    
        return $emailBase;
    }

    private function decryptPassword($encryptedPassword)
    {
        try {
            // Verifica se a senha está presente
            if (!is_null($encryptedPassword)) {
                return Cryptography::decrypt($encryptedPassword, 23);
            } else {
                return null; // Retorna null se não houver senha
            }
        } catch (\Exception $e) {
            // Loga o erro e lança uma exceção personalizada
            Log::error('Erro ao descriptografar a senha.', [
                'exception' => $e->getMessage(),
                'encryptedPassword' => $encryptedPassword,
            ]);
            throw new \Exception('Erro ao processar a senha.');
        }
    }
        

    function generateUsername($usuario, $business_id)
    {
        // Formata o Business_ID para ter pelo menos 2 dígitos, usando STR_PAD_LEFT
        $formattedBusinessID = str_pad($business_id, 2, '0', STR_PAD_LEFT);
    
        // Gera o username no padrão usuario-Business_ID
        $username = strtolower($usuario) . '-' . $formattedBusinessID;
    
        return $username;
    }


}
