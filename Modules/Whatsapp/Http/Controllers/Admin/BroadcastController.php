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
 * auditável. DISPARO É FASE 2 (Job rate-limited com gate [W]) — nenhum
 * endpoint aqui toca driver (anti M-AP-2: sem fingir envio em massa).
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
        ]);

        // Recalcula audiência server-side (mesma lógica do preflight)
        $preflight = $this->preflight($request)->getData(true);

        if (($preflight['with_opt_in'] ?? 0) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'audience' => 'Nenhum contato com opt-in LGPD neste canal — broadcast sem consentimento é proibido (ADR 0268).',
            ]);
        }

        WhatsappBroadcast::query()->create([
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

        return back()->with('success', 'Rascunho de broadcast salvo — disparo em massa é a fase 2 (ADR 0268).');
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
