<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Contracts\NfseCancelDriverInterface;
use Modules\NfeBrasil\Models\NfseEmissao;
use Modules\NfeBrasil\Models\NfseEventoCancelamento;
use RuntimeException;

/**
 * US-NFSE-CANCEL-001 — Service Manager de cancelamento NFSe (Manager pattern).
 *
 * NFSe é fragmentado por padrão municipal — NÃO existe protocolo único. Este
 * service NÃO cancela diretamente; resolve qual `NfseCancelDriverInterface`
 * atende o município da emissão e delega.
 *
 * Drivers são registrados no container (NfeBrasilServiceProvider::register())
 * como instâncias bound em `'nfse.cancel.drivers'`. Cada driver declara seus
 * municípios via `supportedMunicipios()` — vazio = não roteia ninguém ainda
 * (stub esperando integração real).
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - Cross-tenant guard ANTES de delegar pro driver
 *   - Idempotência por NfseEmissao já em status=cancelled
 *
 * Validações antes de delegar:
 *   - Motivo 15-255 chars (regra ABRASF + NT 2024-001 alinhada com NFe55)
 *   - Município preenchido na emissão (senão impossível rotear driver)
 *   - business_id da emissão == businessId passado (defesa em profundidade)
 *
 * @see NfseCancelDriverInterface
 * @see memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md
 */
class NfseCancelService
{
    /** @var array<int, NfseCancelDriverInterface> */
    private array $drivers;

    /**
     * @param  iterable<NfseCancelDriverInterface>  $drivers  Drivers registrados via DI.
     */
    public function __construct(iterable $drivers = [])
    {
        $list = [];
        foreach ($drivers as $driver) {
            if ($driver instanceof NfseCancelDriverInterface) {
                $list[] = $driver;
            }
        }
        $this->drivers = $list;
    }

    /**
     * Cancela uma NFSe via driver per-município.
     *
     * @throws InvalidArgumentException Se motivo fora de 15-255 chars.
     * @throws UnauthorizedActionException Se businessId divergir de nfse.business_id.
     * @throws RuntimeException Se nenhum driver suportar o município, ou
     *                          se o driver falhar.
     */
    public function cancelar(int $businessId, int $nfseEmissaoId, string $motivo): NfseEventoCancelamento
    {
        // ── 1. Validação motivo (15-255 chars — alinhado NFe55 + ABRASF) ─────
        $tamanho = mb_strlen(trim($motivo));
        if ($tamanho < 15 || $tamanho > 255) {
            throw new InvalidArgumentException(
                "Motivo de cancelamento NFSe deve ter 15-255 chars (recebido: {$tamanho})."
            );
        }

        // ── 2. Carregar emissão SEM global scope pra fazer cross-tenant guard
        //      explícito (defesa em profundidade ADR 0093) ───────────────────
        $nfse = NfseEmissao::withoutGlobalScopes()->find($nfseEmissaoId);
        if (! $nfse) {
            throw new RuntimeException("NfseEmissao {$nfseEmissaoId} não encontrada.");
        }

        if ((int) $nfse->business_id !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: business {$businessId} tentou cancelar NfseEmissao " .
                "{$nfseEmissaoId} de business {$nfse->business_id}."
            );
        }

        // ── 3. Idempotência — emissão já cancelled retorna evento existente ─
        if ($nfse->status === NfseEmissao::STATUS_CANCELLED) {
            $existente = NfseEventoCancelamento::withoutGlobalScopes()
                ->where('nfse_emissao_id', $nfse->id)
                ->where('status', NfseEventoCancelamento::STATUS_AUTORIZADO)
                ->latest('id')
                ->first();

            if ($existente) {
                Log::info('NfseCancelService.cancelar: idempotência — NFSe já cancelada, retornando evento existente', [
                    'business_id'     => $businessId,
                    'nfse_emissao_id' => $nfse->id,
                    'evento_id'       => $existente->id,
                ]);
                return $existente;
            }

            Log::warning('NfseCancelService.cancelar: status=cancelled sem evento autorizado (drift) — reemitindo', [
                'business_id'     => $businessId,
                'nfse_emissao_id' => $nfse->id,
            ]);
        }

        // ── 4. Resolver driver pelo município ────────────────────────────────
        $driver = $this->resolveDriver($nfse);

        Log::info('NfseCancelService.cancelar: delegando pro driver', [
            'business_id'     => $businessId,
            'nfse_emissao_id' => $nfse->id,
            'driver_key'      => $driver->getDriverKey(),
            'municipio'       => $nfse->municipio_codigo_ibge ?? null,
        ]);

        // ── 5. Delegar pro driver (driver persiste o evento + integra API) ─
        return $driver->cancelar($nfse, $motivo);
    }

    /**
     * Resolve qual driver atende o município da emissão.
     *
     * @throws RuntimeException Se nenhum driver registrado declarar suporte.
     */
    public function resolveDriver(NfseEmissao $nfse): NfseCancelDriverInterface
    {
        $codIbge = isset($nfse->municipio_codigo_ibge)
            ? (string) $nfse->municipio_codigo_ibge
            : '';

        if ($codIbge === '') {
            throw new RuntimeException(
                "NfseEmissao {$nfse->id} sem `municipio_codigo_ibge` — impossível resolver driver de cancelamento."
            );
        }

        foreach ($this->drivers as $driver) {
            if (in_array($codIbge, $driver->supportedMunicipios(), true)) {
                return $driver;
            }
        }

        throw new RuntimeException(
            "Nenhum driver NFSe de cancelamento registrado pra município IBGE {$codIbge} " .
            '(NfseEmissao ' . $nfse->id . '). ' .
            'Veja memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md pra US per-município pendentes.'
        );
    }

    /**
     * Lista todos drivers registrados (introspecção pra admin/debug).
     *
     * @return array<int, NfseCancelDriverInterface>
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }
}
