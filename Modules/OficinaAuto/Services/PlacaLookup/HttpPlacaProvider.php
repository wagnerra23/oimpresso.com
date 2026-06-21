<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services\PlacaLookup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HttpPlacaProvider — driver genérico contra um fornecedor BR de consulta de placa
 * que devolve JSON. Config-driven (`oficina-auto.placa_lookup.http`): base_url,
 * autenticação (query/bearer/header) e `field_map` (nome-do-campo-do-fornecedor →
 * campo canônico). Plugar um fornecedor = preencher .env, sem mexer em código.
 *
 * Só extrai dados técnicos do veículo — campos de proprietário do fornecedor são
 * IGNORADOS de propósito (sem PII de terceiro). Os nomes dos campos do fornecedor
 * são mapeados via `field_map`.
 *
 * @see Modules\OficinaAuto\Services\PlacaLookup\PlacaProvider
 */
final class HttpPlacaProvider implements PlacaProvider
{
    /**
     * @param array{
     *     base_url?: ?string,
     *     api_key?: ?string,
     *     auth_mode?: string,
     *     auth_key?: string,
     *     timeout?: int,
     *     field_map?: array<string,string>
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function lookup(string $plate): ?PlacaLookupResult
    {
        $baseUrl = $this->config['base_url'] ?? null;
        $apiKey  = $this->config['api_key'] ?? null;

        if (empty($baseUrl) || empty($apiKey)) {
            throw PlacaLookupException::notConfigured('http');
        }

        $authMode = $this->config['auth_mode'] ?? 'query';
        $authKey  = $this->config['auth_key'] ?? 'token';
        $timeout  = (int) ($this->config['timeout'] ?? 8);

        $request = Http::timeout($timeout)
            ->acceptJson()
            ->retry(1, 200);

        $query = ['placa' => $plate];

        if ($authMode === 'bearer') {
            $request = $request->withToken($apiKey);
        } elseif ($authMode === 'header') {
            $request = $request->withHeaders([$authKey => $apiKey]);
        } else { // query (default)
            $query[$authKey] = $apiKey;
        }

        try {
            $response = $request->get(rtrim($baseUrl, '/'), $query);
        } catch (ConnectionException $e) {
            // NÃO inclui a placa na mensagem (PII).
            throw PlacaLookupException::unreachable($e->getMessage());
        }

        // 404 do fornecedor = placa não encontrada (não é erro de sistema).
        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw PlacaLookupException::providerFailed($response->status());
        }

        $body = $response->json();

        if (! is_array($body) || $body === []) {
            return null;
        }

        return $this->mapResponse($plate, $body);
    }

    /**
     * Traduz o JSON do fornecedor pro DTO canônico via `field_map`.
     *
     * @param array<string,mixed> $body
     */
    private function mapResponse(string $plate, array $body): PlacaLookupResult
    {
        $map = $this->config['field_map'] ?? [];

        $get = static function (string $canonical) use ($body, $map): ?string {
            $key = $map[$canonical] ?? $canonical;
            $value = data_get($body, $key);

            if ($value === null || $value === '') {
                return null;
            }

            return is_scalar($value) ? trim((string) $value) : null;
        };

        $year = $get('manufacture_year');
        $modelYear = $get('model_year');

        return new PlacaLookupResult(
            plate: $plate,
            brand: $get('brand'),
            model: $get('model'),
            manufactureYear: $year !== null ? (int) $year : null,
            modelYear: $modelYear !== null ? (int) $modelYear : null,
            color: $get('color'),
            fuelType: $get('fuel_type'),
            chassis: $get('chassis'),
            engine: $get('engine'),
            renavam: $get('renavam'),
        );
    }
}
