<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;

/**
 * BackfillLidConversationsJob — re-linka conversations órfãs após
 * descoberta tardia de phone via LidPhoneMap.
 *
 * Disparado pelo `LidPhoneMapObserver::saved` quando
 * `LidPhoneMap.phone_e164` muda NULL→valor (ou X→Y). Itera todas
 * conversations do `business_id + lid` com `contact_id = NULL` e
 * roda `ConversationContactLinker::tryLink()` em cada uma — fecha o
 * loop da 1ª msg @lid que ficou órfã antes do phone ser descoberto.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — query escopada
 * EXPLICITAMENTE em `business_id` (com `withoutGlobalScope` pois job
 * roda em fila sem session user). UNIQUE (business_id, lid) na
 * `LidPhoneMap` garante que `lid` só pertence a 1 business; ainda
 * assim filtramos pelo `business_id` do construtor pra defesa em
 * profundidade.
 *
 * Idempotente — re-run não cria duplicata (linker pula conv já
 * linkada via `if ($conversation->contact_id !== null) return null`).
 *
 * Retro-compat com pré-PR1 (coluna `lid` em `conversations` ainda não
 * criada): `Schema::hasColumn` decide entre `where('lid', ...)` ou
 * fallback `customer_external_id LIKE '%<lid>@lid'`.
 *
 * Log sem PII (ADR 0093 §LGPD): só ids numéricos + lid_prefix 6 chars.
 *
 * @see \Modules\Whatsapp\Observers\LidPhoneMapObserver
 * @see \Modules\Whatsapp\Services\Contacts\ConversationContactLinker
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md §6
 */
class BackfillLidConversationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int número máx de tentativas (transient DB error → retry) */
    public int $tries = 3;

    /** @var int timeout do job em segundos */
    public int $timeout = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly string $lid,
        public readonly string $phoneE164,
    ) {}

    public function handle(ConversationContactLinker $linker): void
    {
        $hasLidColumn = Schema::hasColumn('conversations', 'lid');

        $query = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->whereNull('contact_id');

        if ($hasLidColumn) {
            // Caminho canônico pós-PR1 — coluna `lid` populada pelo
            // MessagePersister no momento da criação da conversation.
            $query->where('lid', $this->lid);
        } else {
            // Fallback pré-PR1 — parsea LID do `customer_external_id`
            // legacy. Suporta dois formatos histórias observados em prod:
            //   - "<lid>@lid"  (formato atual MessagePersister)
            //   - "<lid>"      (alguns webhook handlers antigos)
            $lid = $this->lid;
            $query->where(function ($q) use ($lid) {
                $q->where('customer_external_id', $lid)
                    ->orWhere('customer_external_id', 'LIKE', '%' . $lid . '@lid');
            });
        }

        $linkedCount = 0;
        $scannedCount = 0;

        foreach ($query->cursor() as $conversation) {
            $scannedCount++;
            // Defense-in-depth Tier 0 — embora a query já filtre por
            // business_id, re-checa cada conversation antes de mexer.
            if ((int) $conversation->business_id !== $this->businessId) {
                continue;
            }

            $linked = $linker->tryLink($conversation);
            if ($linked !== null) {
                $linkedCount++;
            }
        }

        Log::info('[whatsapp.lid_backfill.job_done]', [
            'business_id' => $this->businessId,
            'lid_prefix' => substr($this->lid, 0, 6) . '...',
            'scanned_count' => $scannedCount,
            'linked_count' => $linkedCount,
            'used_lid_column' => $hasLidColumn,
        ]);
    }

    /**
     * Tags Horizon — facilita debug/filtro do job na dashboard.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            "business:{$this->businessId}",
            'whatsapp:lid-backfill',
        ];
    }
}
