<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
use Modules\Financeiro\Http\Requests\UpdateTituloRequest;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Models\TituloComment;
use Spatie\Activitylog\Models\Activity;

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
    use RendersMockCowork;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request): Response|\Illuminate\Http\Response
    {
        // Wagner 2026-05-18 Mock Cowork Mode (config/financeiro.php).
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

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

        // Onda Polish 2026-05-18 — lifecycle multi-select + toggle overdue independente.
        // Lifecycle items combinam via OR (union); overdue é AND multiplicativo.
        // Back-compat: se vazio + tab legacy presente, fallback pro mapping antigo.
        if (! empty($filters['lifecycle'])) {
            $q->where(function ($qq) use ($filters) {
                foreach ($filters['lifecycle'] as $lc) {
                    $qq->orWhere(function ($qqq) use ($lc) {
                        match ($lc) {
                            'ar' => $qqq->where('tipo', 'receber')->whereIn('status', ['aberto', 'parcial']),
                            're' => $qqq->where('tipo', 'receber')->where('status', 'quitado'),
                            'ap' => $qqq->where('tipo', 'pagar')->whereIn('status', ['aberto', 'parcial']),
                            'pa' => $qqq->where('tipo', 'pagar')->where('status', 'quitado'),
                            default => null,
                        };
                    });
                }
            });
        } else {
            // Back-compat tab legacy (bookmarks/links antigos).
            match ($filters['tab']) {
                'open' => $q->whereIn('status', ['aberto', 'parcial']),
                'rec' => $q->where('tipo', 'receber')->whereIn('status', ['aberto', 'parcial']),
                'pay' => $q->where('tipo', 'pagar')->whereIn('status', ['aberto', 'parcial']),
                'received' => $q->where('tipo', 'receber')->where('status', 'quitado'),
                'paid' => $q->where('tipo', 'pagar')->where('status', 'quitado'),
                'late' => $q->whereIn('status', ['aberto', 'parcial'])->where('vencimento', '<', $hoje),
                default => null,
            };
        }

        // Toggle "Só atrasados" — AND multiplicativo (combina com lifecycle).
        if (! empty($filters['overdue'])) {
            $q->whereIn('status', ['aberto', 'parcial'])
              ->where('vencimento', '<', $hoje);
        }

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

    // ─────────── Comments + Audit (Onda DB 2026-05-18) ───────────

    /**
     * GET /financeiro/unificado/{tituloId}/comments
     *
     * Lista comments paginated do título — Tier 0 multi-tenant via session
     * (TituloComment usa BusinessScope trait + filtra titulo via business_id explícito
     * pra evitar IDOR cross-tenant). Eager load user (first_name, last_name, username)
     * pra renderizar autor sem N+1.
     *
     * Response shape (espelha contrato JSX FinCommentsThread):
     *   { comments: [{ id, body, author, when, user_id }], total }
     */
    public function comments(int $tituloId): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        // Confirma que título pertence ao business antes de listar comments
        // (defesa-em-profundidade contra IDOR — BusinessScope já filtra, mas
        // sem o título a UI nem deveria estar mostrando o drawer).
        $titulo = Titulo::where('business_id', $businessId)->find($tituloId);
        if (! $titulo) {
            return response()->json(['comments' => [], 'total' => 0], 404);
        }

        $rows = TituloComment::query()
            ->where('business_id', $businessId)
            ->where('titulo_id', $tituloId)
            ->with('user:id,first_name,last_name,username')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $comments = $rows->map(function (TituloComment $c) {
            $u = $c->user;
            $author = $u
                ? (trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->username ?? 'Usuário'))
                : 'Usuário removido';

            return [
                'id' => $c->id,
                'body' => $c->body,
                'author' => $author,
                'user_id' => $c->user_id,
                'when' => $c->created_at?->locale('pt_BR')->isoFormat('DD/MM HH:mm'),
                'when_iso' => $c->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'comments' => $comments,
            'total' => $comments->count(),
        ]);
    }

    /**
     * POST /financeiro/unificado/{tituloId}/comments
     *
     * Cria comment. Body validado: 1-2000 chars. business_id derivado da session,
     * user_id do auth user. Idempotência: NÃO aplica — comments append-only,
     * dupla submissão = 2 rows (Eliana enxerga e remove manualmente se quiser).
     *
     * Response shape igual ao GET (1 elemento), pra bridge JS já renderizar
     * sem refetch.
     */
    public function addComment(Request $request, int $tituloId): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->find($tituloId);
        if (! $titulo) {
            return response()->json(['error' => 'Título não pertence ao business.'], 404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $comment = TituloComment::create([
            'business_id' => $businessId,
            'titulo_id' => $tituloId,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $u = $request->user();
        $author = trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->username ?? 'Usuário');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'author' => $author,
                'user_id' => $comment->user_id,
                'when' => $comment->created_at?->locale('pt_BR')->isoFormat('DD/MM HH:mm'),
                'when_iso' => $comment->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /financeiro/unificado/{tituloId}/audit
     *
     * Trilha de auditoria read-only — agrega rows da `activity_log` (Spatie
     * ActivityLog) com subject_type=Titulo + subject_id=$tituloId, filtrado por
     * business_id pra Tier 0 safety.
     *
     * Shape espelha contrato JSX FinAuditTrail (financeiro-curation.jsx):
     *   [{ when, who, action, diff?: { field, from, to } }]
     *
     * Action é derivada do `event` (created|updated|deleted) + `description`.
     * Diff inferido das properties.old / properties.attributes (Spatie LogsActivity
     * com logOnly + logOnlyDirty popula essas chaves).
     */
    public function auditTrail(int $tituloId): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->find($tituloId);
        if (! $titulo) {
            return response()->json(['entries' => [], 'total' => 0], 404);
        }

        $rows = Activity::query()
            ->where('subject_type', Titulo::class)
            ->where('subject_id', $tituloId)
            ->where('business_id', $businessId)
            ->with('causer:id,first_name,last_name,username')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $entries = $rows->map(function (Activity $a) {
            $causer = $a->causer;
            $who = $causer
                ? (trim(($causer->first_name ?? '').' '.($causer->last_name ?? '')) ?: ($causer->username ?? 'Sistema'))
                : 'Sistema';

            $action = match ($a->event) {
                'created' => 'criou',
                'updated' => 'editou',
                'deleted' => 'deletou',
                default => $a->description ?: ($a->event ?? 'alterou'),
            };

            $entry = [
                'id' => $a->id,
                'when' => $a->created_at?->locale('pt_BR')->isoFormat('DD/MM HH:mm'),
                'when_iso' => $a->created_at?->toIso8601String(),
                'who' => $who,
                'action' => $action,
                'event' => $a->event,
            ];

            // Diff: Spatie ActivityLog grava ['old' => [...], 'attributes' => [...]]
            // quando logOnly + logOnlyDirty + auto-tracking ativo no model.
            // Pegamos primeiro campo alterado (mostra os mais relevantes na UI).
            $props = is_array($a->properties) ? $a->properties : ($a->properties?->toArray() ?? []);
            $old = $props['old'] ?? null;
            $new = $props['attributes'] ?? null;

            if (is_array($old) && is_array($new)) {
                foreach ($new as $field => $to) {
                    $from = $old[$field] ?? null;
                    if ($from !== $to) {
                        $entry['diff'] = [
                            'field' => $field,
                            'from' => $from,
                            'to' => $to,
                        ];
                        break; // primeiro campo alterado representa o evento na UI
                    }
                }
            }

            return $entry;
        });

        return response()->json([
            'entries' => $entries,
            'total' => $entries->count(),
        ]);
    }

    // ─────────── Helpers privados ───────────

    /**
     * Onda 8c 2026-05-18 — Endpoint JSON saldo sparkline 30d.
     *
     * Retorna array de 30 pontos com saldo cumulativo dia-a-dia (D-29 ... hoje).
     * Algoritmo:
     *  1. saldo_atual = SUM(ContaBancaria.saldo_cached)
     *  2. delta_dia = SUM(receber baixas) - SUM(pagar baixas) por data_baixa
     *  3. saldo[D-29] = saldo_atual - SUM(delta_dia[D-28..hoje]) + delta_dia[D-29]
     *     (running sum a partir da baseline 30d atrás)
     *
     * Tier 0 Multi-tenant: filtra business_id explícito (não dependo do global scope
     * via Auth — query é DB direto pra ser barata o suficiente pra request inline).
     *
     * Response: { points: [{date: "2026-04-19", saldo: 1234.56, in: 100, out: 50}, ...], saldo_atual: N }
     */
    public function saldoSparkline(Request $request): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        if ($businessId <= 0) {
            return response()->json(['points' => [], 'saldo_atual' => 0.0], 400);
        }

        $hoje = now()->startOfDay();
        $inicio = (clone $hoje)->subDays(29); // 30 pontos inclusivo
        $inicioStr = $inicio->toDateString();
        $hojeStr = $hoje->toDateString();

        // 1) Saldo atual (snapshot agregado das contas ativas)
        $saldoAtual = (float) ContaBancaria::where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->sum('saldo_cached');

        // 2) Deltas diários: agrega TituloBaixa por data_baixa + tipo Titulo
        $rows = DB::table('fin_titulo_baixas as tb')
            ->join('fin_titulos as t', 't.id', '=', 'tb.titulo_id')
            ->where('tb.business_id', $businessId)
            ->whereNull('tb.estorno_de_id')
            ->whereBetween('tb.data_baixa', [$inicioStr, $hojeStr])
            ->groupBy('tb.data_baixa', 't.tipo')
            ->selectRaw('tb.data_baixa as d, t.tipo as tipo, SUM(tb.valor_baixa) as total')
            ->get();

        // Mapeia date => ['in' => x, 'out' => y]
        $byDate = [];
        foreach ($rows as $r) {
            $date = (string) $r->d;
            if (! isset($byDate[$date])) {
                $byDate[$date] = ['in' => 0.0, 'out' => 0.0];
            }
            if ($r->tipo === 'receber') {
                $byDate[$date]['in'] += (float) $r->total;
            } else {
                $byDate[$date]['out'] += (float) $r->total;
            }
        }

        // 3) Constrói série 30 pontos forward — começamos da baseline (saldo virtual há 30d)
        // saldoBaseline = saldoAtual - sumNetDelta30d
        $sumNetDelta = 0.0;
        foreach ($byDate as $v) {
            $sumNetDelta += ($v['in'] - $v['out']);
        }
        $saldoBaseline = $saldoAtual - $sumNetDelta;

        $points = [];
        $running = $saldoBaseline;
        for ($i = 0; $i < 30; $i++) {
            $date = (clone $inicio)->addDays($i)->toDateString();
            $in = $byDate[$date]['in'] ?? 0.0;
            $out = $byDate[$date]['out'] ?? 0.0;
            $running += ($in - $out);
            $points[] = [
                'date' => $date,
                'saldo' => round($running, 2),
                'in' => round($in, 2),
                'out' => round($out, 2),
            ];
        }

        return response()->json([
            'points' => $points,
            'saldo_atual' => round($saldoAtual, 2),
            'saldo_baseline_30d' => round($saldoBaseline, 2),
            'periodo' => ['inicio' => $inicioStr, 'fim' => $hojeStr],
        ]);
    }

    private function parseFilters(Request $request): array
    {
        $tabsValidas = ['all', 'open', 'rec', 'pay', 'received', 'paid', 'late'];
        $lifecycleValidos = ['ar', 're', 'ap', 'pa'];
        $densidadesValidas = ['compact', 'comfortable', 'spacious'];
        $periodosValidos = ['mes_atual', 'mes_anterior', '30d', '90d'];

        // Onda Polish 2026-05-18 — lifecycle aceita array OR string CSV ("ar,re").
        $lifecycleRaw = $request->input('lifecycle', []);
        if (is_string($lifecycleRaw)) {
            $lifecycleRaw = $lifecycleRaw === '' ? [] : explode(',', $lifecycleRaw);
        }
        if (! is_array($lifecycleRaw)) {
            $lifecycleRaw = [];
        }
        $lifecycle = array_values(array_unique(array_filter(
            array_map('strval', $lifecycleRaw),
            fn ($x) => in_array($x, $lifecycleValidos, true)
        )));

        return [
            'tab' => in_array($request->string('tab')->toString(), $tabsValidas, true)
                ? $request->string('tab')->toString() : 'all',
            'lifecycle' => $lifecycle,
            'overdue' => $request->boolean('overdue'),
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
