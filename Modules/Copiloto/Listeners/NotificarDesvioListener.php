<?php

namespace Modules\Copiloto\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Events\CopilotoDesvioDetectado;
use Modules\Copiloto\Notifications\MetaDesvioNotification;

/**
 * Ouve CopilotoDesvioDetectado e notifica o usuário responsável pela meta.
 */
class NotificarDesvioListener implements ShouldQueue
{
    public function handle(CopilotoDesvioDetectado $event): void
    {
        $row = DB::table('copiloto_metas')
            ->where('id', $event->meta_id)
            ->select('criada_por_user_id', 'business_id')
            ->first();

        if (! $row) {
            return;
        }

        $user = \App\Models\User::find($row->criada_por_user_id);

        if (! $user) {
            return;
        }

        $user->notify(new MetaDesvioNotification($event));
    }
}
