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
            // HD pode ou nao vir no body. Antes a gente pulava o log quando
            // nao tinha HD — agora logamos tudo pra descobrir endpoints novos
            // que o Delphi bate. HD continua sendo gravado quando presente.
            $hd = $this->extractHd($request);

            $user = $request->user();
            // CNPJ do body tem prioridade sobre user->business_id — o Delphi
            // usa master user compartilhado entre clientes, entao user->business_id
            // sempre aponta pro business do master (ex: WR2) e nao pro cliente real.
            // resolveByCnpj devolve [business_id, business_location_id] — location
            // pode vir null se o CNPJ casou em business.cnpj e nao em location.
            [$businessId, $businessLocationId] = $this->resolveByCnpj($request);
            $businessId ??= $user?->business_id;

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

            // Captura preview do body pra inspecao — util pra entender novos
            // formatos que o Delphi envia. Trunca em 4KB pra nao inchar DB.
            $rawBody = $request->getContent();
            $bodyPreview = $rawBody !== '' ? Str::limit($rawBody, 4000, '…[truncado]') : null;

            $metadata = array_filter([
                'hd'                   => $hd,
                'generation'           => $this->detectGeneration($request),
                'was_blocked'          => $businessBlocked || $licencaBlocked,
                'business_blocked'     => $businessBlocked,
                'licenca_blocked'      => $licencaBlocked,
                'business_location_id' => $businessLocationId,
                'body_format'          => $this->detectBodyFormat($rawBody),
                'body_size'            => strlen($rawBody),
                'body_preview'         => $bodyPreview,
                'request_headers'      => $this->extractRelevantHeaders($request),
            ], fn ($v) => $v !== null && $v !== false && $v !== '');

            LicencaLog::create([
                'event'                => 'api_call',
                'user_id'              => $user?->id,
                'business_id'          => $businessId,
                'business_location_id' => $businessLocationId,
                'licenca_id'           => $licencaId,
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
     *   - Geração 3 (oimpresso/registrar WR Comercial): JSON flat com serial_hd
     *     no root OU pipe-separated string
     *   - Header X-OI-HD (generico)
     */
    private function extractHd(Request $request): ?string
    {
        if ($hd = $request->header('X-OI-HD')) return $hd;
        if ($hd = $request->input('HD'))       return $hd;
        if ($hd = $request->input('hd'))       return $hd;
        if ($hd = $request->input('serial_hd')) return $hd;

        // Gerção 1: array de {NOME_TABELA, ...}
        $payload = $request->json()->all();
        if (is_array($payload)) {
            foreach ($payload as $row) {
                if (is_array($row) && isset($row['NOME_TABELA']) && $row['NOME_TABELA'] === 'LICENCIAMENTO') {
                    return $row['HD'] ?? null;
                }
            }
        }

        // String pipe-separated legado (WR Comercial): 1o campo eh SERIAL/HD
        $raw = trim($request->getContent());
        if ($raw !== '' && ! str_starts_with($raw, '{') && ! str_starts_with($raw, '[') && str_contains($raw, '|')) {
            $parts = explode('|', $raw);
            return $parts[0] !== '' ? $parts[0] : null;
        }

        return null;
    }

    /**
     * Resolve (business_id, business_location_id) a partir do CNPJ no body ou
     * da route. Prioridade:
     *   1. route param {business_id} se presente (salvar-equipamento/{id})
     *   2. business_locations.cnpj = CNPJ do body (unidade fiscal especifica)
     *   3. business.cnpj = CNPJ do body (compat com setup sem location fiscal)
     * Retorna [business_id, business_location_id]. Ambos podem ser null.
     */
    private function resolveByCnpj(Request $request): array
    {
        $bid = $request->route('business_id');
        if ($bid && is_numeric($bid)) return [(int) $bid, null];

        $cnpj = null;

        // Formato 1: array com NOME_TABELA=EMPRESA (processa-dados-cliente)
        $payload = $request->json()->all();
        if (is_array($payload)) {
            foreach ($payload as $row) {
                if (is_array($row) && isset($row['NOME_TABELA']) && $row['NOME_TABELA'] === 'EMPRESA') {
                    $cnpj = $row['CNPJCPF'] ?? null;
                    break;
                }
            }
        }

        // Formato 2: JSON flat com campo cnpj na raiz (oimpresso/registrar WR Comercial)
        if (! $cnpj) {
            $cnpj = $request->input('cnpj') ?: $request->input('CNPJ') ?: $request->input('CNPJCPF');
        }

        // Formato 3: string pipe-separated (5o campo é CNPJ em MontarString do Delphi)
        if (! $cnpj) {
            $raw = trim($request->getContent());
            if ($raw !== '' && ! str_starts_with($raw, '{') && ! str_starts_with($raw, '[') && str_contains($raw, '|')) {
                $parts = explode('|', $raw);
                $cnpj = $parts[4] ?? null;
            }
        }

        if (! $cnpj) return [null, null];

        $loc = \DB::table('business_locations')
            ->where('cnpj', $cnpj)
            ->first(['id', 'business_id']);
        if ($loc) return [(int) $loc->business_id, (int) $loc->id];

        $bid = \DB::table('business')->where('cnpj', $cnpj)->value('id');
        return [$bid ? (int) $bid : null, null];
    }

    /** 'g1' (processa-dados-cliente) | 'g2' (salvar-equipamento) | null */
    private function detectGeneration(Request $request): ?string
    {
        $path = $request->path();
        if (str_contains($path, 'processa-dados-cliente')) return 'g1';
        if (str_contains($path, 'salvar-equipamento'))     return 'g2';
        if (str_contains($path, 'salvar-cliente'))         return 'g1';
        if (str_contains($path, 'oimpresso/registrar'))    return 'g3';
        return null;
    }

    /**
     * Classifica o formato do body — util pra filtrar logs por como o
     * Delphi esta mandando.
     *   - 'array_tabelas'  : [{NOME_TABELA:'EMPRESA',...},{NOME_TABELA:'LICENCIAMENTO',...}]
     *   - 'json_flat'      : {host, ip, serial_hd, ...}
     *   - 'pipe'           : SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|...
     *   - 'empty'          : body vazio
     *   - 'unknown'        : nao identificado
     */
    private function detectBodyFormat(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return 'empty';
        if (str_starts_with($raw, '[')) return 'array_tabelas';
        if (str_starts_with($raw, '{')) return 'json_flat';
        if (str_contains($raw, '|')) return 'pipe';
        return 'unknown';
    }

    /**
     * Pega so headers relevantes pro troubleshooting (evita logar
     * Authorization completo ou cookies).
     */
    private function extractRelevantHeaders(Request $request): array
    {
        return array_filter([
            'content_type' => $request->header('Content-Type'),
            'x_api_key'    => $request->header('X-API-Key'),
            'x_oi_hd'      => $request->header('X-OI-HD'),
            'has_bearer'   => $request->bearerToken() ? true : null,
        ], fn ($v) => $v !== null);
    }
}
