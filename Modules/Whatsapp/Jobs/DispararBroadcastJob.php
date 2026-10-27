<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use App\Scopes\ScopeByBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\WhatsappBroadcast;
use Modules\Whatsapp\Services\Broadcast\PlanoDeDisparo;

/**
 * Disparo real de uma campanha (US-WA-322 · ADR 0268 fase 2).
 *
 * Tira a campanha de `draft` para `dispatching` (uma vez só: a troca é condicional no banco) e
 * agenda UM `EnviarItemBroadcastJob` por conversa, nos horários do `PlanoDeDisparo` (intervalo
 * aleatório, horário comercial, limite diário com aquecimento). Nada é enviado aqui.
 *
 * Só texto livre: o envio pelos canais de campanha (Baileys/Whatsmeow) é texto; template HSM fica
 * para quando houver canal oficial. Desligado por padrão (`whatsapp.broadcast.disparo_enabled`).
 *
 * Tier 0 (ADR 0093): `$businessId` no construtor; toda leitura filtra por ele.
 */
class DispararBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $businessId, public int $broadcastId)
    {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        $ctx = ['business_id' => $this->businessId, 'broadcast_id' => $this->broadcastId];
        if (! config('whatsapp.broadcast.disparo_enabled', false)) {
            Log::info('[whatsapp.broadcast] disparo desligado — skip', $ctx);

            return;
        }

        // SUPERADMIN: job sem sessão; filtro explícito por business_id (ADR 0093).
        $b = WhatsappBroadcast::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', $this->broadcastId)->first();
        if ($b === null || $b->kind !== 'freeform' || trim((string) $b->body) === '') {
            Log::info('[whatsapp.broadcast] campanha ausente ou não é texto livre — skip', $ctx);

            return;
        }
        $ids = array_values(array_unique(array_map('intval', $b->recipient_conversation_ids ?? [])));

        // Troca condicional: duas execuções concorrentes não agendam a campanha duas vezes.
        $mudou = DB::table('whatsapp_broadcasts')
            ->where('business_id', $this->businessId)->where('id', $this->broadcastId)->where('status', 'draft')
            ->update(['status' => 'dispatching', 'dispatched_at' => now(), 'total' => count($ids), 'updated_at' => now()]);
        if ($mudou === 0) {
            Log::info('[whatsapp.broadcast] campanha não está em rascunho — skip', $ctx);

            return;
        }

        // SUPERADMIN: idem — canal da própria empresa.
        $canal = Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', (int) $b->channel_id)->first();
        $cfg = config('whatsapp.broadcast');
        $aquecendo = $canal === null || $canal->created_at === null
            || $canal->created_at->gt(now()->subDays((int) $cfg['dias_aquecimento']));

        $tz = (string) (DB::table('business')->where('id', $this->businessId)->value('time_zone') ?: config('app.timezone'));
        $horarios = PlanoDeDisparo::horarios(
            count($ids),
            CarbonImmutable::now($tz),
            (int) ($aquecendo ? $cfg['limite_dia_aquecimento'] : $cfg['limite_dia']),
            (int) $cfg['intervalo_min_s'],
            (int) $cfg['intervalo_max_s'],
            (int) $cfg['janela_inicio'],
            (int) $cfg['janela_fim'],
            fn (int $a, int $z): int => random_int($a, $z),
        );

        foreach ($ids as $i => $conversaId) {
            EnviarItemBroadcastJob::dispatch($this->businessId, $this->broadcastId, $conversaId)->delay($horarios[$i]);
        }
        Log::info('[whatsapp.broadcast] agendada', $ctx + ['total' => count($ids), 'aquecimento' => $aquecendo]);
    }
}
