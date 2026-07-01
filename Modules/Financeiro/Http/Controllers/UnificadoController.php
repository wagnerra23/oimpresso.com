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
use Modules\Financeiro\Events\TituloCriado;
use Modules\Financeiro\Http\Requests\StoreTituloRequest;
use Modules\Financeiro\Http\Requests\UpdateTituloRequest;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Models\TituloComment;
use Modules\Financeiro\Services\BoletoOcrService;
use Modules\Financeiro\Services\LinhaDigitavelValidator;
use App\Account;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Exceptions\PaymentGatewayException;
use Spatie\Activitylog\Models\Activity;
use Throwable;

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

    public function index(Request $request): Response|\Illuminate\Http\Response
    {

        $businessId = (int) session('user.business_id');
        $hoje = now()->toDateString();
        $vencendoLimite = now()->addDays(7)->toDateString();

        $filters = $this->parseFilters($request);
        [$start, $end] = $this->parsePeriodo($filters['periodo']);
        // Paridade filtros WR (2026-06-03): intervalo explícito sobrepõe o período preset.
        if ($filters['data_inicio'] !== '' && $filters['data_fim'] !== '') {
            try {
                $start = Carbon::parse($filters['data_inicio'])->startOfDay();
                $end = Carbon::parse($filters['data_fim'])->endOfDay();
            } catch (\Throwable $e) { /* intervalo inválido — mantém período */ }
        }

        // ───────────────── Tabela ─────────────────
        $q = Titulo::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->with([
                'categoria:id,nome',
                'planoConta:id,codigo,nome,tipo',
                // `nome` é ACCESSOR (getNomeAttribute → account->name), não coluna —
                // eager-load `:id,nome` gerava `select id, nome` e 500ava (Unknown column
                // 'nome') quando o título tinha conta_bancaria_id. Carrega a FK + account.
                'contaBancaria:id,account_id',
                'contaBancaria.account:id,name',
                'conferidoPor:id,first_name,last_name,username',
                'baixas' => fn ($q) => $q->orderByDesc('data_baixa'),
                'baixas.contaBancaria.account:id,name',
            ]);

        // Filtro "Arquivados" (Wagner 2026-06-03): por padrão a lista mostra só
        // lançamentos ATIVOS (aberto/parcial/quitado) — cancelados/inativos ficam
        // ESCONDIDOS (não somam, não confundem). Com arquivados=true, mostra SÓ os
        // cancelados (arquivados). Os filtros de ativos (lifecycle/overdue/aging/tab)
        // são conceitos de ativos → só aplicam quando NÃO arquivado ($arq abaixo).
        $arq = ! empty($filters['arquivados']);
        if ($arq) {
            $q->where('status', 'cancelado');
        } else {
            $q->whereIn('status', ['aberto', 'parcial', 'quitado']);
        }

        // Filtro por campo de data (default vencimento = comportamento anterior preservado).
        $this->aplicarFiltroData($q, $filters['data_campo'], $start->toDateString(), $end->toDateString());

        // US-FIN-029 (2026-06-10) — lente (Caixa · A receber · A pagar) é a camada 1 do
        // filtro grosso; chips lifecycle refinam DENTRO da lente (charter v14, direção
        // [W] 2026-05-31). Interseção vazia = lente inteira (chip incompatível enviado
        // por bookmark/tampering não derruba a query — defense in depth, o frontend já
        // esconde chips incompatíveis).
        $lenteSets = [
            'caixa' => ['ar', 're', 'ap', 'pa'],
            'receber' => ['ar', 're'],
            'pagar' => ['ap', 'pa'],
        ];
        $lenteSet = $lenteSets[$filters['lente']] ?? $lenteSets['caixa'];

        // Onda Polish 2026-05-18 — lifecycle multi-select + toggle overdue independente.
        // Lifecycle items combinam via OR (union); overdue é AND multiplicativo.
        // Back-compat: se vazio + tab legacy presente, fallback pro mapping antigo
        // (só na lente default caixa — lente explícita tem precedência sobre tab).
        if (! $arq && (! empty($filters['lifecycle']) || $filters['lente'] !== 'caixa')) {
            $lifecycleEfetivo = array_values(array_intersect($filters['lifecycle'], $lenteSet)) ?: $lenteSet;
            $q->where(function ($qq) use ($lifecycleEfetivo) {
                foreach ($lifecycleEfetivo as $lc) {
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
        } elseif (! $arq) {
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

        // US-FIN-027 (Onda 22) — Filtro workflow aprovação multi-select. AND com lifecycle.
        // 'sem_workflow' = títulos SEM aprovacao_status (NULL); demais filtram pelo valor.
        if (! empty($filters['aprovacao_status'])) {
            $q->where(function ($qq) use ($filters) {
                foreach ($filters['aprovacao_status'] as $st) {
                    if ($st === 'sem_workflow') {
                        $qq->orWhereNull('aprovacao_status');
                    } else {
                        $qq->orWhere('aprovacao_status', $st);
                    }
                }
            });
        }

        // Toggle "Só atrasados" — AND multiplicativo (combina com lifecycle).
        if (! $arq && ! empty($filters['overdue'])) {
            $q->whereIn('status', ['aberto', 'parcial'])
              ->where('vencimento', '<', $hoje);
        }

        // PR E (2026-05-25) US-FIN-022 — Aging buckets multi-select. AND com lifecycle.
        // Buckets canon BR financeiro: lt30 / 30-60 / 60-90 / gt90 / gt180 dias vencidos.
        // Cada bucket vira range de vencimento absoluto (data hoje - N dias).
        if (! $arq && ! empty($filters['aging'])) {
            $today = now();
            $q->whereIn('status', ['aberto', 'parcial'])
              ->where('vencimento', '<', $hoje)
              ->where(function ($qq) use ($filters, $today) {
                  foreach ($filters['aging'] as $bucket) {
                      $qq->orWhere(function ($qqq) use ($bucket, $today) {
                          [$min, $max] = match ($bucket) {
                              'lt30' => [0, 29],
                              '30-60' => [30, 59],
                              '60-90' => [60, 89],
                              'gt90' => [90, 179],
                              'gt180' => [180, 99999],
                              default => [null, null],
                          };
                          if ($min === null) {
                              return;
                          }
                          $maxDate = (clone $today)->subDays($min)->toDateString();
                          $minDate = (clone $today)->subDays($max + 1)->toDateString();
                          $qqq->whereBetween('vencimento', [$minDate, $maxDate]);
                      });
                  }
              });
        }

        // Onda 7 (2026-05-20): multi-conta — filters['conta'] agora aceita CSV
        // "1,3,5" (back-compat: 1 single vira "1"). Backend faz whereIn.
        if ($filters['conta']) {
            $contaIds = array_filter(array_map('intval', explode(',', $filters['conta'])));
            if (! empty($contaIds)) {
                $q->whereHas('baixas', fn ($qq) => $qq->whereIn('conta_bancaria_id', $contaIds));
            }
        }
        // Onda 12.7 (2026-05-19) — `filters['categoria']` agora vem da UI como
        // plano_conta_id (Wagner trocou select Categorias por Plano de Contas).
        // Filtra por plano_conta_id. Fallback categoria_id mantido pra back-compat
        // de bookmarks antigos (querystring `categoria=N` onde N é categoria_id).
        if ($filters['categoria']) {
            $q->where(function ($qq) use ($filters) {
                $qq->where('plano_conta_id', $filters['categoria'])
                   ->orWhere('categoria_id', $filters['categoria']);
            });
        }
        if ($filters['busca'] !== '') {
            $busca = '%'.$filters['busca'].'%';
            $q->where(function ($qq) use ($busca) {
                $qq->where('cliente_descricao', 'like', $busca)
                    ->orWhere('numero', 'like', $busca)
                    ->orWhere('observacoes', 'like', $busca);
            });
        }

        // Onda 8 (2026-05-20): sort dinamico whitelist (anti SQL injection).
        // Default: vencimento ASC (canon). Override via filters.sort + filters.dir.
        $sortColMap = [
            'vencimento' => 'vencimento',
            'valor' => 'valor_total',
            'status' => 'status',
            'lancamento' => 'numero',
            'contraparte' => 'cliente_descricao',
        ];
        $sortCol = $sortColMap[$filters['sort']] ?? 'vencimento';
        $sortDir = $filters['dir'] === 'desc' ? 'desc' : 'asc';

        // Onda 13 (2026-05-20): pagination via per_page + page. Default 100 per_page
        // (cobre maioria dos mes pra Eliana sem scroll infinito). Antes era limit(500)
        // fixo — biz grande passaria desse limite e perderia dados visualmente.
        $perPage = max(20, min(500, (int) ($filters['per_page'] ?? 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalRows = (clone $q)->count();
        $rows = $q->orderBy($sortCol, $sortDir)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn (Titulo $t) => $this->shapeTitulo($t, $hoje, $vencendoLimite));

        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'total_pages' => max(1, (int) ceil($totalRows / $perPage)),
        ];

        // ───────────────── KPIs ─────────────────
        // Paridade filtros WR (2026-06-03): cards usam o MESMO data_campo da tabela
        // (antes vencimento fixo) — RECEBIDO/A RECEBER/PAGO/A PAGAR ficam consistentes
        // com a lista filtrada.
        $kpis = $this->kpis($businessId, $start, $end, $filters['data_campo']);

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

        // Onda 12.7 (2026-05-19) — Plano de Contas hierárquico (Wagner pediu trocar
        // 'categorias livres' por estrutura contábil). Filtra só contas que aceitam
        // lançamento (folhas) + ativas. Ordenado por código pra hierarquia visual.
        $planosConta = PlanoConta::where('business_id', $businessId)
            ->where('ativo', true)
            ->where('aceita_lancamento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome', 'tipo', 'nivel']);

        // Header: período em PT-BR (ex "Maio 2026") + nome do business logado.
        // Antes era hardcoded "Maio 2026 · ROTA LIVRE" no .tsx — bug crítico
        // ao logar com biz != ROTA LIVRE (ex: WR2 Sistemas via Wagner).
        $periodLabel = ucfirst($start->locale('pt_BR')->isoFormat('MMMM YYYY'));
        $businessName = (string) session('business.name', '');

        return Inertia::render('Financeiro/Unificado/Index', [
            'kpis' => $kpis,
            'lancamentos' => $rows,
            'pagination' => $pagination, // Onda 13 (2026-05-20)
            'filters' => $filters,
            'contas' => $contas,
            'categorias' => $categorias,
            'planosConta' => $planosConta,
            // PR E (2026-05-25) US-FIN-022 — aging breakdown pra chips de filtro
            'agingBreakdown' => $this->agingBreakdown($businessId),
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
     * POST /financeiro/unificado
     * Onda 25 (2026-05-25) US-FIN-021 — Insert manual de título via TituloCreateSheet.
     * Substitui stub `/unificado/novo` (Non-Goal #1 do charter v6).
     *
     * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): business_id da session, nunca
     * do payload (anti tampering). Numero sequencial business-isolado via
     * `lockForUpdate` (R-FIN-002 idempotência).
     */
    public function store(StoreTituloRequest $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = $request->user()->id;
        $request->assertPlanoCoerente();

        $titulo = DB::transaction(function () use ($request, $businessId, $userId) {
            // Numero sequencial business-isolado.
            // Padrão: receber → R-0001, R-0002… · pagar → P-0001, P-0002…
            $prefixo = $request->input('tipo') === 'receber' ? 'R' : 'P';
            $proximoNumero = (int) Titulo::where('business_id', $businessId)
                ->where('numero', 'like', "{$prefixo}-%")
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n")
                ->value('max_n');
            $proximoNumero = max(1, $proximoNumero + 1);
            $numero = sprintf('%s-%05d', $prefixo, $proximoNumero);

            $valor = (float) $request->input('valor_total');

            return Titulo::create([
                'business_id'       => $businessId,
                'numero'            => $numero,
                'tipo'              => $request->input('tipo'),
                'status'            => 'aberto',
                'cliente_descricao' => $request->input('cliente_descricao'),
                'valor_total'       => $valor,
                'valor_aberto'      => $valor,
                'moeda'             => 'BRL',
                'emissao'           => now()->toDateString(),
                'vencimento'        => $request->date('vencimento')->toDateString(),
                'competencia_mes'   => now()->format('Y-m'),
                'origem'            => 'manual',
                'origem_id'         => null,
                'categoria_id'      => $request->input('categoria_id') ?: null,
                'plano_conta_id'    => $request->input('plano_conta_id') ?: null,
                'forma_pagamento'   => $request->input('forma_pagamento') ?: null,
                'observacoes'       => $request->input('observacoes'),
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);
        });

        $label = $titulo->tipo === 'receber' ? 'a receber' : 'a pagar';

        // PR F (2026-05-25) G9 — Event TituloCriado abre caminho de extensão
        // (notify fornecedor, recalcular cache KPIs, sincronizar accounting).
        // Listener canônico inicial: OnTituloCriadoLog (audit log INFO).
        TituloCriado::dispatch($titulo);

        return back()->with('success', "Lançamento {$titulo->numero} ({$label}) criado.");
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
        $request->assertPlanoCoerente($titulo);

        $payload = [
            'cliente_descricao' => $request->input('cliente_descricao'),
            'observacoes' => $request->input('observacoes'),
            'categoria_id' => $request->input('categoria_id') ?: null,
            'plano_conta_id' => $request->input('plano_conta_id') ?: null,
            'forma_pagamento' => $request->input('forma_pagamento') ?: null,
            'conta_bancaria_id' => $request->input('conta_bancaria_id') ?: null,
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
     * Onda 15 (2026-05-20) — Bulk update categoria em lote.
     * POST /financeiro/unificado/bulk-update-categoria
     * Body: { ids: number[], categoria_id: number }
     *
     * Tier 0 multi-tenant: business_id scope explicito (R-FIN-001).
     * Whitelist categoria pertence ao business (anti cross-tenant via ID guess).
     */
    public function bulkUpdateCategoria(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|min:1',
            'categoria_id' => 'required|integer|min:1',
        ]);

        // Whitelist: categoria precisa pertencer ao business (anti SQL injection
        // via ID arbitrario que vaze pra outro tenant).
        $catExists = Categoria::where('business_id', $businessId)
            ->where('id', $validated['categoria_id'])
            ->whereNull('deleted_at')
            ->exists();
        if (! $catExists) {
            return back()->withErrors(['categoria_id' => 'Categoria inválida pra este business.']);
        }

        $count = Titulo::where('business_id', $businessId)
            ->whereIn('id', $validated['ids'])
            ->whereNull('deleted_at')
            ->update(['categoria_id' => $validated['categoria_id']]);

        return back()->with('flash', "$count lançamentos categorizados em lote.");
    }

    /**
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

        // Conta de destino: escolhida pelo usuário (validada no business — anti
        // cross-tenant) OU auto-pick legacy (default quando baixa rápida sem body).
        if ($request->filled('conta_bancaria_id')) {
            $conta = ContaBancaria::where('business_id', $businessId)
                ->find((int) $request->input('conta_bancaria_id'));
            if (! $conta) {
                return back()->with('error', 'Conta bancária inválida (não pertence a este negócio).');
            }
        } else {
            $conta = ContaBancaria::where('business_id', $businessId)
                ->where('ativo_para_boleto', true)
                ->orderBy('id')
                ->first()
                ?: ContaBancaria::where('business_id', $businessId)->orderBy('id')->first();
        }

        if (! $conta) {
            return back()->with('error', 'Sem conta bancária cadastrada. Cadastre em /financeiro/contas-bancarias.');
        }

        // Valor: escolhido (suporta baixa PARCIAL) OU valor aberto cheio (default).
        // Clamp 0.01..valor_aberto pra nunca baixar mais que o devido.
        $aberto = (float) $titulo->valor_aberto;
        $valor = $request->filled('valor_baixa') ? (float) $request->input('valor_baixa') : $aberto;
        $valor = max(0.01, min($valor, $aberto));

        // Forma de pagamento: escolhida (valida enum canônico) OU 'transferencia' (default legacy).
        $meio = (string) $request->input('meio_pagamento', 'transferencia');
        if (! in_array($meio, Titulo::FORMAS_PAGAMENTO, true)) {
            $meio = 'transferencia';
        }

        $dataBaixa = $request->filled('data_baixa')
            ? $request->date('data_baixa')->toDateString()
            : now()->toDateString();

        // Plano de contas escolhido na hora de receber (opcional) — valida business + grava no título.
        if ($request->filled('plano_conta_id')) {
            $plano = PlanoConta::where('business_id', $businessId)->find((int) $request->input('plano_conta_id'));
            if ($plano) {
                $titulo->plano_conta_id = $plano->id;
            }
        }

        $restante = round($aberto - $valor, 2);
        $parcial = $restante > 0.001;

        DB::transaction(function () use ($businessId, $titulo, $conta, $valor, $meio, $dataBaixa, $request, $parcial, $restante) {
            if ($parcial) {
                // BAIXA PARCIAL → SPLIT (Wagner 2026-06-04): cria um lançamento FILHO
                // quitado com o valor recebido + reduz o ORIGINAL pro restante (que
                // segue "a receber"/"a pagar"). NÃO usa mais status 'parcial'.
                $prefixo = $titulo->getAttribute('tipo') === 'receber' ? 'R' : 'P';
                $proximo = (int) Titulo::where('business_id', $businessId)
                    ->where('numero', 'like', "{$prefixo}-%")
                    ->lockForUpdate()
                    ->selectRaw("MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n")
                    ->value('max_n');
                $numero = sprintf('%s-%05d', $prefixo, max(1, $proximo + 1));
                $paiNumero = (string) $titulo->getAttribute('numero');

                // replicate() copia os atributos do pai (contraparte, plano, categoria,
                // datas…) e fill() sobrescreve só o que muda — evita acesso dinâmico a
                // ~10 propriedades (PHPStan property.notFound).
                $filho = $titulo->replicate(['conferido_by', 'conferido_at', 'aprovacao_status']);
                $filho->fill([
                    'numero'            => $numero,
                    'status'            => 'quitado',
                    'valor_total'       => $valor,
                    'valor_aberto'      => 0,
                    'origem'            => 'manual', // derivado do split (origem_id null evita colisão UNIQUE)
                    'origem_id'         => null,
                    'titulo_pai_id'     => $titulo->id,
                    'forma_pagamento'   => $meio,
                    'conta_bancaria_id' => $conta->id,
                    'observacoes'       => "Baixa parcial de {$paiNumero}",
                    'created_by'        => $request->user()->id,
                    'updated_by'        => $request->user()->id,
                ]);
                $filho->save();

                TituloBaixa::create([
                    'business_id'       => $businessId,
                    'titulo_id'         => $filho->id,
                    'conta_bancaria_id' => $conta->id,
                    'valor_baixa'       => $valor,
                    'data_baixa'        => $dataBaixa,
                    'meio_pagamento'    => $meio,
                    'idempotency_key'   => (string) Str::uuid(),
                    'observacoes'       => "Baixa parcial de {$paiNumero}",
                    'created_by'        => $request->user()->id,
                ]);

                // Reduz o original pro restante (fill evita acesso dinâmico).
                $titulo->fill(['valor_total' => $restante, 'valor_aberto' => $restante])->save();
            } else {
                // Quitação total: baixa no próprio título.
                TituloBaixa::create([
                    'business_id'       => $businessId,
                    'titulo_id'         => $titulo->id,
                    'conta_bancaria_id' => $conta->id,
                    'valor_baixa'       => $valor,
                    'data_baixa'        => $dataBaixa,
                    'meio_pagamento'    => $meio,
                    'idempotency_key'   => (string) Str::uuid(),
                    'observacoes'       => 'Baixa via Visão Unificada',
                    'created_by'        => $request->user()->id,
                ]);
                $titulo->valor_aberto = 0;
                $titulo->status = 'quitado';
                $titulo->save();
            }
        });

        $base = $titulo->tipo === 'receber' ? 'Recebimento' : 'Pagamento';
        $paiNum = (string) $titulo->getAttribute('numero');
        $msg = $parcial
            ? "{$base} parcial de R$ ".number_format($valor, 2, ',', '.')." — {$paiNum} segue com R$ ".number_format($restante, 2, ',', '.')
            : "{$base} confirmado";

        return back()->with('success', $msg);
    }

    /**
     * POST /financeiro/unificado/{tituloId}/boleto
     *
     * Gera boleto registrado (Banco Inter) PRA UM TÍTULO QUE JÁ EXISTE — direto
     * do drawer da Visão Unificada, sem ir pra /financeiro/cobranca. Reaproveita
     * o MESMO motor real do PaymentGateway que o CobrancaController::store usa
     * ($gateway->for($coreAccount)->emitirBoleto()), que está LIVE em prod biz=1.
     *
     * Anti-duplo-recebível (Tier 0 dinheiro): a cobrança nasce com
     * origem_type='fin_titulo' + origem_id=titulo.id. No pagamento, o listener
     * OnCobrancaPagaCreateFinanceiroTitulo dá BAIXA neste título — em vez de
     * criar um título novo (PG-xxx), o que contaria o recebível em dobro.
     *
     * Pré-condição: conta Banco Inter (banco_codigo 077) ativa para boleto, com
     * credencial PaymentGateway configurada em /settings/payment-gateways. Sem
     * isso, o gateway lança CredentialMisconfiguredException (tratada abaixo).
     */
    public function emitirBoletoTitulo(Request $request, int $tituloId, PaymentGatewayContract $gateway): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($tituloId);

        if ($titulo->getAttribute('tipo') !== 'receber') {
            return back()->with('error', 'Boleto só pode ser gerado para título a receber.');
        }
        if (in_array($titulo->status, ['quitado', 'cancelado'], true)) {
            return back()->with('error', 'Título já '.$titulo->status.' — não gera boleto.');
        }

        // Idempotência leve: se já tem boleto emitido, devolve a linha digitável
        // (a idempotencyKey determinística abaixo também protege no gateway).
        $metadata = $titulo->metadata ?? [];
        if (! empty($metadata['boleto']['linha_digitavel'])) {
            return back()->with('success', 'Boleto já emitido: '.$metadata['boleto']['linha_digitavel']);
        }

        // Conta Banco Inter (077) ativa para boleto neste business.
        $conta = ContaBancaria::where('business_id', $businessId)
            ->where('banco_codigo', '077')
            ->where('ativo_para_boleto', true)
            ->orderBy('id')
            ->first();
        if (! $conta) {
            return back()->with('error', 'Sem conta Banco Inter ativa para boleto. Configure a credencial em /settings/payment-gateways.');
        }

        $coreAccount = Account::query()->find($conta->account_id);
        if (! $coreAccount) {
            return back()->with('error', 'Conta bancária core inválida (account_id).');
        }

        // Vencimento nunca no passado — gateway exige >= hoje. Título atrasado
        // recai pra hoje (multa/juros ficam pra onda futura).
        // vencimento é cast 'date' (Carbon não-nulo). Atrasado → recai pra hoje.
        $vencCarbon = $titulo->vencimento->greaterThan(now())
            ? $titulo->vencimento
            : now();
        $vencimento = new \DateTimeImmutable($vencCarbon->toDateString());

        $valorCentavos = (int) round(((float) $titulo->valor_aberto) * 100);
        if ($valorCentavos < 100) {
            return back()->with('error', 'Valor em aberto abaixo do mínimo de R$ 1,00 para boleto.');
        }

        $numero = (string) $titulo->getAttribute('numero');
        $descricao = $numero !== '' ? "Título {$numero}" : 'Cobrança';

        $input = new EmitirCobrancaInput(
            businessId: $businessId,
            contactId: (int) ($titulo->getAttribute('cliente_id') ?? 0),
            valorCentavos: $valorCentavos,
            vencimento: $vencimento,
            descricao: $descricao,
            idempotencyKey: 'fintitulo-'.$titulo->id,
            origemType: 'fin_titulo',
            origemId: (int) $titulo->id,
            meta: [
                'payer_name'     => $titulo->cliente_descricao,
                'payer_cpf_cnpj' => $metadata['cliente_documento'] ?? null,
                'payer_email'    => $metadata['cliente_email'] ?? null,
            ],
        );

        try {
            $result = $gateway->for($coreAccount)->emitirBoleto($input);
        } catch (CredentialMisconfiguredException $e) {
            // DriverNotSupportedException não chega aqui (Inter suporta boleto); se
            // chegar via for(), cai no catch Throwable abaixo. Catch específico
            // removido pra não regredir o ratchet PHPStan (dead catch).
            return back()->with('error', 'Credencial Inter não configurada para boleto: '.$e->getMessage());
        } catch (InvalidPayerException $e) {
            return back()->with('error', 'Pagador inválido (CPF/CNPJ/endereço incompletos): '.$e->getMessage());
        } catch (GatewayUnavailableException $e) {
            return back()->with('error', 'Banco Inter indisponível agora: '.$e->getMessage());
        } catch (PaymentGatewayException | Throwable $e) {
            report($e);
            return back()->with('error', 'Falha ao gerar boleto. Detalhe registrado no log.');
        }

        // Persiste a linha digitável/nosso número no título pra exibir no drawer.
        $titulo->metadata = array_merge($metadata, [
            'boleto' => [
                'linha_digitavel' => $result->linhaDigitavel,
                'codigo_barras'   => $result->codigoBarras,
                'nosso_numero'    => $result->nossoNumero,
                'cobranca_id'     => $result->cobrancaId,
                'gateway'         => 'inter',
                'emitido_em'      => now()->toIso8601String(),
            ],
        ]);
        $titulo->save();

        return back()->with('success', 'Boleto Inter gerado: '.($result->linhaDigitavel ?: $result->nossoNumero));
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

    /**
     * GET /financeiro/unificado/sugerir-valor
     *
     * PR I (2026-05-25) G5 auditoria — sugere valor pra novo Titulo manual
     * baseado em histórico do par (contraparte + tipo). Retorna último valor +
     * média + count pra UI mostrar "geralmente cobra R$ X (N lançamentos)".
     *
     * Query params:
     *   - contraparte: string (cliente_descricao, like %)
     *   - tipo: 'receber' | 'pagar'
     *
     * Multi-tenant Tier 0 (ADR 0093): scope business_id obrigatório.
     */
    public function sugerirValor(Request $request): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $contraparte = trim($request->string('contraparte', '')->toString());
        $tipo = $request->string('tipo', '')->toString();

        if ($contraparte === '' || ! in_array($tipo, ['receber', 'pagar'], true)) {
            return response()->json(['count' => 0, 'ultimo_valor' => null, 'media_valor' => null]);
        }

        // Match permissivo: like %contraparte% (case insensitive via collation default)
        $base = Titulo::where('business_id', $businessId)
            ->where('tipo', $tipo)
            ->where('cliente_descricao', 'like', "%{$contraparte}%")
            ->whereNotIn('status', ['cancelado']);

        $count = (clone $base)->count();
        if ($count === 0) {
            return response()->json(['count' => 0, 'ultimo_valor' => null, 'media_valor' => null]);
        }

        $ultimo = (clone $base)->orderByDesc('id')->first(['valor_total']);
        $media = (float) (clone $base)->avg('valor_total');

        return response()->json([
            'count'        => $count,
            'ultimo_valor' => (float) $ultimo->valor_total,
            'media_valor'  => round($media, 2),
        ]);
    }

    /**
     * GET /financeiro/unificado/buscar-cliente
     *
     * PR J (2026-05-25) US-FIN-024 — Combobox cliente autocomplete.
     * Busca contacts (clientes + fornecedores) por nome, supplier_business_name,
     * mobile, email ou contact_id (CPF/CNPJ).
     *
     * Multi-tenant Tier 0: scope business_id obrigatório.
     */
    public function buscarCliente(Request $request): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $q = trim($request->string('q', '')->toString());

        if (mb_strlen($q) < 2) {
            return response()->json(['contacts' => []]);
        }

        $like = "%{$q}%";
        $contacts = \App\Contact::where('business_id', $businessId)
            ->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                  ->orWhere('supplier_business_name', 'like', $like)
                  ->orWhere('mobile', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('contact_id', 'like', $like);
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'supplier_business_name', 'mobile', 'type', 'contact_id'])
            ->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'biz'   => $c->supplier_business_name,
                'mobile' => $c->mobile,
                'type'  => $c->type,
                'doc'   => $c->contact_id,
            ]);

        return response()->json(['contacts' => $contacts]);
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
            ->where('t.status', '!=', 'cancelado') // não conta baixas de títulos cancelados (Wagner 2026-06-03)
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
        // US-FIN-027 (Onda 22) — workflow aprovação. Default vazio = sem filtro (mostra todos).
        $aprovacaoValidos = ['pendente', 'aprovado', 'rejeitado', 'sem_workflow'];
        // Onda 12.6 (2026-05-19) — Wagner: default compact + remove spacious.
        // Financeiro persona Eliana = densidade alta. "Espaçoso" não tinha uso.
        $densidadesValidas = ['compact', 'comfortable'];
        $periodosValidos = ['mes_atual', 'mes_anterior', '30d', '90d'];

        // Onda Polish 2026-05-18 — lifecycle aceita array OR string CSV ("ar,re").
        // Onda 12.5 (2026-05-19) — default canon REAL: TODOS lifecycle ATIVOS (canon
        // prototipo-ui/cowork/oimpresso.com.html mostra 4 pills `fin-filter-cb on`
        // por default). Antes era array vazio (todos OFF) — não-paridade visual.
        // Se request explicit `?lifecycle=` (vazio) — respeitar intenção do user.
        $lifecycleRaw = $request->has('lifecycle')
            ? $request->input('lifecycle', [])
            : ['ar', 're', 'ap', 'pa'];
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

        // US-FIN-027 (Onda 22) — parse aprovacao_status[] (CSV ou array).
        $aprovacaoRaw = $request->input('aprovacao_status', []);
        if (is_string($aprovacaoRaw)) {
            $aprovacaoRaw = $aprovacaoRaw === '' ? [] : explode(',', $aprovacaoRaw);
        }
        if (! is_array($aprovacaoRaw)) {
            $aprovacaoRaw = [];
        }
        $aprovacao = array_values(array_unique(array_filter(
            array_map('strval', $aprovacaoRaw),
            fn ($x) => in_array($x, $aprovacaoValidos, true)
        )));

        // PR E (2026-05-25) US-FIN-022 — parse aging[] (CSV ou array). 5 buckets canon BR.
        $agingValidos = ['lt30', '30-60', '60-90', 'gt90', 'gt180'];
        $agingRaw = $request->input('aging', []);
        if (is_string($agingRaw)) {
            $agingRaw = $agingRaw === '' ? [] : explode(',', $agingRaw);
        }
        if (! is_array($agingRaw)) {
            $agingRaw = [];
        }
        $aging = array_values(array_unique(array_filter(
            array_map('strval', $agingRaw),
            fn ($x) => in_array($x, $agingValidos, true)
        )));

        return [
            'tab' => in_array($request->string('tab')->toString(), $tabsValidas, true)
                ? $request->string('tab')->toString() : 'all',
            // US-FIN-029 (2026-06-10) — lente do header (segmented Caixa · A receber ·
            // A pagar). Camada 1 do filtro; chips lifecycle refinam DENTRO da lente.
            // Clamp default 'caixa' (mesmo padrão ?tab= do Fluxo) — deep-link funciona.
            'lente' => in_array($request->string('lente')->toString(), ['receber', 'pagar'], true)
                ? $request->string('lente')->toString() : 'caixa',
            'lifecycle' => $lifecycle,
            'aprovacao_status' => $aprovacao,
            'aging' => $aging,
            'overdue' => $request->boolean('overdue'),
            // Filtro "Arquivados" (Wagner 2026-06-03): mostra só cancelados/inativos.
            'arquivados' => $request->boolean('arquivados'),
            'busca' => trim($request->string('busca', '')->toString()),
            'conta' => $request->string('conta', '')->toString(),
            'categoria' => $request->string('categoria', '')->toString(),
            'periodo' => in_array($request->string('periodo')->toString(), $periodosValidos, true)
                ? $request->string('periodo')->toString() : 'mes_atual',
            // Paridade filtros WR (2026-06-03): campo de data (4 dos 6 do WR).
            // emissao | vencimento(default) | pagamento(via baixa) | competencia.
            // NF/Vendas do WR exigem link titulo->transaction (origem_id) — pendente.
            'data_campo' => in_array($request->string('data_campo')->toString(), ['vencimento', 'emissao', 'pagamento', 'competencia'], true)
                ? $request->string('data_campo')->toString() : 'vencimento',
            'data_inicio' => $request->string('data_inicio', '')->toString(),
            'data_fim' => $request->string('data_fim', '')->toString(),
            'densidade' => in_array($request->string('densidade')->toString(), $densidadesValidas, true)
                ? $request->string('densidade')->toString() : 'compact',
            // Onda 8 (2026-05-20): sort + dir. Whitelist forçada na query (não confia
            // no input). 'sort' default vazio → orderBy vencimento; 'dir' default asc.
            'sort' => $request->string('sort', '')->toString(),
            'dir' => $request->string('dir', 'asc')->toString() === 'desc' ? 'desc' : 'asc',
            // Onda 13 (2026-05-20): pagination. Defaults page 1, per_page 100.
            // Backend clampa per_page entre 20-500. Frontend mostra controls
            // se total > per_page.
            'page' => max(1, (int) $request->integer('page', 1)),
            'per_page' => max(20, min(500, (int) $request->integer('per_page', 100))),
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

    /**
     * Filtro por campo de data — paridade com os filtros do WR Comercial (2026-06-03).
     * vencimento (default, back-compat) | emissao | pagamento (via baixa) | competencia (YYYY-MM).
     * NF/Vendas do WR exigem link título→transaction (origem_id), ainda pendente.
     */
    private function aplicarFiltroData($q, string $campo, string $sd, string $ed): void
    {
        match ($campo) {
            'emissao' => $q->whereBetween('emissao', [$sd, $ed]),
            'competencia' => $q->whereBetween('competencia_mes', [substr($sd, 0, 7), substr($ed, 0, 7)]),
            'pagamento' => $q->whereHas('baixas', fn ($b) => $b->whereBetween('data_baixa', [$sd, $ed])),
            default => $q->whereBetween('vencimento', [$sd, $ed]),
        };
    }

    /**
     * PR E (2026-05-25) US-FIN-022 — Aging breakdown.
     * Conta títulos vencidos (status aberto/parcial + vencimento < hoje) por
     * bucket BR canon. Usado pra chips de filtro mostrarem contagem visível
     * — "lt30 (5) · 30-60 (3) · 60-90 (1) · gt90 (0) · gt180 (0)".
     *
     * Multi-tenant Tier 0: scope business_id obrigatório.
     */
    private function agingBreakdown(int $businessId): array
    {
        $hoje = now()->toDateString();
        $base = Titulo::where('business_id', $businessId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('vencimento', '<', $hoje);

        $today = now();
        $bucketRange = function (int $minDays, int $maxDays) use ($today) {
            $maxDate = (clone $today)->subDays($minDays)->toDateString();
            $minDate = (clone $today)->subDays($maxDays + 1)->toDateString();
            return [$minDate, $maxDate];
        };

        return [
            'lt30'  => (clone $base)->whereBetween('vencimento', $bucketRange(0, 29))->count(),
            '30-60' => (clone $base)->whereBetween('vencimento', $bucketRange(30, 59))->count(),
            '60-90' => (clone $base)->whereBetween('vencimento', $bucketRange(60, 89))->count(),
            'gt90'  => (clone $base)->whereBetween('vencimento', $bucketRange(90, 179))->count(),
            'gt180' => (clone $base)->whereBetween('vencimento', $bucketRange(180, 99999))->count(),
        ];
    }

    private function kpis(int $businessId, Carbon $start, Carbon $end, string $campo = 'vencimento'): array
    {
        $atual = $this->kpisCore($businessId, $start, $end, $campo);

        // PR H (2026-05-25) US-FIN-023 — delta_pct vs mês anterior.
        // Calcula KPIs do período equivalente do mês anterior pra comparativo
        // visual ("Recebido R$ [redacted Tier 0] ↑+12%" — Eliana vê tendência).
        $startPrior = (clone $start)->subMonth();
        $endPrior = (clone $end)->subMonth();
        $anterior = $this->kpisCore($businessId, $startPrior, $endPrior, $campo);

        $atual['delta_pct'] = [
            'saldo_previsto' => $this->deltaPct($anterior['saldo_previsto'], $atual['saldo_previsto']),
            'recebido'       => $this->deltaPct($anterior['recebido']['valor'], $atual['recebido']['valor']),
            'a_receber'      => $this->deltaPct($anterior['a_receber']['valor'], $atual['a_receber']['valor']),
            'pago'           => $this->deltaPct($anterior['pago']['valor'], $atual['pago']['valor']),
            'a_pagar'        => $this->deltaPct($anterior['a_pagar']['valor'], $atual['a_pagar']['valor']),
        ];

        return $atual;
    }

    /**
     * PR H (2026-05-25) — core dos KPIs (sem delta_pct).
     * Extraído pra reuso: `kpis()` chama 2x (atual + anterior) pra delta_pct.
     */
    private function kpisCore(int $businessId, Carbon $start, Carbon $end, string $campo = 'vencimento'): array
    {
        $sd = $start->toDateString();
        $ed = $end->toDateString();

        // Paridade filtros WR (2026-06-03): A receber / A pagar filtram pelo mesmo
        // campo de data escolhido (default vencimento = comportamento anterior).
        $base = Titulo::where('business_id', $businessId);
        $this->aplicarFiltroData($base, $campo, $sd, $ed);

        $rec = (clone $base)->where('tipo', 'receber');
        $pay = (clone $base)->where('tipo', 'pagar');

        $aReceber = (clone $rec)->whereIn('status', ['aberto', 'parcial']);
        $aPagar = (clone $pay)->whereIn('status', ['aberto', 'parcial']);

        // Recebido / Pago = baixas (dinheiro que entrou/saiu).
        //  - campo 'pagamento': data_baixa É a data relevante → filtra direto (exato).
        //  - demais campos: filtra as baixas cujos TÍTULOS casam o campo escolhido,
        //    mantendo os cards consistentes com a lista filtrada.
        // Wagner 2026-06-03: baixas de títulos CANCELADOS (status='cancelado')
        // NÃO podem somar em RECEBIDO/PAGO — um título cancelado não é um
        // recebimento/pagamento válido (pareia com a lista, que esconde cancelado).
        $aplicarBaixa = function ($q, string $tipo) use ($campo, $sd, $ed) {
            $q->whereNull('estorno_de_id');
            if ($campo === 'pagamento') {
                $q->whereBetween('data_baixa', [$sd, $ed])
                  ->whereHas('titulo', fn ($t) => $t->where('tipo', $tipo)->where('status', '!=', 'cancelado'));
            } else {
                $q->whereHas('titulo', function ($t) use ($tipo, $campo, $sd, $ed) {
                    $t->where('tipo', $tipo)->where('status', '!=', 'cancelado');
                    $this->aplicarFiltroData($t, $campo, $sd, $ed);
                });
            }

            return $q;
        };

        $recebido = $aplicarBaixa(TituloBaixa::where('business_id', $businessId), 'receber');
        $pago = $aplicarBaixa(TituloBaixa::where('business_id', $businessId), 'pagar');

        $saldoAtual = (float) ContaBancaria::where('business_id', $businessId)->sum('saldo_cached');
        $saldoPrevisto = $saldoAtual
            + (float) (clone $aReceber)->sum('valor_aberto')
            - (float) (clone $aPagar)->sum('valor_aberto');

        // Eliana [E] 2026-06-07: KPIs RECEBIDO/PAGO mostram valor REAL pago,
        // incluindo juros + multa - desconto (paridade WR Comercial legacy
        // DÉBITO/CRÉDITO). Antes era só valor_baixa, gerando diff em
        // conferências (jan/2026: PAGO R$ [redacted Tier 0] + juros R$ [redacted Tier 0] =
        // R$ [redacted Tier 0] real vs WR2 R$ [redacted Tier 0] — diff < 1ct arredondamento).
        $totalRealExpr = 'COALESCE(SUM(valor_baixa + juros + multa - desconto), 0)';
        $recebidoAgg = (clone $recebido)->selectRaw("{$totalRealExpr} as total, COUNT(*) as qtd")->first();
        $pagoAgg = (clone $pago)->selectRaw("{$totalRealExpr} as total, COUNT(*) as qtd")->first();

        return [
            'saldo_previsto' => (float) $saldoPrevisto,
            'recebido' => [
                'valor' => (float) ($recebidoAgg->total ?? 0),
                'qtd' => (int) ($recebidoAgg->qtd ?? 0),
            ],
            'a_receber' => [
                'valor' => (float) (clone $aReceber)->sum('valor_aberto'),
                'qtd' => (clone $aReceber)->count(),
            ],
            'pago' => [
                'valor' => (float) ($pagoAgg->total ?? 0),
                'qtd' => (int) ($pagoAgg->qtd ?? 0),
            ],
            'a_pagar' => [
                'valor' => (float) (clone $aPagar)->sum('valor_aberto'),
                'qtd' => (clone $aPagar)->count(),
            ],
        ];
    }

    /**
     * PR H (2026-05-25) US-FIN-023 — calcula delta percentual seguro.
     * Retorna null se denominador zero/negativo (evita divisão por zero e
     * +infinito visual quando KPI nasce do zero).
     */
    private function deltaPct(float $anterior, float $atual): ?float
    {
        if ($anterior <= 0) {
            return null;
        }
        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * Mapeia Titulo (schema real) pro shape esperado pelo Index.tsx (schema do protótipo).
     */
    private function shapeTitulo(Titulo $t, string $hoje, string $vencendoLimite): array
    {
        $kind = $t->tipo === 'receber' ? 'receivable' : 'payable';
        $status = $this->deriveStatus($t, $hoje, $vencendoLimite);

        $ultimaBaixa = $t->relationLoaded('baixas') ? $t->baixas->first() : null;
        $dataBaixa = $ultimaBaixa?->data_baixa; // acesso único (Larastan ratchet: 1× data_baixa)
        // Conta exibida: a REALIZADA (baixa) tem prioridade; senão a PREVISTA (título); senão "—".
        $contaBancariaNome = $ultimaBaixa?->contaBancaria?->nome
            ?? $ultimaBaixa?->contaBancaria?->account?->name
            ?? $t->contaBancaria?->nome
            ?? '—';

        $metadata = $t->metadata ?? [];
        $nfeNumero = $metadata['nfe_numero'] ?? $metadata['nfe_chave'] ?? null;
        $canal = $metadata['canal'] ?? $t->origem;

        // PR 3 (2026-05-20): metadata['descricao'] tem prioridade sobre cliente_descricao.
        // Razao: 'descricao' = descricao do ITEM/SERVICO (ex.: "Banner lona 4x1m #V-7831"),
        // enquanto 'cliente_descricao' = nome do CLIENTE quando nao ha FK contacts.id (vira
        // 'contraparte' na linha 758). Antes ?:  usava cliente_descricao como descricao →
        // FinCrossLinkify nao encontrava refs #V-/#OS-/#BL- pq descs viravam nome cliente.
        $descricao = ($metadata['descricao'] ?? null)
            ?: ($t->cliente_descricao ?: "Titulo {$t->numero}");

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
            'plano_conta_id' => $t->plano_conta_id,
            'plano_conta_codigo' => $t->planoConta?->codigo,
            'plano_conta_nome' => $t->planoConta?->nome,
            'conta_bancaria' => $contaBancariaNome,
            'conta_bancaria_id' => $t->getAttribute('conta_bancaria_id'),
            // Forma de pagamento: a REALIZADA (última baixa) manda quando existe
            // e é read-only; senão a PREVISTA (titulo.forma_pagamento), editável.
            // Fallback `delphi_tipopagto` (metadata) pros títulos migrados do WR.
            'forma_pagamento' => $ultimaBaixa?->meio_pagamento ?? $t->forma_pagamento ?? ($metadata['delphi_tipopagto'] ?? null),
            'forma_pagamento_realizada' => $ultimaBaixa?->meio_pagamento !== null,
            // Paridade campos lançamento WR (Fase 1 — 2026-06-03): dado já disponível
            // (coluna ou metadata.delphi_* dos migrados). Fase 2 (doc/NF real, conta,
            // plano de contas, usuário WR, cheque, dt NF) exige re-import.
            'emissao' => $t->emissao?->toDateString(),
            'competencia_mes' => $t->competencia_mes,
            'condicao_pagamento' => $metadata['delphi_condicaopagto'] ?? null,
            'desconto' => (float) ($metadata['delphi_desconto'] ?? 0),
            'juros' => (float) ($metadata['delphi_juros'] ?? 0),
            // Documento = campo DOCUMENTO real do WR (NÃO o codpedido — Wagner 2026-06-03).
            // O DOCUMENTO foi redactado na migração → valor real só vem na Fase 2 (re-import
            // sem redact), junto com a Nota Fiscal. Por ora exibe nfe_numero quando houver
            // (nativos) ou "—" (migrados, pendente Fase 2).
            'documento' => $nfeNumero ?? null,
            // Paridade campos WR Fase 2 (2026-06-04, sobre base Felipe) — dado disponível.
            'numero' => $t->getAttribute('numero'),
            'parcela' => (function () use ($t) {
                $pn = $t->getAttribute('parcela_numero');
                $pt = $t->getAttribute('parcela_total');

                return $pn ? ($pt ? "{$pn}/{$pt}" : (string) $pn) : null;
            })(),
            'pedido' => $metadata['delphi_codpedido'] ?? null,
            'vencimento' => $t->vencimento?->toDateString(),
            'vencimento_label' => $t->vencimento?->locale('pt_BR')->isoFormat('ddd, DD MMM'),
            'liquidacao' => $dataBaixa?->locale('pt_BR')->isoFormat('DD MMM'),
            // Data de pagamento (data cheia da baixa). Hora completa virá no re-import (Fase 2).
            'data_pagamento' => $dataBaixa?->toDateString(),
            'valor' => (float) $t->valor_total,
            'valor_aberto' => (float) $t->valor_aberto,
            'nfe_numero' => $nfeNumero,
            'canal' => $canal,
            // Onda 3 (2026-05-20): strip prefix de tag interno (SEEDER_DEMO :: , etc)
            // antes de exibir pro usuario. Tag usado pra idempotencia do seeder
            // mas nao deve vazar pra UI (Wagner viu no drawer 'SEEDER_DEMO :: ...').
            'observacao' => $t->observacoes
                ? preg_replace('/^SEEDER_DEMO\s*::\s*/', '', $t->observacoes)
                : null,
            'conferido_by' => $t->conferido_by,
            'conferido_at' => $t->conferido_at?->toIso8601String(),
            'conferido_user_nome' => $conferidoNome,
            'valor_mutavel' => ! in_array($t->status, ['quitado', 'cancelado'], true),
            // Onda 21 #55 — Workflow aprovação (nullable: títulos sem fluxo)
            'aprovacao_status' => $t->aprovacao_status ?? null,
            // Gerar Boleto no drawer (2026-06-08) — linha digitável persistida em
            // metadata.boleto após emissão via PaymentGateway (InterDriver). Null
            // até o título ter um boleto emitido.
            'boleto' => $metadata['boleto'] ?? null,
        ];
    }

    private function deriveStatus(Titulo $t, string $hoje, string $vencendoLimite): string
    {
        if ($t->status === 'quitado') {
            return $t->tipo === 'receber' ? 'recebido' : 'pago';
        }

        $venc = $t->vencimento->toDateString();
        if ($venc < $hoje) {
            return 'atrasado';
        }
        if ($venc <= $vencendoLimite) {
            return 'vencendo';
        }

        return 'aberto';
    }

    // ════════════════════════════════════════════════════════════════════════
    // Onda 23 (2026-05-20) US-FIN-029 — OCR boleto upload OpenAI Vision.
    // KILLER feature vs Conta Azul: Eliana cola foto/PDF → sistema extrai
    // linha digitável + valor + vencimento + beneficiário automaticamente.
    // ════════════════════════════════════════════════════════════════════════

    /**
     * POST /financeiro/unificado/ocr-boleto
     * Recebe upload imagem boleto → OCR → retorna campos extraídos editáveis pra UI.
     * NÃO cria Titulo ainda — UI mostra preview + user confirma + chama POST /unificado (store).
     */
    public function ocrBoleto(Request $request, BoletoOcrService $ocr): JsonResponse
    {
        $request->validate([
            'arquivo' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;
        $file = $request->file('arquivo');

        $result = $ocr->extract($file, $businessId, $userId);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Falha no OCR.',
            ], 422);
        }

        $data = $result['data'];

        // Normaliza pra shape esperado pelo frontend TituloEditSheet/Novo.
        // CNPJ beneficiário NÃO retornado pro client em raw — só visualização parcial pra confirmar.
        $cnpjBenef = preg_replace('/[^0-9]/', '', $data['beneficiario_cnpj'] ?? '');
        $cnpjMasked = strlen($cnpjBenef) === 14
            ? substr($cnpjBenef, 0, 2) . '.' . substr($cnpjBenef, 2, 3) . '.xxx-' . substr($cnpjBenef, 8, 4) . '-xx'
            : null;

        return response()->json([
            'success' => true,
            'from_cache' => $result['from_cache'] ?? false,
            'cost_usd' => $result['cost_usd'] ?? 0.0,
            'extracted' => [
                'linha_digitavel' => $data['linha_digitavel'],
                'codigo_barras' => LinhaDigitavelValidator::toCodigoBarras($data['linha_digitavel']),
                'valor' => $data['valor'] ?? null,
                'vencimento' => $data['vencimento'] ?? null,
                'beneficiario_nome' => $data['beneficiario_nome'] ?? null,
                'beneficiario_cnpj_masked' => $cnpjMasked,
                'beneficiario_cnpj' => $cnpjBenef ?: null, // raw vai pro form mas tela mostra masked
                'pagador_nome' => $data['pagador_nome'] ?? null,
                'confidence' => $data['confidence'] ?? null,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Onda 20 (2026-05-19) #50 — Anexos NF / comprovante por título.
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Listar anexos de um título.
     * GET /financeiro/unificado/{id}/anexos
     */
    public function listarAnexos(int $id): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        $anexos = \Modules\Financeiro\Models\TituloAnexo::query()
            ->where('business_id', $businessId)
            ->where('titulo_id', $id)
            ->orderByDesc('created_at')
            ->get(['id', 'nome', 'mime', 'tamanho_bytes', 'uploaded_by', 'created_at']);

        return response()->json(['anexos' => $anexos]);
    }

    /**
     * Anexar arquivo a um título.
     * POST /financeiro/unificado/{id}/anexos (multipart com `arquivo`).
     */
    public function anexar(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'arquivo' => 'required|file|max:10240', // 10 MB
        ]);

        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;

        // Validar título existe + scope multi-tenant.
        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        $file = $request->file('arquivo');
        $hash = hash_file('sha256', $file->getPathname());

        // Idempotência: se já existe anexo com mesmo hash, não duplica.
        $existente = \Modules\Financeiro\Models\TituloAnexo::query()
            ->where('business_id', $businessId)
            ->where('hash_sha256', $hash)
            ->first();
        if ($existente) {
            return back()->with('warning', 'Arquivo já anexado previamente.');
        }

        // Storage local privado.
        $relativePath = "financeiro/anexos/{$businessId}/{$id}/" . uniqid() . '_' . $file->getClientOriginalName();
        \Illuminate\Support\Facades\Storage::disk('local')->put($relativePath, file_get_contents($file->getPathname()));

        \Modules\Financeiro\Models\TituloAnexo::create([
            'business_id' => $businessId,
            'titulo_id' => $titulo->id,
            'nome' => $file->getClientOriginalName(),
            'path' => $relativePath,
            'mime' => $file->getMimeType(),
            'tamanho_bytes' => $file->getSize(),
            'hash_sha256' => $hash,
            'uploaded_by' => $userId,
        ]);

        return back()->with('success', 'Anexo adicionado.');
    }

    /**
     * Download anexo (stream binário via Storage::disk(local), business_id scope).
     * GET /financeiro/unificado/{id}/anexos/{anexoId}/download
     *
     * US-FIN-026 (Onda 22) — antes da Onda 22 anexos só upload+remove; agora
     * lista + baixa também. Stream direto evita signed URL extra (S3-style)
     * porque disco é local privado — controller scope + auth resolvem segurança.
     */
    public function baixarAnexo(Request $request, int $id, int $anexoId)
    {
        $businessId = (int) session('user.business_id');

        $anexo = \Modules\Financeiro\Models\TituloAnexo::query()
            ->where('business_id', $businessId)
            ->where('titulo_id', $id)
            ->findOrFail($anexoId);

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($anexo->path), 404, 'Arquivo não encontrado no storage.');

        return $disk->download($anexo->path, $anexo->nome, [
            'Content-Type' => $anexo->mime ?: 'application/octet-stream',
        ]);
    }

    /**
     * Remover anexo.
     * DELETE /financeiro/unificado/{id}/anexos/{anexoId}
     */
    public function removerAnexo(Request $request, int $id, int $anexoId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $anexo = \Modules\Financeiro\Models\TituloAnexo::query()
            ->where('business_id', $businessId)
            ->where('titulo_id', $id)
            ->findOrFail($anexoId);

        // Soft-delete (preserva audit trail).
        $anexo->delete();

        return back()->with('success', 'Anexo removido.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Onda 21 (2026-05-19) #55 — Workflow aprovação pagamento.
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Marcar título pra aprovação (Eliana cria → status pendente).
     * POST /financeiro/unificado/{id}/solicitar-aprovacao
     */
    public function solicitarAprovacao(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        // Apenas títulos a PAGAR em aberto/parcial podem requerer aprovação.
        if ($titulo->tipo !== 'pagar' || ! in_array($titulo->status, ['aberto', 'parcial'])) {
            return back()->with('error', 'Aprovação só pra títulos a pagar abertos.');
        }

        $titulo->update(['aprovacao_status' => 'pendente']);

        return back()->with('success', 'Título marcado pra aprovação.');
    }

    /**
     * Aprovar título (Wagner ou owner de permissão `financeiro.titulo.aprovar`).
     * POST /financeiro/unificado/{id}/aprovar
     */
    public function aprovar(Request $request, int $id): RedirectResponse
    {
        // US-FIN-028 (Onda 22) — gate Spatie. Solicitar continua aberto (qualquer
        // user do biz solicita), aprovar/rejeitar precisa permissão dedicada.
        abort_unless(
            $request->user()?->can('financeiro.titulo.aprovar') || $request->user()?->can('superadmin'),
            403,
            'Sem permissão pra aprovar títulos financeiros.'
        );

        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;
        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        if ($titulo->aprovacao_status !== 'pendente') {
            return back()->with('error', 'Só títulos pendentes podem ser aprovados.');
        }

        $titulo->update([
            'aprovacao_status' => 'aprovado',
            'aprovado_by' => $userId,
            'aprovado_at' => now(),
            'aprovacao_motivo' => null,
        ]);

        return back()->with('success', 'Título aprovado.');
    }

    /**
     * Rejeitar título com motivo obrigatório.
     * POST /financeiro/unificado/{id}/rejeitar com {motivo}.
     */
    public function rejeitar(Request $request, int $id): RedirectResponse
    {
        // US-FIN-028 (Onda 22) — mesma gate Spatie que aprovar.
        abort_unless(
            $request->user()?->can('financeiro.titulo.aprovar') || $request->user()?->can('superadmin'),
            403,
            'Sem permissão pra rejeitar títulos financeiros.'
        );

        $request->validate(['motivo' => 'required|string|max:500']);

        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;
        $titulo = Titulo::where('business_id', $businessId)->findOrFail($id);

        if ($titulo->aprovacao_status !== 'pendente') {
            return back()->with('error', 'Só títulos pendentes podem ser rejeitados.');
        }

        $titulo->update([
            'aprovacao_status' => 'rejeitado',
            'aprovado_by' => $userId,
            'aprovado_at' => now(),
            'aprovacao_motivo' => $request->string('motivo'),
        ]);

        return back()->with('success', 'Título rejeitado.');
    }
}
