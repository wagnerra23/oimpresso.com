<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Business;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Officeimpresso\Entities\Licenca_Computador;

/**
 * Fluxo do WR Comercial Delphi (Services.OImpresso.Registro.pas):
 *
 *   POST /connector/api/oimpresso/registrar
 *   Body JSON flat:
 *     { cnpj, razao_social, hostname, serial_hd, processador, memoria,
 *       sistema_operacional, ip_local, pasta_instalacao, versao_exe,
 *       versao_banco, caminho_banco, sistema, paf }
 *   OU string pipe:
 *     SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|PASTA|SO|PROC|MEM|VER_BANCO|CAM_BANCO|SISTEMA|PAF
 *
 *   Response JSON:
 *     {
 *       "success": true,
 *       "autorizado": "S" | "N",
 *       "message": "...",
 *       "licenca_id": 123,
 *       "business_id": 456,
 *       "dias_restantes": 30,
 *       "data_expiracao": "2026-12-31"
 *     }
 *
 * Identidade: cnpj bate em business_locations.cnpj primeiro (ADR multi-CNPJ),
 * fallback pra business.cnpj. Autorizacao por business_id (HD liberado por
 * empresa/operacao, nao por location fiscal).
 */
class OImpressoRegistroController extends Controller
{
    public function registrar(Request $request)
    {
        $payload = $this->extractPayload($request);

        if (! ($payload['cnpj'] ?? null) || ! ($payload['serial_hd'] ?? null)) {
            return response()->json([
                'success'    => false,
                'autorizado' => 'N',
                'message'    => 'CNPJ e serial_hd sao obrigatorios',
                'licenca_id' => 0,
                'business_id' => 0,
                'dias_restantes' => 0,
                'data_expiracao' => '',
            ], 400);
        }

        [$businessId, $businessLocationId] = $this->resolveBusiness($payload['cnpj']);

        if (! $businessId) {
            return response()->json([
                'success'    => false,
                'autorizado' => 'N',
                'message'    => 'Empresa nao cadastrada. Contate o suporte.',
                'licenca_id' => 0,
                'business_id' => 0,
                'dias_restantes' => 0,
                'data_expiracao' => '',
            ], 200);
        }

        $business = Business::find($businessId);
        if ($business && $business->officeimpresso_bloqueado) {
            // Mesmo bloqueado, atualiza cadastro pra fins de auditoria.
            $this->upsertLicenca($businessId, $payload);
            return response()->json([
                'success'    => true,
                'autorizado' => 'N',
                'message'    => 'Empresa bloqueada',
                'licenca_id' => 0,
                'business_id' => $businessId,
                'dias_restantes' => 0,
                'data_expiracao' => '',
            ], 200);
        }

        $equipamento = $this->upsertLicenca($businessId, $payload);

        if ($equipamento->bloqueado) {
            $motivo = ! empty($equipamento->motivo) ? $equipamento->motivo : 'Maquina bloqueada';
            return response()->json([
                'success'    => true,
                'autorizado' => 'N',
                'message'    => $motivo,
                'licenca_id' => $equipamento->id,
                'business_id' => $businessId,
                'dias_restantes' => 0,
                'data_expiracao' => '',
            ], 200);
        }

        $diasRestantes = 0;
        $dataExpiracao = '';
        if ($equipamento->dt_validade) {
            $validade = Carbon::parse($equipamento->dt_validade);
            $dataExpiracao = $validade->format('Y-m-d');
            $diasRestantes = max(0, (int) now()->startOfDay()->diffInDays($validade->startOfDay(), false));
        }

        return response()->json([
            'success'    => true,
            'autorizado' => 'S',
            'message'    => 'Autorizado',
            'licenca_id' => $equipamento->id,
            'business_id' => $businessId,
            'dias_restantes' => $diasRestantes,
            'data_expiracao' => $dataExpiracao,
        ], 200);
    }

    /**
     * Parse do body: aceita JSON flat OU string pipe-separated.
     *
     * Pipe format (14 campos):
     *   SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|PASTA|SO|PROC|MEM|VER_BANCO|CAM_BANCO|SISTEMA|PAF
     */
    private function extractPayload(Request $request): array
    {
        // Tenta JSON primeiro
        $json = $request->json()->all();
        if (is_array($json) && ! empty($json)) {
            return array_change_key_case($json, CASE_LOWER);
        }

        $raw = trim($request->getContent());
        if ($raw === '') return [];

        // Se tem pipe, trata como formato string legado
        if (str_contains($raw, '|')) {
            $parts = explode('|', $raw);
            return [
                'serial_hd'           => $parts[0] ?? null,
                'hostname'            => $parts[1] ?? null,
                'versao_exe'          => $parts[2] ?? null,
                'ip_local'            => $parts[3] ?? null,
                'cnpj'                => $parts[4] ?? null,
                'razao_social'        => $parts[5] ?? null,
                'pasta_instalacao'    => $parts[6] ?? null,
                'sistema_operacional' => $parts[7] ?? null,
                'processador'         => $parts[8] ?? null,
                'memoria'             => $parts[9] ?? null,
                'versao_banco'        => $parts[10] ?? null,
                'caminho_banco'       => $parts[11] ?? null,
                'sistema'             => $parts[12] ?? null,
                'paf'                 => $parts[13] ?? null,
            ];
        }

        return [];
    }

    /**
     * Resolve (business_id, business_location_id) pelo CNPJ.
     * Prioridade: business_locations.cnpj → business.cnpj.
     */
    private function resolveBusiness(string $cnpj): array
    {
        $loc = DB::table('business_locations')->where('cnpj', $cnpj)->first(['id', 'business_id']);
        if ($loc) return [(int) $loc->business_id, (int) $loc->id];

        $bid = DB::table('business')->where('cnpj', $cnpj)->value('id');
        return [$bid ? (int) $bid : null, null];
    }

    /**
     * Cria ou atualiza licenca_computador. Match: business_id + hd + hostname.
     * Maquina nova nasce bloqueado=true (admin aprova manualmente).
     */
    private function upsertLicenca(int $businessId, array $p): Licenca_Computador
    {
        $hd = $p['serial_hd'] ?? null;
        $hostname = $p['hostname'] ?? null;
        // Compat com saveEquipamento do 3.7 (salva em user_win o hostname tambem)
        $userWin = $hostname ?: ($p['user_win'] ?? null);

        $equipamento = Licenca_Computador::where('hd', $hd)
            ->where('business_id', $businessId)
            ->where('user_win', $userWin)
            ->first();

        if (! $equipamento) {
            $equipamento = new Licenca_Computador();
            $equipamento->business_id = $businessId;
            $equipamento->hd = $hd;
            $equipamento->user_win = $userWin;
            $equipamento->bloqueado = true; // Admin aprova primeiro acesso manualmente
            $equipamento->motivo = 'Aguardando aprovacao do superadmin';
            $equipamento->dt_cadastro = now();
        }

        $equipamento->hostname = $hostname ?: $equipamento->hostname;
        $equipamento->ip_interno = $p['ip_local'] ?? $equipamento->ip_interno;
        $equipamento->processador = $p['processador'] ?? $equipamento->processador;
        $equipamento->memoria = $p['memoria'] ?? $equipamento->memoria;
        $equipamento->sistema_operacional = $p['sistema_operacional'] ?? $equipamento->sistema_operacional;
        $equipamento->pasta_instalacao = $p['pasta_instalacao'] ?? $equipamento->pasta_instalacao;
        $equipamento->versao_exe = $p['versao_exe'] ?? $equipamento->versao_exe;
        $equipamento->versao_banco = $p['versao_banco'] ?? $equipamento->versao_banco;
        $equipamento->caminho_banco = $p['caminho_banco'] ?? $equipamento->caminho_banco;
        $equipamento->sistema = $p['sistema'] ?? $equipamento->sistema;
        $equipamento->paf = $p['paf'] ?? $equipamento->paf;
        $equipamento->dt_ultimo_acesso = now();

        try {
            $equipamento->save();
        } catch (\Throwable $e) {
            Log::error('[oimpresso/registrar] falha ao salvar licenca_computador: ' . $e->getMessage());
            throw $e;
        }

        return $equipamento;
    }
}
