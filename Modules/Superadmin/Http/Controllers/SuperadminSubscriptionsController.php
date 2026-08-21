<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Util\OtelHelper;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Services\SubscriptionLifecycleService;
use Modules\Superadmin\Support\RotuloAssinatura;

class SuperadminSubscriptionsController extends BaseController
{
    protected $businessUtil;

    /**
     * Constructor
     *
     * @param  BusinessUtil  $businessUtil
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil)
    {
        $this->businessUtil = $businessUtil;
    }

    /**
     * Lista de assinaturas (`GET /superadmin/superadmin-subscription`) — onda SA-O4a.
     *
     * Deixou de servir DataTables por AJAX e passou a Inertia com paginação SERVER-SIDE.
     * O legado devolvia a consulta inteira ao DataTables (que ordenava e paginava no
     * cliente) e montava HTML de botão DENTRO da query, na coluna `action` — era isso que
     * tornava a coluna intraduzível para React.
     *
     * O join (`subscriptions → business → packages`) é 1-para-1 nos dois lados a partir da
     * assinatura, então `paginate()` conta certo SEM a subquery escalar que a SA-O2 precisou.
     *
     * SUPERADMIN: leitura GLOBAL cross-tenant intencional (ADR 0093 §exceções) — esta tela
     * existe justamente para enxergar a cobrança de todos os negócios.
     *
     * @see memory/requisitos/Superadmin/RUNBOOK-assinaturas.md
     */
    public function index(Request $request): InertiaResponse
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return OtelHelper::spanBiz('superadmin.assinaturas.index', function () use ($request) {
            $filtros = [
                'pacote' => $request->input('pacote'),
                'status' => $this->opcaoValida($request->input('status'), RotuloAssinatura::FILTROS),
                'periodo' => $this->opcaoValida($request->input('periodo'), array_keys(self::PERIODOS)),
                'ordem' => $this->opcaoValida($request->input('ordem'), array_keys(self::ORDENS)) ?? 'criado',
                'dir' => $request->input('dir') === 'asc' ? 'asc' : 'desc',
            ];

            return Inertia::render('superadmin/Assinaturas/Index', [
                'filtros' => $filtros,
                'pacotes' => Inertia::defer(fn () => $this->opcoesDePacote()),
                'kpis' => Inertia::defer(fn () => $this->kpisPayload()),
                'assinaturas' => Inertia::defer(fn () => $this->assinaturasPayload($filtros)),
            ]);
        }, ['component' => 'superadmin.assinaturas.index']);
    }

    /**
     * Colunas ordenáveis → coluna real. É WHITELIST, não conveniência: `orderBy()` com valor
     * de request é injeção, e o F1 pede cabeçalho clicável.
     */
    private const ORDENS = [
        'criado' => 'subscriptions.created_at',
        'negocio' => 'business.name',
        'status' => 'subscriptions.status',
        'inicio' => 'subscriptions.start_date',
        'preco' => 'subscriptions.package_price',
    ];

    /** Janelas do filtro "Criada em" (F1). O valor é o número de dias, ou `mes` (mês corrente). */
    private const PERIODOS = ['7d' => 7, '30d' => 30, 'mes' => null];

    /** Opção de filtro fora da lista vira `null` — nunca chega crua na query. */
    private function opcaoValida(?string $valor, array $aceitos): ?string
    {
        return in_array($valor, $aceitos, true) ? $valor : null;
    }

    /**
     * Pacotes para o filtro. Só id + nome: a lista é combo, não catálogo.
     *
     * Inclui pacote inativo de propósito — assinatura antiga aponta para grade descontinuada,
     * e filtrar por ela é exatamente o que se quer ao investigar um contrato legado.
     *
     * @return array<int, array{id: int, nome: string}>
     */
    private function opcoesDePacote(): array
    {
        return Package::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'nome' => (string) $p->name])
            ->all();
    }

    /**
     * Os 4 KPI do F1 + o recorte que o F1 não previu.
     *
     * `bloqueadas` (`declined`) NÃO entra em nenhum dos quatro: bloqueio por inadimplência e
     * cancelamento a pedido são eventos comerciais diferentes. Em vez de escondê-lo dentro de
     * "canceladas", o número vai junto e a tela DIZ que ele está fora do recorte
     * (RUNBOOK-assinaturas §1).
     *
     * Nenhum KPI de receita aqui: MRR tem dono (`SubscriptionRepository::mrrBaselineCached`,
     * do RecurringBilling) e a regra R1 do F1 ainda está sendo verificada. Um segundo oráculo
     * de receita nesta tela seria régua duplicada.
     *
     * @return array<string, int>
     */
    private function kpisPayload(): array
    {
        $hoje = \Carbon::today()->toDateString();

        // SUPERADMIN: contagem GLOBAL cross-tenant intencional (ADR 0093 §exceções).
        $porStatus = DB::table('subscriptions')
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN end_date IS NOT NULL AND end_date < ? THEN 1 ELSE 0 END) AS vencidas', [$hoje])
            ->selectRaw('SUM(CASE WHEN trial_end_date IS NOT NULL AND trial_end_date >= ? THEN 1 ELSE 0 END) AS em_trial', [$hoje])
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $de = fn (string $s, string $campo) => (int) ($porStatus[$s]->{$campo} ?? 0);

        $aprovadas = $de('approved', 'total');
        $aprovadasVencidas = $de('approved', 'vencidas');

        return [
            // "Aprovada" do F1 = aprovada E ainda dentro da vigência.
            'ativas' => $aprovadas - $aprovadasVencidas,
            'trial' => $de('approved', 'em_trial'),
            'pendentes' => $de('waiting', 'total'),
            // Vencida tem duas origens: o sweep marcou `expired`, ou a data passou e ninguém
            // marcou nada. A fila de cobrança precisa das duas.
            'vencidas_canceladas' => $aprovadasVencidas + $de('expired', 'total') + $de('cancelled', 'total'),
            'bloqueadas' => $de('declined', 'total'),
        ];
    }

    /**
     * Página de assinaturas já filtrada e ordenada.
     *
     * `DB::table`, não `Subscription::query()`: as colunas do join (`negocio`, `pacote`) não
     * existem no model, e hidratar Eloquent para montar lista de leitura não paga.
     *
     * @param  array<string, string|null>  $filtros
     * @return array<string, mixed>
     */
    private function assinaturasPayload(array $filtros): array
    {
        $hoje = \Carbon::today()->toDateString();

        // SUPERADMIN: leitura GLOBAL cross-tenant intencional (ADR 0093 §exceções).
        $query = DB::table('subscriptions')
            ->join('business', 'subscriptions.business_id', '=', 'business.id')
            ->join('packages AS p', 'subscriptions.package_id', '=', 'p.id')
            ->whereNull('subscriptions.deleted_at')
            ->select([
                'subscriptions.id',
                'subscriptions.status',
                'subscriptions.created_at',
                'subscriptions.start_date',
                'subscriptions.trial_end_date',
                'subscriptions.end_date',
                'subscriptions.package_price',
                'subscriptions.paid_via',
                'subscriptions.payment_transaction_id',
                'business.id AS negocio_id',
                'business.name AS negocio',
                'p.name AS pacote',
            ]);

        if (! empty($filtros['pacote'])) {
            $query->where('p.id', (int) $filtros['pacote']);
        }

        if ($filtros['status'] !== null) {
            // A tradução rótulo→enum é do MESMO dono do mapa de leitura. Separá-los é como
            // eles divergem no primeiro status novo.
            RotuloAssinatura::filtro($query, $filtros['status'], $hoje);
        }

        if ($filtros['periodo'] !== null) {
            $dias = self::PERIODOS[$filtros['periodo']];
            $desde = $dias === null
                ? \Carbon::today()->startOfMonth()->toDateString()
                : \Carbon::today()->subDays($dias)->toDateString();

            $query->whereDate('subscriptions.created_at', '>=', $desde);
        }

        $pagina = $query
            ->orderBy(self::ORDENS[$filtros['ordem']], $filtros['dir'])
            // Desempate estável: sem ele, duas assinaturas criadas no mesmo dia trocam de
            // lugar entre páginas e a paginação parece perder linha.
            ->orderByDesc('subscriptions.id')
            ->paginate(20)
            ->withQueryString();

        return [
            'linhas' => collect($pagina->items())->map(fn ($s) => [
                'id' => (int) $s->id,
                'negocio_id' => (int) $s->negocio_id,
                'negocio' => (string) $s->negocio,
                'pacote' => (string) $s->pacote,
                // Enum cru NUNCA chega ao .tsx (RUNBOOK §1).
                'situacao' => RotuloAssinatura::de($s->status, $s->end_date),
                'criado' => $this->dataCurta($s->created_at),
                'inicio' => $this->dataCurta($s->start_date),
                'fim' => $this->dataCurta($s->end_date),
                'trial_fim' => $this->dataCurta($s->trial_end_date),
                // Número puro: quem formata moeda é a tela. Nenhum literal em R$ no back.
                'preco' => (float) $s->package_price,
                'via' => $s->paid_via ?: null,
                'transacao' => $s->payment_transaction_id ?: null,
            ])->all(),
            'total' => $pagina->total(),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'por_pagina' => $pagina->perPage(),
        ];
    }

    /** `null` continua `null` — a tela decide como desenhar ausência, o back não inventa traço. */
    private function dataCurta($valor): ?string
    {
        return $valor ? \Carbon::parse($valor)->format('d/m/Y') : null;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->input('business_id');
        $packages = Package::active()->orderby('sort_order')->pluck('name', 'id');

        $gateways = $this->_payment_gateways();

        return view('superadmin::superadmin_subscription.add_subscription')
              ->with(compact('packages', 'business_id', 'gateways'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('subscribe')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $input = $request->only(['business_id', 'package_id', 'paid_via', 'payment_transaction_id']);
            $package = Package::find($input['package_id']);
            $user_id = $request->session()->get('user.id');

            $subscription = $this->_add_subscription($input['business_id'], $package, $input['paid_via'], $input['payment_transaction_id'], $user_id, true);

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->_log_emergency_redacted($e, 'SuperadminSubscriptionsController');

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return back()->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('superadmin::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $status = Subscription::package_subscription_status();
            $subscription = Subscription::find($id);

            return view('superadmin::superadmin_subscription.edit')
                        ->with(compact('subscription', 'status'));
        }
    }

    /**
     * Muda o STATUS de uma assinatura (`PUT /superadmin/superadmin-subscription/{id}`) — SA-O4b.
     *
     * O que mudou em relação ao legado, e é o ponto todo desta onda: o legado fazia
     * `$subscription->status = $input['status']; save();` — escrita direta, sem trilha de
     * transição e sem regra. Aqui a escrita passa pelo `SubscriptionLifecycleService`, que é
     * quem sabe calcular vigência ao aprovar, é idempotente ao expirar e persiste motivo ao
     * cancelar.
     *
     * ⚠️ POR QUE 3 AÇÕES E NÃO OS 5 STATUS DO F1 — divergência declarada, não recorte por
     * preguiça. O Service modela três transições (`approve`, `expire`, `cancel`). Os outros
     * dois valores do enum não são ações de operador:
     *
     *   - `waiting` (Pendente) é o estado INICIAL de quem ainda não teve baixa. "Voltar pra
     *     pendente" seria des-aprovar, o que o Service não modela — e fazer na mão aqui é
     *     exatamente a escrita direta que esta onda veio remover.
     *   - `declined` (Bloqueada) é gravado por `OnCobrancaVencidaBloqueaSubscription` quando a
     *     cobrança vence. É consequência de evento, não escolha de tela.
     *
     * Oferecer os cinco exigiria ou ampliar o Service (decisão de domínio) ou furar o próprio
     * contrato. Fica registrado no RUNBOOK-assinaturas §7 como decisão [W].
     *
     * SUPERADMIN: escrita cross-tenant intencional (ADR 0093 §exceções) — o superadmin opera
     * sobre a assinatura de qualquer negócio por desenho.
     *
     * @see Modules\Superadmin\Services\SubscriptionLifecycleService
     * @see memory/requisitos/Superadmin/RUNBOOK-assinaturas.md §7
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $dados = $request->validate([
            'acao' => ['required', 'string', 'in:aprovar,vencer,cancelar'],
            // O motivo só é exigido no cancelamento, que é o único que o registra. Exigir
            // sempre treinaria o operador a digitar qualquer coisa pra passar.
            'motivo' => ['nullable', 'string', 'in:'.implode(',', SubscriptionLifecycleService::MOTIVOS_CANCELAMENTO)],
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        // SUPERADMIN: busca cross-tenant intencional (ADR 0093 §exceções) — nenhum tenant
        // filtra esta consulta; a tela opera sobre a assinatura de qualquer negócio.
        $assinatura = Subscription::findOrFail($id);

        // O `business_id` do alvo é lido para o REGISTRO da ação, não para filtrar. Ele fica
        // no código (e não só no comentário acima) de propósito: a regra
        // `NoMissingTenantScopeRule` serializa o AST do método — string, variável, identificador
        // — e NÃO enxerga comentário. Declarar a exceção só em prosa passa despercebido por ela,
        // e o span de escrita cross-tenant sem o tenant alvo é justamente o que ninguém consegue
        // auditar depois.
        $business_id = (int) ($assinatura->business_id ?? 0);

        $servico = app(SubscriptionLifecycleService::class);

        $aplicou = OtelHelper::spanBiz('superadmin.assinaturas.acao', fn (): bool => match ($dados['acao']) {
            'aprovar' => $servico->approve($assinatura),
            'vencer' => $servico->expire($assinatura),
            'cancelar' => $servico->cancel($assinatura, $dados['motivo'] ?? '', $dados['nota'] ?? ''),
        }, [
            'module' => 'Superadmin',
            'component' => 'superadmin.assinaturas.acao',
            'acao' => $dados['acao'],
            'subscription_id' => (int) $id,
            'target_biz' => $business_id,
        ]);

        // O Service devolve `false` quando a transição não se aplica (já cancelada, ainda
        // vigente, status incompatível). Isso NÃO é erro — é o guarda funcionando. A tela
        // precisa saber a diferença, senão mostra "salvo" pra uma escrita que não houve.
        $recado = $aplicou
            ? self::RECADO_OK[$dados['acao']]
            : 'Nada mudou: a assinatura não estava num estado que permitisse essa ação.';

        return back()->with('status', [
            'success' => $aplicou ? 1 : 0,
            'msg' => $recado,
        ]);
    }

    /** Recado por ação aplicada. Fala do EFEITO, não do verbo — é o que o operador precisa saber. */
    private const RECADO_OK = [
        'aprovar' => 'Assinatura aprovada. O acesso vale a partir de agora e a vigência foi calculada pelo pacote.',
        'vencer' => 'Assinatura marcada como vencida.',
        'cancelar' => 'Assinatura cancelada. Ela para de renovar no fim da vigência e o acesso continua até lá.',
    ];

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy()
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function editSubscription($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $subscription = Subscription::find($id);

            return view('superadmin::superadmin_subscription.edit_date_modal')
                        ->with(compact('subscription'));
        }
    }

    /**
     * Edita a VIGÊNCIA de uma assinatura (`POST /superadmin/update-subscription`) — SA-O4b.
     *
     * UC-SA-009 do F1: prorrogar sem gerar cobrança nova. É exatamente o que este método faz e
     * o que ele NÃO faz — mexer em data não emite nada, não toca `package_price` e não muda
     * `status`. A tela diz isso em texto, porque a pergunta "isso vai cobrar de novo?" é a
     * primeira que aparece na cabeça de quem prorroga.
     *
     * O legado respondia JSON pra um modal AJAX; agora redireciona de volta, que é o que o
     * Inertia espera. As datas continuam passando por `BusinessUtil::uf_date` — o formato de
     * entrada é `dd/mm/aaaa` e o parser é o mesmo do resto do ERP.
     *
     * ⚠️ Datas AQUI não são valor monetário, e por isso este método não cai na REGRA MESTRE de
     * "CÁLCULO DE VALOR ou ESTOQUE": nenhuma conta de preço é refeita. O que muda é até quando
     * o acesso vale.
     *
     * SUPERADMIN: escrita cross-tenant intencional (ADR 0093 §exceções).
     */
    public function updateSubscription(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $dados = $request->validate([
            'subscription_id' => ['required', 'integer'],
            'start_date' => ['nullable', 'string', 'max:10'],
            'end_date' => ['nullable', 'string', 'max:10'],
            'trial_end_date' => ['nullable', 'string', 'max:10'],
        ]);

        // SUPERADMIN: busca cross-tenant intencional (ADR 0093 §exceções) — nenhum tenant
        // filtra esta consulta.
        $assinatura = Subscription::findOrFail($dados['subscription_id']);

        // Mesma razão do `update()`: o `business_id` do alvo entra no CÓDIGO, não só no
        // comentário, porque a `NoMissingTenantScopeRule` lê AST e é cega a comentário.
        $business_id = (int) ($assinatura->business_id ?? 0);

        $inicio = ! empty($dados['start_date']) ? $this->businessUtil->uf_date($dados['start_date']) : null;
        $fim = ! empty($dados['end_date']) ? $this->businessUtil->uf_date($dados['end_date']) : null;

        // Vigência invertida é erro de digitação, não estado válido: gravada, ela faz a
        // assinatura nascer vencida e some da fila de cobrança sem ninguém entender por quê.
        if ($inicio && $fim && $fim < $inicio) {
            return back()->with('status', [
                'success' => 0,
                'msg' => 'O fim da vigência não pode ser anterior ao início.',
            ]);
        }

        $assinatura->start_date = $inicio;
        $assinatura->end_date = $fim;
        $assinatura->trial_end_date = ! empty($dados['trial_end_date'])
            ? $this->businessUtil->uf_date($dados['trial_end_date'])
            : null;

        // O span envolve a ESCRITA, não fica ao lado dela: span que não abraça a operação mede
        // o tempo de nada e não falha junto quando a operação falha.
        //
        // Spatie LogsActivity no model registra o delta — a trilha da mudança de data sai daqui
        // sem código extra (é o mesmo mecanismo que cobre status).
        OtelHelper::spanBiz('superadmin.assinaturas.vigencia', function () use ($assinatura): bool {
            $assinatura->save();

            return true;
        }, [
            'module' => 'Superadmin',
            'component' => 'superadmin.assinaturas.vigencia',
            'subscription_id' => (int) $assinatura->id,
            'target_biz' => $business_id,
        ]);

        return back()->with('status', [
            'success' => 1,
            'msg' => 'Vigência salva. Nenhuma cobrança nova foi gerada.',
        ]);
    }
}
