<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Events\TituloCriado;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Impostos & obrigações — sub-tela do Financeiro (PACOTE-FINANCEIRO-F2 PR-2,
 * [W] "aprovado" 2026-06-10 · referência F1: TelaImpostos em
 * financeiro-telas-extras.jsx do protótipo Cowork).
 *
 * ESTIMATIVA VISUAL (Simples Nacional, regime caixa) — a apuração oficial,
 * cálculo por anexo e emissão de guia moram no módulo Fiscal. Censo 2026-06-09
 * validado @main 2026-06-10: "impostos a recolher + calendário" não existia em
 * nenhum módulo (Fiscal = cockpit de emissão NF-e/NFS-e, sem guias).
 *
 * Dados 100% derivados (zero tabela nova):
 *  - DAS estimado ≈6% sobre o RECEBIDO do mês (baixas regime caixa, espelha
 *    kpisCore: exclui estornos e títulos cancelados, soma juros+multa-desconto)
 *  - Guias FGTS/DCTFWeb/INSS/DAS históricas = títulos payable já lançados cujo
 *    descritivo bate o vocabulário de guia (o sistema não tem folha — honesto)
 *  - "Lançar a pagar" cria título payable no Unificado (costura do protótipo),
 *    idempotente por metadata.guia = "das-YYYY-MM"
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id da session em TODAS as queries.
 */
class ImpostosController extends Controller
{
    private const DAS_RATE = 0.06; // alíquota efetiva típica — estimativa, não apuração

    public function __construct()
    {
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(): Response
    {
        $businessId = (int) session('user.business_id');
        $hoje = now()->toDateString();
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $comp = now()->format('Y-m');

        $receitaRecebida = $this->receitaRecebidaMes($businessId, $start, $end);
        $dasEstimado = round($receitaRecebida * self::DAS_RATE, 2);

        // Título payable já lançado pra guia DAS desta competência (idempotência da costura).
        $dasLancado = $this->tituloDaGuia($businessId, "das-{$comp}");

        // DAS vence dia 20 do mês seguinte à competência (calendário Simples Nacional).
        $dasVenc = now()->addMonthNoOverflow()->setDay(20)->toDateString();

        $guias = [[
            'id' => "das-{$comp}",
            'nome' => 'DAS · Simples Nacional',
            'det' => '≈ 6% s/ recebido no mês · regime caixa',
            'competencia' => $comp,
            'competencia_label' => $this->compLabel($comp),
            'vencimento' => $dasVenc,
            'valor' => $dasLancado ? (float) $dasLancado->valor_total : $dasEstimado,
            'status' => $dasLancado && $dasLancado->status === 'quitado' ? 'paga'
                : ($dasVenc < $hoje ? 'atrasada' : 'a_vencer'),
            'estimado' => ! $dasLancado,
            'lancavel' => ! $dasLancado && $dasEstimado > 0,
            'lanc' => $dasLancado?->numero,
        ]];

        // Histórico: guias já lançadas como título payable (FGTS · DCTFWeb/INSS ·
        // DAS de competências anteriores). Últimos 6 meses por vencimento.
        $historico = Titulo::where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelado')
            ->where('vencimento', '>=', now()->subMonths(6)->toDateString())
            ->where(function ($q) {
                foreach (['FGTS', 'DCTF', 'INSS', 'DAS', 'Simples Nacional'] as $termo) {
                    $q->orWhere('cliente_descricao', 'like', "%{$termo}%");
                }
            })
            ->when($dasLancado, fn ($q) => $q->where('id', '!=', $dasLancado->id))
            ->orderByDesc('vencimento')
            ->limit(24)
            ->get(['id', 'numero', 'cliente_descricao', 'valor_total', 'valor_aberto', 'vencimento', 'competencia_mes', 'status']);

        foreach ($historico as $t) {
            $guias[] = [
                'id' => 'titulo-'.$t->id,
                'nome' => (string) $t->cliente_descricao,
                'det' => 'título '.$t->numero.' no caixa unificado',
                'competencia' => (string) $t->competencia_mes,
                'competencia_label' => $this->compLabel((string) $t->competencia_mes),
                // Legacy WR traz vencimento com timestamp ("2026-12-18 00:00:00") —
                // normaliza pra date-only senão o front renderiza "18 00:00:00/12".
                'vencimento' => substr((string) $t->vencimento, 0, 10),
                'valor' => (float) $t->valor_total,
                'status' => $t->status === 'quitado' ? 'paga'
                    : ((string) $t->vencimento < $hoje ? 'atrasada' : 'a_vencer'),
                'estimado' => false,
                'lancavel' => false,
                'lanc' => $t->numero,
            ];
        }

        $abertas = array_values(array_filter($guias, fn ($g) => $g['status'] !== 'paga'));
        usort($abertas, fn ($a, $b) => strcmp($a['vencimento'], $b['vencimento']));
        $aRecolher = array_sum(array_column($abertas, 'valor'));

        // Costura NF ↔ título: recebíveis do mês sem NF vinculada distorcem a base
        // do DAS (aviso pré-fechamento). NF vive em metadata (nfe_numero/nfe_chave).
        $recebiveis = Titulo::where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelado')
            ->whereBetween('vencimento', [$start->toDateString(), $end->toDateString()])
            ->limit(500)
            ->get(['id', 'numero', 'cliente_descricao', 'valor_total', 'metadata']);

        $temNf = fn (Titulo $t) => ! empty(($t->metadata ?? [])['nfe_numero'] ?? null)
            || ! empty(($t->metadata ?? [])['nfe_chave'] ?? null);
        $semNf = $recebiveis->reject($temNf);
        $pctComNf = $recebiveis->isEmpty()
            ? 100
            : (int) round(($recebiveis->count() - $semNf->count()) / $recebiveis->count() * 100);

        return Inertia::render('Financeiro/Impostos/Index', [
            'kpis' => [
                'a_recolher' => ['valor' => round($aRecolher, 2), 'qtd' => count($abertas)],
                'proxima' => $abertas[0] ?? null,
                'pct_com_nf' => $pctComNf,
                'sem_nf_qtd' => $semNf->count(),
            ],
            'guias' => $guias,
            'calendario' => $abertas,
            'sem_nf' => $semNf->take(5)->map(fn (Titulo $t) => [
                'id' => $t->id,
                'numero' => $t->numero,
                'contraparte' => (string) ($t->cliente_descricao ?: '—'),
                'valor' => (float) $t->valor_total,
            ])->values(),
            'receita_recebida' => $receitaRecebida,
            'das_rate' => self::DAS_RATE,
            'periodLabel' => ucfirst($start->locale('pt_BR')->isoFormat('MMMM YYYY')),
            'businessName' => (string) session('business.name', ''),
        ]);
    }

    /**
     * POST /financeiro/impostos/lancar
     * "Lançar a pagar" — cria o título payable da guia DAS estimada no caixa
     * unificado (costura, espelha o protótipo). Valor SEMPRE recalculado no
     * servidor (anti tampering). Idempotente por metadata.guia.
     */
    public function lancar(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'competencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $comp = $validated['competencia'];
        $guiaKey = "das-{$comp}";

        if ($existente = $this->tituloDaGuia($businessId, $guiaKey)) {
            return back()->with('success', "Guia já lançada como {$existente->numero}.");
        }

        $start = Carbon::createFromFormat('Y-m-d', $comp.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $valor = round($this->receitaRecebidaMes($businessId, $start, $end) * self::DAS_RATE, 2);

        if ($valor <= 0) {
            return back()->with('error', 'Sem receita recebida na competência — nada a lançar.');
        }

        $vencimento = (clone $start)->addMonthNoOverflow()->setDay(20)->toDateString();

        $titulo = DB::transaction(function () use ($businessId, $userId, $comp, $guiaKey, $valor, $vencimento) {
            // Numero sequencial business-isolado P-NNNNN (mesmo padrão do
            // UnificadoController::store, R-FIN-002 idempotência via lock).
            $proximoNumero = (int) Titulo::where('business_id', $businessId)
                ->where('numero', 'like', 'P-%')
                ->lockForUpdate()
                ->selectRaw('MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n')
                ->value('max_n');
            $numero = sprintf('P-%05d', max(1, $proximoNumero + 1));

            return Titulo::create([
                'business_id' => $businessId,
                'numero' => $numero,
                'tipo' => 'pagar',
                'status' => 'aberto',
                'cliente_descricao' => 'DAS · Simples Nacional',
                'valor_total' => $valor,
                'valor_aberto' => $valor,
                'moeda' => 'BRL',
                'emissao' => now()->toDateString(),
                'vencimento' => $vencimento,
                'competencia_mes' => $comp,
                'origem' => 'manual',
                'origem_id' => null,
                'observacoes' => "Guia DAS estimada (≈6% s/ recebido, regime caixa) — competência {$comp}. Estimativa: apuração oficial no módulo Fiscal.",
                'metadata' => [
                    'guia' => $guiaKey,
                    'descricao' => "DAS · Simples Nacional · {$this->compLabel($comp)} (estimado ≈6%)",
                ],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });

        TituloCriado::dispatch($titulo);

        return back()->with('success', "Guia DAS lançada a pagar como {$titulo->numero} (vence ".Carbon::parse($vencimento)->format('d/m').').');
    }

    /**
     * Receita RECEBIDA no período — base do DAS (regime caixa). Espelha o card
     * "Recebido" do kpisCore: baixas sem estorno, título receber não-cancelado,
     * valor real (valor_baixa + juros + multa - desconto).
     */
    private function receitaRecebidaMes(int $businessId, Carbon $start, Carbon $end): float
    {
        return (float) TituloBaixa::where('business_id', $businessId)
            ->whereNull('estorno_de_id')
            ->whereBetween('data_baixa', [$start->toDateString(), $end->toDateString()])
            ->whereHas('titulo', fn ($t) => $t->where('tipo', 'receber')->where('status', '!=', 'cancelado'))
            ->selectRaw('COALESCE(SUM(valor_baixa + juros + multa - desconto), 0) as total')
            ->value('total');
    }

    private function tituloDaGuia(int $businessId, string $guiaKey): ?Titulo
    {
        return Titulo::where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelado')
            ->where('metadata->guia', $guiaKey)
            ->first();
    }

    private function compLabel(string $comp): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $comp.'-01')->locale('pt_BR')->isoFormat('MMMM YYYY');
        } catch (\Throwable) {
            return $comp ?: '—';
        }
    }
}
