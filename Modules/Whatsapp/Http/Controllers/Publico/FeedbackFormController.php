<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Publico;

use App\Business;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\ClientFeedback;
use Modules\Whatsapp\Services\FeedbackRelevanceService;

/**
 * Canal PÚBLICO de sinal do cliente — US-INFRA-002 · ADR 0105 · ADR 0334.
 *
 * O órgão sensor: a Larissa (ROTA LIVRE) abre um link assinado e reporta a dor DELA,
 * sem depender de o [W] ouvir no WhatsApp e transcrever. O irmão deste controller
 * (Admin/ClientFeedbackController::capture) é o canal 'whatsapp' e segue intacto.
 *
 * ── Tier 0 (ADR 0093) — LEIA ANTES DE MEXER ─────────────────────────────────────────
 * Esta rota NÃO tem auth, então `session('user.business_id')` é null e o global scope
 * ScopeByBusiness é NO-OP aqui (ele retorna cedo quando `!auth()->check()`). Duas
 * consequências que o código abaixo trata explicitamente:
 *
 *   1. O business_id vem da URL ASSINADA, nunca do input. O middleware `signed` valida
 *      HMAC sobre a URL inteira (APP_KEY): trocar ?biz=4 por ?biz=1 quebra a assinatura
 *      → 403. O tenant é criptograficamente amarrado ao link — não é um campo que o
 *      cliente possa forjar.
 *   2. Toda query daqui filtra business_id À MÃO. Não confie no global scope nesta
 *      classe. (O dedup usa findDuplicateWithin90d($sig, $bizId), que já filtra
 *      explicitamente — verificado, não presumido.)
 *
 * Link gerado por `php artisan feedback:link {business_id}` (validade 30d, ADR 0105).
 */
class FeedbackFormController extends Controller
{
    /**
     * GET /feedback?biz=N&expires=…&signature=… (middleware: signed)
     *
     * O form que a Larissa vê. Assinatura inválida/expirada → 403 pelo middleware.
     */
    public function show(Request $request): Response
    {
        $businessId = $this->businessIdAssinado($request);

        // Escapa o global scope de propósito: sem auth ele é no-op, mas ser explícito
        // aqui documenta que a leitura é deliberada e escopada por 1 id vindo do HMAC.
        // SUPERADMIN: rota pública sem sessão — o tenant vem da URL assinada, não da session.
        $business = Business::withoutGlobalScopes()->find($businessId);

        if (! $business) {
            abort(404);
        }

        // Tela em Pages/Whatsapp/ (não Pages/Feedback/, como dizia o SPEC): a tela é DESTE
        // módulo — controller, entity e tabela vivem aqui. Um Pages/Feedback/ criaria um
        // módulo-fantasma em memory/requisitos/ só pra hospedar o RUNBOOK dela.
        return Inertia::render('Whatsapp/FeedbackPublico', [
            'business_nome' => $business->name,
            // O form posta na MESMA URL assinada (o HMAC do Laravel cobre a URL, não o
            // método) — assim não há um 2º token pra gerar, expirar ou vazar.
            'submit_url' => $request->fullUrl(),
            'severidades' => collect(ClientFeedback::SEVERITY_LABELS)
                ->map(fn (string $label, int $v) => ['valor' => $v, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * POST /feedback?biz=N&expires=…&signature=… (middleware: signed)
     *
     * Grava o sinal. Reusa o mesmo motor do canal 'whatsapp': dedup por signature,
     * relevance_score via Observer, workflow de status. Zero duplicação.
     */
    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->businessIdAssinado($request);

        $validator = Validator::make($request->all(), [
            'literal' => ['required', 'string', 'min:5', 'max:5000'],
            'reporter_name' => ['nullable', 'string', 'max:120'],
            'url_seen' => ['nullable', 'string', 'max:255'],
            'severity_self_reported' => ['required', 'integer', 'min:0', 'max:4'],
            'browser_console_dump' => ['nullable', 'string', 'max:10000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        // Tier 0 — SEMPRE do HMAC, nunca do request. Ver cabeçalho da classe.
        $data['business_id'] = $businessId;
        $data['canal'] = ClientFeedback::CANAL_WEB_FORM;
        $data['status'] = ClientFeedback::STATUS_NOVO;
        // O que o cliente disse semeia o julgado; a triagem pode ajustar `severity_nng`
        // sem nunca sobrescrever `severity_self_reported` (dado bruto do cliente).
        $data['severity_nng'] = $data['severity_self_reported'];
        $data['created_by'] = null;   // não há usuário logado — o cliente é a fonte

        $relevance = app(FeedbackRelevanceService::class);

        $temp = new ClientFeedback($data);
        $temp->business_id = $businessId;
        $signature = $relevance->computeSignature($temp);

        // Dedup 90d — filtra business_id explicitamente (não depende do global scope,
        // que é no-op sem auth). Mesma regra do canal whatsapp: recorrência bumpa em vez
        // de duplicar, e é justamente o que faz `pattern_emergente` acender.
        $existing = $relevance->findDuplicateWithin90d($signature, $businessId);

        if ($existing) {
            $existing->recorrente_count = ($existing->recorrente_count ?? 1) + 1;
            $existing->pattern_emergente = $existing->recorrente_count >= 3;
            $existing->severity_nng = max($existing->severity_nng, (int) $data['severity_nng']);
            $existing->last_seen_at = now();
            $existing->save();   // Observer rescore

            Log::info('[feedback.publico] dedup hit', [
                'feedback_id' => $existing->id,
                'business_id' => $businessId,
                'canal' => ClientFeedback::CANAL_WEB_FORM,
                'recorrente_count' => $existing->recorrente_count,
            ]);

            return back()->with('feedback_recebido', true);
        }

        $feedback = ClientFeedback::create($data);

        Log::info('[feedback.publico] sinal novo', [
            'feedback_id' => $feedback->id,
            'business_id' => $businessId,
            'canal' => ClientFeedback::CANAL_WEB_FORM,
            'severity_self_reported' => $feedback->severity_self_reported,
        ]);

        return back()->with('feedback_recebido', true);
    }

    /**
     * O tenant, extraído da URL assinada.
     *
     * Chega aqui só depois do middleware `signed` — se o ?biz foi adulterado, o HMAC já
     * derrubou com 403. O cast é defensivo (o middleware garante integridade, não tipo).
     */
    private function businessIdAssinado(Request $request): int
    {
        $businessId = (int) $request->query('biz');

        if ($businessId < 1) {
            abort(400, 'Link de feedback sem business.');
        }

        return $businessId;
    }
}
