<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Services\TituloService;

/**
 * Tela /financeiro/boletos — dashboard de cobrança (Cockpit V2).
 *
 * Origem: protótipo Cowork "Boleto e Contas Inter" aprovado por [W] 2026-05-09 +
 * decisões Q1-Q5 aprovadas [W] 2026-05-14 (memory/requisitos/Financeiro/
 * boletos-visual-comparison.md).
 *
 * Persona-foco: Eliana [E] — financeiro escritório.
 * Stories: US-BOL-XXX (refator visual).
 *
 * Refator F3 entrega:
 *  - Funil cobrança 5 etapas UI-only (Q1 aprovado: derivar de status)
 *  - 3 KPIs (Pago mês, Vencido, Em aberto)
 *  - Tabela rica com chip banco
 *  - Drawer simplificado F1 (Q5)
 *  Sheets emitir/remessa fora de escopo (Q2/Q3) — backlog.
 */
class BoletoController extends Controller
{
    use RendersMockCowork;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request): Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

        $businessId = (int) $request->session()->get('business.id');
        $hoje = CarbonImmutable::today();

        $remessas = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->conta_id, fn ($q, $c) => $q->where('conta_bancaria_id', (int) $c))
            ->with([
                'titulo:id,numero,cliente_descricao,vencimento',
                'contaBancaria:id,account_id,banco_codigo',
                'contaBancaria.account:id,name',
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $rows = $remessas->map(fn (BoletoRemessa $b) => $this->shapeRemessa($b, $hoje));

        $kpis = $this->kpis($businessId, $hoje);
        $funil = $this->funil($businessId, $hoje);
        $contas = $this->listarContas($businessId);

        return Inertia::render('Financeiro/Boletos/Index', [
            'remessas' => $rows,
            'kpis' => $kpis,
            'funil' => $funil,
            'contas' => $contas,
            'filtros' => [
                'status' => $request->status,
                'conta_id' => $request->conta_id ? (int) $request->conta_id : null,
            ],
        ]);
    }

    public function cancelar(Request $request, int $remessaId, TituloService $service): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        // Wave 17 D9 — operação mutativa observada (span por op + business).
        return OtelHelper::spanBiz('financeiro.boleto.cancelar', function () use ($request, $remessaId, $service, $businessId) {
            $remessa = BoletoRemessa::where('business_id', $businessId)->findOrFail($remessaId);

            if ($remessa->status === BoletoRemessa::STATUS_CANCELADO) {
                return back()->with('error', 'Boleto ja cancelado.');
            }

            if ($remessa->status === BoletoRemessa::STATUS_PAGO) {
                return back()->with('error', 'Boleto ja pago — nao pode ser cancelado.');
            }

            $service->cancelarBoleto($remessa, $request->input('motivo', 'cancelado pelo usuario'));

            return back()->with('success', 'Boleto cancelado.');
        }, ['op' => 'cancelar', 'remessa_id' => $remessaId]);
    }

    /**
     * Shape Eloquent → array pro Inertia (T-AP-5 do LICOES — não vazar Eloquent).
     */
    private function shapeRemessa(BoletoRemessa $b, CarbonImmutable $hoje): array
    {
        $conta = $b->contaBancaria;

        return [
            'id' => $b->id,
            'titulo_id' => $b->titulo_id,
            'titulo_numero' => $b->titulo?->numero,
            'cliente' => $b->titulo?->cliente_descricao,
            'nosso_numero' => $b->nosso_numero,
            'linha_digitavel' => $b->linha_digitavel,
            'codigo_barras' => $b->codigo_barras,
            'valor_total' => $b->valor_total,
            'vencimento' => $b->vencimento?->toDateString(),
            'status' => $b->status,
            'strategy' => $b->strategy,
            'enviado_em' => $b->enviado_em?->toIso8601String(),
            'pago_em' => $b->pago_em?->toIso8601String(),
            'created_at' => $b->created_at->toIso8601String(),
            'conta_id' => $b->conta_bancaria_id,
            'conta_nome' => $conta?->account?->name,
            'banco_codigo' => $conta?->banco_codigo,
            'banco_short' => $this->bancoShort($conta?->banco_codigo),
            'dias_atraso' => $b->vencimento ? max(0, $hoje->diffInDays($b->vencimento, false) * -1) : 0,
        ];
    }

    /**
     * KPIs derivados: Pago mês + Vencido + Em aberto.
     */
    private function kpis(int $businessId, CarbonImmutable $hoje): array
    {
        $inicioMes = $hoje->startOfMonth();
        $fimMes = $hoje->endOfMonth();

        $pagoMes = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->where('status', BoletoRemessa::STATUS_PAGO)
            ->whereBetween('pago_em', [$inicioMes, $fimMes])
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as valor')
            ->first();

        $vencido = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->where('status', BoletoRemessa::STATUS_VENCIDO)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as valor')
            ->first();

        $aberto = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [
                BoletoRemessa::STATUS_GERADO,
                BoletoRemessa::STATUS_ENVIADO,
                BoletoRemessa::STATUS_REGISTRADO,
            ])
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as valor')
            ->first();

        return [
            'pago_mes' => ['qtd' => (int) $pagoMes->qtd, 'valor' => (float) $pagoMes->valor],
            'vencido'  => ['qtd' => (int) $vencido->qtd, 'valor' => (float) $vencido->valor],
            'aberto'   => ['qtd' => (int) $aberto->qtd, 'valor' => (float) $aberto->valor],
        ];
    }

    /**
     * Funil 5 etapas (Q1 aprovado UI-only: derivar de status + regras simples).
     * Lembrete/Cobrança ativa/Protesto = heurística sobre vencimento; jobs reais
     * de cobrança automática entram em Onda 2 (CYCLE-XXX).
     */
    private function funil(int $businessId, CarbonImmutable $hoje): array
    {
        $aberto = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [BoletoRemessa::STATUS_REGISTRADO, BoletoRemessa::STATUS_ENVIADO, BoletoRemessa::STATUS_GERADO])
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as valor')
            ->first();

        $lembrete = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [BoletoRemessa::STATUS_REGISTRADO, BoletoRemessa::STATUS_ENVIADO])
            ->whereBetween('vencimento', [$hoje->addDay()->toDateString(), $hoje->addDays(3)->toDateString()])
            ->whereNull('deleted_at')
            ->count();

        $cobrancaAtiva = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [BoletoRemessa::STATUS_REGISTRADO, BoletoRemessa::STATUS_ENVIADO])
            ->where('vencimento', '<', $hoje->toDateString())
            ->where('vencimento', '>=', $hoje->subDays(5)->toDateString())
            ->whereNull('deleted_at')
            ->count();

        $vencidoMais5d = BoletoRemessa::query()
            ->where('business_id', $businessId)
            ->where('status', BoletoRemessa::STATUS_VENCIDO)
            ->where('vencimento', '<', $hoje->subDays(5)->toDateString())
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as valor')
            ->first();

        return [
            'aberto'        => ['qtd' => (int) $aberto->qtd, 'valor' => (float) $aberto->valor],
            'lembrete'      => ['qtd' => (int) $lembrete, 'desc' => '3d antes do vcto'],
            'cobranca'      => ['qtd' => (int) $cobrancaAtiva, 'desc' => '1-5d apos vcto'],
            'vencido_5d'    => ['qtd' => (int) $vencidoMais5d->qtd, 'valor' => (float) $vencidoMais5d->valor],
            'protesto'      => ['qtd' => 0, 'desc' => '30d+ (Onda 2)'],
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, banco_codigo: ?string, banco_short: ?string}>
     */
    private function listarContas(int $businessId): Collection
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
                'banco_codigo' => $c->banco_codigo,
                'banco_short' => $this->bancoShort($c->banco_codigo),
            ]);
    }

    /**
     * Mapping fixo banco_codigo → short name pro chip visual.
     * Códigos COMPE (Banco Central do Brasil).
     */
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
