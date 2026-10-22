<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\ReguaCobrancaConfig;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\Financeiro\Services\TituloService;

/**
 * Tela /financeiro/contas-receber.
 *
 * Lista titulos tipo='receber' com badge de status. Acao "Emitir boleto"
 * chama TituloService que delega pra CnabDirectStrategy.
 *
 * Pattern: ADR 0029 (Inertia + React + UPos).
 */
class ContaReceberController extends Controller
{
    /**
     * Gate de permissão (Camada 3 Spatie). Até 2026-09-23 este controller não
     * verificava permissão nenhuma — rota e FormRequest só exigiam login.
     * O escopo por business_id segue no corpo dos métodos; isto fecha o acesso
     * DENTRO da empresa. Admin#{biz} passa pelo Gate::before (AuthServiceProvider).
     */
    public function __construct()
    {
        $this->middleware('can:financeiro.contas_receber.view')->only('index');
        $this->middleware('can:financeiro.contas_receber.create')->only('emitirBoleto');
    }


    public function index(Request $request, RetencaoNfseTituloService $retencao): Response|\Illuminate\Http\Response
    {

        $businessId = $request->session()->get('business.id');

        $titulos = Titulo::where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereNull('deleted_at')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->vence_em, function ($q, $vence) {
                if ($vence === 'hoje') return $q->whereDate('vencimento', today());
                if ($vence === 'atrasado') return $q->whereDate('vencimento', '<', today())->where('status', '!=', 'quitado');
                if ($vence === 'semana') return $q->whereBetween('vencimento', [today(), today()->addDays(7)]);
                return $q;
            })
            ->orderBy('vencimento')
            ->limit(100)
            ->get()
            ->map(function (Titulo $t) use ($retencao) {
                // US-FIN-072: bruto / retido (NFS-e) / líquido, em centavos → string 2 casas.
                $v = $retencao->valoresDoTitulo($t->valor_total, $t->metadata);
                $boleto = BoletoRemessa::where('titulo_id', $t->id)
                    ->whereNotIn('status', ['cancelado'])
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => $t->id,
                    'numero' => $t->numero,
                    'cliente_descricao' => $t->cliente_descricao,
                    'cliente_id' => $t->cliente_id,
                    'valor_total' => $t->valor_total,
                    'valor_aberto' => $t->valor_aberto,
                    'valor_retido' => number_format($v['retido'] / 100, 2, '.', ''),
                    'valor_liquido' => number_format($v['liquido'] / 100, 2, '.', ''),
                    'vencimento' => $t->vencimento?->toDateString(),
                    'status' => $t->status,
                    'origem' => $t->origem,
                    'origem_id' => $t->origem_id,
                    'boleto' => $boleto ? [
                        'id' => $boleto->id,
                        'status' => $boleto->status,
                        'linha_digitavel' => $boleto->linha_digitavel,
                        'nosso_numero' => $boleto->nosso_numero,
                    ] : null,
                ];
            });

        return Inertia::render('Financeiro/ContasReceber/Index', [
            'titulos' => $titulos,
            'filtros' => [
                'status' => $request->status,
                'vence_em' => $request->vence_em,
            ],
            // Só calcula quando o painel "Lembretes" abre (reload com only: ['lembretes']).
            'lembretes' => Inertia::optional(fn () => $this->lembretes((int) $businessId)),
        ]);
    }

    /**
     * Lembretes da régua de cobrança (US-FIN-071 · UC-FLX-29), mais recentes primeiro, e o estado
     * da régua. O título é lido no MESMO business (Tier 0): o join filtra os dois lados.
     *
     * @return array{regua: array{ativo: bool, dias: list<int>}, itens: list<array<string, mixed>>}
     */
    private function lembretes(int $businessId): array
    {
        $regua = app(ReguaCobrancaConfig::class)->de($businessId);
        if (! Schema::hasTable('fin_titulo_lembretes')) {
            return ['regua' => $regua, 'itens' => []];
        }

        $itens = DB::table('fin_titulo_lembretes as l')
            ->join('fin_titulos as t', function ($j) use ($businessId) {
                $j->on('t.id', '=', 'l.titulo_id')->where('t.business_id', '=', $businessId);
            })
            ->where('l.business_id', $businessId)
            ->orderByDesc('l.created_at')
            ->orderByDesc('l.id')
            ->limit(100)
            ->get(['l.id', 'l.titulo_id', 't.numero', 't.cliente_descricao', 't.vencimento', 'l.etapa_dias',
                'l.canal', 'l.status', 'l.motivo', 'l.tarefa_id', 'l.enviado_em', 'l.created_at'])
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'titulo_id' => (int) $l->titulo_id,
                'numero' => $l->numero,
                'cliente' => $l->cliente_descricao,
                'vencimento' => $l->vencimento ? substr((string) $l->vencimento, 0, 10) : null,
                'etapa_dias' => (int) $l->etapa_dias,
                'canal' => $l->canal,
                'status' => $l->status,
                'motivo' => $l->motivo,
                'tarefa' => $l->tarefa_id !== null,
                'quando' => (string) ($l->enviado_em ?? $l->created_at),
            ])
            ->all();

        return ['regua' => $regua, 'itens' => $itens];
    }

    public function emitirBoleto(Request $request, int $tituloId, TituloService $service): RedirectResponse
    {
        $businessId = $request->session()->get('business.id');

        // Wave 17 D9 — emissão de boleto é fluxo crítico observado (latência HTTP gateway
        // Inter/Asaas + erro 4xx/5xx). Span captura biz + titulo + falha gateway.
        return OtelHelper::spanBiz('financeiro.boleto.emitir', function () use ($request, $tituloId, $service, $businessId) {
            $titulo = Titulo::where('business_id', $businessId)->findOrFail($tituloId);

            try {
                $remessa = $service->emitirBoleto(
                    $titulo,
                    $request->integer('conta_bancaria_id') ?: null
                );

                return back()->with('success', "Boleto gerado: {$remessa->nosso_numero}");
            } catch (\DomainException $e) {
                return back()->with('error', $e->getMessage());
            }
        }, ['op' => 'emitir_boleto', 'titulo_id' => $tituloId]);
    }
}
