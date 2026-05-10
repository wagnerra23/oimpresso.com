<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Jobs;

use App\Domain\Fsm\Models\StockReservation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Job daily — expira reservas ACTIVE com expires_at < now() (US-SELL-013).
 *
 * Schedule sugerido: hourly em CT 100 (Hostinger não roda Horizon — ADR 0062).
 * Não roda side-effect (reserva expirada NÃO baixa qty_available — só libera
 * a quantidade contada no cálculo "disponível pra venda").
 *
 * Multi-tenant: usa withoutGlobalScope porque é cron sem auth (CLI).
 * Retorna count de reservas expiradas (pra log/observability).
 */
class ExpireStaleReservationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): int
    {
        return StockReservation::withoutGlobalScope(ScopeByBusiness::class)
            ->where('status', StockReservation::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => StockReservation::STATUS_EXPIRED]);
    }
}
