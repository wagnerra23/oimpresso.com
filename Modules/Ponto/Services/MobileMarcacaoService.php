<?php

declare(strict_types=1);

namespace Modules\Ponto\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Ponto\Entities\Marcacao;
use RuntimeException;

/**
 * MobileMarcacaoService — W28-8 Tangerino-like Ponto mobile.
 *
 * Recebe marcacoes via mobile app (REP-P / Portaria MTP 671/2021 reconhece
 * registrador eletronico por programa) com geolocation + device fingerprint,
 * valida anti-cheat e delega persistencia ao MarcacaoService
 * canonico (mantem append-only + hash encadeado SHA-256 + NSR sequencial).
 *
 * Estado-da-arte 2026 (Tangerino/Sólides DP, Pontotel, mywork):
 *   - geofence opcional por business (raio em metros)
 *   - timestamp_device validado contra clock-skew (anti-cheat)
 *   - device_uuid fingerprint per-funcionario (anti-substituicao)
 *
 * Tier 0 IRREVOGAVEL:
 *   - APPEND-ONLY Portaria MTP 671/2021 Art. 85 — delega ao MarcacaoService
 *     (NSR + hash chain garantidos). NUNCA UPDATE/DELETE em ponto_marcacoes.
 *   - business_id global scope ([ADR 0093]) — todo metodo recebe $businessId
 *     explicito (jobs/API sem session).
 *   - SEM BIOMETRIA. Decisao [W] 2026-08-27: o ponto INTERNO nao coleta
 *     imagem facial. Dado biometrico e dado pessoal SENSIVEL (LGPD Art. 5o,
 *     II) e seu tratamento exige hipotese propria do Art. 11 — que nao se
 *     justifica aqui, porque GPS + clock-skew + NSR server-authoritative ja
 *     entregam o anti-fraude sem tocar dado sensivel. Nao reintroduzir sem
 *     ADR nova. Antes desta decisao o endpoint EXIGIA selfie (nunca chegou a
 *     rodar: o controller nunca teve rota).
 *
 * Pendencias futuras (out-of-scope W28-8):
 *   - Push notification escalada quando suspeita_score >0.8.
 *   - Sincronizacao offline (queue local mobile → flush ao reconectar).
 *
 * @see MarcacaoService::registrar
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Portaria MTP 671/2021 Art. 85 (imutabilidade) + REP-P
 */
class MobileMarcacaoService
{
    /** Accuracy GPS maxima aceita (metros). >500m sugere GPS off ou spoof. */
    public const GPS_ACCURACY_MAX_METROS = 500.0;

    /** Drift maximo permitido entre timestamp_device e server now (segundos). */
    public const TIMESTAMP_DRIFT_MAX_SEG = 30;

    /** Raio default geofence biz (metros) — pode ser sobrescrito por config biz. */
    public const GEOFENCE_RAIO_DEFAULT_METROS = 1000.0;

    /** @var MarcacaoService */
    protected $marcacaoService;

    public function __construct(MarcacaoService $marcacaoService)
    {
        $this->marcacaoService = $marcacaoService;
    }

    /**
     * Registra marcacao mobile. Valida payload anti-cheat + delega persistencia
     * ao MarcacaoService canonico (APPEND-ONLY + hash + NSR + multi-tenant).
     *
     * @param int   $businessId   ADR 0093 explicito (API sem session)
     * @param int   $funcionarioId
     * @param array $payload {
     *   @var string $tipo                ENTRADA|SAIDA|ALMOCO_INICIO|ALMOCO_FIM (Marcacao::TIPO_*)
     *   @var float  $lat                 latitude device
     *   @var float  $lng                 longitude device
     *   @var float  $accuracy            GPS accuracy em metros
     *   @var string $device_uuid         fingerprint device (anti-substituicao)
     *   @var string $timestamp_device    ISO 8601 do clock do device (anti-cheat)
     *   @var int    $usuario_criador_id  user.id que executou (Sanctum)
     * }
     *
     * @return Marcacao Marcacao persistida (ja com hash + NSR).
     * @throws RuntimeException quando payload viola anti-cheat.
     */
    public function registrarMarcacaoMobile(int $businessId, int $funcionarioId, array $payload): Marcacao
    {
        $this->validarPayload($payload);

        // Anti-cheat #1: accuracy GPS razoavel (>500m sugere GPS off/spoof)
        $accuracy = (float) ($payload['accuracy'] ?? PHP_FLOAT_MAX);
        if ($accuracy > self::GPS_ACCURACY_MAX_METROS) {
            throw new RuntimeException(sprintf(
                'GPS accuracy %.1fm acima do limite (%.0fm). Aguarde sinal melhor.',
                $accuracy,
                self::GPS_ACCURACY_MAX_METROS
            ));
        }

        // Anti-cheat #2: timestamp_device alinhado com server now (clock-skew)
        $tsDevice = $this->parseTimestampDevice((string) ($payload['timestamp_device'] ?? ''));
        $drift = abs(now()->diffInSeconds($tsDevice));
        if ($drift > self::TIMESTAMP_DRIFT_MAX_SEG) {
            throw new RuntimeException(sprintf(
                'Timestamp device fora de sincronia (drift %ds > %ds). Ajustar relogio.',
                $drift,
                self::TIMESTAMP_DRIFT_MAX_SEG
            ));
        }

        // Validacao geofence (flag — NAO bloqueia, marca pra revisao humana)
        $dentroGeofence = $this->validarGeolocation(
            (float) $payload['lat'],
            (float) $payload['lng'],
            $businessId
        );

        // O device_uuid sozinho identifica o aparelho. Antes o sufixo era
        // SHA-256 da selfie — derivado de dado sensivel, e PERSISTIDO na
        // marcacao via dispositivo_id. Saiu com a captura.
        $dispositivoId = sprintf(
            'mobile:%s',
            substr((string) ($payload['device_uuid'] ?? 'unknown'), 0, 32)
        );

        // Delega ao Service canonico — mantem append-only + hash + NSR + multi-tenant
        $marcacao = $this->marcacaoService->registrar([
            'business_id'           => $businessId,
            'colaborador_config_id' => $funcionarioId,
            'rep_id'                => null, // REP-P mobile sem hardware fisico
            'momento'               => now(), // server-authoritative (anti-cheat)
            'origem'                => Marcacao::ORIGEM_REP_P,
            'tipo'                  => (string) $payload['tipo'],
            'dispositivo_id'        => $dispositivoId,
            'latitude'              => (float) $payload['lat'],
            'longitude'             => (float) $payload['lng'],
            'usuario_criador_id'    => (int) ($payload['usuario_criador_id'] ?? $funcionarioId),
        ]);

        // Audit estruturado (sem PII — apenas IDs + hash truncado)
        Log::info('ponto.mobile.marcacao.registrada', [
            'business_id'        => $businessId,
            'funcionario_id'     => $funcionarioId,
            'marcacao_id'        => (string) $marcacao->id,
            'tipo'               => (string) $payload['tipo'],
            'dentro_geofence'    => $dentroGeofence,
            'gps_accuracy'       => $accuracy,
            'drift_segundos'     => $drift,
        ]);

        return $marcacao;
    }

    /**
     * Valida se lat/lng estao dentro do geofence configurado para o business.
     * Por enquanto retorna true (geofence opt-in por business — config futura).
     * Quando configurado, calcula haversine vs centro biz com raio default 1km.
     */
    public function validarGeolocation(float $lat, float $lng, int $businessId): bool
    {
        $centro = config("pontowr2.geofence.business_{$businessId}");

        if (! is_array($centro) || ! isset($centro['lat'], $centro['lng'])) {
            // Sem geofence configurado — permite (opt-in)
            return true;
        }

        $raio = (float) ($centro['raio_metros'] ?? self::GEOFENCE_RAIO_DEFAULT_METROS);
        $distancia = $this->haversineMetros($lat, $lng, (float) $centro['lat'], (float) $centro['lng']);

        return $distancia <= $raio;
    }

    /**
     * Lista marcacoes mobile que precisam de revisao humana
     * (ex: fora geofence).
     * Filtra por dispositivo_id LIKE 'mobile:%' + business_id scope ([ADR 0093]).
     */
    public function listarMarcacoesMobilePendentesValidacao(int $businessId): Collection
    {
        return Marcacao::where('business_id', $businessId)
            ->where('dispositivo_id', 'like', 'mobile:%')
            ->where('momento', '>=', now()->subDays(7))
            ->orderByDesc('momento')
            ->limit(500)
            ->get();
    }

    // ========================================================================
    // Helpers privados
    // ========================================================================

    /** Valida estrutura minima do payload mobile. */
    protected function validarPayload(array $payload): void
    {
        $obrigatorios = ['tipo', 'lat', 'lng', 'accuracy', 'device_uuid', 'timestamp_device'];
        foreach ($obrigatorios as $campo) {
            if (! array_key_exists($campo, $payload) || $payload[$campo] === null || $payload[$campo] === '') {
                throw new RuntimeException("Campo obrigatorio ausente: {$campo}");
            }
        }

        $tiposValidos = [
            Marcacao::TIPO_ENTRADA,
            Marcacao::TIPO_SAIDA,
            Marcacao::TIPO_ALMOCO_INICIO,
            Marcacao::TIPO_ALMOCO_FIM,
        ];
        if (! in_array((string) $payload['tipo'], $tiposValidos, true)) {
            throw new RuntimeException("Tipo invalido: {$payload['tipo']}");
        }
    }

    /** Parse timestamp_device aceitando varios formatos ISO 8601. */
    protected function parseTimestampDevice(string $iso): Carbon
    {
        try {
            return Carbon::parse($iso);
        } catch (\Exception $e) {
            throw new RuntimeException("timestamp_device invalido (esperado ISO 8601): {$iso}");
        }
    }

    /** Distancia haversine em metros entre 2 pontos lat/lng. */
    protected function haversineMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $raioTerra = 6_371_000.0; // metros
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $raioTerra * $c;
    }
}
