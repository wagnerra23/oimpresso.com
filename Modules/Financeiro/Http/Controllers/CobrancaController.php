<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Account;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\IdempotencyConflictException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Exceptions\PaymentGatewayException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Repositories\CobrancaQuery;
use Throwable;

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

    /**
     * Emite cobrança via PaymentGatewayContract::emitirX() — Onda 4d.5 wire-up.
     *
     * Disparado por:
     *  - SheetNovaCobranca step 4 (Revisar) → onClick "Emitir cobrança"
     *  - CobrancaDrawer (Sells) form → onClick "Emitir cobrança" (rota dedicada
     *    /sells/{id}/emitir-cobranca em SellController)
     *
     * Idempotência via idempotency_key — double-submit retorna 200 com Cobranca
     * existente (PaymentGatewayService::emitir() pipeline canônica).
     *
     * Validation:
     *  - tipo: required, in:boleto,pix_cob,pix_cobv,pix_recv,card
     *  - valor_centavos: required, int, min:100 (R$ [redacted Tier 0] mínimo)
     *  - vencimento: required, date, after_or_equal:today
     *  - account_id: required, exists em fin_contas_bancarias do biz
     *  - contact_id ou payer_name: pelo menos um (LGPD: pagador identificado)
     *
     * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
     *  - $businessId vem de session('user.business_id')
     *  - Account validada exists em scope biz
     *  - Cobranca criada com business_id correto via PaymentGatewayService
     */
    public function store(Request $request, PaymentGatewayContract $gateway): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $validated = $request->validate([
            'tipo' => 'required|string|in:boleto,pix_cob,pix_cobv,pix_recv,card',
            'valor_centavos' => 'required|integer|min:100',
            'vencimento' => 'required|date|after_or_equal:today',
            'account_id' => 'required|integer|exists:fin_contas_bancarias,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'payer_name' => 'nullable|string|max:255',
            'payer_cpf_cnpj' => 'nullable|string|max:20',
            'payer_email' => 'nullable|email|max:255',
            'descricao' => 'nullable|string|max:500',
            'origem_type' => 'nullable|string|in:sale,invoice,subscription_license',
            'origem_id' => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:36',
        ]);

        // LGPD: exige pagador identificado (contact_id OU payer_name)
        if (empty($validated['contact_id']) && empty($validated['payer_name'])) {
            return back()->withErrors(['payer_name' => 'Informe contact_id ou payer_name (LGPD Art. 7º).']);
        }

        // Multi-tenant Tier 0: confirma account pertence ao business
        $account = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->find($validated['account_id']);

        if (! $account) {
            return back()->withErrors(['account_id' => 'Conta destino não encontrada neste business.']);
        }

        $coreAccount = Account::query()->find($account->account_id);
        if (! $coreAccount) {
            return back()->withErrors(['account_id' => 'Conta bancária core inválida.']);
        }

        try {
            $input = $this->buildInput($validated, $businessId);
            $result = match ($validated['tipo']) {
                'boleto'   => $gateway->for($coreAccount)->emitirBoleto($input),
                'pix_cob'  => $gateway->for($coreAccount)->emitirPix($input, 'cob'),
                'pix_cobv' => $gateway->for($coreAccount)->emitirPix($input, 'cobv'),
                'pix_recv' => $gateway->for($coreAccount)->emitirPixAutomatico($input),
                'card'     => back()->withErrors(['tipo' => 'Cartão exige CardToken — endpoint dedicado pendente Onda 4d.6']),
            };

            if ($result instanceof RedirectResponse) {
                return $result;
            }

            return back()->with('success', "Cobrança #{$result->cobrancaId} emitida via {$account->banco_codigo}.");
        } catch (IdempotencyConflictException $e) {
            return back()->withErrors(['idempotency_key' => $e->getMessage()]);
        } catch (CredentialMisconfiguredException | DriverNotSupportedException $e) {
            return back()->withErrors(['gateway' => "Configuração de gateway inválida: {$e->getMessage()}"]);
        } catch (InvalidPayerException $e) {
            return back()->withErrors(['payer_name' => "Pagador inválido: {$e->getMessage()}"]);
        } catch (GatewayUnavailableException $e) {
            return back()->withErrors(['gateway' => "Gateway indisponível: {$e->getMessage()}. Tente novamente em alguns minutos."]);
        } catch (PaymentGatewayException | Throwable $e) {
            report($e);
            return back()->withErrors(['gateway' => 'Falha ao emitir cobrança. Detalhe registrado no log.']);
        }
    }

    /**
     * Constrói EmitirCobrancaInput DTO imutável a partir do payload validado.
     *
     * @param  array<string, mixed>  $validated
     */
    private function buildInput(array $validated, int $businessId): \Modules\PaymentGateway\Dto\EmitirCobrancaInput
    {
        return new \Modules\PaymentGateway\Dto\EmitirCobrancaInput(
            businessId: $businessId,
            contactId: (int) ($validated['contact_id'] ?? 0),
            valorCentavos: (int) $validated['valor_centavos'],
            vencimento: new DateTimeImmutable($validated['vencimento']),
            descricao: $validated['descricao'] ?? 'Cobrança avulsa',
            idempotencyKey: $validated['idempotency_key'] ?? (string) Str::uuid(),
            origemType: $validated['origem_type'] ?? null,
            origemId: isset($validated['origem_id']) ? (int) $validated['origem_id'] : null,
            meta: [
                'payer_name'     => $validated['payer_name'] ?? null,
                'payer_cpf_cnpj' => $validated['payer_cpf_cnpj'] ?? null,
                'payer_email'    => $validated['payer_email'] ?? null,
            ],
        );
    }
}
