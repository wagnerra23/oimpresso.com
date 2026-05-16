<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\CustomerMemory;

use App\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;
use Throwable;

/**
 * Observabilidade D9.a (ADR 0155): `rebuild()` envolto em `OtelHelper::span(`
 * (Tracer whatsapp.customer_memory.rebuild) — mede latência por business.
 *
 * US-WA-VOZ-001 — Recompila o registro `customer_memory` de 1 cliente.
 *
 * Stateless. Idempotente. Tier 0 multi-tenant ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)).
 *
 * Responsabilidades:
 *   1. Resolve identidade (phone → Contact CRM via ConversationContactLinker)
 *   2. Recalcula stats agregados (n_conversations, n_msgs_*, first/last)
 *   3. Atualiza display_name + consent_status denormalizados
 *   4. Marca `last_rebuilt_at` + `rebuilt_via`
 *
 * NÃO toca em:
 *   - Inferências IA (`temas_recorrentes`, `sentimento_score`, `churn_risk_score`,
 *     `comunicacao_preferida`) — esses são populados por jobs separados Onda 3
 *   - Notas qualitativas (`notas_jana`, `flags`) — só humano/Jana escrevem
 *   - `erasure_requested_at` — só endpoint LGPD apaga
 *
 * Fail-open: erro em sub-step (ex: ConversationContactLinker down) loga e
 * continua com identity_match_method=unknown. Stats são best-effort.
 *
 * @see Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php
 * @see Modules/Whatsapp/Entities/CustomerMemory.php
 */
class CustomerMemoryRebuilder
{
    public function __construct(
        protected readonly ConversationContactLinker $linker,
    ) {
    }

    /**
     * Recompila customer_memory pra `(business_id, customer_external_id)`.
     * Cria row se não existir (upsert).
     *
     * @param  string  $via  origem do rebuild (REBUILT_VIA_*)
     */
    public function rebuild(int $businessId, string $customerExternalId, string $via = CustomerMemory::REBUILT_VIA_MANUAL): CustomerMemory
    {
        $extId = $this->normalizeExternalId($customerExternalId);
        $phoneDigits = preg_replace('/\D+/', '', $extId);

        // SUPERADMIN: service roda em Job/Command sem session HTTP —
        // withoutGlobalScope + filtro defensivo where('business_id').
        $memory = CustomerMemory::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('customer_external_id', $extId)
            ->first();

        if ($memory === null) {
            $memory = new CustomerMemory();
            $memory->business_id = $businessId;
            $memory->customer_external_id = $extId;
        }

        $memory->phone_normalized = $phoneDigits !== '' ? $phoneDigits : null;

        // Step 1 — Identity Resolution (phone → Contact CRM)
        $this->resolveIdentity($memory, $businessId, $extId);

        // Step 2 — Stats agregados (n_conversations, n_msgs_*, first/last)
        $this->refreshStats($memory, $businessId, $extId);

        // Step 3 — Display name + consent denormalize (cache de Contact)
        $this->refreshDenormalized($memory);

        // Step 4 — Funcionário responsável + funcionário mais ativo (US-WA-VOZ-002)
        $this->refreshAssignedUser($memory, $businessId, $extId);

        // Step 5 — Reclamações heurística keywords (sem IA, custo zero)
        $this->refreshReclamacoes($memory, $businessId, $extId);

        // Step 6 — Tracking
        $memory->last_rebuilt_at = now();
        $memory->rebuilt_via = $via;

        $memory->save();

        Log::channel('single')->info('[customer_memory.rebuilt]', [
            'metric_name' => 'customer_memory_rebuilt',
            'business_id' => $businessId,
            'customer_memory_id' => $memory->id,
            'contact_id' => $memory->contact_id,
            'match_method' => $memory->identity_match_method,
            'n_conversations' => $memory->n_conversations,
            'n_msgs_total' => $memory->n_msgs_total,
            'via' => $via,
        ]);

        return $memory;
    }

    /**
     * Apenas atualiza `last_interaction_at` (cheap, real-time listener).
     * Cria row mínima se não existir (lazy init).
     * NÃO recompila stats — Job/cron faz isso em background.
     */
    public function touch(int $businessId, string $customerExternalId, ?\DateTimeInterface $when = null): void
    {
        $extId = $this->normalizeExternalId($customerExternalId);
        $when = $when ?? now();

        // Upsert defensivo — race-safe via INSERT ... ON DUPLICATE KEY UPDATE
        DB::table('customer_memory')->updateOrInsert(
            [
                'business_id' => $businessId,
                'customer_external_id' => $extId,
            ],
            [
                'last_interaction_at' => $when,
                'first_interaction_at' => DB::raw("COALESCE(first_interaction_at, '" . $when->format('Y-m-d H:i:s') . "')"),
                'phone_normalized' => preg_replace('/\D+/', '', $extId) ?: null,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    /**
     * Normaliza customer_external_id pra E.164 sem '+' nem espaços.
     * '+5548999...' → '5548999...'
     */
    protected function normalizeExternalId(string $raw): string
    {
        $trimmed = trim($raw);
        return ltrim($trimmed, '+');
    }

    /**
     * Step 1 — Resolve identidade (phone → Contact).
     * Reusa ConversationContactLinker que já tem cache + suffix_8 match.
     */
    protected function resolveIdentity(CustomerMemory $memory, int $businessId, string $extId): void
    {
        try {
            $beforeContactId = $memory->contact_id;

            // ConversationContactLinker::attemptLink → cached 1h, retorna ?int
            $contactId = $this->linker->attemptLink($businessId, $extId);

            if ($contactId === null) {
                // Sem match — preserva contact_id antigo se já tinha (manual link)
                if ($beforeContactId === null) {
                    $memory->identity_match_method = CustomerMemory::MATCH_UNKNOWN;
                    $memory->identity_match_confidence = 0.0;
                    $memory->identity_match_at = now();
                }
                return;
            }

            // Match encontrado — confidence depende do número de candidates
            // (recuperar via findMatchesForPhone pra inferir confiança)
            $phoneDigits = preg_replace('/\D+/', '', $extId);
            $candidates = $this->linker->findMatchesForPhone($businessId, $phoneDigits);
            $count = $candidates->count();

            $method = CustomerMemory::MATCH_SUFFIX_8;
            $confidence = 1.0;

            if ($count > 1) {
                $method = CustomerMemory::MATCH_AMBIGUOUS;
                $confidence = round(1.0 / (float) $count, 2); // 0.5 pra 2, 0.33 pra 3
            }

            // Se Contact tem mobile/landline/alternate que bate EXATO (sem normalize),
            // marca como exact (confidence permanece 1.0)
            if ($count === 1 && $this->isExactMatch($candidates->first(), $extId)) {
                $method = CustomerMemory::MATCH_EXACT;
            }

            $memory->contact_id = $contactId;
            $memory->identity_match_method = $method;
            $memory->identity_match_confidence = $confidence;
            $memory->identity_match_at = now();
        } catch (Throwable $e) {
            Log::channel('single')->warning('[customer_memory.identity_resolve_failed]', [
                'business_id' => $businessId,
                'customer_external_id_redacted' => substr($extId, 0, 4) . '***' . substr($extId, -2),
                'error' => $e->getMessage(),
            ]);
            // Fail-open: continua sem identity, mas stats continuam
        }
    }

    /**
     * Step 2 — Recalcula stats agregados.
     *
     * 1 query por contagem (n_conversations + n_msgs_inbound + n_msgs_outbound)
     * + 1 query first/last_interaction_at. Total: 2 queries per rebuild.
     */
    protected function refreshStats(CustomerMemory $memory, int $businessId, string $extId): void
    {
        try {
            $convStats = DB::table('conversations')
                ->where('business_id', $businessId)
                ->where('customer_external_id', '+' . $extId)
                ->orWhere(function ($q) use ($businessId, $extId) {
                    $q->where('business_id', $businessId)
                      ->where('customer_external_id', $extId);
                })
                ->selectRaw('COUNT(*) as n_conversations, MIN(created_at) as first_conv_at')
                ->first();

            $memory->n_conversations = (int) ($convStats->n_conversations ?? 0);

            // Stats msgs — JOIN conversations pra filtrar por external_id
            $msgStats = DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->where('c.business_id', $businessId)
                ->where(function ($q) use ($extId) {
                    $q->where('c.customer_external_id', $extId)
                      ->orWhere('c.customer_external_id', '+' . $extId);
                })
                ->selectRaw('
                    SUM(CASE WHEN m.direction = "inbound" THEN 1 ELSE 0 END) as n_in,
                    SUM(CASE WHEN m.direction = "outbound" THEN 1 ELSE 0 END) as n_out,
                    MIN(m.created_at) as first_msg_at,
                    MAX(m.created_at) as last_msg_at
                ')
                ->first();

            $memory->n_msgs_inbound = (int) ($msgStats->n_in ?? 0);
            $memory->n_msgs_outbound = (int) ($msgStats->n_out ?? 0);
            $memory->first_interaction_at = $msgStats->first_msg_at ?? $convStats->first_conv_at ?? null;
            $memory->last_interaction_at = $msgStats->last_msg_at ?? null;
        } catch (Throwable $e) {
            Log::channel('single')->warning('[customer_memory.stats_failed]', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
            // Fail-open: zeros mantidos
        }
    }

    /**
     * Step 3 — Denormaliza display_name + consent_status do Contact.
     * Sem JOIN posterior na sidebar.
     */
    protected function refreshDenormalized(CustomerMemory $memory): void
    {
        if ($memory->contact_id === null) {
            // Sem contact linkado — usa contact_name da última conversation se houver
            $convName = DB::table('conversations')
                ->where('business_id', $memory->business_id)
                ->where(function ($q) use ($memory) {
                    $q->where('customer_external_id', $memory->customer_external_id)
                      ->orWhere('customer_external_id', '+' . $memory->customer_external_id);
                })
                ->whereNotNull('contact_name')
                ->where('contact_name', '!=', '')
                ->orderByDesc('updated_at')
                ->value('contact_name');

            $memory->display_name = $convName ?: ('+' . $memory->customer_external_id);
            $memory->consent_status = CustomerMemory::CONSENT_UNKNOWN;
            return;
        }

        // Tem contact — busca name + whatsapp_consent
        try {
            $contact = Contact::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $memory->business_id)
                ->where('id', $memory->contact_id)
                ->first(['id', 'name', 'whatsapp_consent']);

            if ($contact === null) {
                $memory->display_name = '+' . $memory->customer_external_id;
                $memory->consent_status = CustomerMemory::CONSENT_UNKNOWN;
                return;
            }

            $memory->display_name = $contact->name ?: ('+' . $memory->customer_external_id);

            // whatsapp_consent é boolean/tinyint em UltimatePOS — mapeia pra string
            $memory->consent_status = match (true) {
                $contact->whatsapp_consent === 1 || $contact->whatsapp_consent === true => CustomerMemory::CONSENT_GIVEN,
                $contact->whatsapp_consent === 0 || $contact->whatsapp_consent === false => CustomerMemory::CONSENT_WITHDRAWN,
                default => CustomerMemory::CONSENT_UNKNOWN,
            };
        } catch (Throwable $e) {
            Log::channel('single')->warning('[customer_memory.denorm_failed]', [
                'business_id' => $memory->business_id,
                'contact_id' => $memory->contact_id,
                'error' => $e->getMessage(),
            ]);
            $memory->display_name = $memory->display_name ?: ('+' . $memory->customer_external_id);
        }
    }

    /**
     * Step 4 — Funcionário responsável (US-WA-VOZ-002).
     *
     * 2 derivações:
     *   - assigned_user_id    = sender_user_id da msg outbound MAIS RECENTE
     *   - most_active_user_id = sender_user_id com MAIS msgs outbound histórico
     *                            (GROUP BY ORDER BY DESC LIMIT 1)
     *
     * `sender_user_id` é NULL quando outbound veio do chip direto (Wagner manda
     * do celular) ou do bot Jana — esses casos não viram "atendente".
     */
    protected function refreshAssignedUser(CustomerMemory $memory, int $businessId, string $extId): void
    {
        try {
            // assigned = última outbound humana
            $assigned = DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->where('c.business_id', $businessId)
                ->where(function ($q) use ($extId) {
                    $q->where('c.customer_external_id', $extId)
                      ->orWhere('c.customer_external_id', '+' . $extId);
                })
                ->where('m.direction', 'outbound')
                ->whereNotNull('m.sender_user_id')
                ->orderByDesc('m.created_at')
                ->value('m.sender_user_id');

            $memory->assigned_user_id = $assigned ? (int) $assigned : null;

            // most_active = quem mais respondeu histórico
            $mostActive = DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->where('c.business_id', $businessId)
                ->where(function ($q) use ($extId) {
                    $q->where('c.customer_external_id', $extId)
                      ->orWhere('c.customer_external_id', '+' . $extId);
                })
                ->where('m.direction', 'outbound')
                ->whereNotNull('m.sender_user_id')
                ->select('m.sender_user_id', DB::raw('COUNT(*) as n'))
                ->groupBy('m.sender_user_id')
                ->orderByDesc('n')
                ->limit(1)
                ->first();

            if ($mostActive !== null) {
                $memory->most_active_user_id = (int) $mostActive->sender_user_id;
                $memory->most_active_user_count = (int) $mostActive->n;
            } else {
                $memory->most_active_user_id = null;
                $memory->most_active_user_count = null;
            }
        } catch (Throwable $e) {
            Log::channel('single')->warning('[customer_memory.assigned_user_failed]', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Step 5 — Reclamações heurística keywords (US-WA-VOZ-002).
     *
     * SEM IA — usa regex sobre body das msgs inbound últimas 30d.
     *
     * Keywords mapeadas pra `severity`:
     *   - critica: "processo", "advogado", "absurdo", "nunca mais", "cancelar", "reembolso"
     *   - alta:    "reclamar", "péssimo", "horrível", "demais", "insuportável"
     *   - media:   "problema", "erro", "bug", "atraso", "atrasado", "não consigo", "não funciona"
     *   - baixa:   "dúvida", "ajuda" (sinais de fricção sem reclamação explícita)
     *
     * Output JSON: top 5 mais recentes com severity descendente.
     *
     * Quando Onda 3 ativar IA per-msg (PR #916), este código será substituído
     * por leitura direta de `messages.analise_categoria = 'reclamacao'`.
     */
    protected function refreshReclamacoes(CustomerMemory $memory, int $businessId, string $extId): void
    {
        $patterns = [
            'critica' => '/(processo|advogado|absurdo|nunca\s*mais|cancelar|reembolso|procon)/i',
            'alta' => '/(reclamar|péssimo|pessimo|horrível|horrivel|insuportável|insuportavel|inadmissível|inadmissivel|esperando\s*até\s*agora)/i',
            'media' => '/(problema|erro|bug|atras|não\s*consig|nao\s*consig|não\s*funcion|nao\s*funcion|deu\s*ruim|travou)/i',
            'baixa' => '/(dúvida|duvida|ajuda|preciso)/i',
        ];

        $since = now()->subDays(30);

        try {
            $msgs = DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->where('c.business_id', $businessId)
                ->where(function ($q) use ($extId) {
                    $q->where('c.customer_external_id', $extId)
                      ->orWhere('c.customer_external_id', '+' . $extId);
                })
                ->where('m.direction', 'inbound')
                ->where('m.is_internal_note', false)
                ->where('m.type', 'text')
                ->whereNotNull('m.body')
                ->where('m.created_at', '>=', $since)
                ->orderByDesc('m.created_at')
                ->limit(500) // cap defensivo
                ->get(['m.id', 'm.body', 'm.created_at']);

            $flagged = [];
            foreach ($msgs as $m) {
                $body = (string) $m->body;
                $severity = null;
                foreach (['critica', 'alta', 'media', 'baixa'] as $sev) {
                    if (preg_match($patterns[$sev], $body)) {
                        $severity = $sev;
                        break; // first match wins (mais severo primeiro)
                    }
                }
                if ($severity === null) {
                    continue;
                }
                $flagged[] = [
                    'date' => (string) $m->created_at,
                    'msg_id' => (int) $m->id,
                    'severity' => $severity,
                    'preview' => mb_substr(preg_replace('/\s+/', ' ', $body), 0, 140),
                ];
            }

            $memory->total_reclamacoes = count($flagged);

            // Top 5 mais recentes (já ordenadas por created_at desc na query)
            $memory->reclamacoes_recentes = array_slice($flagged, 0, 5);
            if (empty($memory->reclamacoes_recentes)) {
                $memory->reclamacoes_recentes = null;
            }
        } catch (Throwable $e) {
            Log::channel('single')->warning('[customer_memory.reclamacoes_failed]', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Match exato = um dos campos phone do contact tem EXATAMENTE o E.164 sem +.
     */
    protected function isExactMatch(Contact $contact, string $extId): bool
    {
        foreach (['mobile', 'landline', 'alternate_number'] as $field) {
            $raw = preg_replace('/\D+/', '', (string) ($contact->{$field} ?? ''));
            if ($raw === $extId) {
                return true;
            }
        }
        return false;
    }
}
