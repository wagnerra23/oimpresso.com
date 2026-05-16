<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Contacts;

use Illuminate\Support\Facades\Cache;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\LidPhoneMap;

/**
 * Observabilidade D9.a (ADR 0155): cache lookup é sub-µs (Redis local),
 * Tracer via `OtelHelper::span(` reservado pra DB miss path quando útil.
 *
 * LidPhoneResolver — resolve / persiste o mapping LID ↔ phone E.164.
 *
 * Workaround pra limitação atual do Baileys 6.7.9 (ainda em prod CT 100):
 * quando cliente fala com a Business via Click-to-Chat / Status / Ads, o
 * `remoteJid` chega como `X@lid` (Linked ID — ID anti-spam Multi-Device),
 * mascarando o número real. WhatsApp ÀS VEZES envia `senderPn` no msg.key
 * com o phone real ao lado — quando isso acontece, gravamos o par aqui
 * pra resolver próximas msgs do mesmo LID.
 *
 * Migração pra Baileys 7.x trará Alt JID nativo (P0 #3 do top 20 gaps —
 * skill `baileys-update-procedure`). Quando lá, este Service vira fallback
 * apenas pra LIDs ainda não resolvidos pelo lib nova.
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *  - Sempre `business_id` explícito (caller webhook não tem session user).
 *  - UNIQUE (business_id, lid) na DB impede merge cross-tenant.
 *  - `withoutGlobalScope` em queries CLI (justificado por SUPERADMIN).
 *
 * Logs sem PII (LGPD): só `business_id` + `lid_hash_prefix` + `source`,
 * nunca o phone real (lid em si pode conter dígitos do phone, então
 * preferimos log curto sem o LID inteiro).
 *
 * @see Modules\Whatsapp\Entities\LidPhoneMap
 * @see Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController
 */
class LidPhoneResolver
{
    /**
     * Cache TTL pro mapping LID→phone (24h é razoável — phone troca raro).
     */
    private const CACHE_TTL_HOURS = 24;

    /**
     * Resolve phone E.164 a partir do LID já cacheado.
     *
     * Retorna null quando:
     *  - LID não encontrado no business.
     *  - LID encontrado mas phone_e164 ainda NULL (descoberta deferida).
     *
     * Caller (webhook) decide o fallback — atualmente usa o próprio LID
     * como customer_external_id, UI marca com badge "número oculto".
     *
     * P1 (#697) — Cache layer (TTL 24h) reduz 1 SELECT/msg `@lid` no webhook
     * pra 1 SELECT/24h por LID resolvido. Usa `Cache::remember` sem tags pra
     * funcionar com qualquer driver (default `file` em prod Hostinger não
     * suporta `Cache::tags`). Invalidação em `record()` quando phone muda.
     */
    public function resolve(int $businessId, string $lid): ?string
    {
        $normalized = $this->normalize($lid);
        if ($normalized === '') {
            return null;
        }

        $cacheKey = $this->cacheKey($businessId, $normalized);

        // remember() retorna o miss closure result; pra distinguir "LID não
        // existe" vs "LID existe com phone NULL" usamos sentinel '' (string
        // vazia) → cache miss vira NULL real. NULL não cacheia bem em alguns
        // drivers, sentinel '' resolve.
        $cached = Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_TTL_HOURS),
            function () use ($businessId, $normalized): string {
                // SUPERADMIN: ADR 0093 — webhook sem session user; scope manual
                $phone = LidPhoneMap::query()
                    ->withoutGlobalScope(ScopeByBusiness::class)
                    ->where('business_id', $businessId)
                    ->where('lid', $normalized)
                    ->value('phone_e164');

                return $phone ?? '';
            }
        );

        return $cached === '' ? null : $cached;
    }

    /**
     * Chave de cache canônica por business + LID normalizado.
     *
     * Formato: `whatsapp:lid:{businessId}:{lidDigits}` — namespace evita
     * colisão com outras keys; biz no path facilita debug + flush manual
     * (`php artisan cache:forget whatsapp:lid:1:5196915463394`).
     */
    private function cacheKey(int $businessId, string $normalizedLid): string
    {
        return "whatsapp:lid:{$businessId}:{$normalizedLid}";
    }

    /**
     * Persiste / atualiza mapping LID → phone (idempotente).
     *
     * Comportamento:
     *  - 1ª chamada com phone NULL → cria row com phone=NULL (rastreia LID visto)
     *  - Chamada com phone != NULL e row sem phone → preenche phone
     *  - Chamada com phone != NULL e row com phone diferente → UPDATE
     *    (assume última leitura é canônica; WA pode trocar mapeamento se
     *    cliente trocar chip)
     *  - Sempre: bump `last_seen_at`
     *
     * Sem PII em log — só ids e source.
     */
    public function record(
        int $businessId,
        string $lid,
        ?string $phone = null,
        string $source = LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN,
    ): ?LidPhoneMap {
        $normalizedLid = $this->normalize($lid);
        if ($normalizedLid === '') {
            return null;
        }

        $normalizedPhone = $phone !== null ? $this->normalizePhone($phone) : null;

        // INCIDENT 2026-05-14 P0-2: mapping `source=manual` ANTES aceitava
        // direto via UI/CLI/SSH sem evidência prévia de webhook real do
        // daemon — produziu 13 rows ad-hoc 14/mai 08:40 sem trail git (drift
        // Tier 0 proibido por Constituição). Defesa: `source=manual` SÓ é
        // permitido quando o LID já foi visto via webhook ao menos 1× (row
        // companion `source=webhook_senderPn`). Webhook real sempre precede
        // manual — quem cadastra manualmente está corrigindo um LID já
        // observado pelo sistema, nunca inventando um do nada.
        if ($source === LidPhoneMap::SOURCE_MANUAL && $normalizedPhone !== null) {
            $hasWebhookEvidence = LidPhoneMap::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('lid', $normalizedLid)
                ->where('source', LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN)
                ->exists();

            if (! $hasWebhookEvidence) {
                \Illuminate\Support\Facades\Log::warning('[whatsapp.lid_resolver.manual_rejected_no_webhook_evidence]', [
                    'business_id' => $businessId,
                    'lid_prefix' => substr($normalizedLid, 0, 6) . '...',
                ]);
                throw new \DomainException(
                    'LID manual requer webhook_senderPn prévio (anti-cross-contact incident 2026-05-14).'
                );
            }
        }

        // SUPERADMIN: ADR 0093 — webhook sem session user
        /** @var LidPhoneMap $row */
        $row = LidPhoneMap::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                [
                    'business_id' => $businessId,
                    'lid' => $normalizedLid,
                ],
                [
                    'phone_e164' => $normalizedPhone,
                    'source' => $source,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ]
            );

        $dirty = false;

        // Atualiza phone se descobriu (NULL → valor) OU mudou (valor antigo → novo).
        if ($normalizedPhone !== null && $row->phone_e164 !== $normalizedPhone) {
            $row->phone_e164 = $normalizedPhone;
            $row->source = $source;
            $dirty = true;
        }

        // Bump last_seen sempre que tem hit (até reentregas duplicadas
        // confirmam que LID continua ativo — útil pra TTL/cleanup futuro)
        $row->last_seen_at = now();
        $dirty = true;

        if ($dirty) {
            $row->save();
        }

        // Invalida cache pra próximo resolve() ver o phone descoberto/atualizado.
        // Mesmo quando $dirty=true só por last_seen_at bump, forget é barato
        // (1 op O(1)) e mantém o invariante: cache nunca fica stale.
        Cache::forget($this->cacheKey($businessId, $normalizedLid));

        return $row;
    }

    /**
     * Heurística: detecta se um JID/phone parece LID (não phone real).
     *
     * Critérios:
     *  - Sufixo `@lid` presente → LID confirmado.
     *  - Sem prefixo BR (55) e mais de 13 dígitos → suspeito LID.
     *  - String só dígitos (após strip `+`) com 14+ chars E não começa
     *    com DDI BR (55) → suspeito.
     *
     * Falso positivo aceitável: phones internacionais com 14+ dígitos
     * (raros em ROTA LIVRE biz=1; biz futuros internacionais reavaliar).
     */
    public function isLid(string $jid): bool
    {
        if ($jid === '') {
            return false;
        }

        if (str_contains($jid, '@lid')) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $jid) ?? '';

        // Phones BR válidos: 12-13 dígitos com DDI (55 + DDD 2 + número 8-9)
        // LIDs observados em prod: 15+ dígitos sem prefixo DDI conhecido.
        if (strlen($digits) >= 14 && ! str_starts_with($digits, '55')) {
            return true;
        }

        return false;
    }

    /**
     * Normaliza LID pra chave canônica de armazenamento.
     *
     * Aceita formatos:
     *  - "X@lid" (raw Baileys)
     *  - "+X" (já normalizado pelo controller pra customer_external_id)
     *  - "X" (só dígitos)
     *
     * Saída: sempre só dígitos (sem `+`, sem `@lid`). Empty string se input
     * inválido — caller deve early-return.
     */
    protected function normalize(string $lid): string
    {
        // Remove tudo após `@` (inclusive `@lid`, `@s.whatsapp.net`)
        $stripped = preg_replace('/@.+$/', '', $lid) ?? '';
        // Remove `+` e qualquer não-dígito
        $digits = preg_replace('/\D+/', '', $stripped) ?? '';

        return $digits;
    }

    /**
     * Normaliza phone pra formato canônico E.164 com `+`.
     */
    protected function normalizePhone(string $phone): string
    {
        $stripped = preg_replace('/@.+$/', '', $phone) ?? '';
        $digits = preg_replace('/\D+/', '', $stripped) ?? '';

        if ($digits === '') {
            return $phone; // não conseguiu normalizar; preserva input
        }

        return '+' . $digits;
    }
}
