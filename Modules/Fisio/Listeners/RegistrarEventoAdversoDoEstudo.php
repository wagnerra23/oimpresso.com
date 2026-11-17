<?php

declare(strict_types=1);

namespace Modules\Fisio\Listeners;

use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Events\CheckinRegistrado;
use Modules\Fisio\Services\Pesquisa\EventosAdversos;
use Throwable;

/**
 * Check-in de participante de estudo pode virar evento adverso (etapa 07 P-06). Falha aqui
 * não derruba o check-in do paciente: vai para o log e o evento entra no próximo check-in.
 */
final class RegistrarEventoAdversoDoEstudo
{
    public function __construct(private readonly EventosAdversos $eventos) {}

    public function handle(CheckinRegistrado $evento): void
    {
        if (! Schema::hasTable('fisio_study_adverse_events')) {
            return;
        }
        // Filtro explícito pelo business do evento: no portal não há sessão de usuário.
        $checkin = Checkin::query()->where('business_id', $evento->businessId)->whereKey($evento->checkinId)->first();
        if ($checkin === null) {
            return;
        }
        try {
            $this->eventos->registrarDoCheckin($checkin);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
