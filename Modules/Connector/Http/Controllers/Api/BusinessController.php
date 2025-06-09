<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Business;
use App\Utils\BusinessUtil;
use App\Services\BusinessService;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;


class BusinessController extends Controller
{

    protected $businessUtil;

    public function __construct()
    {
        // Usa o container de serviço para instanciar o BusinessUtil
        $this->businessUtil = app()->make(BusinessUtil::class);
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('connector::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        return view('connector::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        return view('connector::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        return view('connector::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Cria ou atualiza o cliente (Business).
     */
    public function saveBusiness(Request $request)
    {
        
        // Adiciona valores padrão para campos ausentes antes da validação
        $dadosEmpresa = $request->all();

        // Realiza a validação com os valores padrão definidos
        $validatedData = $request->validate(
            [
                'CNPJCPF' => 'required|max:18', // CNPJ ou CPF é obrigatório e com limite de 18 caracteres
                'RAZAOSOCIAL' => 'required|max:255' // Nome da empresa (Razão Social) obrigatório
                // 'FANTASIA' => 'nullable|max:150', // Nome fantasia é opcional, limite de 150 caracteres
                // 'ENDERECO' => 'nullable|max:80', // Endereço é opcional, limite de 80 caracteres
                // 'CIDADE' => 'required|max:255', // Cidade é obrigatória, limite de 255 caracteres
                // 'CEP' => 'required|max:10', // CEP é obrigatório, limite de 10 caracteres
                // 'FONE1' => 'nullable|max:16', // Telefone principal é opcional, limite de 16 caracteres
                // 'EMAIL' => 'nullable|email|max:50', // Email é opcional, deve ser um email válido com limite de 50 caracteres
            ]
        );      
        

        try {
            // Verifica se o cliente já existe no banco de dados usando o CNPJ/CPF
            $business = Business::where('cnpj', $dadosEmpresa['CNPJCPF'])->first();

            if (!$business) {
                DB::beginTransaction();

                // Cria o usuário proprietário
                $user = User::createOwnerUser();

                try {
                    $dadosPacote = [
                        'name' => 'Contrato ' . $dadosEmpresa['RAZAOSOCIAL'], // Use . para concatenação
                        'description' => 'Contrato gerado no registro',
                        'location_count' => 0,
                        'user_count' => 0,
                        'product_count' => 0,
                        'invoice_count' => 0,
                        'interval' => 'months',
                        'interval_count' => 1,
                        'trial_days' => 30,
                        'price' => 0,
                        'sort_order' => 1,
                        'is_active' => 1,
                        'is_private' => 1,
                        'officeimpresso_limitemaquinas' => 0
                    ];
                    $novoPacote = Package::create($dadosPacote);                       
        
                    //Aqui tem que criar uma package_id            
                    $dadosEmpresa['package_id'] = $novoPacote->id;
                    echo "Pacote criado com sucesso! ID: " . $novoPacote->id;
                } catch (\Exception $e) {
                    echo "Erro ao criar o pacote: " . $e->getMessage();
                }

                // Dados básicos do negócio
                $business_details = [
                    'name' => $dadosEmpresa['RAZAOSOCIAL'],
                    'start_date' => $dadosEmpresa['DT_CADASTRO'] ?? date('Y-m-d'),
                    'currency_id' => $dadosEmpresa['currency_id'] ?? 1,
                    'time_zone' => $dadosEmpresa['TIME_ZONE'] ?? 'America/Sao_Paulo',
                    'fy_start_month' => 1,
                    'owner_id' => $user->id,
                ];

                // Formatar a data de início
                if (!empty($business_details['start_date'])) {
                    $business_details['start_date'] = Carbon::parse($business_details['start_date'])->toDateString();
                }

                // Módulos padrão habilitados
                $business_details['enabled_modules'] = ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses', 'officeimpresso'];

                // Mapear dados adicionais para os campos do modelo Business
                $business_details['cnpj'] = $dadosEmpresa['CNPJCPF'];
                $business_details['razao_social'] = $dadosEmpresa['RAZAOSOCIAL'];
                $business_details['ie'] = $dadosEmpresa['INSCIDENT'] ?? 'ISENTO';
                $business_details['start_date'] = $dadosEmpresa['DT_CADASTRO'] ?? date('Y-m-d');

                // Campos obrigatórios adicionais
                $business_details['currency_id'] = $dadosEmpresa['currency_id'] ?? 18; // Moeda padrão ID 18, caso não fornecido
                $business_details['owner_id'] = $user->id; // ID do proprietário recém-criado

                // Configurações fiscais obrigatórias
                $business_details['sell_price_tax'] = 'includes'; // ou 'excludes'
                $business_details['expiry_type'] = 'add_expiry'; // ou 'add_manufacturing'
                $business_details['on_product_expiry'] = 'keep_selling'; // ou 'stop_selling', 'auto_delete'
                $business_details['stop_selling_before'] = 0; // Número de dias antes da expiração para parar de vender

                // Informações de contato e endereço
                $business_details['rua'] = $dadosEmpresa['RUA'] ?? '*';
                $business_details['numero'] = $dadosEmpresa['NUMERO'] ?? '*';
                $business_details['bairro'] = $dadosEmpresa['BAIRRO'] ?? '*';
                $business_details['cep'] = $dadosEmpresa['CEP'] ?? '00000-000';
                // $business_details['zip_code'] = $dadosEmpresa['CEP'] ?? '00000-000';  // isso só tem no Location
                $business_details['telefone'] = $dadosEmpresa['FONE1'] ?? '00 00000-0000';

                // $business_details['email'] = $dadosEmpresa['EMAIL'] ?? '';

                // Informações adicionais obrigatórias
                $business_details['senha_certificado'] = $dadosEmpresa['SENHA_CERTIFICADO'] ?? '1234';
                $business_details['certificado'] = $dadosEmpresa['CERTIFICADO'] ?? ''; // Deve ser o conteúdo do certificado em binário
                $business_details['ncm_padrao'] = $dadosEmpresa['NCM_PADRAO'] ?? '00000000';
                $business_details['cfop_saida_estadual_padrao'] = $dadosEmpresa['CFOP_ESTADUAL'] ?? '5102';
                $business_details['cfop_saida_inter_estadual_padrao'] = $dadosEmpresa['CFOP_INTERESTADUAL'] ?? '6102';
                $business_details['csc'] = $dadosEmpresa['CSC'] ?? '';
                $business_details['csc_id'] = $dadosEmpresa['CSC_ID'] ?? '';

                // Outros campos com valores padrão ou opcionais
                $business_details['default_profit_percent'] = 0;
                $business_details['enable_product_expiry'] = 0;
                $business_details['is_active'] = 1;                        
                $business_details['officeimpresso_bloqueado'] = 0;          
                $business_details['is_officeimpresso'] = true;       
                
                // Criando o negócio usando o BusinessUtil
                $business = $this->businessUtil->createNewBusiness($business_details);

                $business->save(); // Salva o novo cliente no banco de dados

                // Atualiza o usuário com o ID do negócio
                $user->business_id = $business->id;
                $user->username = 'officelocal-' . $business->id
                $user->email = $user->username. '@wr2.com.br';  // se aqui ficar o email fico feliz
                $user->save();

                // Após criar o pacote e salvar a empresa, crie a assinatura
                if ($novoPacote && $business) {
                    // Defina parâmetros necessários
                    $business_id = $business->id;
                    $package = $novoPacote;
                    $gateway = 'offline'; // ou o método de pagamento que preferir
                    $payment_transaction_id = 'auto_generated_' . uniqid();
                    $user_id = auth()->user()->id;
                    $is_superadmin = true;

                    // Chama o método de criação de assinatura
                    $subscription = ['business_id' => $business_id,
                                    'package_id' => $package->id,
                                    'paid_via' => $gateway,
                                    'payment_transaction_id' => $payment_transaction_id
                                ];
            

                    //If offline then dates will be decided when approved by superadmin
                    $dates = ['start' => '', 'end' => '', 'trial' => ''];

                    //calculate start date
                    $start_date = Subscription::end_date($business_id);
                    $dates['start'] = $start_date->toDateString();
            
                    //Calculate end date
                    if ($package->interval == 'days') {
                        $dates['end'] = $start_date->addDays($package->interval_count)->toDateString();
                    } elseif ($package->interval == 'months') {
                        $dates['end'] = $start_date->addMonths($package->interval_count)->toDateString();
                    } elseif ($package->interval == 'years') {
                        $dates['end'] = $start_date->addYears($package->interval_count)->toDateString();
                    }
                    
                    $dates['trial'] = $start_date->addDays($package->trial_days);                    

                    $subscription['start_date'] = $dates['start'];
                    $subscription['end_date'] = $dates['end'];
                    $subscription['trial_end_date'] = $dates['trial'];
                    $subscription['status'] = 'approved';

            
                    $subscription['package_price'] = $package->price;
                    $subscription['package_details'] = [
                            'location_count' => $package->location_count,
                            'user_count' => $package->user_count,
                            'product_count' => $package->product_count,
                            'invoice_count' => $package->invoice_count,
                            'name' => $package->name
                        ];
                    //Custom permissions.
                    if (!empty($package->custom_permissions)) {
                        foreach ($package->custom_permissions as $name => $value) {
                            $subscription['package_details'][$name] = $value;
                        }
                    }
                    
                    $subscription['created_id'] = $user_id;
            
                    $subscription = Subscription::create($subscription);
            
                    if (!$is_superadmin) {
                        $email = System::getProperty('email');
                        $is_notif_enabled = System::getProperty('enable_new_subscription_notification');
            
                        if (!empty($email) && $is_notif_enabled == 1) {
                            Notification::route('mail', $email)
                            ->notify(new NewSubscriptionNotification($subscription));
                        }
                    }
            
                    $subscription->save();     

                }                
    
                // Extraindo detalhes da localização
                $business_location = [
                    'name' => $dadosEmpresa['RAZAOSOCIAL'],
                    'country' => $dadosEmpresa['PAIS'] ?? 'Brasil',
                    'state' => $dadosEmpresa['UF'],
                    'city' => $dadosEmpresa['CIDADE'],
                    'zip_code' => $dadosEmpresa['CEP'],
                    'landmark' => $dadosEmpresa['BAIRRO'],
                    'mobile' => $dadosEmpresa['FONE1'] ?? '00 00000-0000',
                    'alternate_number' => $dadosEmpresa['FONE2'] ?? null,
                    'website' => $dadosEmpresa['WEBSITE'] ?? null,
                    'rua' => $dadosEmpresa['ENDERECO'],
                    'numero' => $dadosEmpresa['NUMERO'],
                    // Outros campos de endereço, se houver
                ];

                // Cria recursos padrão para o novo negócio (se aplicável)
                $this->businessUtil->newBusinessDefaultResources($business->id, $user->id);
                $new_location = $this->businessUtil->addLocation($business->id, $business_location);
   
                // Cria nova permissão para a localização
                Permission::create(['name' => 'location.' . $new_location->id]);

                DB::commit();
            }

            
             
            return $business; 
            

            // $cliente->cnpj = $dadosEmpresa['CNPJCPF'];
            // $cliente->inscricao_estadual = $dadosEmpresa['INSCIDENT'];
            // $cliente->razao_social = $dadosEmpresa['RAZAOSOCIAL'];
            // $cliente->fantasia = $dadosEmpresa['FANTASIA'];
            // $cliente->contato = $dadosEmpresa['CONTATO'];
            // $cliente->cidade = $dadosEmpresa['CIDADE'];
            // $cliente->bairro = $dadosEmpresa['BAIRRO'];
            // $cliente->cep = $dadosEmpresa['CEP'];
            // $cliente->uf = $dadosEmpresa['UF'];
            // $cliente->rua = $dadosEmpresa['ENDERECO'];
            // $cliente->fone2 = $dadosEmpresa['FONE2'];
            // $cliente->fax = $dadosEmpresa['FAX'];
            // $cliente->email = $dadosEmpresa['EMAIL'];
            // $cliente->tipo = $dadosEmpresa['TIPO'];
            // $cliente->pagina = $dadosEmpresa['PAGINA'];
            // $cliente->ativo = $dadosEmpresa['ATIVO'];
            // $cliente->modulo = $dadosEmpresa['MODULO'];
            // $cliente->codcidade = $dadosEmpresa['CODCIDADE'];
            // $cliente->numero = $dadosEmpresa['NUMERO'];
            // $cliente->im = $dadosEmpresa['IM'];
            // $cliente->iest = $dadosEmpresa['IEST'];
            // $cliente->cnae = $dadosEmpresa['CNAE'];
            // $cliente->issqn = $dadosEmpresa['ISSQN'];
            // $cliente->crt = $dadosEmpresa['CRT'];
            // $cliente->codigo_municipio = $dadosEmpresa['CODIGO_MUNICIPIO'];
            // $cliente->suframa = $dadosEmpresa['SUFRAMA'];
            // $cliente->cpf_proprietario = $dadosEmpresa['CPF_PROPRIETARIO'];
            // $cliente->contador_nome = $dadosEmpresa['CONTADOR_NOME'];
            // $cliente->contador_cpf = $dadosEmpresa['CONTADOR_CPF'];
            // $cliente->contador_crc = $dadosEmpresa['CONTADOR_CRC'];
            // $cliente->contador_cnpj = $dadosEmpresa['CONTADOR_CNPJ'];
            // $cliente->contador_cep = $dadosEmpresa['CONTADOR_CEP'];
            // $cliente->contador_endereco = $dadosEmpresa['CONTADOR_ENDERECO'];
            // $cliente->contador_numero = $dadosEmpresa['CONTADOR_NUMERO'];
            // $cliente->contador_complemento = $dadosEmpresa['CONTADOR_COMPLEMENTO'];
            // $cliente->contador_bairro = $dadosEmpresa['CONTADOR_BAIRRO'];
            // $cliente->contador_fone = $dadosEmpresa['CONTADOR_FONE'];
            // $cliente->contador_fax = $dadosEmpresa['CONTADOR_FAX'];
            // $cliente->contador_email = $dadosEmpresa['CONTADOR_EMAIL'];
            // $cliente->contador_codigo_municipio = $dadosEmpresa['CONTADOR_CODIGO_MUNICIPIO'];
            // $cliente->contador_uf = $dadosEmpresa['CONTADOR_UF'];
            // $cliente->complemento = $dadosEmpresa['COMPLEMENTO'];
            // $cliente->tipo_os = $dadosEmpresa['TIPO_OS'];
            // $cliente->pais = $dadosEmpresa['PAIS'];
            // $cliente->codpais = $dadosEmpresa['CODPAIS'];
            // $cliente->app_senha = $dadosEmpresa['APP_SENHA'];
            // $cliente->emite_nfe = $dadosEmpresa['EMITE_NFE'];
            // $cliente->contador_im = $dadosEmpresa['CONTADOR_IM'];
            // $cliente->cmc = $dadosEmpresa['CMC'];
            // $cliente->dt_cadastro = $dadosEmpresa['DT_CADASTRO'];
            // $cliente->dt_alteracao = $dadosEmpresa['DT_ALTERACAO'];
            // $cliente->regime = $dadosEmpresa['REGIME'];
            // $cliente->cnpj_autorizacao_nfe = $dadosEmpresa['CNPJ_AUTORIZACAO_NFE'];
            // $cliente->pcredsn = $dadosEmpresa['PCREDSN'];
            // $cliente->web_service = $dadosEmpresa['WEB_SERVICE'];
            // $cliente->web_service_login = $dadosEmpresa['WEB_SERVICE_LOGIN'];
            // $cliente->web_service_senha = $dadosEmpresa['WEB_SERVICE_SENHA'];
            // $cliente->emite_nfce = $dadosEmpresa['EMITE_NFCE'];
            // $cliente->emite_nfse = $dadosEmpresa['EMITE_NFSE'];
            // $cliente->emite_sat = $dadosEmpresa['EMITE_SAT'];
            // $cliente->certificado = $dadosEmpresa['CERTIFICADO'];
            // $cliente->tem_certificado = $dadosEmpresa['TEM_CERTIFICADO'];
            // $cliente->certificado_senha = $dadosEmpresa['CERTIFICADO_SENHA'];
            // $cliente->nf_email_mensagem = $dadosEmpresa['NF_EMAIL_MENSAGEM'];
            // $cliente->nf_email_assunto = $dadosEmpresa['NF_EMAIL_ASSUNTO'];
            // $cliente->tem_nf_email_envio = $dadosEmpresa['TEM_NF_EMAIL_ENVIO'];
            // $cliente->nfse_usuario = $dadosEmpresa['NFSE_USUARIO'];
            // $cliente->nfse_senha = $dadosEmpresa['NFSE_SENHA'];
            // $cliente->nfce_producao_id = $dadosEmpresa['NFCE_PRODUCAO_ID'];
            // $cliente->nfce_producao_csc = $dadosEmpresa['NFCE_PRODUCAO_CSC'];
            // $cliente->nfce_homologacao_id = $dadosEmpresa['NFCE_HOMOLOGACAO_ID'];
            // $cliente->nfce_homologacao_csc = $dadosEmpresa['NFCE_HOMOLOGACAO_CSC'];
            // $cliente->nf_email_backup = $dadosEmpresa['NF_EMAIL_BACKUP'];
            // $cliente->fuso_emissao = $dadosEmpresa['FUSO_EMISSAO'];
            // $cliente->fuso_emissao_str = $dadosEmpresa['FUSO_EMISSAO_STR'];
            // $cliente->fuso_cancelamento = $dadosEmpresa['FUSO_CANCELAMENTO'];
            // $cliente->fuso_cancelamento_str = $dadosEmpresa['FUSO_CANCELAMENTO_STR'];
            // $cliente->fuso_cce = $dadosEmpresa['FUSO_CCE'];
            // $cliente->fuso_cce_str = $dadosEmpresa['FUSO_CCE_STR'];
            // $cliente->fuso_inutilizacao = $dadosEmpresa['FUSO_INUTILIZACAO'];
            // $cliente->fuso_inutilizacao_str = $dadosEmpresa['FUSO_INUTILIZACAO_STR'];
            // $cliente->pode_nf_salvar_xml = $dadosEmpresa['PODE_NF_SALVAR_XML'];
            // $cliente->nfe_danfe = $dadosEmpresa['NFE_DANFE'];
            // $cliente->nfe_path = $dadosEmpresa['NFE_PATH'];
            // $cliente->pode_nfe_cnpj_desenvolvedor = $dadosEmpresa['PODE_NFE_CNPJ_DESENVOLVEDOR'];
            // $cliente->pode_nfe_cnpj_contador = $dadosEmpresa['PODE_NFE_CNPJ_CONTADOR'];
            // $cliente->nfe_numserie = $dadosEmpresa['NFE_NUMSERIE'];
            // $cliente->nfe_dt_validade = $dadosEmpresa['NFE_DT_VALIDADE'];
            // $cliente->tem_nf_email_envio_nfse = $dadosEmpresa['TEM_NF_EMAIL_ENVIO_NFSE'];
            // $cliente->nfse_danfe = $dadosEmpresa['NFSE_DANFE'];
            // $cliente->nfse_webfrasescr = $dadosEmpresa['NFSE_WEBFRASESECR'];
            // $cliente->nfse_aliq_iss = $dadosEmpresa['NFSE_ALIQ_ISS'];
            // $cliente->nfse_serie = $dadosEmpresa['NFSE_SERIE'];
            // $cliente->tem_nfse_servico_padrao = $dadosEmpresa['TEM_NFSE_SERVICO_PADRAO'];
            // $cliente->nfse_servico_padrao = $dadosEmpresa['NFSE_SERVICO_PADRAO'];
            // $cliente->tem_nfse_uso_multiplos_servico = $dadosEmpresa['TEM_NFSE_USO_MULTIPLOS_SERVICO'];
            // $cliente->nfse_codigotributacaomunicipio = $dadosEmpresa['NFSE_CODIGOTRIBUTACAOMUNICIPIO'];
            // $cliente->nfe_dados_simples_nacional = $dadosEmpresa['NFE_DADOS_SIMPLES_NACIONAL'];
            // $cliente->nfe_imprimir_qtdepeca = $dadosEmpresa['NFE_IMPRIMIR_QTDEPECA'];
            // $cliente->slack = $dadosEmpresa['SLACK'];
            // $cliente->emite_tef = $dadosEmpresa['EMITE_TEF'];
            // $cliente->cor = $dadosEmpresa['COR'];
            // $cliente->tef_codigo_loja = $dadosEmpresa['TEF_CODIGO_LOJA'];
            // $cliente->nf_serie = $dadosEmpresa['NF_SERIE'];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Erro ao salvar o cliente: " . $e->getMessage());
            return response('S;Erro ao salvar o cliente: ' . $e->getMessage(), 500);
        }
    }
}
