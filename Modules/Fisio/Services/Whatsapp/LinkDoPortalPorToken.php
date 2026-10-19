<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

use DomainException;
use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\PortalTokenService;

/**
 * Implementação padrão do contrato `LinkDoPortal`: delega ao `PortalTokenService` do portal do
 * paciente (US-FISIO-007a). A URL pública é `/fisio/p/{token}`.
 *
 * Emitir revoga o link anterior do paciente, por isso quem chama só emite depois da reserva
 * idempotente do envio (EnvioWhatsappFisio). Paciente bloqueado não recebe link: devolve null
 * e nada é enviado.
 */
final class LinkDoPortalPorToken implements LinkDoPortal
{
    public function __construct(private readonly PortalTokenService $portal) {}

    public function emitir(Patient $paciente): ?array
    {
        try {
            $emitido = $this->portal->emitir($paciente);
        } catch (DomainException) {
            return null;
        }

        return ['url' => url('/fisio/p/'.$emitido['token']), 'pin' => $emitido['pin']];
    }
}