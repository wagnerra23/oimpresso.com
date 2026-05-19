<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Repositories\CobrancaQuery;

/**
 * Tela /financeiro/cobranca — F3 PaymentGateway UI Tela 1.
 *
 * Substitui /financeiro/boletos. Escopo expandido pra todos tipos de
 * cobrança (boleto + pix_cob + pix_cobv + pix_recv + card), todos gateways
 * (Inter/C6/Asaas/BCB Pix/PesaPal) e todas origens (sale/invoice/sub_license).
 *
 * Persona-foco: Eliana [E] (financeiro escritório) + Larissa [Cliente Piloto]
 * via Sells drawer chip (PR-3).
 *
 * Origem: Cowork F1 + F1.5 (score 96/100) aprovado [W] 2026-05-19. ADR 0144 +
 * ADR 0170. Charter: resources/js/Pages/Financeiro/Cobranca/Index.charter.md.
 *
 * Inertia::defer() em props pesadas (cobrancas/kpis/funil) — pattern canon
 * RUNBOOK-inertia-defer-pattern.md (validado D-14 300ms → 50ms).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — Models PaymentGateway\Cobranca
 * e PaymentGatewayCredential herdam business_id global scope via
 * HasBusinessScope.
 *
 * Permission canon: financeiro.dashboard.view (granularidade
 * paymentgateway.cobranca.view em backlog F2).
 */
class CobrancaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request, CobrancaQuery $query): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));
        $hoje = CarbonImmutable::today();

        $filtros = [
            'status' => $request->string('status')->toString() ?: null,
            'tipo' => $request->string('tipo')->toString() ?: null,
            'gateway' => $request->string('gateway')->toString() ?: null,
            'account_id' => $request->integer('account_id') ?: null,
            'origem' => $request->string('origem')->toString() ?: null,
            'busca' => $request->string('busca')->toString() ?: null,
        ];

        // Wagner SaaS dogfooding: biz=1 vê origem subscription_license; demais não.
        $isSaasBusiness = $businessId === 1;

        return Inertia::render('Financeiro/Cobranca/Index', [
            'today' => $hoje->toDateString(),
            'filtros' => $filtros,
            'isSaasBusiness' => $isSaasBusiness,
            'accounts' => $this->listarContasDestino($businessId),
            'gateways' => $query->gateways($businessId),

            // Defer props caras (Inertia::defer DEFAULT — RUNBOOK pattern)
            'cobrancas' => Inertia::defer(fn () => $query->listar($businessId, $filtros)),
            'kpis' => Inertia::defer(fn () => $query->kpis($businessId, $hoje)),
            'funil' => Inertia::defer(fn () => $query->funil($businessId, $hoje)),
        ]);
    }

    /**
     * Contas bancárias do business + gateway driver (se houver credential
     * vinculada). Shape pro frontend (`Account`).
     *
     * @return array<int, array<string, mixed>>
     */
    private function listarContasDestino(int $businessId): array
    {
        $contas = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->with('account:id,name')
            ->orderBy('id')
            ->get();

        $credenciais = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->whereNotNull('conta_bancaria_id')
            ->where('ativo', true)
            ->get()
            ->keyBy('conta_bancaria_id');

        return $contas->map(function (ContaBancaria $c) use ($credenciais) {
            $cred = $credenciais->get($c->id);

            return [
                'id' => $c->id,
                'name' => $c->account?->name ?? '(sem nome)',
                'agencia' => $c->agencia ?? null,
                'conta' => $c->conta ?? null,
                'banco' => $this->bancoShort($c->banco_codigo),
                'driver' => $cred?->gateway_key,
            ];
        })->all();
    }

    private function bancoShort(?string $codigo): ?string
    {
        return match ($codigo) {
            '001' => 'BB',
            '033' => 'Santander',
            '077' => 'Inter',
            '104' => 'Caixa',
            '237' => 'Bradesco',
            '274' => 'Asaas',
            '336' => 'C6',
            '341' => 'Itaú',
            '748' => 'Sicredi',
            '756' => 'Sicoob',
            default => $codigo,
        };
    }
}
