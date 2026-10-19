<?php

declare(strict_types=1);

namespace Modules\Fisio\Events;

/**
 * O check-in do dia foi gravado (criado OU atualizado por reenvio) — US-FISIO-008.
 *
 * Ponto de entrada ÚNICO do jogo: quem grava o check-in (o controller do portal, ou o botão
 * "Já fiz" do WhatsApp no futuro) dispara este evento DEPOIS de gravar, na mesma requisição.
 * O listener `LancarXpDoCheckin` lança o XP no ledger de forma síncrona e idempotente —
 * disparar duas vezes para o mesmo relato não lança nada a mais.
 *
 * `vezes` é o que o paciente informou (item_id => vezes feitas hoje). Não fica no check-in:
 * o ledger guarda o que foi lançado e o reenvio só lança a diferença positiva.
 */
final class CheckinRegistrado
{
    /**
     * @param  array<int|string,int|string>  $vezes
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $checkinId,
        public readonly array $vezes = [],
    ) {}
}