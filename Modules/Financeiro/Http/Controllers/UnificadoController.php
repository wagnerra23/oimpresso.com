<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Requests\UpdateTituloRequest;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Tela /financeiro/unificado — Visão Unificada (Cockpit V2).
 *
 * Origem: protótipo Cowork aprovado por [W] em 2026-05-09.
 * Persona-foco: Eliana [E] — financeiro de escritório, densidade alta, atalhos teclado.
 * Stories: US-FIN-013 (dashboard unificado) e US-FIN-020 (visão unificada cockpit V2).
 *
 * Decisões aplicadas (esquema real vs protótipo):
 *  - FinancialEntry  -> Titulo
 *  - BankAccount     -> ContaBancaria
 *  - kind            -> tipo (receber|pagar) mapeado pra receivable|payable no shape
 *  - status          -> derivado de Titulo.status + vencimento (recebido|pago|atrasado|vencendo|aberto)
 *  - BaixaService    -> lógica inline (mesmo padrão de ContaPagarController::pagar)
 */
class UnificadoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');
        $hoje = now()->toDateString();
        $vencendoLimite = now()->addDays(7)->toDateString();

        $filters = $this->parseFilters($request);
        [$start, $end] = $this->parsePeriodo($filters['periodo']);

        // ───────────────── Tabela ─────────────────
        $q = Titulo::query()
            ->where('business_id', $businessId)
            ->whereBetween('vencimento', [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')
            ->whereIn('status', ['aberto', 'parcial', 'quitado'])
            ->with([
                'categoria:id,nome',
                'conferidoPor:id,first_name,last_name,username',
                'baixas' => fn ($q) => $q->orderByDesc('data_baixa'),
                'baixas.contaBancaria.account:id,name',
            ]);

        // Tabs
        match ($filters['tab']) {
            'open' => $q->whereIn('status', ['aberto', 'parcial']),
            'rec' => $q->where('tipo', 'receber')->whereIn('status', ['aberto', 'parcial']),
            'pay' => $q->where('tipo', 'pagar')->whereIn('status', ['aberto', 'parcial']),
            'received' => $q->where('tipo', 'receber')->where('status', 'quitado'),
            'paid' => $q->where('tipo', 'pagar')->where('status', 'quitado'),
            'late' => $q->whereIn('status', ['aberto', 'parcial'])->where('vencimento', '<', $hoje),
            default => null,
        };

        if ($filters['conta']) {
            $q->whereHas('baixas', fn ($qq) => $qq->where('conta_bancaria_id', $filters['conta']));
        }
        if ($filters['categoria']) {
            $q->where('categoria_id', $filters['categoria']);
        }
        if ($filters['busca'] !== '') {
            $busca = '%'.$filters['busca'].'%';
            $q->where(function ($qq) use ($busca) {
                $qq->where('cliente_descricao', 'like', $busca)
                    ->orWhere('numero', 'like', $busca)
                    ->orWhere('observacoes', 'like', $busca);
            });
        }

        $rows = $q->orderBy('vencimento')
            ->limit(500)
            ->get()
            ->map(fn (Titulo $t) => $this->shapeTitulo($t, $hoje, $vencendoLimite));

        // ───────────────── KPIs ─────────────────
        $kpis = $this->kpis($businessId, $start, $end);

        // ───────────────── Listas pra filtros ─────────────────
        $contas = ContaBancaria::where('business_id', $businessId)
            ->with('account:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ContaBancaria $c) => [
                'id' => $c->id,
                'nome' => $c->nome,
            ]);

        $categorias = Categoria::where('business_id', $businessId)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get(['id', 'nome']);

        // Header: período em PT-BR (ex "Maio 2026") + nome do business logado.
        // Antes era hardcoded "Maio 2026 · ROTA LIVRE" no .tsx — bug crítico
        // ao logar com biz != ROTA LIVRE (ex: WR2 Sistemas via Wagner).
        $periodLabel = ucfirst($start->locale('pt_BR')->isoFormat('MMMM YYYY'));
        $businessName = (string) session('business.name', '');

        return Inertia::render('Financeiro/Unificado/Index', [
            'kpis' => $kpis,
            'lancamentos' => $rows,
            'filters' => $filters,
            'contas' => $contas,
            'categorias' => $categorias,
            'periodLabel' => $periodLabel,
            'businessName' => $businessName,
        ]);
    }

    /**
     * GET /financeiro/unificado/novo
     * Stub — formulário unificado de novo lançamento ainda não foi implementado.
     * Por enquanto oferece picker entre receber/pagar e redireciona pra rotas
     * existentes. Substituir por modal/sheet inline quando US-FIN-XXX entrar.
     */
    public function novo(): Response
    {
        return Inertia::render('Financeiro/Unificado/Novo', [
            'breadcrumbs' => [
                ['label' => 'Financeiro', 'href' => '/financeiro'],
                ['label' => 'Visão unificada', 'href' => '/financeiro/unificado'],
                ['label' => 'Novo lançamento', 'href' => null],
            ],
        ]);
    }

    /**
     * PUT /financeiro/unificado/{id}
     * Edit Sheet inline (Onda Edit 2026-05-18). Campos seguros: descricao,
     * observacoes, categoria_id, vencimento. valor_total mutável só se status
     * aberto/parcial (ADR fin-tech/0002 imutabilidade pós-baixa).
     */
    public function update(UpdateTituloRequest $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);
        $request->assertValorMutavel($titulo);

        $payload = [
            'cliente_descricao' => $request->input('cliente_descricao'),
            'observacoes' => $request->input('observacoes'),
            'categoria_id' => $request->input('categoria_id') ?: null,
            'vencimento' => $request->date('vencimento')->toDateString(),
            'updated_by' => $request->user()->id,
        ];

        if ($request->has('valor_total')) {
            $valorTotal = (float) $request->input('valor_total');
            // Recalcula valor_aberto preservando baixas existentes.
            $somaBaixas = (float) $titulo->baixas()->sum('valor_baixa');
            $payload['valor_total'] = $valorTotal;
            $payload['valor_aberto'] = max(0, $valorTotal - $somaBaixas);
        }

        $titulo->fill($payload)->save();

        return back()->with('success', "Lançamento {$titulo->numero} atualizado.");
    }

    /**
     * POST /financeiro/unificado/{id}/conferir
     * Marca título como conferido pelo user atual (Eliana/Wagner — audit per-user).
     * Idempotente: re-marcar não altera timestamp original.
     */
    public function conferir(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        if ($titulo->conferido_by !== null) {
            return back()->with('info', 'Lançamento já conferido.');
        }

        $titulo->conferido_by = $request->user()->id;
        $titulo->conferido_at = now();
        $titulo->save();

        return back()->with('success', 'Lançamento conferido.');
    }

    /**
     * DELETE /financeiro/unificado/{id}/conferir
     * Desmarca conferido (caso usuário detecte que conferiu por engano).
     */
    public function unconferir(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        $titulo->conferido_by = null;
        $titulo->conferido_at = null;
        $titulo->save();

        return back()->with('success', 'Conferência removida.');
    }

    /**
     * POST /financeiro/unificado/{id}/baixar
     * 1-clique: marca como recebido/pago com data=hoje, conta=primeira ativa do tenant.
     * Lógica inline (mesmo padrão de ContaPagarController::pagar).
     */
    public function baixar(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        if ($titulo->status === 'quitado' || $titulo->status === 'cancelado') {
            return back()->with('error', 'Titulo ja '.$titulo->status.'. Nao pode receber baixa.');
        }

        $conta = ContaBancaria::where('business_id', $businessId)
            ->where('ativo_para_boleto', true)
            ->orderBy('id')
            ->first()
            ?: ContaBancaria::where('business_id', $businessId)->orderBy('id')->first();

        if (! $conta) {
            return back()->with('error', 'Sem conta bancária cadastrada. Cadastre em /financeiro/contas-bancarias.');
        }

        $valor = (float) $titulo->valor_aberto;

        TituloBaixa::create([
            'business_id' => $businessId,
            'titulo_id' => $titulo->id,
            'conta_bancaria_id' => $conta->id,
            'valor_baixa' => $valor,
            'data_baixa' => now()->toDateString(),
            'meio_pagamento' => 'transferencia',
            'idempotency_key' => (string) Str::uuid(),
            'observacoes' => 'Baixa rápida via Visão Unificada',
            'created_by' => $request->user()->id,
        ]);

        $titulo->valor_aberto = 0;
        $titulo->status = 'quitado';
        $titulo->save();

        $msg = $titulo->tipo === 'receber' ? 'Recebimento confirmado' : 'Pagamento confirmado';

        return back()->with('success', $msg);
    }

    // ─────────── Helpers privados ───────────

    private function parseFilters(Request $request): array
    {
        $tabsValidas = ['all', 'open', 'rec', 'pay', 'received', 'paid', 'late'];
        $densidadesValidas = ['compact', 'comfortable', 'spacious'];
        $periodosValidos = ['mes_atual', 'mes_anterior', '30d', '90d'];

        return [
            'tab' => in_array($request->string('tab')->toString(), $tabsValidas, true)
                ? $request->string('tab')->toString() : 'all',
            'busca' => trim($request->string('busca', '')->toString()),
            'conta' => $request->string('conta', '')->toString(),
            'categoria' => $request->string('categoria', '')->toString(),
            'periodo' => in_array($request->string('periodo')->toString(), $periodosValidos, true)
                ? $request->string('periodo')->toString() : 'mes_atual',
            'densidade' => in_array($request->string('densidade')->toString(), $densidadesValidas, true)
                ? $request->string('densidade')->toString() : 'comfortable',
        ];
    }

    private function parsePeriodo(string $periodo): array
    {
        return match ($periodo) {
            'mes_anterior' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            '30d' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            '90d' => [now()->subDays(90)->startOfDay(), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function kpis(int $businessId, Carbon $start, Carbon $end): array
    {
        $base = Titulo::where('business_id', $businessId)
            ->whereBetween('vencimento', [$start->toDateString(), $end->toDateString()]);

        $rec = (clone $base)->where('tipo', 'receber');
        $pay = (clone $base)->where('tipo', 'pagar');

        $aReceber = (clone $rec)->whereIn('status', ['aberto', 'parcial']);
        $aPagar = (clone $pay)->whereIn('status', ['aberto', 'parcial']);

        // Recebido/Pago no período: soma TituloBaixa.valor_baixa via data_baixa.
        $recebido = TituloBaixa::where('business_id', $businessId)
            ->whereBetween('data_baixa', [$start->toDateString(), $end->toDateString()])
            ->whereNull('estorno_de_id')
            ->whereHas('titulo', fn ($q) => $q->where('tipo', 'receber'));

        $pago = TituloBaixa::where('business_id', $businessId)
            ->whereBetween('data_baixa', [$start->toDateString(), $end->toDateString()])
            ->whereNull('estorno_de_id')
            ->whereHas('titulo', fn ($q) => $q->where('tipo', 'pagar'));

        $saldoAtual = (float) ContaBancaria::where('business_id', $businessId)->sum('saldo_cached');
        $saldoPrevisto = $saldoAtual
            + (float) (clone $aReceber)->sum('valor_aberto')
            - (float) (clone $aPagar)->sum('valor_aberto');

        return [
            'saldo_previsto' => (float) $saldoPrevisto,
            'recebido' => [
                'valor' => (float) (clone $recebido)->sum('valor_baixa'),
                'qtd' => (clone $recebido)->count(),
            ],
            'a_receber' => [
                'valor' => (float) (clone $aReceber)->sum('valor_aberto'),
                'qtd' => (clone $aReceber)->count(),
            ],
            'pago' => [
                'valor' => (float) (clone $pago)->sum('valor_baixa'),
                'qtd' => (clone $pago)->count(),
            ],
            'a_pagar' => [
                'valor' => (float) (clone $aPagar)->sum('valor_aberto'),
                'qtd' => (clone $aPagar)->count(),
            ],
        ];
    }

    /**
     * Mapeia Titulo (schema real) pro shape esperado pelo Index.tsx (schema do protótipo).
     */
    private function shapeTitulo(Titulo $t, string $hoje, string $vencendoLimite): array
    {
        $kind = $t->tipo === 'receber' ? 'receivable' : 'payable';
        $status = $this->deriveStatus($t, $hoje, $vencendoLimite);

        $ultimaBaixa = $t->relationLoaded('baixas') ? $t->baixas->first() : null;
        $contaBancariaNome = $ultimaBaixa?->contaBancaria?->nome
            ?? $ultimaBaixa?->contaBancaria?->account?->name
            ?? '—';

        $metadata = $t->metadata ?? [];
        $nfeNumero = $metadata['nfe_numero'] ?? $metadata['nfe_chave'] ?? null;
        $canal = $metadata['canal'] ?? $t->origem;

        $descricao = $t->cliente_descricao
            ?: ($metadata['descricao'] ?? "Titulo {$t->numero}");

        $conferidoUser = $t->relationLoaded('conferidoPor') ? $t->conferidoPor : null;
        $conferidoNome = $conferidoUser
            ? trim(($conferidoUser->first_name ?? '').' '.($conferidoUser->last_name ?? '')) ?: ($conferidoUser->username ?? null)
            : null;

        return [
            'id' => $t->id,
            'kind' => $kind,
            'status' => $status,
            'descricao' => $descricao,
            'contraparte' => $t->cliente_descricao ?? '—',
            'contraparte_doc' => $metadata['cliente_documento'] ?? null,
            'categoria' => $t->categoria?->nome ?? 'Sem categoria',
            'categoria_id' => $t->categoria_id,
            'conta_bancaria' => $contaBancariaNome,
            'vencimento' => $t->vencimento?->toDateString(),
            'vencimento_label' => $t->vencimento?->locale('pt_BR')->isoFormat('ddd, DD MMM'),
            'liquidacao' => $ultimaBaixa?->data_baixa?->locale('pt_BR')->isoFormat('DD MMM'),
            'valor' => (float) $t->valor_total,
            'nfe_numero' => $nfeNumero,
            'canal' => $canal,
            'observacao' => $t->observacoes,
            'conferido_by' => $t->conferido_by,
            'conferido_at' => $t->conferido_at?->toIso8601String(),
            'conferido_user_nome' => $conferidoNome,
            'valor_mutavel' => ! in_array($t->status, ['quitado', 'cancelado'], true),
        ];
    }

    private function deriveStatus(Titulo $t, string $hoje, string $vencendoLimite): string
    {
        if ($t->status === 'quitado') {
            return $t->tipo === 'receber' ? 'recebido' : 'pago';
        }

        $venc = $t->vencimento?->toDateString();
        if ($venc !== null && $venc < $hoje) {
            return 'atrasado';
        }
        if ($venc !== null && $venc <= $vencendoLimite) {
            return 'vencendo';
        }

        return 'aberto';
    }
}
