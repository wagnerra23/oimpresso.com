<?php

declare(strict_types=1);

namespace Modules\Fisio\Listeners;

use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Events\CheckinRegistrado;
use Modules\Fisio\Services\JogoService;

/**
 * Lança o XP do check-in no ledger do jogo (US-FISIO-008). Síncrono de propósito: o portal
 * mostra XP, nível e sequência na resposta do próprio check-in.
 */
final class LancarXpDoCheckin
{
    public function __construct(private readonly JogoService $jogo) {}

    public function handle(CheckinRegistrado $evento): void
    {
        // Filtro explícito pelo business do evento: no portal não há sessão de usuário.
        $checkin = Checkin::query()->where('business_id', $evento->businessId)->whereKey($evento->checkinId)->first();
        if ($checkin !== null) {
            $this->jogo->registrarCheckin($checkin, $evento->vezes);
        }
    }
}