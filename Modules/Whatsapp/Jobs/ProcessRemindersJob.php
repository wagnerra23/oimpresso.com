<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\WhatsappReminder;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * ProcessRemindersJob — US-WA-076 (ADR 0142 §5).
 *
 * Job genérico cross-tenant — varre `whatsapp_reminders` com `status='pending'`
 * + `due_at<=now()` + `notified_at IS NULL` e publica Centrifugo no canal
 * `user:{atendente_user_id}` pra notificar o atendente.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):**
 *
 *   - Job assíncrono SEM session() — usa `withoutGlobalScopes()` deliberado
 *     pra varrer reminders de TODOS businesses. Cada reminder traz seu
 *     próprio `business_id` persistido; canal Centrifugo é per-user
 *     (`user:{id}`), não per-business — não vaza dados cross-tenant.
 *
 *   - Idempotência: `notified_at` preenchido depois de publish() impede
 *     re-publish se job rodar duas vezes (cron `withoutOverlapping` cobre
 *     também).
 *
 *   - Falhas individuais (Centrifugo down etc.) não derrubam o batch — log
 *     warning + continue. `CentrifugoPublisher::publish()` já falha silente.
 *
 * **Scheduling:** registrar `hourly()->withoutOverlapping(30)` em
 * `app/Console/Kernel.php`.
 *
 * **Custo:** trivial — query `(status, due_at)` index é range scan O(log n)
 * + lazy(50) bate em batches. Sem LLM.
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-076
 */
class ProcessRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(CentrifugoPublisher $publisher): void
    {
        $startedAt = microtime(true);
        $processed = 0;
        $published = 0;
        $failed = 0;

        // Job assíncrono sem session() — varre reminders de TODOS businesses.
        // Cada row traz seu próprio business_id; canal Centrifugo é per-user
        // (user:{id}), sem leak cross-tenant.
        $query = WhatsappReminder::withoutGlobalScopes() // SUPERADMIN: cron sem session, cada row escopa seu business_id (ADR 0093)
            ->where('status', WhatsappReminder::STATUS_PENDING)
            ->where('due_at', '<=', now())
            ->whereNull('notified_at')
            ->orderBy('due_at');

        foreach ($query->lazy(50) as $reminder) {
            $processed++;

            try {
                $ok = $publisher->publish(
                    'user:' . (int) $reminder->atendente_user_id,
                    [
                        'type' => 'reminder',
                        'reminder_id' => (int) $reminder->id,
                        'body' => (string) $reminder->body,
                        'conversation_id' => (int) $reminder->conversation_id,
                        'due_at' => optional($reminder->due_at)->toIso8601String(),
                    ],
                );

                // Marca notified_at independente de Centrifugo OK — evita
                // retry infinito se publisher down. UI cliente reconecta
                // e busca via REST quando reabrir. Idempotência > entrega
                // garantida (Centrifugo é eventually consistent — ADR 0058).
                $reminder->forceFill([
                    'notified_at' => now(),
                    'status' => WhatsappReminder::STATUS_NOTIFIED,
                ])->save();

                if ($ok) {
                    $published++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[whatsapp.reminders.process] falha em reminder individual', [
                    'reminder_id' => $reminder->id,
                    'business_id' => $reminder->business_id,
                    'exception' => mb_substr($e->getMessage(), 0, 240),
                ]);
            }
        }

        Log::info('[whatsapp.reminders.process] batch concluído', [
            'processed' => $processed,
            'published' => $published,
            'failed' => $failed,
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
