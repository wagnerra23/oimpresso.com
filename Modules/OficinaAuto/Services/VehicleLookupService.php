<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Modules\OficinaAuto\Services\PlacaLookup\HttpPlacaProvider;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupException;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupResult;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaProvider;
use Modules\OficinaAuto\Services\PlacaLookup\StubPlacaProvider;

/**
 * VehicleLookupService — orquestra a consulta de placa (digita placa → dados do
 * veículo). Resolve o driver por config (adapter agnóstico), normaliza/valida a
 * placa, cacheia por (business_id, placa) pra não pagar consulta repetida no mesmo
 * cadastro, e envelopa em span Otel (D9.a).
 *
 * Escopo LGPD (decisão Wagner 2026-06-09): SÓ dados técnicos do veículo. O
 * proprietário (PII de terceiro) não é consultado nem armazenado — owner continua
 * vinculado manualmente a um Contact pelo operador.
 *
 * Multi-tenant Tier 0 (ADR 0093): a cache é namespeada por business_id pra um
 * tenant nunca enxergar o resultado cacheado de outro.
 *
 * @see Modules\OficinaAuto\Services\PlacaLookup\PlacaProvider
 * @see Modules\OficinaAuto\Http\Controllers\VehicleController::consultaPlaca
 */
class VehicleLookupService
{
    /** Placa BR: antiga (ABC1234) ou Mercosul (ABC1D23). */
    private const PLATE_REGEX = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    public function __construct(private readonly ?PlacaProvider $provider = null)
    {
    }

    /**
     * Normaliza a placa: remove separadores e força uppercase.
     */
    public static function normalizePlate(string $raw): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
    }

    /**
     * Valida formato de placa BR (antiga ou Mercosul).
     */
    public static function isValidPlate(string $plate): bool
    {
        return (bool) preg_match(self::PLATE_REGEX, self::normalizePlate($plate));
    }

    /**
     * Consulta a placa e devolve dados técnicos normalizados (ou null se não achou).
     *
     * @param int $businessId  tenant da sessão — namespeia a cache (Tier 0).
     *
     * @throws PlacaLookupException  config ausente / fornecedor indisponível.
     */
    public function lookup(string $rawPlate, int $businessId): ?PlacaLookupResult
    {
        $plate = self::normalizePlate($rawPlate);

        return OtelHelper::spanBiz('oficinaauto.vehicle.lookup_placa', function () use ($plate, $businessId) {
            if (! self::isValidPlate($plate)) {
                return null;
            }

            // Resolve ANTES da cache: driver indisponível neste ambiente nunca serve o que já cacheou.
            $provider = $this->resolveProvider();

            $ttl = (int) config('oficina-auto.placa_lookup.cache_ttl', 86400);
            // O driver entra na chave: o que o stub cacheou não vale para o fornecedor real (e vice-versa).
            $cacheKey = 'oficina:placa:' . ($this->provider !== null ? 'injetado' : self::driver()) . ":{$businessId}:{$plate}";

            $cached = Cache::get($cacheKey);
            if ($cached instanceof PlacaLookupResult) {
                return $cached;
            }

            $result = $provider->lookup($plate);

            if ($result !== null && $ttl > 0) {
                Cache::put($cacheKey, $result, $ttl);
            }

            return $result;
        }, [
            'module' => 'OficinaAuto',
            // NUNCA logar a placa em claro (PII) — só os 3 primeiros chars.
            'plate_prefix' => substr($plate, 0, 3),
        ]);
    }

    /**
     * Resolve o driver concreto a partir da config (ou o provider injetado em teste).
     */
    private function resolveProvider(): PlacaProvider
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $driver = self::driver();
        if (! self::disponivel()) {
            throw PlacaLookupException::notConfigured($driver);
        }

        return match ($driver) {
            'http'  => new HttpPlacaProvider((array) config('oficina-auto.placa_lookup.http', [])),
            'stub'  => new StubPlacaProvider(),
            default => throw PlacaLookupException::notConfigured($driver),
        };
    }

    /** Driver configurado (`oficina-auto.placa_lookup.driver`; padrão `stub`). */
    public static function driver(): string
    {
        return (string) config('oficina-auto.placa_lookup.driver', 'stub');
    }

    /**
     * A consulta pode responder neste ambiente? O `stub` INVENTA dados a partir da placa: só vale
     * em desenvolvimento, teste e staging. Em produção (APP_ENV "live" no Hostinger, que não é
     * "production") ele não responde — dado falso iria parar em veículo real (decisão [W]
     * 2026-10-05, medido: produção estava em stub, sem nenhuma variável OFICINA_* no .env).
     */
    public static function disponivel(): bool
    {
        return match (self::driver()) {
            'http' => true,
            'stub' => app()->environment(self::AMBIENTES_COM_STUB),
            default => false,
        };
    }

    /** Ambientes onde o stub pode responder (dado inventado é aceitável). */
    private const AMBIENTES_COM_STUB = ['local', 'testing', 'development', 'dev', 'staging'];
}
