<?php

namespace Modules\Copiloto\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Modules\Copiloto\Events\CopilotoDesvioDetectado;

/**
 * Notificação de desvio de meta — canal database + broadcast.
 */
class MetaDesvioNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CopilotoDesvioDetectado $evento,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'meta_id'    => $this->evento->meta_id,
            'desvio_pct' => $this->evento->desvio_pct,
            'severidade' => $this->evento->severidade,
            'data_ref'   => $this->evento->data_ref,
            'mensagem'   => $this->mensagemHumana(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'meta_id'    => $this->evento->meta_id,
            'desvio_pct' => $this->evento->desvio_pct,
            'severidade' => $this->evento->severidade,
            'data_ref'   => $this->evento->data_ref,
            'mensagem'   => $this->mensagemHumana(),
        ]);
    }

    protected function mensagemHumana(): string
    {
        $dir  = $this->evento->desvio_pct < 0 ? 'abaixo' : 'acima';
        $pct  = abs(round($this->evento->desvio_pct, 1));
        $sev  = ucfirst($this->evento->severidade);

        return "[{$sev}] Meta #{$this->evento->meta_id}: desvio de {$pct}% {$dir} da projeção em {$this->evento->data_ref}.";
    }
}
