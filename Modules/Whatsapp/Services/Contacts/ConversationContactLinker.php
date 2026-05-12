<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Contacts;

use App\Contact;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;

/**
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
        // Tail mais curto (5 últimos dígitos) usado APENAS no SQL pre-fetch
        // pra capturar formatos legados com separadores entre dígitos —
        // ex: "(48) 99872-2822" tem `2822` literal mas não `99872822`
        // sem-separador. Filtragem fina rola em PHP depois (normaliza)
        // então o pre-fetch pode ser fuzzy sem custo de correção.
        $tail = mb_substr($phoneDigits, -5);

        // Pre-fetch — pega candidatos. Como Contact UltimatePOS legacy
        // pode armazenar phone com separadores ("(48) 99872-2822"), LIKE
        // direto contra dígitos contínuos não pega. Fazemos um LIKE bem
        // generoso (qualquer um dos últimos 4 dígitos OU phoneDigits inteiro)
        // pra capturar todos os candidatos plausíveis. PHP filter remove os
        // falsos positivos normalizando os formatos.
        //
        // Limit 200 — em business com 10k+ contacts e LIKE matching 4 últimos
        // dígitos (≈10k/10000=1 hit por dígito), volume médio fica baixo.
        // Wagner 2026-05-12 prod biz=1 tem ~80 contacts → custo trivial.
        $tail4 = mb_substr($phoneDigits, -4);
        $candidates = Contact::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $conversation->business_id)
            ->where(function ($q) use ($phoneDigits, $tail4) {
                // Match direto E.164 vs E.164 (caso bonito)
                $q->where('mobile', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('landline', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('alternate_number', 'LIKE', '%' . $phoneDigits . '%')
                  // Match fuzzy 4 últimos dígitos pra pegar formatos com
                  // separadores (PHP filter elimina falsos positivos)
                  ->orWhere('mobile', 'LIKE', '%' . $tail4)
                  ->orWhere('landline', 'LIKE', '%' . $tail4)
                  ->orWhere('alternate_number', 'LIKE', '%' . $tail4);
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
