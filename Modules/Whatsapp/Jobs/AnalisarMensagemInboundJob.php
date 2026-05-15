<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Analise\AnaliseMensagemService;

/**
 * US-WA-095 — Async Job que chama Jana pra analisar 1 mensagem inbound.
 *
 * Pattern: Listener `AnalisarMensagemInboundComJana` dispatcha ao receber
 * `OmnichannelMessageReceived`. Job roda em queue=`jana-analise` (separada
 * da `whatsapp-history` pra não competir com persistência crítica).
 *
 * Idempotente — Service.analisar() respeita `analise_at` (skip se já feito).
 * Fail-open — Service não rethrow; Job sempre completa.
 *
 * Backoff: 30s/120s. 3 tries antes de drop.
 *
 * @see Modules/Whatsapp/Services/Analise/AnaliseMensagemService.php
 * @see Modules/Whatsapp/Listeners/AnalisarMensagemInboundComJana.php
 */
class AnalisarMensagemInboundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(
        public readonly int $businessId,
        public readonly int $messageId,
    ) {
        $this->onConnection('database');
        $this->onQueue('jana-analise');
    }

    public function handle(AnaliseMensagemService $service): void
    {
        // SUPERADMIN: Job sem session HTTP — withoutGlobalScope + filtro
        // defensivo where('business_id') preserva Tier 0 (ADR 0093).
        $message = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->messageId)
            ->first();

        if ($message === null) {
            Log::channel('single')->warning('[whatsapp.analise-job] mensagem não encontrada', [
                'business_id' => $this->businessId,
                'message_id' => $this->messageId,
            ]);
            return;
        }

        $service->analisar($message);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('single')->error('[whatsapp.analise-job] todas tentativas falharam', [
            'metric_name' => 'whatsapp_analise_job_failed',
            'business_id' => $this->businessId,
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
        ]);
    }
}
