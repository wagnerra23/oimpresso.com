<?php

declare(strict_types=1);

namespace App\Services\BR;

use Eduardokum\LaravelBoleto\Util;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service que consulta CNPJ na BrasilAPI (proxy informativo público sobre
 * dados gov.br — NÃO é Receita Federal direta, é mesmo dado público).
 *
 * Endpoint: https://brasilapi.com.br/api/cnpj/v1/{cnpj}
 *
 * Cache:
 *   - TTL 30 dias (CNPJ raramente muda)
 *   - Key namespaced `brasilapi:cnpj:{digits}` — cache driver default (Redis CT 100, file Hostinger)
 *   - Cache hit pula HTTP request, importante pra evitar throttle BrasilAPI (sem rate limit publicado mas mantemos conservador)
 *
 * Failure modes:
 *   - CNPJ inválido (mod-11) → null (não consulta API, economiza HTTP)
 *   - API timeout/5xx → null + Log::warning (não bloqueia form)
 *   - API 404 → null + Log::info
 *
 * LGPD:
 *   - Log NÃO grava CNPJ plain — só comprimento e status HTTP
 *   - Cache key usa dígitos mas é interno (Redis isolado por business via prefix global)
 *
 * Charter Cliente/Create — Non-Goal "Validação CNPJ via Receita Federal" cobre
 * validação. Este service faz lookup informativo (preenchimento auxiliar de form), OK.
 */
class BrasilApiService
{
    private const CACHE_TTL_DAYS = 30;

    private const TIMEOUT_SECONDS = 8;

    private const BASE_URL = 'https://brasilapi.com.br/api/cnpj/v1/';

    /**
     * Lookup CNPJ na BrasilAPI e retorna campos normalizados pro schema BR.
     *
     * @return array{cnpj:string,razao_social:?string,nome_fantasia:?string,cep:?string,logradouro:?string,numero:?string,bairro:?string,municipio:?string,uf:?string}|null
     */
    public function lookupCnpj(string $cnpj): ?array
    {
        $digits = preg_replace('/\D/', '', $cnpj);

        if (! Util::validarCnpjCpf($cnpj) || strlen((string) $digits) !== 14) {
            return null;
        }

        $cacheKey = "brasilapi:cnpj:{$digits}";

        return Cache::remember(
            $cacheKey,
            now()->addDays(self::CACHE_TTL_DAYS),
            function () use ($digits) {
                try {
                    $resp = Http::timeout(self::TIMEOUT_SECONDS)
                        ->retry(2, 200)
                        ->acceptJson()
                        ->get(self::BASE_URL.$digits);

                    if ($resp->status() === 404) {
                        Log::info('BrasilAPI CNPJ não encontrado', ['cnpj_len' => strlen((string) $digits)]);

                        return null;
                    }

                    if (! $resp->successful()) {
                        Log::warning('BrasilAPI CNPJ lookup falhou', [
                            'status' => $resp->status(),
                            'cnpj_len' => strlen((string) $digits),
                        ]);

                        return null;
                    }

                    $data = $resp->json();

                    if (! is_array($data)) {
                        return null;
                    }

                    return [
                        'cnpj' => (string) $digits,
                        'razao_social' => $this->str($data, 'razao_social'),
                        'nome_fantasia' => $this->str($data, 'nome_fantasia'),
                        'cep' => $this->onlyDigitsOrNull($data['cep'] ?? null),
                        'logradouro' => $this->str($data, 'logradouro'),
                        'numero' => $this->str($data, 'numero'),
                        'bairro' => $this->str($data, 'bairro'),
                        'municipio' => $this->str($data, 'municipio'),
                        'uf' => $this->str($data, 'uf'),
                    ];
                } catch (\Throwable $e) {
                    Log::error('BrasilAPI CNPJ exception', [
                        'msg' => $e->getMessage(),
                        'cnpj_len' => strlen((string) $digits),
                    ]);

                    return null;
                }
            }
        );
    }

    /**
     * Helper: extrai string trimada ou null pra campo opcional.
     */
    private function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Helper: limpa não-dígitos do CEP ou retorna null.
     */
    private function onlyDigitsOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
