<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Whatsapp\Entities\ClientFeedback;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\FeedbackRelevanceService;

/**
 * ClientFeedbackController — captura + listagem + status update de feedback canon.
 *
 * Refs: ADR UI-0016, ADR 0093, ADR 0105, ADR 0135 (omnichannel).
 *
 * Endpoints:
 *   POST   /atendimento/feedback/capture          → captura novo feedback
 *   GET    /atendimento/feedback                  → lista (filtros via query)
 *   PATCH  /atendimento/feedback/{id}/status      → transita status
 *   GET    /atendimento/feedback/persona-resolve  → auto-detect persona via phone
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): global scope `business_id` no Model
 * + auth user.business_id session injection no create.
 */
class ClientFeedbackController extends Controller
{
    /**
     * POST /atendimento/feedback/capture
     *
     * Captura feedback estruturado a partir do inbox WhatsApp.
     */
    public function capture(Request $request): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $userId = (int) $request->session()->get('user.id');

        $validator = Validator::make($request->all(), [
            'contact_id' => ['nullable', 'integer'],
            'source_message_id' => ['nullable', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'persona_slug' => ['nullable', 'string', 'max:80'],
            'cliente_slug' => ['nullable', 'string', 'max:80'],
            'canal' => ['nullable', 'string', 'max:32'],
            'literal' => ['required', 'string'],
            'contexto' => ['nullable', 'string'],
            'modulo_afetado' => ['nullable', 'string', 'max:80'],
            'tela_afetada' => ['nullable', 'string', 'max:160'],
            'acao_afetada' => ['nullable', 'string', 'max:80'],
            'job' => ['nullable', 'string', 'max:255'],
            'motivacao_tipo' => ['nullable', 'string', 'in:funcional,emocional,social'],
            'workaround_o_que_faz' => ['nullable', 'string', 'max:255'],
            'workaround_custo' => ['nullable', 'string', 'max:255'],
            'severity_nng' => ['required', 'integer', 'min:0', 'max:4'],
            'primeira_vez' => ['nullable', 'boolean'],
            'create_dev_task' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $createDevTaskRequested = (bool) ($data['create_dev_task'] ?? false);
        unset($data['create_dev_task']);

        $data['business_id'] = $businessId;       // Tier 0 — server-side ALWAYS
        $data['created_by'] = $userId;
        $data['canal'] = $data['canal'] ?? 'whatsapp';
        $data['status'] = ClientFeedback::STATUS_NOVO;
        $data['primeira_vez'] = $data['primeira_vez'] ?? true;
        $data['dev_task_requested'] = false; // será reavaliado server-side abaixo

        // Validação cross-tenant: conversation_id pertence ao business
        if (! empty($data['conversation_id'])) {
            $conv = Conversation::where('id', $data['conversation_id'])->first();
            if (! $conv) {
                return response()->json(['errors' => ['conversation_id' => 'Conversa não encontrada neste business.']], 404);
            }
        }

        // Dedup por signature (últimos 90d): mesma persona + módulo + ação + literal
        // normalizada → bump recorrente_count em vez de criar duplicata.
        $relevance = app(FeedbackRelevanceService::class);
        $tempFb = new ClientFeedback($data);
        $tempFb->business_id = $businessId;
        $signature = $relevance->computeSignature($tempFb);
        $existing = $relevance->findDuplicateWithin90d($signature, $businessId);

        $isDedup = false;
        if ($existing) {
            $existing->recorrente_count = ($existing->recorrente_count ?? 1) + 1;
            $existing->pattern_emergente = $existing->recorrente_count >= 3;
            $existing->severity_nng = max($existing->severity_nng, (int) $data['severity_nng']);
            $existing->last_seen_at = now();
            $existing->save();   // Observer rescore automaticamente
            $feedback = $existing;
            $isDedup = true;

            Log::info('[client-feedback.capture] dedup hit', [
                'feedback_id' => $feedback->id,
                'business_id' => $businessId,
                'signature' => $signature,
                'recorrente_count' => $feedback->recorrente_count,
                'pattern_emergente' => $feedback->pattern_emergente,
            ]);
        } else {
            $feedback = ClientFeedback::create($data);
        }

        // Severity ≥ 3 → MCP task (via Observer/event ou inline).
        // MVP: log apenas. Job assíncrono em PR follow-up.
        if ($feedback->shouldHaveMcpTask()) {
            Log::info('[client-feedback.capture] severity ≥ 3 — criar MCP task pendente', [
                'feedback_id' => $feedback->id,
                'business_id' => $businessId,
                'severity' => $feedback->severity_nng,
                'persona_slug' => $feedback->persona_slug,
            ]);
        }

        // Dev task request — ADR 0105 guard rails (server-side é a verdade)
        $devTaskInfo = ['requested' => false, 'reason' => null, 'message' => null];
        if ($createDevTaskRequested) {
            $qual = $feedback->qualifiesForDevTask();
            if ($qual['ok']) {
                $feedback->update(['dev_task_requested' => true]);
                $devTaskInfo = [
                    'requested' => true,
                    'reason' => null,
                    'message' => 'Chamado registrado. Wagner triagem cria task MCP em até 24h.',
                ];
                Log::info('[client-feedback.capture] dev_task_requested', [
                    'feedback_id' => $feedback->id,
                    'business_id' => $businessId,
                    'contact_id' => $feedback->contact_id,
                    'severity' => $feedback->severity_nng,
                ]);
            } else {
                $devTaskInfo = [
                    'requested' => false,
                    'reason' => $qual['reason'],
                    'message' => $qual['message'],
                ];
                Log::info('[client-feedback.capture] dev_task rejected by guard rail', [
                    'feedback_id' => $feedback->id,
                    'business_id' => $businessId,
                    'reason' => $qual['reason'],
                ]);
            }
        }

        Log::info('[client-feedback.capture] novo feedback', [
            'feedback_id' => $feedback->id,
            'business_id' => $businessId,
            'persona' => $feedback->persona_slug,
            'severity' => $feedback->severity_nng,
        ]);

        return response()->json([
            'success' => true,
            'feedback' => [
                'id' => $feedback->id,
                'severity_label' => $feedback->severity_label,
                'status' => $feedback->status,
                'mcp_task_pending' => $feedback->shouldHaveMcpTask(),
                'dev_task_requested' => $feedback->dev_task_requested,
                'signature' => $feedback->signature,
                'recorrente_count' => $feedback->recorrente_count,
                'pattern_emergente' => $feedback->pattern_emergente,
                'relevance_score' => (float) $feedback->relevance_score,
            ],
            'dev_task' => $devTaskInfo,
            'dedup' => [
                'matched_existing' => $isDedup,
                'recorrente_count' => $feedback->recorrente_count,
            ],
            'message' => $isDedup
                ? "Mesma reclamação já registrada — agora com {$feedback->recorrente_count} ocorrência(s)."
                : 'Feedback capturado com sucesso.',
        ], 201);
    }

    /**
     * GET /atendimento/feedback
     *
     * Lista feedbacks com filtros básicos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClientFeedback::query();    // HasBusinessScope filtra Tier 0

        if ($persona = $request->query('persona_slug')) {
            $query->where('persona_slug', $persona);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($severity = $request->query('severity_min')) {
            $query->where('severity_nng', '>=', (int) $severity);
        }
        if ($contactId = $request->query('contact_id')) {
            $query->where('contact_id', (int) $contactId);
        }

        $feedbacks = $query
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $feedbacks->map(fn ($f) => [
                'id' => $f->id,
                'persona_slug' => $f->persona_slug,
                'cliente_slug' => $f->cliente_slug,
                'literal' => $f->literal,
                'severity_nng' => $f->severity_nng,
                'severity_label' => $f->severity_label,
                'status' => $f->status,
                'modulo_afetado' => $f->modulo_afetado,
                'job' => $f->job,
                'created_at' => optional($f->created_at)->toIso8601String(),
                'data_resolvido' => optional($f->data_resolvido)->toIso8601String(),
                'cliente_confirmou' => $f->cliente_confirmou,
            ])->all(),
            'total' => $feedbacks->count(),
        ]);
    }

    /**
     * PATCH /atendimento/feedback/{id}/status
     *
     * Transita status do feedback (novo → triaged → backlog → in_progress → resolved → closed).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:' . implode(',', ClientFeedback::STATUSES)],
            'pr_link' => ['nullable', 'string', 'url'],
            'cliente_confirmou' => ['nullable', 'boolean'],
            're_reclamacao' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback = ClientFeedback::find($id);   // global scope filtra Tier 0
        if (! $feedback) {
            return response()->json(['errors' => ['id' => 'Feedback não encontrado.']], 404);
        }

        $data = $validator->validated();

        // Se status = resolved, marcar data_resolvido se ainda não tem
        if ($data['status'] === ClientFeedback::STATUS_RESOLVED && ! $feedback->data_resolvido) {
            $data['data_resolvido'] = now();
        }

        $feedback->update($data);

        return response()->json([
            'success' => true,
            'feedback' => [
                'id' => $feedback->id,
                'status' => $feedback->status,
                'data_resolvido' => optional($feedback->data_resolvido)->toIso8601String(),
                'cliente_confirmou' => $feedback->cliente_confirmou,
            ],
        ]);
    }
}
