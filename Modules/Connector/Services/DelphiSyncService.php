<?php

declare(strict_types=1);

namespace Modules\Connector\Services;

use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DelphiSyncService — extrai pattern de sincronização Delphi → oimpresso.
 *
 * Centraliza a lógica que hoje vive espalhada entre LicencaComputadorController,
 * BusinessController, OImpressoRegistroController e CheckUpdateController.
 *
 * 3 formatos de payload suportados (catalogados em
 * `tests/Feature/Connector/DelphiOImpressoContractTest.php` + fixtures):
 *
 *   1. `array_tabelas` — JSON array com NOME_TABELA=EMPRESA + LICENCIAMENTO (Delphi G1, legacy 3.7)
 *   2. `json_flat`     — JSON object com cnpj/serial_hd/versao (WR Comercial atual, G2)
 *   3. `pipe`          — text/plain "SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|..." (TThreadLicenca, fallback)
 *
 * Contrato de resposta CRÍTICO (NÃO mudar — Delphi parsa literal):
 *   - ProcessaDadosCliente → STRING `S;msg` ou `N;motivo` (Content-Type: text/plain)
 *   - CheckUpdate → STRING `VersaoNova;VersaoMinObrig` ou `N;VersaoMinObrig`
 *   - OImpressoRegistrar → JSON `{autorizado: 'S'|'N', licenca_id, dias_restantes, data_expiracao}`
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - Resolução de business_id via CNPJ (business_locations.cnpj OU business.cnpj)
 *   - HD lookup em licenca_computador.hd (cross-business permitido pra notebook de suporte)
 *
 * @see Modules\Connector\Http\Controllers\Api\LicencaComputadorController
 * @see Modules\Connector\Http\Controllers\Api\OImpressoRegistroController
 * @see Modules\Officeimpresso\Http\Middleware\LogDelphiAccess
 * @see memory/requisitos/Connector/SPEC.md (US-CONN-001..012)
 */
class DelphiSyncService
{
    /**
     * Detecta formato do body Delphi.
     *
     * @return string 'array_tabelas' | 'json_flat' | 'pipe' | 'unknown'
     */
    public function detectBodyFormat(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '' || $trimmed === '{}' || $trimmed === '[]') {
            return 'unknown';
        }

        // pipe format: começa com 8 chars hex (serial HD) seguido de pipe
        if (str_contains($trimmed, '|') && ! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return 'pipe';
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            return 'unknown';
        }

        // array_tabelas: array de objetos com NOME_TABELA
        if (isset($decoded[0]['NOME_TABELA']) || isset($decoded[0]['nome_tabela'])) {
            return 'array_tabelas';
        }

        // json_flat: objeto com chaves diretas (serial_hd, cnpj, etc)
        if (isset($decoded['serial_hd']) || isset($decoded['cnpj']) || isset($decoded['HD'])) {
            return 'json_flat';
        }

        return 'unknown';
    }

    /**
     * Extrai HD (serial do disco) do body, qualquer formato.
     *
     * Fallback chain:
     *   1. Header X-OI-HD (explícito)
     *   2. Body parse por formato detectado
     *
     * @return string|null HD em UPPER, ou null se ausente
     */
    public function extractHd(Request $request): ?string
    {
        // D9.a OTel — wrap extração HD (chamada por todos endpoints licença).
        return OtelHelper::spanBiz('connector.delphi.extract_hd', function () use ($request) {
            // Fallback header explícito
            $headerHd = $request->headers->get('X-OI-HD');
            if ($headerHd) {
                return strtoupper(trim($headerHd));
            }

            $body = $request->getContent();
            $format = $this->detectBodyFormat($body);

            return match ($format) {
                'array_tabelas' => $this->extractHdFromArrayTabelas($body),
                'json_flat'     => $this->extractHdFromJsonFlat($body),
                'pipe'          => $this->extractHdFromPipe($body),
                default         => null,
            };
        }, ['connector.service' => self::class]);
    }

    /**
     * Resolve [business_id, business_location_id] a partir do CNPJ no payload.
     *
     * Prioridade:
     *   1. Route param {business_id} (se URL `/salvar-equipamento/{business_id}`)
     *   2. business_locations.cnpj (filial-específica)
     *   3. business.cnpj (matriz)
     *
     * @return array{0: ?int, 1: ?int} [business_id, business_location_id]
     */
    public function resolveByCnpj(Request $request): array
    {
        // D9.a OTel — wrap resolução CNPJ (chamada por todos endpoints Delphi).
        return OtelHelper::spanBiz('connector.delphi.resolve_by_cnpj', function () use ($request) {
            return $this->doResolveByCnpj($request);
        }, ['connector.service' => self::class]);
    }

    private function doResolveByCnpj(Request $request): array
    {
        // 1. URL param explícito
        $routeBizId = $request->route('business_id');
        if ($routeBizId !== null && is_numeric($routeBizId)) {
            return [(int) $routeBizId, null];
        }

        $cnpj = $this->extractCnpj($request);
        if (! $cnpj) {
            return [null, null];
        }

        $cnpjNormalizado = preg_replace('/[^0-9]/', '', $cnpj);
        if (! $cnpjNormalizado) {
            return [null, null];
        }

        // 2. Tenta business_locations.cnpj
        $location = DB::table('business_locations')
            ->where('cnpj', $cnpj)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpjNormalizado])
            ->first();

        if ($location) {
            return [(int) $location->business_id, (int) $location->id];
        }

        // 3. Fallback business.cnpj
        $business = DB::table('business')
            ->where('cnpj', $cnpj)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpjNormalizado])
            ->first();

        if ($business) {
            return [(int) $business->id, null];
        }

        return [null, null];
    }

    /**
     * Formata resposta legacy Delphi (string simples).
     *
     * @param bool   $ok     true → "S;...", false → "N;..."
     * @param string $message mensagem (sem ponto-e-vírgula)
     */
    public function formatLegacyResponse(bool $ok, string $message): string
    {
        // Delphi parsa split(';'), então message não pode ter ';'
        $sanitized = str_replace(';', ',', $message);

        return ($ok ? 'S' : 'N') . ';' . $sanitized;
    }

    /**
     * Log estruturado de drift suspeito (HD não cadastrado, CNPJ órfão, etc).
     */
    public function logDrift(string $reason, array $context = []): void
    {
        Log::channel('stack')->warning('[DelphiSync] drift detectado', array_merge([
            'reason'     => $reason,
            'service'    => self::class,
            'timestamp'  => now()->toIso8601String(),
        ], $context));
    }

    // ===== Helpers privados =====

    private function extractHdFromArrayTabelas(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $row) {
            $tabela = strtoupper((string) ($row['NOME_TABELA'] ?? $row['nome_tabela'] ?? ''));
            if ($tabela === 'LICENCIAMENTO' && ! empty($row['HD'])) {
                return strtoupper(trim($row['HD']));
            }
        }

        return null;
    }

    private function extractHdFromJsonFlat(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        $hd = $decoded['serial_hd'] ?? $decoded['HD'] ?? $decoded['hd'] ?? null;

        return $hd ? strtoupper(trim((string) $hd)) : null;
    }

    private function extractHdFromPipe(string $body): ?string
    {
        // SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|...
        $parts = explode('|', trim($body));

        return ! empty($parts[0]) ? strtoupper(trim($parts[0])) : null;
    }

    private function extractCnpj(Request $request): ?string
    {
        $body = $request->getContent();
        $format = $this->detectBodyFormat($body);

        return match ($format) {
            'array_tabelas' => $this->extractCnpjFromArrayTabelas($body),
            'json_flat'     => json_decode($body, true)['cnpj'] ?? json_decode($body, true)['CNPJCPF'] ?? null,
            'pipe'          => explode('|', $body)[4] ?? null,
            default         => null,
        };
    }

    private function extractCnpjFromArrayTabelas(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $row) {
            $tabela = strtoupper((string) ($row['NOME_TABELA'] ?? $row['nome_tabela'] ?? ''));
            if ($tabela === 'EMPRESA' && ! empty($row['CNPJCPF'])) {
                return trim($row['CNPJCPF']);
            }
        }

        return null;
    }
}
