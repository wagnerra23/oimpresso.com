<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Officeimpresso\Entities\Licenca_Computador;
use App\Business;
use Carbon\Carbon;


class LicencaComputadorController extends Controller
{

    /**
     * Processa os dados do cliente e do equipamento recebidos em um JSON.
     *
     * Aceita 2 formatos de body:
     *
     * 1. ARRAY (Services.RegistroSistema.pas — fluxo completo, traz CNPJ):
     *    [{NOME_TABELA:'EMPRESA', CNPJCPF:..., RAZAOSOCIAL:..., ...},
     *     {NOME_TABELA:'LICENCIAMENTO', HD:..., DESCRICAO:..., ...}]
     *
     * 2. FLAT (Services.LicencaThread.pas — fluxo rapido, SO o HD):
     *    {host, ip, serial_hd, sistema, versao}
     *    Sem CNPJ: resolve business via lookup do HD em licenca_computador.
     *
     * Response (contrato Delphi): STRING 'S;msg' ou 'N;motivo'.
     */
    public function ProcessaDadosCliente(Request $request)
    {
        $dados = $request->json()->all();

        // Formato 1: array com NOME_TABELA (fluxo completo com CNPJ)
        if (is_array($dados) && ! empty($dados) && isset($dados[0])) {
            $dadosEmpresa = collect($dados)->firstWhere('NOME_TABELA', 'EMPRESA');
            $dadosLicenciamento = collect($dados)->firstWhere('NOME_TABELA', 'LICENCIAMENTO');

            if ($dadosEmpresa && $dadosLicenciamento) {
                return $this->processarComEmpresa($dadosEmpresa, $dadosLicenciamento);
            }

            return response()->json(['error' => 'Dados de EMPRESA ou LICENCIAMENTO ausentes'], 400);
        }

        // Formato 2: flat com serial_hd (fluxo rapido sem CNPJ) — Services.LicencaThread.pas
        $flat = is_array($dados) ? array_change_key_case($dados, CASE_LOWER) : [];
        $hd = $flat['serial_hd'] ?? $flat['hd'] ?? null;

        if ($hd) {
            return $this->processarApenasHd($hd, $flat);
        }

        return response()->json(['error' => 'Dados de EMPRESA ou LICENCIAMENTO ausentes'], 400);
    }

    /**
     * Fluxo completo: cria/atualiza business + equipamento.
     */
    private function processarComEmpresa(array $dadosEmpresa, array $dadosLicenciamento)
    {
        $businessController = new BusinessController();
        $business = $businessController->saveBusiness(new Request($dadosEmpresa));

        if ($business->officeimpresso_bloqueado) {
            return response('N;Cliente bloqueado', 200);
        }

        $equipamento = $this->saveEquipamento(new Request($dadosLicenciamento), $business->id);

        if ($equipamento->bloqueado) {
            $motivo = ! empty($equipamento->motivo) ? $equipamento->motivo : 'Motivo não informado';
            return response('N;' . $motivo, 200);
        }

        return response('S;Cliente e equipamento liberados', 200);
    }

    /**
     * Fluxo rapido: sem CNPJ no body, resolve business via HD.
     *
     * Um mesmo HD pode estar cadastrado em N businesses (ex: notebook de
     * suporte que acessa DBs de varios clientes). Sem CNPJ no body, NAO
     * da pra desambiguar qual business o Delphi esta atendendo naquela
     * chamada especifica.
     *
     * Politica adotada: **atualizar TODAS as linhas licenca_computador com
     * aquele HD**. "O HD pingou agora" -> todas as suas instancias sobem
     * pro topo do grid. Se qualquer uma estiver com business ou maquina
     * bloqueada, responde N com esse motivo (conservador — se uma das
     * empresas bloqueou o HD, o Delphi respeita).
     */
    private function processarApenasHd(string $hd, array $flat)
    {
        $equipamentos = Licenca_Computador::where('hd', $hd)->get();

        if ($equipamentos->isEmpty()) {
            return response('N;Maquina nao cadastrada. Contate o suporte.', 200);
        }

        // Se QUALQUER business deste HD esta bloqueado, recusa (conservador)
        $businessIds = $equipamentos->pluck('business_id')->filter()->unique();
        $bizBloqueada = \DB::table('business')
            ->whereIn('id', $businessIds)
            ->where('officeimpresso_bloqueado', 1)
            ->exists();
        if ($bizBloqueada) {
            return response('N;Cliente bloqueado', 200);
        }

        // Se qualquer maquina esta com bloqueado=1, recusa
        $bloqueada = $equipamentos->firstWhere('bloqueado', 1);
        if ($bloqueada) {
            $motivo = ! empty($bloqueada->motivo) ? $bloqueada->motivo : 'Motivo não informado';
            return response('N;' . $motivo, 200);
        }

        // Atualiza TODAS as linhas com aquele HD (1 UPDATE eficiente)
        $fields = array_filter([
            'hostname'      => $flat['host'] ?? null,
            'ip_interno'    => $flat['ip'] ?? null,
            'sistema'       => $flat['sistema'] ?? null,
            'versao_exe'    => $flat['versao'] ?? null,
        ], fn ($v) => ! empty($v));
        $fields['dt_ultimo_acesso'] = now();

        Licenca_Computador::where('hd', $hd)->update($fields);

        return response('S;Cliente e equipamento liberados', 200);
    }

    /**
     * Processa o equipamento com base no cliente já cadastrado.
     */
    public function saveEquipamento(Request $request, $business_id)
    {                                                  
        $dadosLicenciamento = $request->all();

        try {
            // Verifica se o equipamento está cadastrado com base no HD
            $equipamento = Licenca_Computador::where('hd', $dadosLicenciamento['HD'])
                                             ->where('business_id', $business_id)  // Aqui deve buscar por usuário tbm
                                             ->where('user_win', $dadosLicenciamento['DESCRICAO'])   // USER_WIN
                                             ->first();

            if (!$equipamento) {
                // Se não estiver cadastrado, realiza o cadastro do novo equipamento
                $equipamento = new Licenca_Computador();
                $equipamento->business_id = $business_id;
                
                // Chave primaria de localização
                $equipamento->hd = $dadosLicenciamento['HD'] ?? null;
                $equipamento->user_win = $dadosLicenciamento['DESCRICAO'] ?? null;

                // Bloqueio do computador
                $equipamento->liberado = $dadosLicenciamento['LIBERADO'] ?? 'N';                
                $equipamento->motivo = $dadosLicenciamento['MOTIVO'] ?? null;
                $equipamento->bloqueado = true; // Sempre cadastra bloqueado
                
                
            }

            // $equipamento->tipo_de_acesso = $dadosLicenciamento['TIPODEACESSO'] ?? null;
            $equipamento->conexao = $dadosLicenciamento['CONEXAO'] ?? null;
            $equipamento->usuario = $dadosLicenciamento['USUARIO'] ?? null;
            $equipamento->senha = $dadosLicenciamento['SENHA'] ?? null;
            $equipamento->sistema_operacional = $dadosLicenciamento['SISTEMA_OPERACIONAL'] ?? null;
            $equipamento->ip_interno = $dadosLicenciamento['IP_INTERNO'] ?? null;
            $equipamento->antivirus = $dadosLicenciamento['ANTIVIRUS'] ?? null;
            $equipamento->pasta_instalacao = $dadosLicenciamento['PASTA_INSTALACAO'] ?? null;
            $equipamento->versao_exe = $dadosLicenciamento['VERSAO_EXE'] ?? null;
            $equipamento->versao_banco = $dadosLicenciamento['VERSAO_BANCO'] ?? null;
            $equipamento->dt_ultima_assistencia = $dadosLicenciamento['DT_ULTIMA_ASSISTENCIA'] ?? null;
            $equipamento->backup_automatico = $dadosLicenciamento['BACKUP_AUTOMATICO'] ?? null;
            $equipamento->paf = $dadosLicenciamento['PAF'] ?? null;
            $equipamento->processador = $dadosLicenciamento['PROCESSADOR'] ?? null;
            $equipamento->memoria = $dadosLicenciamento['MEMORIA'] ?? null;
            $equipamento->velocidade_conexao = $dadosLicenciamento['VELOCIDADE_CONEXAO'] ?? null;
            $equipamento->impressora_fiscal = $dadosLicenciamento['IMPRESSORA_FISCAL'] ?? null;
            $equipamento->leitor_barras = $dadosLicenciamento['LEITOR_BARRAS'] ?? null;
            $equipamento->gera_mensalidade = $dadosLicenciamento['GERA_MENSALIDADE'] ?? null;
            $equipamento->hostname = $dadosLicenciamento['HOSTNAME'] ?? null;
            $equipamento->dt_validade = $dadosLicenciamento['DT_VALIDADE'] ?? null;
            $equipamento->serial = $dadosLicenciamento['SERIAL'] ?? null;
            $equipamento->contra_senha = $dadosLicenciamento['CONTRA_SENHA'] ?? null;
            $equipamento->valor = $dadosLicenciamento['VALOR'] ?? null;
            $equipamento->caminho_banco = $dadosLicenciamento['CAMINHO_BANCO'] ?? null;
            
            // $equipamento->ativo = $dadosLicenciamento['ATIVO'] ?? null;

            $equipamento->descricao = $dadosLicenciamento['DESCRICAO'] ?? null;
            $equipamento->sistema = $dadosLicenciamento['SISTEMA'] ?? null;
            $equipamento->dt_cadastro = $dadosLicenciamento['DT_CADASTRO'] ?? now();

            $equipamento->updated_at = $dadosLicenciamento['DT_ALTERACAO'] ?? null;
            $equipamento->dt_ultimo_acesso = now();
            // $equipamento->codempresa = $dadosLicenciamento['CODEMPRESA'] ?? null;

            $equipamento->save();
            return $equipamento;

        } catch (\Exception $e) {
            Log::error('Erro ao processar o equipamento: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao processar o equipamento: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $computadores = Licenca_Computador::all();
        return response()->json($computadores, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validação dos dados recebidos
        $validated = $request->validate([
            'business_id' => 'required|exists:business,id',
            'licenca_id' => 'required|exists:licenca,id',
            'hd' => 'required|unique:licenca_computador,hd',
            'processador' => 'required',
            'memoria' => 'required',
            'versao_exe' => 'required',
            'bloqueado' => 'boolean',
        ]);

        // Criação de um novo registro
        $computador = Licenca_Computador::create($validated);

        return response()->json($computador, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $computador = Licenca_Computador::find($id);

        if (!$computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }

        return response()->json($computador, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validação dos dados recebidos
        $validated = $request->validate([
            'business_id' => 'required|exists:business,id',
            'licenca_id' => 'required|exists:licenca,id',
            'hd' => 'required|unique:licenca_computador,hd,' . $id,
            'processador' => 'required',
            'memoria' => 'required',
            'versao_exe' => 'required',
            'bloqueado' => 'boolean',
        ]);

        // Encontrar o computador pelo ID
        $computador = Licenca_Computador::find($id);

        if (!$computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }

        // Atualizar os dados do computador
        $computador->update($validated);

        return response()->json($computador, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $computador = Licenca_Computador::find($id);

        if (!$computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }

        $computador->delete();

        return response()->json(['message' => 'Computador deletado com sucesso'], 200);
    }


}
