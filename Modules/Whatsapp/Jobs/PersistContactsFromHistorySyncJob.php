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
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;

/**
 * Persiste o array `contacts` do payload `messaging-history.set` Baileys.
 *
 * **Por que existe (incident 2026-05-15 09:25 — ROTA LIVRE biz=1):**
 * pós re-pareamento Baileys 7.x, Wagner reportou "sincronia dos contatos
 * não trouxe contatos". Root cause: daemon Node ENVIA `contacts` no payload
 * `history.sync` (Instance.ts:244-281, chunk_index=0), mas o handler PHP
 * `ChannelBaileysWebhookController::handleHistorySync` IGNORAVA esse campo
 * — só processava `messages`. Resultado: Conversation só ganhava nome
 * quando msg com `pushName` chegava — antes disso, só E.164 cru exibido.
 *
 * **Solução**: este Job, dispatchado ao receber chunk_index=0 com
 * `contacts`, hidrata `Conversation.contact_name` para cada contato cujo
 * phone bata com `customer_external_id` de uma conv existente. Preserva
 * nome existente (não sobrescreve nome já curado pelo atendente / push_name).
 *
 * **Escopo mínimo (Wagner aprovação 2026-05-15)**:
 * - SÓ atualiza `Conversation.contact_name` quando vazio ou igual ao
 *   `customer_external_id` (idempotente via COALESCE).
 * - NÃO cria tabela `whatsapp_contacts` (escopo separado, ADR futura).
 * - NÃO duplica `ConversationContactLinker` (Contact CRM continua sendo
 *   linkado pelo handleMessage do real-time).
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)**:
 * - businessId no constructor (Jobs sem session()).
 * - `withoutGlobalScope` justificado SUPERADMIN — filtro explícito
 *   por business_id + channel_id em todas as queries.
 *
 * **Idempotência**: re-run safe — COALESCE(NULLIF(contact_name, ''), :name)
 * preserva nome já preenchido. Sem chave UNIQUE específica — múltiplos
 * runs do mesmo payload = no-op.
 *
 * **LID resolution**: contact pode vir com `@lid` em vez de
 * `@s.whatsapp.net` (Multi-Device anti-spam). Quando isso acontece,
 * tenta resolver via `LidPhoneResolver` (cache 24h alimentado pelo
 * pipeline real-time `MessagePersister`). Se LID não resolvido ainda,
 * skip (próxima msg do contato pelo path real-time vai descobrir).
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php
 * @see Modules/Whatsapp/daemon-node/src/baileys/Instance.ts L243-288 (shape origem)
 * @see memory/sessions/2026-05-15-agent-c-contact-sync-history-sync.md
 */
class PersistContactsFromHistorySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60; // contacts array ~ 100-500 items max — 1min sobra

    /**
     * @return array<int, int> backoff em segundos (idêntico ao PersistHistorySyncBatchJob)
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * @param  array<int, mixed>  $contacts  Array shape Baileys:
     *   [
     *     ['id' => '5511999998888@s.whatsapp.net', 'name' => 'Maria', 'notify' => 'Maria', 'verifiedName' => null],
     *     ['id' => 'X@lid', 'name' => null, 'notify' => 'Bruno', ...],
     *     ...
     *   ]
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $channelId,
        public readonly int $syncType,
        public readonly array $contacts,
    ) {
        // Mesma arquitetura "buffer rápido + worker async" do PersistHistorySyncBatchJob.
        // onConnection('database') override QUEUE_CONNECTION=sync default Hostinger —
        // Job vai pra tabela `jobs` (persistente, atômico). Worker:
        //   php artisan queue:work database --queue=whatsapp-history --max-time=55
        $this->onConnection('database');
        $this->onQueue('whatsapp-history');
    }

    public function handle(): void
    {
        if (empty($this->contacts)) {
            return;
        }

        // SUPERADMIN: ADR 0093 — Job sem session user; filtro explícito por biz+id
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->channelId)
            ->first();

        if (! $channel) {
            Log::warning('[whatsapp.history-sync-contacts-job] channel not found', [
                'channel_id' => $this->channelId,
                'business_id' => $this->businessId,
            ]);
            return;
        }

        $startedAtMs = (int) (microtime(true) * 1000);
        $attempt = $this->attempts() > 0 ? $this->attempts() : 1;

        $resolver = app(LidPhoneResolver::class);

        $updated = 0;
        $skippedNoName = 0;
        $skippedNoMatch = 0;
        $skippedLidUnresolved = 0;
        $skippedAlreadyNamed = 0;

        foreach ($this->contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $id = (string) ($contact['id'] ?? '');
            if ($id === '') {
                continue;
            }

            // Nome preferido: name > notify > verifiedName (Baileys envia em ordem
            // de "qualidade" — verifiedName é Business Profile oficial, name é
            // push name salvo pelo dono do contato, notify é display volátil).
            $name = $this->pickName($contact);
            if ($name === null) {
                $skippedNoName++;
                continue;
            }

            // E.164 resolution — mirror do MessagePersister:
            //   - @s.whatsapp.net normal → '+' . dígitos
            //   - @lid → tenta LidPhoneResolver (cache populado pelo path real-time)
            $customerExternalId = $this->resolveCustomerExternalId($id, $resolver);
            if ($customerExternalId === null) {
                $skippedLidUnresolved++;
                continue;
            }

            // UPDATE idempotente — COALESCE(NULLIF(contact_name, ''), :name):
            //   - se contact_name é NULL ou '' → seta :name
            //   - se contact_name já tem valor não-vazio → preserva (NÃO sobrescreve)
            //
            // Igual ao comportamento do ConversationContactLinker::tryLink (linha
            // 256 — "Curado pelo atendente? Não toca"). Push_name de msg real-time
            // tem prioridade sobre history.sync contacts pq é mais "fresco".
            //
            // SUPERADMIN: ADR 0093 — Job sem session user, scope manual
            $affected = Conversation::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->where('channel_id', $this->channelId)
                ->where('customer_external_id', $customerExternalId)
                ->where(function ($q) use ($customerExternalId) {
                    // Só atualiza se contact_name está vazio OU é apenas o E.164
                    // cru (fallback inicial do MessagePersister linha 232 quando
                    // pushName não veio). Preserva nome curado pelo atendente.
                    $q->whereNull('contact_name')
                      ->orWhere('contact_name', '')
                      ->orWhere('contact_name', $customerExternalId);
                })
                ->update(['contact_name' => $name]);

            if ($affected > 0) {
                $updated += $affected;
            } else {
                // Pode ser que (a) não existe Conversation com esse phone ainda
                // (nenhum msg recebido), OU (b) já tem nome curado — em ambos
                // casos o comportamento mínimo é skip silencioso. Distinguir
                // os 2 exigiria SELECT extra; agregamos em "no_match".
                $skippedNoMatch++;
            }
        }

        $durationMs = (int) (microtime(true) * 1000) - $startedAtMs;

        // ─── Métrica OTel lightweight bridge: contacts_persisted ───────────
        // Loki agrega via logQL `metric_name="whatsapp_history_contacts_persisted"`
        // → contador Grafana. Tier 0 multi-tenant: business_id SEMPRE presente.
        // PII redact: zero phone/E.164 — só counts e IDs internos.
        Log::channel('single')->info('[whatsapp.history-sync-contacts-job] contacts processados', [
            'metric_name' => 'whatsapp_history_contacts_persisted',
            'business_id' => $this->businessId,
            'channel_id' => $this->channelId,
            'sync_type' => $this->syncType,
            'contacts_count' => count($this->contacts),
            'updated' => $updated,
            'skipped_no_name' => $skippedNoName,
            'skipped_no_match' => $skippedNoMatch,
            'skipped_lid_unresolved' => $skippedLidUnresolved,
            'skipped_already_named' => $skippedAlreadyNamed,
            'attempt' => $attempt,
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('single')->error('[whatsapp.history-sync-contacts-job] todas tentativas falharam', [
            'metric_name' => 'whatsapp_history_contacts_failed',
            'business_id' => $this->businessId,
            'channel_id' => $this->channelId,
            'sync_type' => $this->syncType,
            'contacts_count' => count($this->contacts),
            'attempt' => $this->tries,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Escolhe o nome canônico do contato priorizando qualidade da fonte.
     *
     * Ordem Baileys:
     *   1. verifiedName — Business Profile oficial verificado (raro mas autoritativo)
     *   2. name — Push name salvo pelo dono do contato (estável)
     *   3. notify — Display name volátil (pode ser nick do momento)
     *
     * Retorna null se todos estão vazios (contato bloqueado pelo cliente,
     * ou contact sem nome cadastrado).
     */
    protected function pickName(array $contact): ?string
    {
        foreach (['verifiedName', 'name', 'notify'] as $field) {
            $value = $contact[$field] ?? null;
            if (! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }
        return null;
    }

    /**
     * Resolve `customer_external_id` (formato `+E.164`) do `id` do contato.
     *
     * Casos:
     *   - `5511999998888@s.whatsapp.net` → `+5511999998888`
     *   - `X@lid` → consulta LidPhoneResolver (cache 24h alimentado pelo
     *     pipeline real-time); se null, retorna null (skip).
     *   - sem `@` → assume só dígitos, normaliza pra `+`.
     */
    protected function resolveCustomerExternalId(string $id, LidPhoneResolver $resolver): ?string
    {
        if (str_contains($id, '@s.whatsapp.net')) {
            $digits = preg_replace('/@.+$/', '', $id) ?? '';
            $digits = preg_replace('/\D+/', '', $digits) ?? '';
            return $digits !== '' ? '+' . $digits : null;
        }

        if (str_contains($id, '@lid')) {
            // LidPhoneResolver retorna `+E.164` ou null
            $resolved = $resolver->resolve($this->businessId, $id);
            return $resolved; // null = LID não resolvido ainda, caller skip
        }

        // Formato bruto sem `@` — normaliza defensivamente
        $digits = preg_replace('/\D+/', '', $id) ?? '';
        return $digits !== '' ? '+' . $digits : null;
    }
}
