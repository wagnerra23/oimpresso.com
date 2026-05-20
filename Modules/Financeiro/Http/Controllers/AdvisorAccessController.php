<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Financeiro\Models\Advisor;
use Modules\Financeiro\Models\AdvisorBusinessAccess;

/**
 * AdvisorAccessController — Onda 31 #57 US-FIN-037 Fase 1 MVP.
 *
 * Tela `/financeiro/configuracoes/contador` no contexto do business — onde
 * o owner adiciona o contador da empresa e gerencia grants. NUNCA aqui o
 * advisor faz login — login dele é em `/advisor/login` (AdvisorAuthController).
 *
 * Fluxo grant:
 *  1. Owner busca contador por email+cnpj
 *  2. Se não existe → cria advisor (sem senha — convite por email)
 *  3. Marca consentimento LGPD (checkbox obrigatório no form)
 *  4. Cria row em advisor_business_access com scope + consented_at
 *
 * Permission: `financeiro.advisor.grant` (registrado em DataController).
 */
class AdvisorAccessController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Permission gate: só owner com permission explícita.
        $this->middleware('can:financeiro.advisor.grant');
    }

    /**
     * GET /financeiro/configuracoes/contador
     */
    public function index(): InertiaResponse
    {
        $businessId = (int) session('user.business_id');

        $accesses = AdvisorBusinessAccess::query()
            ->where('business_id', $businessId)
            ->whereNull('revoked_at')
            ->whereNull('deleted_at')
            ->with('advisor:id,nome,email,cnpj_contador,referral_code,ativo')
            ->orderByDesc('granted_at')
            ->get()
            ->map(function (AdvisorBusinessAccess $a) {
                return [
                    'id' => $a->id,
                    'advisor_nome' => $a->advisor?->nome,
                    'advisor_email' => $a->advisor?->email,
                    // PII LGPD-safe: nunca expor CNPJ completo no front sem mascarar.
                    'advisor_cnpj_mascarado' => $a->advisor?->cnpj_masked,
                    'granted_at' => $a->granted_at?->toIso8601String(),
                    'granted_at_label' => $a->granted_at?->locale('pt_BR')->isoFormat('DD/MM/YYYY'),
                    'can_view_unificado' => $a->canViewUnificado(),
                    'can_view_reports' => $a->canViewReports(),
                    'has_consent' => $a->hasConsent(),
                ];
            });

        return Inertia::render('Financeiro/Configuracoes/Contador', [
            'accesses' => $accesses,
            'breadcrumbs' => [
                ['label' => 'Financeiro', 'href' => '/financeiro'],
                ['label' => 'Configurações', 'href' => null],
                ['label' => 'Contador', 'href' => null],
            ],
        ]);
    }

    /**
     * POST /financeiro/configuracoes/contador/grant
     *
     * Cria/recupera advisor por email+cnpj e concede acesso ao business atual.
     * Validações:
     *  - cnpj_contador: 14 dígitos (PII redacted em log)
     *  - email: válido + unique-safe (firstOrCreate)
     *  - consent_lgpd: checkbox obrigatório (must equal "1"/true)
     */
    public function grant(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = $request->user()->id;

        $validated = $request->validate([
            'cnpj_contador' => ['required', 'string', 'size:14', 'regex:/^\d{14}$/'],
            'nome' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'consent_lgpd' => ['required', 'accepted'],
            'can_view_unificado' => ['sometimes', 'boolean'],
            'can_view_reports' => ['sometimes', 'boolean'],
        ]);

        // Busca ou cria o advisor (cnpj+email são chaves globais).
        $advisor = Advisor::firstOrCreate(
            ['cnpj_contador' => $validated['cnpj_contador']],
            [
                'nome' => $validated['nome'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'referral_code' => Advisor::generateReferralCode(),
                'ativo' => true,
            ]
        );

        // Se já existia mas o email não bate → conflito (CNPJ é chave única forte).
        if ($advisor->wasRecentlyCreated === false && $advisor->email !== $validated['email']) {
            Log::warning('Onda 31: tentativa grant com email divergente pro mesmo CNPJ', [
                'business_id' => $businessId,
                'advisor_id' => $advisor->id,
                'cnpj_contador' => '[REDACTED]',
            ]);
            return back()->with('error', 'Contador já cadastrado com outro email. Solicite ao contador atualizar antes de conceder acesso.');
        }

        // Verifica se já existe grant ativo (idempotência).
        $jaTem = AdvisorBusinessAccess::query()
            ->where('advisor_id', $advisor->id)
            ->where('business_id', $businessId)
            ->whereNull('revoked_at')
            ->whereNull('deleted_at')
            ->exists();

        if ($jaTem) {
            return back()->with('info', 'Esse contador já tem acesso a este negócio.');
        }

        $scopeJson = [
            'can_view_unificado' => (bool) ($validated['can_view_unificado'] ?? true),
            'can_view_reports' => (bool) ($validated['can_view_reports'] ?? true),
            'consented_at' => now()->toIso8601String(),
            'consented_by' => $userId,
            'consent_versao' => 'lgpd-v1-2026-05-20',
        ];

        AdvisorBusinessAccess::create([
            'advisor_id' => $advisor->id,
            'business_id' => $businessId,
            'granted_at' => now(),
            'granted_by' => $userId,
            'scope_json' => $scopeJson,
        ]);

        Log::info('Onda 31: grant criado', [
            'business_id' => $businessId,
            'advisor_id' => $advisor->id,
            'granted_by' => $userId,
            // CNPJ redacted — LGPD.
            'cnpj_contador' => '[REDACTED]',
        ]);

        return back()->with('success', "Acesso concedido a {$advisor->nome}. Contador receberá email pra definir senha.");
    }

    /**
     * DELETE /financeiro/configuracoes/contador/{accessId}
     *
     * Revoga acesso — soft delete + set revoked_at + revoked_by. Histórico
     * preservado pra audit LGPD (Art. 18 — direito à informação).
     */
    public function revoke(Request $request, int $accessId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = $request->user()->id;

        $access = AdvisorBusinessAccess::query()
            ->where('business_id', $businessId)
            ->whereNull('revoked_at')
            ->findOrFail($accessId);

        $access->update([
            'revoked_at' => now(),
            'revoked_by' => $userId,
        ]);
        $access->delete(); // Soft delete — preserva audit.

        Log::info('Onda 31: grant revogado', [
            'business_id' => $businessId,
            'advisor_id' => $access->advisor_id,
            'access_id' => $access->id,
            'revoked_by' => $userId,
        ]);

        return back()->with('success', 'Acesso revogado. Contador não verá mais este negócio.');
    }
}
