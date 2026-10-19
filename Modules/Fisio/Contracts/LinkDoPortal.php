<?php

declare(strict_types=1);

namespace Modules\Fisio\Contracts;

use Modules\Fisio\Entities\Patient;

/**
 * Ponto de integração do WhatsApp do Fisio (US-FISIO-009) com o portal do paciente (US-FISIO-007a).
 *
 * O envio do programa precisa de uma coisa só do portal: "emita um acesso novo para este paciente e
 * me diga a URL pública e o PIN". Este contrato é esse pedido, e nada além dele, para que o job de
 * WhatsApp não dependa de como o portal guarda token, PIN ou lockout.
 *
 * A implementação padrão (`Services\Whatsapp\LinkDoPortalPorToken`) delega ao `PortalTokenService`
 * do 007a quando ele existe. Enquanto o 007a não estiver no main, ela devolve null e o envio não
 * acontece (nada é registrado, então a próxima tentativa envia). Os testes trocam a implementação
 * por um falso (`Tests/Support/LinkDoPortalFalso`).
 *
 * Emitir REVOGA o acesso anterior (um token vivo por paciente — decisão nº 8 do README da suíte).
 * Por isso só se emite depois que a reserva idempotente do envio deu certo.
 */
interface LinkDoPortal
{
    /**
     * @return array{url: string, pin: string}|null null = portal indisponível; nada foi emitido
     */
    public function emitir(Patient $paciente): ?array;
}