<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Contacts;

use App\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;

/**
 * Observabilidade D9.a (ADR 0155): `tryLink()` invocado em hot path do
 * webhook — Tracer via `OtelHelper::span(` mede latência por business.
 *
 * Auto-link Conversation → Contact CRM (UltimatePOS) por phone normalizado.
 *
 * Usado em 2 contextos (US-WA-078):
 *   1. Webhook inbound — quando Conversation é criada/atualizada,
 *      `ChannelBaileysWebhookController::handleMessage` chama `tryLink()`
 *      pra vincular automaticamente se Contact CRM já existe com o mesmo
 *      phone.
 *   2. Backfill CLI — `whatsapp:auto-link-contacts` itera convs com
 *      `contact_id=null` aplicando a MESMA heurística. Garante consistência
 *      entre prod-runtime e batch-job.
 *
 * Heurística:
 *  - Extrai dígitos do `customer_external_id` (E.164 sem '+').
 *  - Mínimo 8 dígitos pra evitar falso-positivo ("99872" matching anything).
 *  - Match LIKE %phone% contra `mobile`/`landline`/`alternate_number`. Wagner
 *    decisão 2026-05-12: prefere fuzzy LIKE em vez de exact match pq Contact
 *    UltimatePOS legacy tem phones formatados como "(48) 99872-822" enquanto
 *    o E.164 vem como "+554899872822". Trade-off: false-positive em phones
 *    muito curtos (mitigado pelo >=8 dígitos).
 *  - `business_id` scope explícito (Tier 0 ADR 0093) — `withoutGlobalScope`
 *    pra rodar de webhook (sem session user) e CLI (sem auth) sem misturar
 *    business_id de outro tenant.
 *  - Ambiguidade (>=2 Contacts match): linka o primeiro (ordem id ASC) e
 *    loga warning. Atendente pode trocar via modal US-WA-064 se errado.
 *
 * Logs sem PII (ADR 0093 §LGPD): só ids numéricos (conversation_id +
 * contact_id + match count). Nunca o phone real.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-078
 */
class ConversationContactLinker
{
    /**
     * Tamanho mínimo de dígitos do phone pra rodar o LIKE.
     *
     * Phones BR válidos têm >= 8 dígitos (fixo regional 8 dígitos sem DDD)
     * ou 10-11 dígitos com DDD ou 13 com país. Abaixo de 8 só é falso
     * positivo garantido.
     */
    public const MIN_PHONE_DIGITS = 8;

    /**
     * TTL do cache do `attemptLink(biz, phone)`. 1h é suficiente pra cobrir
     * burst de mensagens consecutivas do mesmo contato sem reconsultar DB,
     * e curto o bastante pra refletir Contact recém-cadastrado pela UI.
     */
    public const ATTEMPT_LINK_CACHE_TTL = 3600;

    /**
     * Tenta resolver um Contact CRM (id) pra um phone E.164 cru — variante
     * stateless do `tryLink()` (não recebe Conversation, não persiste).
     *
     * Uso canônico (US-WA-078 PR-5):
     *  - Pipelines que recebem só `(business_id, phone)` sem ter Conversation
     *    instanciada (jobs assíncronos, integrações externas, smoke probes).
     *  - Resolução cacheada quando o caller repete a query várias vezes em
     *    janela curta (UI listagem, dashboard de inadimplência, etc).
     *
     * Cache `auto_link:{biz}:{phoneDigits}` por 1h (`ATTEMPT_LINK_CACHE_TTL`):
     *  - HIT → retorna contact_id (ou null marker) sem tocar DB.
     *  - MISS → roda findMatchesForPhone() e grava resultado (incluindo null
     *    pra evitar restampede em phone sem match).
     *
     * Devolve int (contact_id) OU null se nenhum match / phone curto demais.
     *
     * @see findMatches()  variante stateful (recebe Conversation)
     */
    public function attemptLink(int $businessId, string $customerPhone): ?int
    {
        $phoneDigits = $this->normalizePhone($customerPhone);

        if (mb_strlen($phoneDigits) < self::MIN_PHONE_DIGITS) {
            return null;
        }

        $cacheKey = sprintf('whatsapp.auto_link:%d:%s', $businessId, $phoneDigits);

        // `Cache::remember` aceita closures retornando null — porém alguns
        // drivers (Database/Redis) gravam null como "miss" e re-disparam a
        // closure no próximo hit. Pra cachear miss explícito (anti-stampede
        // em phone sem match), usamos sentinel int 0 e mapeamos pra null
        // na leitura.
        $cached = Cache::remember(
            $cacheKey,
            self::ATTEMPT_LINK_CACHE_TTL,
            function () use ($businessId, $phoneDigits) {
                $matches = $this->findMatchesForPhone($businessId, $phoneDigits);

                if ($matches->isEmpty()) {
                    return 0; // sentinel "no match" — distinguir miss real
                }

                $picked = $matches->first();

                if ($matches->count() > 1) {
                    Log::warning('[whatsapp.auto_link_contact.ambiguous]', [
                        'business_id' => $businessId,
                        'picked_contact_id' => $picked->id,
                        'match_count' => $matches->count(),
                        'via' => 'attemptLink',
                    ]);
                }

                return (int) $picked->id;
            }
        );

        return $cached === 0 ? null : (int) $cached;
    }

    /**
     * Limpa cache do `attemptLink()` pra `(biz, phone)` específico.
     *
     * Usado quando Contact é criado/editado via UI — o caller dispara para
     * invalidar entradas stale antes do TTL expirar (background sync admin).
     */
    public function forgetAttemptLinkCache(int $businessId, string $customerPhone): void
    {
        $phoneDigits = $this->normalizePhone($customerPhone);
        if ($phoneDigits === '') {
            return;
        }

        Cache::forget(sprintf('whatsapp.auto_link:%d:%s', $businessId, $phoneDigits));
    }

    /**
     * Normaliza phone E.164/BR pra string só com dígitos (sem '+').
     *
     * Heurística simples — regex strip. `libphonenumber-for-php` não está
     * em `composer.json`; adicionar dependência só pra strip de não-dígitos
     * seria sobre-engenharia. O domínio BR é fechado: Baileys envia E.164
     * crú, webhook Z-API/Meta idem, Contact UltimatePOS aceita formato livre
     * (filtrado depois). Caso futuro precise validar país/DDD, plug aqui.
     */
    private function normalizePhone(string $raw): string
    {
        return (string) preg_replace('/\D+/', '', $raw);
    }

    /**
     * Variante stateless do findMatches — recebe `(biz, phoneDigits)` em vez
     * de Conversation. Compartilha mesma estratégia LIKE+PHP-filter.
     *
     * @return \Illuminate\Support\Collection<int, Contact>
     */
    public function findMatchesForPhone(int $businessId, string $phoneDigits): \Illuminate\Support\Collection
    {
        if (mb_strlen($phoneDigits) < self::MIN_PHONE_DIGITS) {
            return collect();
        }

        // INCIDENT 2026-05-14: suffix 8 (não tail4) — ver findMatches() pra histórico.
        $suffix = mb_substr($phoneDigits, -8);

        $candidates = Contact::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where(function ($q) use ($phoneDigits, $suffix) {
                $q->where('mobile', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('landline', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('alternate_number', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('mobile', 'LIKE', '%' . $suffix . '%')
                  ->orWhere('landline', 'LIKE', '%' . $suffix . '%')
                  ->orWhere('alternate_number', 'LIKE', '%' . $suffix . '%');
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc') // Wagner regra: ambíguo → mais recente
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get(['id', 'name', 'business_id', 'mobile', 'landline', 'alternate_number', 'created_at']);

        return $candidates->filter(function (Contact $c) use ($phoneDigits, $suffix) {
            foreach (['mobile', 'landline', 'alternate_number'] as $field) {
                $raw = (string) ($c->{$field} ?? '');
                if ($raw === '') {
                    continue;
                }
                $clean = preg_replace('/\D+/', '', $raw);
                if (! is_string($clean) || mb_strlen($clean) < self::MIN_PHONE_DIGITS) {
                    continue;
                }
                if (str_contains($clean, $phoneDigits) || str_contains($clean, $suffix)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Tenta vincular um Contact CRM à Conversation.
     *
     * Retorna o Contact linkado OU null se nenhum match / phone curto demais.
     *
     * Mutação:
     *  - `$conversation->contact_id` setado quando match.
     *  - `$conversation->contact_name` atualizado APENAS se atualmente vazio
     *    ou ainda igual ao customer_external_id raw (não sobrescreve nome
     *    curado pelo atendente).
     *  - `save()` chamado SÓ se algo mudou — evita touch desnecessário do
     *    `updated_at`.
     */
    public function tryLink(Conversation $conversation): ?Contact
    {
        // Já linkado — nada a fazer. Caller (webhook) só deveria chamar se
        // contact_id=null OU recém-criada, mas defense-in-depth aqui também.
        if ($conversation->contact_id !== null) {
            return null;
        }

        $matches = $this->findMatches($conversation);
        if ($matches->isEmpty()) {
            return null;
        }

        $customerExternalId = (string) $conversation->customer_external_id;

        /** @var Contact $contact */
        $contact = $matches->first();

        if ($matches->count() > 1) {
            // Ambíguo — linka o primeiro mas avisa pra atendente revisar.
            // Sem PII: só ids + count.
            Log::warning('[whatsapp.auto_link_contact.ambiguous]', [
                'conversation_id' => $conversation->id,
                'business_id' => $conversation->business_id,
                'picked_contact_id' => $contact->id,
                'match_count' => $matches->count(),
            ]);
        }

        $changed = false;

        $conversation->contact_id = $contact->id;
        $changed = true;

        // Curado pelo atendente? Não toca. Caso comum onde push_name veio
        // mas atendente vai customizar nome → respeita.
        $rawName = $conversation->contact_name ?? null;
        if (empty($rawName) || $rawName === $customerExternalId) {
            $conversation->contact_name = $contact->name;
        }

        if ($changed) {
            $conversation->save();
            Log::info('[whatsapp.auto_link_contact.linked]', [
                'conversation_id' => $conversation->id,
                'business_id' => $conversation->business_id,
                'contact_id' => $contact->id,
            ]);
        }

        return $contact;
    }

    /**
     * Encontra Contacts CRM do business que batem com o phone da conv.
     *
     * Retorna Collection<Contact> filtrada (após normalização em PHP). Vazia
     * se phone curto demais, nenhum match, ou business sem Contacts.
     *
     * Reusado pelo `AutoLinkConversationContactsCommand` no caminho dry-run
     * pra contagem fiel sem persistir.
     *
     * @return \Illuminate\Support\Collection<int, Contact>
     */
    public function findMatches(Conversation $conversation): \Illuminate\Support\Collection
    {
        $phone = preg_replace('/^\+/', '', (string) $conversation->customer_external_id);
        $phoneDigits = preg_replace('/\D+/', '', (string) $phone);

        if (mb_strlen((string) $phoneDigits) < self::MIN_PHONE_DIGITS) {
            return collect();
        }

        // Suffix dos últimos 8 dígitos pra match BR ("48999872822") vs E.164
        // ("+5548999872822"). DDD+phone é único o suficiente no BR.
        $suffix = mb_substr($phoneDigits, -8);

        // INCIDENT 2026-05-14 (Wagner→Eliana cross-contact): pre-fetch antes
        // usava `tail4` (últimos 4 dígitos) — 4 dígitos coincidem por puro
        // acaso a cada ~10k phones, produzindo falso-positivo matemático.
        // Trocamos pelo suffix de 8 dígitos: probabilidade de colisão cai
        // pra ~10^-8. Phones BR sem separadores ("+5548999872822") matcham
        // direto; com separadores ("(48) 9 9987-2822" cujos 8 últimos dígitos
        // após strip = "99872822") matcham pelo suffix do prefixo aberto.
        // Twilio Identity Resolution (docs.twilio.com/conversations/memory)
        // e HubSpot phone normalization recomendam exact-or-suffix-mínimo,
        // nunca fuzzy abaixo de E.164 - DDI.
        $candidates = Contact::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $conversation->business_id)
            ->where(function ($q) use ($phoneDigits, $suffix) {
                // Match direto E.164 vs E.164 (caso bonito)
                $q->where('mobile', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('landline', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('alternate_number', 'LIKE', '%' . $phoneDigits . '%')
                  // Suffix 8 dígitos cobre formato legacy com separadores
                  // (após PHP filter normalizar — linhas 335-351 abaixo).
                  ->orWhere('mobile', 'LIKE', '%' . $suffix . '%')
                  ->orWhere('landline', 'LIKE', '%' . $suffix . '%')
                  ->orWhere('alternate_number', 'LIKE', '%' . $suffix . '%');
            })
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'name', 'business_id', 'mobile', 'landline', 'alternate_number']);

        // Refina — strip não-dígito de cada campo e compara contra phoneDigits.
        // Cobre formato legacy UltimatePOS "(48) 99872-2822" que LIKE direto
        // não pega pelos separadores.
        return $candidates->filter(function (Contact $c) use ($phoneDigits, $suffix) {
            foreach (['mobile', 'landline', 'alternate_number'] as $field) {
                $raw = (string) ($c->{$field} ?? '');
                if ($raw === '') {
                    continue;
                }
                $clean = preg_replace('/\D+/', '', $raw);
                if (! is_string($clean) || mb_strlen($clean) < self::MIN_PHONE_DIGITS) {
                    continue;
                }
                if (str_contains($clean, $phoneDigits) || str_contains($clean, $suffix)) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
