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

        $linhas = $this->base($user, '')
            ->whereIn('sps.key', self::COLUNAS_PRODUCAO)
            ->where('sps.is_terminal', false)
            ->orderByRaw('t.delivery_date IS NULL, t.delivery_date ASC')
            ->limit(self::POR_COLUNA * count(self::COLUNAS_PRODUCAO))
            ->get([
                't.id', 't.invoice_no', 't.final_total', 't.delivery_date',
                'c.name as cliente', 'c.supplier_business_name as cliente_empresa',
                'sps.key as etapa_chave', 'sps.name as etapa_nome', 'sps.is_terminal',
            ]);

        $colunas = [];
        foreach (self::COLUNAS_PRODUCAO as $chave) {
            $daColuna = $linhas->where('etapa_chave', $chave);
            $colunas[] = [
                'id' => $chave,
                'rotulo' => (string) ($daColuna->first()->etapa_nome ?? self::ROTULOS_PADRAO[$chave]),
                'itens' => $daColuna->take(self::POR_COLUNA)->map(fn ($l) => $this->item($l))->values(),
            ];
        }

        return response()->json(['colunas' => $colunas]);
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

    // ------------------------------------------------------------------

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
    private function base(User $user, string $busca): Builder
    {
        $q = DB::table('transactions as t')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->leftJoin('sale_process_stages as sps', 't.current_stage_id', '=', 'sps.id')
            ->where('t.business_id', (int) $user->business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
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
