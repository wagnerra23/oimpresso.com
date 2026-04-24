<?php

namespace Modules\Officeimpresso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Officeimpresso\Entities\LicencaLog;

/**
 * Log acesso do Delphi legado aos endpoints /connector/api/{...} do 3.7.
 *
 * Diferente do LogDesktopAccess (que busca `hd` como param ou header flat),
 * este entende o formato JSON do Delphi:
 *   - POST /processa-dados-cliente → array com NOME_TABELA=LICENCIAMENTO
 *     que contem campo `HD`
 *   - POST /salvar-equipamento/{business_id} → body flat com `HD`
 *
 * SEGURANCA: try/catch em tudo. Se log falhar, Delphi continua recebendo
 * response do controller normalmente.
 */
class LogDelphiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        try {
            $hd = $this->extractHd($request);
            if (! $hd) return $response;  // sem hd = sem log

            $user = $request->user();
            // CNPJ do body tem prioridade sobre user->business_id — o Delphi
            // usa master user compartilhado entre clientes, entao user->business_id
            // sempre aponta pro business do master (ex: WR2) e nao pro cliente real.
            $businessId = $this->extractBusinessId($request) ?? $user?->business_id;

            $licencaId = null;
            if ($businessId && $hd) {
                $licencaId = \DB::table('licenca_computador')
                    ->where('business_id', $businessId)
                    ->where('hd', $hd)
                    ->value('id');
            }

            $businessBlocked = $businessId
                ? (bool) \DB::table('business')->where('id', $businessId)->value('officeimpresso_bloqueado')
                : false;
            $licencaBlocked = $licencaId
                ? (bool) \DB::table('licenca_computador')->where('id', $licencaId)->value('bloqueado')
                : false;

            $token = $user?->token();

            $metadata = array_filter([
                'hd'               => $hd,
                'generation'       => $this->detectGeneration($request),
                'was_blocked'      => $businessBlocked || $licencaBlocked,
                'business_blocked' => $businessBlocked,
                'licenca_blocked'  => $licencaBlocked,
            ], fn ($v) => $v !== null && $v !== false && $v !== '');

            LicencaLog::create([
                'event'       => 'api_call',
                'user_id'     => $user?->id,
                'business_id' => $businessId,
                'licenca_id'  => $licencaId,
                'client_id'   => $token?->client_id ? (string) $token->client_id : null,
                'token_hint'  => $token?->id ? substr($token->id, 0, 8) . '…' . substr($token->id, -4) : null,
                'ip'          => $request->ip(),
                'user_agent'  => Str::limit($request->userAgent() ?? '', 500, ''),
                'endpoint'    => Str::limit($request->path(), 255, ''),
                'http_method' => $request->method(),
                'http_status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'metadata'    => $metadata ?: null,
                'source'      => 'delphi_middleware',
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogDelphiAccess falhou: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Extrai hd dos formatos que o Delphi usa:
     *   - Geração 1 (processa-dados-cliente): JSON array de tabelas,
     *     LICENCIAMENTO tem campo HD
     *   - Geração 2 (salvar-equipamento): JSON flat com HD no root
     *   - Header X-OI-HD (generico)
     */
    private function extractHd(Request $request): ?string
    {
        if ($hd = $request->header('X-OI-HD')) return $hd;
        if ($hd = $request->input('HD'))       return $hd;
        if ($hd = $request->input('hd'))       return $hd;

        // Gerção 1: array de {NOME_TABELA, ...}
        $payload = $request->json()->all();
        if (is_array($payload)) {
            foreach ($payload as $row) {
                if (is_array($row) && isset($row['NOME_TABELA']) && $row['NOME_TABELA'] === 'LICENCIAMENTO') {
                    return $row['HD'] ?? null;
                }
            }
        }
        return null;
    }

    private function extractBusinessId(Request $request): ?int
    {
        $bid = $request->route('business_id');
        if ($bid && is_numeric($bid)) return (int) $bid;

        $payload = $request->json()->all();
        if (is_array($payload)) {
            foreach ($payload as $row) {
                if (is_array($row) && isset($row['NOME_TABELA']) && $row['NOME_TABELA'] === 'EMPRESA') {
                    $cnpj = $row['CNPJCPF'] ?? null;
                    if ($cnpj) {
                        return (int) \DB::table('business')->where('cnpj', $cnpj)->value('id');
                    }
                }
            }
        }
        return null;
    }

    /** 'g1' (processa-dados-cliente) | 'g2' (salvar-equipamento) | null */
    private function detectGeneration(Request $request): ?string
    {
        $path = $request->path();
        if (str_contains($path, 'processa-dados-cliente')) return 'g1';
        if (str_contains($path, 'salvar-equipamento'))     return 'g2';
        if (str_contains($path, 'salvar-cliente'))         return 'g1';
        return null;
    }
}
