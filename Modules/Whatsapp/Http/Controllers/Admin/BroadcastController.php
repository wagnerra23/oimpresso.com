<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\WhatsappBroadcast;

/**
 * BroadcastController — broadcast cross-canal FASE 1 (US-WA-306 · ADR 0268).
 *
 * Pre-flight REAL (audiência com opt-in LGPD + janela 24h Meta) + draft
 * auditável. Fase 2 (US-WA-323, destravada por [W] em 2026-10-26): `store` com
 * `disparar=true` agenda o `DispararBroadcastJob` (US-WA-322) e `cancelar` tira a
 * campanha do ar. Nenhum endpoint aqui toca o driver: o envio é todo em fila.
 *
 * Permission: `whatsapp.send` (operacional) + ACL canal (US-WA-069).
 * Tier 0 ADR 0093: where business_id explícito em toda query.
 */
class BroadcastController extends Controller
{
    /**
     * POST /atendimento/broadcast/preflight — calcula audiência da campanha.
     *
     * Elegibilidade (sobre conversas EXISTENTES do canal — broadcast fase 1
     * não prospecta números frios):
     *   - conversa não-bloqueada do canal escolhido
     *   - `with_opt_in`: Contact CRM vinculado COM `whatsapp_opt_in_at` (LGPD)
     *     → SÓ estes entram em `recipient_conversation_ids`
     *   - `in_window`: last_inbound_at ≥ now-24h (freeform permitido)
     *   - `hsm_only`: fora da janela → exigirá template HSM APPROVED (fase 2)
     */
    public function preflight(Request $request): JsonResponse
    {
        [$businessId, , $channel] = $this->resolveChannelOrAbort($request);

        $base = Conversation::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channel->id)
            ->where('is_blocked', false);

        // US-CRM-098 — público por segmento RFM (opcional): todos os números contam só clientes do segmento, pelo contrato do Crm.
        $segmento = $request->input('segmento_rfm');
        if (is_string($segmento) && $segmento !== '') {
            $rfm = app()->bound(\App\Contracts\Crm\SegmentacaoRfm::class) ? app(\App\Contracts\Crm\SegmentacaoRfm::class) : null;
            if ($rfm === null || ! array_key_exists($segmento, $rfm->segmentos())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['segmento_rfm' => 'Segmento de clientes desconhecido.']);
            }
            $base->whereIn('contact_id', $rfm->contatosDoSegmento($businessId, $segmento) ?: [0]);
        }

        $total = (clone $base)->count();

        $optIn = (clone $base)
            ->whereNotNull('contact_id')
            ->whereExists(function ($q) use ($businessId) {
                $q->selectRaw('1')
                    ->from('contacts')
                    ->whereColumn('contacts.id', 'conversations.contact_id')
                    ->where('contacts.business_id', $businessId)
                    ->whereNotNull('contacts.whatsapp_opt_in_at');
            });

        $withOptIn = (clone $optIn)->count();
        $inWindow = (clone $optIn)->where('last_inbound_at', '>=', now()->subHours(24))->count();
        $recipientIds = (clone $optIn)->pluck('id')->all();

        return response()->json([
            'total' => $total,
            'with_opt_in' => $withOptIn,
            'without_opt_in' => $total - $withOptIn,
            'in_window' => $inWindow,
            'hsm_only' => $withOptIn - $inWindow,
            'recipient_conversation_ids' => $recipientIds,
            'segmento_rfm' => is_string($segmento) && $segmento !== '' ? $segmento : null,
        ]);
    }

    /**
     * POST /atendimento/broadcast — salva DRAFT auditável (fase 1).
     *
     * Snapshot da audiência é recalculado server-side no save (não confia no
     * que o frontend mostrou) — draft congela quem estaria na lista.
     */
    public function store(Request $request): RedirectResponse
    {
        [$businessId, $userId, $channel] = $this->resolveChannelOrAbort($request);

        $data = $request->validate([
            'kind' => ['required', Rule::in(WhatsappBroadcast::KINDS)],
            'template_name' => ['required_if:kind,template', 'nullable', 'string', 'max:64'],
            'body' => ['required_if:kind,freeform', 'nullable', 'string', 'max:4096'],
            'disparar' => ['nullable', 'boolean'],
        ]);
        $disparar = (bool) ($data['disparar'] ?? false);

        // US-WA-323 — "Salvar e disparar": só com a flag do disparo real, só texto livre e com o canal
        // livre (uma campanha por vez por canal, para o limite diário anti-ban valer de verdade).
        if ($disparar) {
            if (! config('whatsapp.broadcast.disparo_enabled', false)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['disparar' => 'O disparo de campanhas não está ligado nesta empresa.']);
            }
            if ($data['kind'] !== 'freeform') {
                throw \Illuminate\Validation\ValidationException::withMessages(['disparar' => 'Por enquanto só campanha de texto livre pode ser disparada.']);
            }
            $emAndamento = WhatsappBroadcast::query()
                ->where('business_id', $businessId)
                ->where('channel_id', $channel->id)
                ->where('status', 'dispatching')
                ->exists();
            if ($emAndamento) {
                throw \Illuminate\Validation\ValidationException::withMessages(['disparar' => 'Já há uma campanha em disparo neste canal. Espere terminar ou cancele.']);
            }
        }

        // Recalcula audiência server-side (mesma lógica do preflight)
        $preflight = $this->preflight($request)->getData(true);

        if (($preflight['with_opt_in'] ?? 0) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'audience' => 'Nenhum contato com opt-in LGPD neste canal — broadcast sem consentimento é proibido (ADR 0268).',
            ]);
        }

        $broadcast = WhatsappBroadcast::query()->create([
            'business_id' => $businessId,
            'channel_id' => $channel->id,
            'created_by_user_id' => $userId,
            'kind' => $data['kind'],
            'template_name' => $data['template_name'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => 'draft',
            'audience_snapshot' => collect($preflight)->except('recipient_conversation_ids')->all(),
            'recipient_conversation_ids' => $preflight['recipient_conversation_ids'] ?? [],
        ]);

        if ($disparar) {
            \Modules\Whatsapp\Jobs\DispararBroadcastJob::dispatch($businessId, (int) $broadcast->id);

            return back()->with('success', 'Campanha agendada: os envios saem aos poucos, em horário comercial.');
        }

        return back()->with('success', 'Rascunho de campanha salvo.');
    }

    /**
     * POST /atendimento/broadcast/{id}/cancelar — US-WA-323.
     *
     * Tira a campanha de `dispatching`; cada envio agendado confere o status antes de sair e não
     * sai mais. O que já foi enviado continua contado. Só quem tem acesso ao canal da campanha.
     */
    public function cancelar(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $broadcast = WhatsappBroadcast::query()->where('business_id', $businessId)->where('id', $id)->first();
        if ($broadcast === null) {
            abort(404);
        }
        $request->merge(['channel_id' => $broadcast->channel_id]);
        $this->resolveChannelOrAbort($request);

        $mudou = WhatsappBroadcast::query()
            ->where('business_id', $businessId)->where('id', $id)->where('status', 'dispatching')
            ->update(['status' => 'cancelled', 'concluido_em' => now()]);

        return $mudou > 0
            ? back()->with('success', 'Campanha cancelada: os envios que faltavam não saem.')
            : back()->withErrors(['cancelar' => 'Esta campanha não está em disparo.']);
    }

    /**
     * Canal: do business + ATIVO + ACL do user (fail-loud).
     *
     * @return array{0: int, 1: int, 2: Channel}
     */
    protected function resolveChannelOrAbort(Request $request): array
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $request->validate(['channel_id' => ['required', 'integer']]);
        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->where('id', (int) $request->input('channel_id'))
            ->first();
        if (! $channel) {
            abort(403, 'Canal não encontrado ou sem acesso.');
        }
        if ($channel->status !== 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'channel_id' => "Canal \"{$channel->label}\" não está ativo.",
            ]);
        }
        $canSeeAll = (bool) (auth()->user()?->can('whatsapp.view-all-phones') ?? false);
        if (! $canSeeAll) {
            $hasAccess = ChannelUserAccess::query()
                ->where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('channel_id', $channel->id)
                ->whereNull('revoked_at')
                ->exists();
            if (! $hasAccess) {
                abort(403, 'Sem acesso a este canal.');
            }
        }

        return [$businessId, $userId, $channel];
    }
}
