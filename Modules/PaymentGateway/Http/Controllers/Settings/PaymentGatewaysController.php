<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\HealthCheckService;

/**
 * Tela /settings/payment-gateways — F3 PaymentGateway UI Tela 2.
 *
 * Persona-foco: Wagner (superadmin / dono). Tela read+toggle — emissão
 * full de credencial fica no SheetNovoGateway (3-step wizard, frontend);
 * health check real via HealthCheckService (Onda 4d.3).
 *
 * Origem: Cowork F1+F1.5 (score 93/100) aprovado [W] 2026-05-19.
 * ADR 0144 + ADR 0170. Charter:
 * resources/js/Pages/Settings/PaymentGateways/Index.charter.md.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL — `PaymentGatewayCredential` herda
 * business_id global scope via HasBusinessScope.
 *
 * Permission canon: `paymentgateway.credenciais.view` (granular). Hoje
 * usamos `system_settings.access` como fallback (UPOS canon) — granular
 * em backlog post-merge.
 */
class PaymentGatewaysController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // TODO[follow-up]: criar permission granular paymentgateway.credenciais.*
        // e adicionar via PermissionSeeder. Por hora usa system_settings.access
        // (UPOS canon — superadmin/owner já têm).
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        return Inertia::render('Settings/PaymentGateways/Index', [
            'today' => CarbonImmutable::today()->toDateString(),

            'accounts' => Inertia::defer(fn () => $this->listarContasDestino($businessId)),
            'gateways' => Inertia::defer(fn () => $this->listarGateways($businessId)),
            'kpis' => Inertia::defer(fn () => $this->kpis($businessId)),
        ]);
    }

    /**
     * Health check endpoint — rodar 1 credencial OU todas (?all=1).
     * Retorna JSON (chamado via fetch/axios direto do frontend).
     */
    public function healthCheck(Request $request, HealthCheckService $svc, ?int $credentialId = null): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        if ($credentialId) {
            $cred = PaymentGatewayCredential::query()
                ->where('business_id', $businessId)
                ->findOrFail($credentialId);

            $h = $svc->check($cred);

            return response()->json([
                'credential_id' => $cred->id,
                'status' => $h->status,
                'latency_ms' => $h->latencyMs,
                'message' => $h->errorMessage,
                'checked_at' => $h->checkedAt->format('c'),
            ]);
        }

        $results = $svc->checkAll($businessId);

        return response()->json(['results' => $results]);
    }

    /**
     * Toggle ativo/inativo da credencial (Trust L3 — confirma front-end).
     */
    public function toggle(Request $request, int $credentialId): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $cred = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->findOrFail($credentialId);

        $cred->update(['ativo' => ! $cred->ativo]);

        return response()->json([
            'credential_id' => $cred->id,
            'ativo' => $cred->ativo,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listarContasDestino(int $businessId): array
    {
        return ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->with('account:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ContaBancaria $c) => [
                'id' => $c->id,
                'name' => $c->account?->name ?? '(sem nome)',
                'agencia' => $c->agencia ?? null,
                'conta' => $c->conta ?? null,
                'banco' => $c->banco_codigo,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listarGateways(int $businessId): array
    {
        return PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->orderByDesc('ativo')
            ->orderBy('gateway_key')
            ->get()
            ->map(fn (PaymentGatewayCredential $c) => [
                'id' => $c->id,
                'driver' => $c->gateway_key,
                'nome' => $c->nome_display ?: $c->gateway_key,
                'ambiente' => $c->ambiente,
                'ativo' => (bool) $c->ativo,
                'account_id' => $c->conta_bancaria_id,
                'last_check' => $c->health_checked_at?->toIso8601String(),
                'health' => $c->health_status ?? 'ok',
                'latencia' => null,
                'created_at' => $c->created_at?->toDateString(),
                'warn' => $this->warnFor($c),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(int $businessId): array
    {
        $ativos = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->count();
        $total = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->count();
        $fail = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->where('health_status', '!=', 'ok')
            ->count();
        $cobsHoje = Cobranca::query()
            ->where('business_id', $businessId)
            ->whereDate('created_at', CarbonImmutable::today())
            ->count();

        return [
            'ativos' => $ativos,
            'total' => $total,
            'fail' => $fail,
            'cobs_hoje' => $cobsHoje,
        ];
    }

    private function warnFor(PaymentGatewayCredential $c): ?string
    {
        // Driver legacy = warn
        if ($c->gateway_key === 'pesapal') {
            return 'Deprecated — migrar pra Asaas (BR nativo + 3DS)';
        }
        // mTLS expira em ≤30d (heurística simples — config_json poderia ter expira_em real)
        // Não implementado por enquanto.

        return null;
    }
}
