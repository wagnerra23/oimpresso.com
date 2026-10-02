<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Http\Controllers\Controller;
use App\Transaction;
use App\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos do app das lojas (oimpresso-app) — SÓ LEITURA na v1.
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §2. Pedido = venda do ERP (D11).
 *
 * Mesmas regras da lista web de vendas (SellController::inertiaList): venda final sem
 * sub_type, permissões direct_sell.view / view_own_sell_only / view_commission_agent_sell e
 * locais permitidos. Tier 0 (ADR 0093): business_id do usuário do token, nunca da requisição.
 *
 * As ações de etapa só são LISTADAS (com `pode`); executar fica fora da v1 porque várias
 * reservam/baixam estoque ou cancelam cobrança (regra mestre de valor/estoque).
 */
class PedidosController extends Controller
{
    /** Estágio da FSM `venda_com_producao` → passo do protótipo (MAPA-DE-DADOS-v1 §3). */
    public const GRUPOS = [
        'quote_draft' => 'orcamento',
        'quote_sent' => 'orcamento',
        'quote_approved' => 'aprovacao',
        'in_production' => 'producao',
        'on_hold' => 'producao',
        'ready_for_invoice' => 'entrega',
        'invoiced' => 'entrega',
        'paid' => 'entrega',
        'delivered' => 'entrega',
        'completed' => 'concluido',
        'cancelled' => 'cancelado',
    ];

    public const ORDEM = ['orcamento', 'aprovacao', 'producao', 'entrega', 'concluido'];

    private const POR_PAGINA = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        $filtro = (string) $request->query('filtro', 'ativos');
        if (! in_array($filtro, ['ativos', 'atrasados', 'concluidos', 'todos'], true)) {
            $filtro = 'ativos';
        }
        $pagina = max((int) $request->query('pagina', 1), 1);
        $busca = trim((string) $request->query('q', ''));

        $linhas = $this->filtrar($this->base($user, $busca), $filtro)
            ->orderByRaw('t.delivery_date IS NULL, t.delivery_date ASC')
            ->orderByDesc('t.transaction_date')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([
                't.id', 't.invoice_no', 't.final_total', 't.delivery_date',
                'c.name as cliente', 'c.supplier_business_name as cliente_empresa',
                'sps.key as etapa_chave', 'sps.name as etapa_nome', 'sps.is_terminal',
                // Resumo do cartão (o v4 mostra o produto): nome do 1º item da venda.
                DB::raw('(SELECT p.name FROM transaction_sell_lines tsl JOIN products p ON p.id = tsl.product_id'
                    . ' WHERE tsl.transaction_id = t.id AND tsl.parent_sell_line_id IS NULL ORDER BY tsl.id LIMIT 1) as resumo'),
            ]);

        $temMais = $linhas->count() > self::POR_PAGINA;

        $contadores = [];
        foreach (['ativos', 'atrasados', 'concluidos', 'todos'] as $f) {
            $contadores[$f] = $this->filtrar($this->base($user, $busca), $f)->count();
        }

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($l) => $this->item($l))->values(),
            'contadores' => $contadores,
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        $l = $this->base($user, '')
            ->where('t.id', $id)
            ->first([
                't.id', 't.invoice_no', 't.final_total', 't.delivery_date', 't.current_stage_id',
                'c.id as cliente_id', 'c.name as cliente', 'c.supplier_business_name as cliente_empresa',
                'c.mobile as cliente_telefone',
                'sps.key as etapa_chave', 'sps.name as etapa_nome', 'sps.is_terminal',
            ]);

        if (! $l) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Pedido não encontrado.'], 404);
        }

        $itensVenda = DB::table('transaction_sell_lines as tsl')
            ->leftJoin('products as p', 'tsl.product_id', '=', 'p.id')
            ->where('tsl.transaction_id', $l->id)
            ->whereNull('tsl.parent_sell_line_id')
            ->get(['p.name as produto', 'tsl.quantity', 'tsl.unit_price_inc_tax'])
            ->map(fn ($s) => [
                'produto' => (string) $s->produto,
                'quantidade' => (float) $s->quantity,
                'total' => round((float) $s->quantity * (float) $s->unit_price_inc_tax, 2),
            ])->values();

        $grupo = self::GRUPOS[$l->etapa_chave] ?? null;
        $atual = $grupo !== null ? array_search($grupo, self::ORDEM, true) : false;

        return response()->json($this->item($l) + [
            'cliente_detalhe' => [
                'id' => (int) $l->cliente_id,
                'nome' => $this->nomeCliente($l),
                'telefone' => $l->cliente_telefone,
            ],
            'itens_venda' => $itensVenda,
            'etapas' => collect(self::ORDEM)->map(fn ($g, $i) => [
                'grupo' => $g,
                'estado' => $atual === false ? 'futuro' : ($i < $atual ? 'feito' : ($i === $atual ? 'atual' : 'futuro')),
            ])->values(),
            'acoes' => $this->acoes($user, (int) $l->id, $l->current_stage_id),
        ]);
    }

    /** Colunas da aba Produção (decisão [W] 2026-10-02: Produção usa as etapas da VENDA). */
    public const COLUNAS_PRODUCAO = ['quote_approved', 'in_production', 'on_hold', 'ready_for_invoice'];

    private const POR_COLUNA = 50;

    /**
     * GET /api/app/producao — fila de produção por etapa da venda (contrato §5). Mesmas regras de
     * visibilidade da lista de pedidos; só leitura (mover de etapa é ação FSM, fora da v1).
     * Rótulo da coluna = nome do estágio cadastrado no business.
     */
    public function producao(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        // Uma consulta por coluna: o limite vale POR coluna (contrato §5). Com um limit único no
        // total, uma etapa cheia ocupava todas as vagas e esvaziava as outras.
        $colunas = [];
        foreach (self::COLUNAS_PRODUCAO as $chave) {
            $daColuna = fn () => $this->base($user, '')
                ->where('sps.key', $chave)
                ->where('sps.is_terminal', false);

            $linhas = $daColuna()
                ->orderByRaw('t.delivery_date IS NULL, t.delivery_date ASC')
                ->orderBy('t.id')
                ->limit(self::POR_COLUNA)
                ->get([
                    't.id', 't.invoice_no', 't.final_total', 't.delivery_date',
                    'c.name as cliente', 'c.supplier_business_name as cliente_empresa',
                    'sps.key as etapa_chave', 'sps.name as etapa_nome', 'sps.is_terminal',
                ]);

            $colunas[] = [
                'id' => $chave,
                'rotulo' => (string) ($linhas->first()->etapa_nome ?? self::ROTULOS_PADRAO[$chave]),
                'total' => $linhas->count() < self::POR_COLUNA ? $linhas->count() : $daColuna()->count(),
                'itens' => $linhas->map(fn ($l) => $this->item($l))->values(),
            ];
        }

        return response()->json(['colunas' => $colunas]);
    }

    public const STATUS_ORCAMENTO = ['todos', 'rascunho', 'enviado', 'aprovado', 'convertido'];

    /**
     * GET /api/app/orcamentos — tela 04, só leitura (contrato §2.1). Orçamento no ERP é venda em
     * rascunho (`status=draft`); com `sub_status=quotation` foi enviado ao cliente
     * (InitialStageResolver). Aprovado = etapa `quote_approved`. Convertido = venda `final` cujo
     * histórico da FSM passou por uma etapa de orçamento. Mesma visibilidade de Pedidos.
     */
    public function orcamentos(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        $status = (string) $request->query('status', 'todos');
        if (! in_array($status, self::STATUS_ORCAMENTO, true)) {
            $status = 'todos';
        }
        $pagina = max((int) $request->query('pagina', 1), 1);

        $linhas = $this->filtrarOrcamento($this->base($user, '', null), $status)
            ->orderByDesc('t.transaction_date')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([
                't.id', 't.invoice_no', 't.ref_no', 't.final_total', 't.status', 't.sub_status',
                'c.name as cliente', 'c.supplier_business_name as cliente_empresa', 'sps.key as etapa_chave',
                DB::raw('(SELECT p.name FROM transaction_sell_lines tsl JOIN products p ON p.id = tsl.product_id'
                    . ' WHERE tsl.transaction_id = t.id AND tsl.parent_sell_line_id IS NULL ORDER BY tsl.id LIMIT 1) as titulo'),
                DB::raw('(SELECT COUNT(*) FROM transaction_sell_lines tsl WHERE tsl.transaction_id = t.id AND tsl.parent_sell_line_id IS NULL) as qtd_itens'),
            ]);

        $contadores = [];
        foreach (self::STATUS_ORCAMENTO as $st) {
            $contadores[$st] = $this->filtrarOrcamento($this->base($user, '', null), $st)->count();
        }

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($l) => [
                'id' => (int) $l->id,
                'numero' => (string) ($l->invoice_no ?: $l->ref_no),
                'titulo' => $l->titulo !== null ? (string) $l->titulo : null,
                'cliente' => $this->nomeCliente($l),
                'validade' => null,
                'status' => $this->statusOrcamento($l),
                'valor' => round((float) $l->final_total, 2),
                'area_m2' => null,
                'itens' => (int) $l->qtd_itens,
            ])->values(),
            'contadores' => $contadores,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
        ]);
    }

    private function filtrarOrcamento(Builder $q, string $status): Builder
    {
        $rascunho = fn ($qq) => $qq->where('t.status', 'draft')
            ->where(fn ($x) => $x->whereNull('t.sub_status')->orWhere('t.sub_status', '!=', 'quotation'))
            ->where(fn ($x) => $x->whereNull('sps.key')->orWhere('sps.key', 'quote_draft'));
        $enviado = fn ($qq) => $qq->where('t.status', 'draft')->where('t.sub_status', 'quotation')
            ->where(fn ($x) => $x->whereNull('sps.key')->orWhere('sps.key', 'quote_sent'));
        $aprovado = fn ($qq) => $qq->where('sps.key', 'quote_approved');
        $convertido = fn ($qq) => $qq->where('t.status', 'final')
            ->whereNotIn('sps.key', ['quote_draft', 'quote_sent', 'quote_approved', 'cancelled'])
            ->whereExists(fn ($e) => $e->selectRaw('1')->from('sale_stage_history as h')
                ->join('sale_process_stages as hs', 'hs.id', '=', 'h.to_stage_id')
                ->whereColumn('h.transaction_id', 't.id')
                ->whereIn('hs.key', ['quote_draft', 'quote_sent', 'quote_approved']));

        return match ($status) {
            'rascunho' => $rascunho($q),
            'enviado' => $enviado($q),
            'aprovado' => $aprovado($q),
            'convertido' => $convertido($q),
            default => $q->where(fn ($o) => $o->where($rascunho)->orWhere($enviado)->orWhere($aprovado)->orWhere($convertido)),
        };
    }

    private function statusOrcamento(object $l): string
    {
        if ($l->etapa_chave === 'quote_approved') {
            return 'aprovado';
        }
        if ($l->status === 'final') {
            return 'convertido';
        }

        return $l->sub_status === 'quotation' ? 'enviado' : 'rascunho';
    }

    /** Rótulo quando a coluna está vazia (os do seed FsmProcessoVendaComProducaoSeeder). */
    private const ROTULOS_PADRAO = [
        'quote_approved' => 'Aprovado pelo cliente',
        'in_production' => 'Em produção',
        'on_hold' => 'Em espera',
        'ready_for_invoice' => 'Pronto pra faturar',
    ];

    /**
     * Contagem de pedidos ativos e atrasados do usuário (para o Início), com as mesmas regras da
     * lista. null quando o usuário não vê vendas.
     *
     * @return array{ativos:int, atrasados:int}|null
     */
    public function contadoresPara(User $user): ?array
    {
        if (! $this->podeVer($user)) {
            return null;
        }

        return [
            'ativos' => $this->filtrar($this->base($user, ''), 'ativos')->count(),
            'atrasados' => $this->filtrar($this->base($user, ''), 'atrasados')->count(),
        ];
    }

    /**
     * Pedidos (vendas finais visíveis ao usuário, mesmas regras da lista) por data da venda nos
     * últimos `$dias` dias até hoje, do mais antigo ao de hoje. null quando o usuário não vê vendas.
     * O último ponto é o `pedidos_novos` do Dashboard (§10.4).
     *
     * @return list<array{data: string, total: int}>|null
     */
    public function porDiaPara(User $user, int $dias): ?array
    {
        if (! $this->podeVer($user)) {
            return null;
        }
        $hoje = now()->startOfDay();
        $ini = $hoje->copy()->subDays($dias - 1);
        $contagem = $this->base($user, '')
            ->whereDate('t.transaction_date', '>=', $ini->toDateString())
            ->whereDate('t.transaction_date', '<=', $hoje->toDateString())
            ->groupBy(DB::raw('DATE(t.transaction_date)'))
            ->selectRaw('DATE(t.transaction_date) as dia, COUNT(*) as total')
            ->get()
            ->pluck('total', 'dia');

        $serie = [];
        for ($d = $ini->copy(); $d->lte($hoje); $d->addDay()) {
            $serie[] = ['data' => $d->toDateString(), 'total' => (int) ($contagem[$d->toDateString()] ?? 0)];
        }

        return $serie;
    }

    // ------------------------------------------------------------------

    /** Quem vê Pedidos e Produção (mesma regra das duas abas). */
    public function podeVerVendas(User $user): bool
    {
        return $this->podeVer($user);
    }

    private function podeVer(?User $user): bool
    {
        return $user !== null && (
            $user->can('direct_sell.view')
            || $user->can('view_own_sell_only')
            || $user->can('view_commission_agent_sell')
        );
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não tem acesso aos pedidos.',
        ], 403);
    }

    /** Vendas visíveis ao usuário, com cliente e estágio. Mesmas regras da lista web. */
    private function base(User $user, string $busca, ?string $status = 'final'): Builder
    {
        $q = DB::table('transactions as t')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->leftJoin('sale_process_stages as sps', 't.current_stage_id', '=', 'sps.id')
            ->where('t.business_id', (int) $user->business_id)
            ->where('t.type', 'sell')
            ->when($status !== null, fn ($qq) => $qq->where('t.status', $status))
            ->whereNull('t.sub_type');

        if (! $user->can('direct_sell.view')) {
            $q->where(function ($qq) use ($user) {
                $qq->whereRaw('1 = 0');
                if ($user->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $qq->orWhere('t.created_by', $user->id);
                }
                if ($user->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $qq->orWhere('t.commission_agent', $user->id);
                }
            });
        }

        $locais = $user->permitted_locations((int) $user->business_id);
        if ($locais !== 'all') {
            $q->whereIn('t.location_id', (array) $locais);
        }

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $q->where(fn ($qq) => $qq->where('c.name', 'like', $like)
                ->orWhere('c.supplier_business_name', 'like', $like)
                ->orWhere('t.invoice_no', 'like', $like));
        }

        return $q;
    }

    /**
     * Ativos/atrasados/concluídos só existem para venda no pipeline FSM (`current_stage_id`);
     * venda legada sem estágio aparece apenas em "todos".
     */
    private function filtrar(Builder $q, string $filtro): Builder
    {
        $hoje = now()->toDateString();

        return match ($filtro) {
            'ativos' => $q->where('sps.is_terminal', false),
            'atrasados' => $q->where('sps.is_terminal', false)
                ->whereNotNull('t.delivery_date')
                ->whereDate('t.delivery_date', '<', $hoje),
            'concluidos' => $q->where('sps.is_terminal', true),
            default => $q,
        };
    }

    private function item(object $l): array
    {
        $grupo = self::GRUPOS[$l->etapa_chave] ?? null;
        $terminal = (bool) ($l->is_terminal ?? false);
        $prazo = $l->delivery_date ? substr((string) $l->delivery_date, 0, 10) : null;
        $pos = $grupo !== null ? array_search($grupo, self::ORDEM, true) : false;

        return [
            'id' => (int) $l->id,
            'numero' => (string) $l->invoice_no,
            'cliente' => $this->nomeCliente($l),
            'resumo' => isset($l->resumo) ? (string) $l->resumo : null,
            'valor' => round((float) $l->final_total, 2),
            'prazo' => $prazo,
            'atrasado' => $prazo !== null && ! $terminal && $l->etapa_chave !== null && $prazo < now()->toDateString(),
            'etapa' => $l->etapa_chave === null ? null : [
                'chave' => (string) $l->etapa_chave,
                'rotulo' => (string) $l->etapa_nome,
                'grupo' => $grupo,
            ],
            'progresso' => $pos === false ? null : $pos / (count(self::ORDEM) - 1),
        ];
    }

    private function nomeCliente(object $l): string
    {
        return trim((string) ($l->cliente_empresa ?: $l->cliente)) ?: '—';
    }

    /** Ações da etapa atual, só para mostrar (rótulo + se o usuário poderia executar). */
    private function acoes(User $user, int $id, $stageId): array
    {
        if ($stageId === null) {
            return [];
        }
        $venda = Transaction::where('business_id', (int) $user->business_id)->find($id);
        $policy = app(StageActionPolicy::class);

        return SaleStageAction::query()
            ->where('stage_id', (int) $stageId)
            ->get()
            ->map(fn (SaleStageAction $a) => [
                'chave' => (string) $a->key,
                'rotulo' => (string) $a->label,
                'pode' => $venda ? (bool) $policy->canExecute($user, $venda, $a->key) : false,
            ])->values()->all();
    }
}
