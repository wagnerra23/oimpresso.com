<?php

namespace Modules\Copiloto\Services\Channels;

use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Support\Channels\IncomingMessage;

/**
 * ChannelIdentityResolver — wire (channel, wire_id) → (business_id, user_id).
 *
 * GUARDRAIL multi-tenant (skill `multi-tenant-patterns`): TODA mensagem
 * inbound passa por aqui ANTES de chegar no ChatService. Sem identity
 * resolvida = mensagem rejeitada (não cria contexto, não invoca tools).
 *
 * Fluxo de identity:
 *  1. wire_id já existe e `opted_in_at` preenchido → conversa livre.
 *  2. wire_id existe mas `opted_in_at` NULL → fluxo opt-in (responder
 *     mensagem de consentimento; só marca `opted_in_at` quando user
 *     responder "ACEITO" / "OK" / "SIM").
 *  3. wire_id novo → onboarding manual no admin (Wagner mapeia ao business)
 *     OU futuramente auto-link via match de telefone em `users` /
 *     `contacts`. Fase 0: manual.
 *  4. `revoked_at` preenchido → silêncio (LGPD opt-out respeitado).
 */
class ChannelIdentityResolver
{
    /**
     * @return array{business_id:int,user_id:int,opted_in:bool,revoked:bool}|null
     *         null = wire_id desconhecido (precisa onboarding) ou revogado.
     */
    public function resolve(IncomingMessage $message): ?array
    {
        $row = DB::table('copiloto_channel_identity')
            ->where('channel', $message->channel)
            ->where('wire_id', $message->wireId)
            ->first();

        if ($row === null) {
            return null;
        }

        if ($row->revoked_at !== null) {
            return null;
        }

        DB::table('copiloto_channel_identity')
            ->where('id', $row->id)
            ->update(['last_seen_at' => now()]);

        return [
            'business_id' => (int) $row->business_id,
            'user_id'     => (int) $row->user_id,
            'opted_in'    => $row->opted_in_at !== null,
            'revoked'     => false,
        ];
    }

    /**
     * Marca opt-in confirmado pelo usuário (resposta "ACEITO"/"OK" ao primeiro
     * prompt de consentimento). Idempotente.
     */
    public function markOptIn(string $channel, string $wireId): void
    {
        DB::table('copiloto_channel_identity')
            ->where('channel', $channel)
            ->where('wire_id', $wireId)
            ->whereNull('opted_in_at')
            ->update(['opted_in_at' => now()]);
    }

    /**
     * Marca revogação (mensagem "SAIR" ou request admin). LGPD Art. 18.
     * Hard delete cascata é responsabilidade de processo admin, não daqui.
     */
    public function revoke(string $channel, string $wireId): void
    {
        DB::table('copiloto_channel_identity')
            ->where('channel', $channel)
            ->where('wire_id', $wireId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
