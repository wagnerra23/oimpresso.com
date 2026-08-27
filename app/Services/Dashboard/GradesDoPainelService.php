<?php

namespace App\Services\Dashboard;

use App\Transaction;
use App\Utils\ProductUtil;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Grades do painel "Visão geral" — a camada de DADOS das abas (US-DASH-005).
 *
 * POR QUE ESTE SERVICE EXISTE, e não o reuso dos endpoints AJAX:
 * o charter de `Home/Index` tem Non-Goal explícito — "NÃO toca endpoints AJAX
 * (/home/get-totals, /home/product-stock-alert, /home/purchase-payment-dues,
 * /home/sales-payment-dues) — preservados intactos pro Blade legacy continuar
 * funcionando". Medido em 2026-08-27: os 3 endpoints de grade devolvem HTML
 * DENTRO das células (`<span class="display_currency">`, `<a class="btn-modal"
 * data-href>`, coluna `action` com `add_payment_modal`), com `rawColumns` e
 * `make(false)`. O número só vira número depois do `__currency_convert_recursively`
 * do jQuery, e o link do `btn-modal` abre um modal que não existe fora do Blade.
 * Consumir aquilo do React exigiria ou injetar HTML dependente de jQuery, ou
 * mudar o formato de saída — que é justamente o que o Non-Goal proíbe.
 *
 * Então: queries próprias, saída JSON limpa, e os endpoints antigos INTACTOS.
 * A duplicação de critério (o "vence em <= 7 dias" das duas grades de título) é
 * imposta pelo Non-Goal, não é descuido — e fica travada contra drift pelo teste
 * de paridade em tests/Feature/Home/GradesDoPainelTest.php, que compara o conjunto
 * de ids desta classe com o do endpoint legado.
 *
 * Onde o método público já existia, ele é REUSADO em vez de replicado:
 * `estoque` chama `ProductUtil::getProductAlert`, o mesmo que o endpoint legado usa.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): toda query filtra `business_id`.
 */
class GradesDoPainelService
{
    /** Linhas por página em cada grade. */
    public const POR_PAGINA = 10;

    public function __construct(private ProductUtil $productUtil)
    {
    }

    /**
     * As 8 grades do Blade legado, medidas no arquivo em 2026-08-27.
     *
     * `perms` é OR — basta uma. `setting` é a chave que precisa estar ligada, o
     * mesmo gate condicional do Blade: é por isso que uma sondagem de runtime vê
     * 5 abas num business e 8 noutro. Aba ausente é ausência de PERMISSÃO ou de
     * SETTING, nunca de capacidade.
     *
     * @return array<string, array{label: string, perms: string[], setting: string|null}>
     */
    public static function catalogo(): array
    {
        return [
            'venc-venda' => [
                'label' => 'Vencimentos de venda',
                'perms' => ['sell.view', 'direct_sell.view'],
                'setting' => null,
            ],
            'venc-compra' => [
                'label' => 'Vencimentos de compra',
                'perms' => ['purchase.view'],
                'setting' => null,
            ],
            'estoque' => [
                'label' => 'Estoque mínimo',
                'perms' => ['stock_report.view'],
                'setting' => null,
            ],
            'validade' => [
                'label' => 'Lotes a vencer',
                'perms' => ['stock_report.view'],
                'setting' => 'enable_product_expiry',
            ],
            'pedidos' => [
                'label' => 'Pedidos de venda',
                'perms' => ['so.view_all', 'so.view_own'],
                'setting' => null,
            ],
            'compras-abertas' => [
                'label' => 'Ordens de compra',
                'perms' => ['purchase_order.view_all', 'purchase_order.view_own'],
                'setting' => 'enable_purchase_order',
            ],
            'requisicoes' => [
                'label' => 'Requisições',
                'perms' => ['purchase_requisition.view_all', 'purchase_requisition.view_own'],
                'setting' => 'enable_purchase_requisition',
            ],
            'expedicao' => [
                'label' => 'Expedições pendentes',
                'perms' => ['access_shipping', 'access_pending_shipments_only', 'access_own_shipping'],
                'setting' => null,
            ],
        ];
    }

    /**
     * Abas que ESTE usuário, NESTE business, pode ver.
     *
     * A aba some quando falta permissão — não aparece desabilitada. É o que o
     * protótipo faz (`abas = Object.keys(GRADES).filter(id => can(...))`) e o que
     * o Blade faz com `@can` em volta de cada card.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function abasPermitidas(): array
    {
        $user = auth()->user();
        $abas = [];

        foreach (self::catalogo() as $key => $cfg) {
            if ($cfg['setting'] !== null && ! $this->settingLigado($cfg['setting'])) {
                continue;
            }

            foreach ($cfg['perms'] as $perm) {
                if ($user->can($perm)) {
                    $abas[] = ['key' => $key, 'label' => $cfg['label']];
                    break;
                }
            }
        }

        return $abas;
    }

    /**
     * Resolve a aba pedida contra as permitidas. Chave desconhecida, sem permissão
     * ou ausente cai na primeira permitida — nunca estoura, nunca serve dado que o
     * usuário não pode ver.
     */
    public function resolverAba(?string $pedida): ?string
    {
        $permitidas = array_column($this->abasPermitidas(), 'key');

        if ($pedida !== null && in_array($pedida, $permitidas, true)) {
            return $pedida;
        }

        return $permitidas[0] ?? null;
    }

    /**
     * Linhas da grade, paginadas. Devolve `null` quando a aba não é permitida — a
     * checagem é refeita aqui de propósito: quem chama pode ter recebido a chave de
     * uma query string, e query string é entrada do usuário.
     */
    public function linhas(string $aba, int $businessId, ?int $locationId = null, int $pagina = 1): ?LengthAwarePaginator
    {
        if (! in_array($aba, array_column($this->abasPermitidas(), 'key'), true)) {
            return null;
        }

        return match ($aba) {
            'venc-venda' => $this->titulosVencendo($businessId, $locationId, $pagina, 'sell'),
            'venc-compra' => $this->titulosVencendo($businessId, $locationId, $pagina, 'purchase'),
            'estoque' => $this->estoqueMinimo($businessId, $pagina),
            'validade' => $this->lotesAVencer($businessId, $locationId, $pagina),
            'pedidos' => $this->pedidosDeVenda($businessId, $locationId, $pagina),
            'compras-abertas' => $this->documentosDeCompra($businessId, $locationId, $pagina, 'purchase_order'),
            'requisicoes' => $this->documentosDeCompra($businessId, $locationId, $pagina, 'purchase_requisition'),
            'expedicao' => $this->expedicoesPendentes($businessId, $locationId, $pagina),
            default => null,
        };
    }

    /**
     * Títulos de venda ou compra vencendo em até 7 dias — mesmo critério do
     * `getSalesPaymentDues` / `getPurchasePaymentDues` do HomeController.
     *
     * Diferença deliberada de implementação: o legado faz `join` em
     * `transaction_payments` + `groupBy`, o que quebra a contagem do `paginate()`.
     * Aqui o pago vem por subconsulta escalar — mesmo número, sem agregação na
     * cláusula principal.
     */
    private function titulosVencendo(int $businessId, ?int $locationId, int $pagina, string $tipo): LengthAwarePaginator
    {
        $vencimento = "DATE_ADD(transactions.transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY)";

        $query = Transaction::query()
            ->join('contacts as c', 'transactions.contact_id', '=', 'c.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', $tipo)
            ->where('transactions.payment_status', '!=', 'paid')
            ->whereRaw("DATEDIFF({$vencimento}, ?) <= 7", [now()->format('Y-m-d H:i:s')]);

        if ($tipo === 'sell') {
            $query->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type');
        }

        $this->escoparLocais($query, $locationId);

        $paginator = $query
            ->select([
                'transactions.id',
                'transactions.invoice_no',
                'transactions.ref_no',
                'transactions.payment_status',
                'transactions.final_total',
                'c.name as contato',
                'c.supplier_business_name',
                DB::raw("{$vencimento} as vencimento"),
                DB::raw('(SELECT COALESCE(SUM(tp.amount), 0) FROM transaction_payments tp WHERE tp.transaction_id = transactions.id) as total_pago'),
            ])
            ->orderBy('vencimento')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina);

        return $paginator->through(function ($row) use ($tipo) {
            $devido = (float) $row->final_total - (float) $row->total_pago;

            return [
                'id' => (int) $row->id,
                'documento' => (string) ($tipo === 'sell' ? $row->invoice_no : $row->ref_no),
                'contato' => (string) ($row->supplier_business_name ?: $row->contato),
                'vencimento' => $this->dia($row->vencimento),
                'situacao' => (string) $row->payment_status,
                'devido' => round($devido, 2),
                'state' => $this->venceu($row->vencimento) ? 'urgent' : null,
            ];
        });
    }

    /**
     * Estoque abaixo do mínimo. REUSA `ProductUtil::getProductAlert` — o mesmo
     * método que `/home/product-stock-alert` chama. Zero duplicação de query aqui.
     *
     * O `alert_quantity` é adicionado por `addSelect` NESTA instância, e não dentro
     * do `getProductAlert`: o método é compartilhado com o endpoint legado, e uma
     * coluna a mais no select dele apareceria no JSON do `/home/product-stock-alert`
     * — que o charter manda preservar intacto.
     *
     * `state: urgent` só na ruptura de fato (saldo <= 0). Toda linha desta grade
     * está abaixo do mínimo por definição; marcar todas de vermelho não distingue
     * nada e é ruído.
     */
    private function estoqueMinimo(int $businessId, int $pagina): LengthAwarePaginator
    {
        $query = $this->productUtil->getProductAlert($businessId, auth()->user()->permitted_locations());

        return $query
            ->addSelect('p.alert_quantity as minimo')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina)
            ->through(fn ($row) => [
                'id' => (string) ($row->sub_sku ?: $row->sku),
                'produto' => $row->type === 'single'
                    ? $row->product.' ('.$row->sku.')'
                    : $row->product.' - '.$row->variation.' ('.$row->sub_sku.')',
                'loja' => (string) ($row->location ?? ''),
                'atual' => trim(((float) ($row->stock ?? 0)).' '.($row->unit ?? '')),
                'minimo' => (float) ($row->minimo ?? 0),
                'state' => (float) ($row->stock ?? 0) <= 0 ? 'urgent' : null,
            ]);
    }

    /** Lotes que vencem dentro da janela do business (`stock_expiry_alert_days`, default 30). */
    private function lotesAVencer(int $businessId, ?int $locationId, int $pagina): LengthAwarePaginator
    {
        $limite = now()->addDays((int) session('business.stock_expiry_alert_days', 30))->format('Y-m-d');

        $query = DB::table('purchase_lines as pl')
            ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->join('products as p', 'pl.product_id', '=', 'p.id')
            ->leftJoin('business_locations as l', 't.location_id', '=', 'l.id')
            ->where('t.business_id', $businessId)
            ->whereNotNull('pl.exp_date')
            ->whereDate('pl.exp_date', '<=', $limite)
            ->whereRaw('(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned) > 0');

        $permitidos = auth()->user()->permitted_locations();
        if ($permitidos !== 'all') {
            $query->whereIn('t.location_id', $permitidos);
        }

        if ($locationId !== null) {
            $query->where('t.location_id', $locationId);
        }

        return $query
            ->select([
                'pl.id',
                'p.name as produto',
                'pl.lot_number',
                'pl.exp_date',
                'l.name as loja',
                DB::raw('(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned) as saldo'),
            ])
            ->orderBy('pl.exp_date')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina)
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'produto' => (string) $row->produto,
                'lote' => (string) ($row->lot_number ?? ''),
                'loja' => (string) ($row->loja ?? ''),
                'saldo' => (float) $row->saldo,
                'vencimento' => $this->dia($row->exp_date),
                'state' => $this->venceu($row->exp_date) ? 'urgent' : null,
            ]);
    }

    /**
     * Pedidos de venda em aberto — `status IN (partial, ordered)`, o mesmo filtro
     * que `SellController@index` aplica sob `for_dashboard_sales_order`.
     *
     * `so.view_own` sem `so.view_all` restringe ao que o próprio usuário criou — a
     * mesma regra do SellController.
     */
    private function pedidosDeVenda(int $businessId, ?int $locationId, int $pagina): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->leftJoin('contacts as c', 'transactions.contact_id', '=', 'c.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sales_order')
            ->whereIn('transactions.status', ['partial', 'ordered']);

        if (! auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
            $query->where('transactions.created_by', auth()->id());
        }

        $this->escoparLocais($query, $locationId);

        return $this->documentos($query, 'invoice_no', $pagina);
    }

    /** Ordens de compra / requisições em aberto — `status != completed`. */
    private function documentosDeCompra(int $businessId, ?int $locationId, int $pagina, string $tipo): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->leftJoin('contacts as c', 'transactions.contact_id', '=', 'c.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', $tipo)
            ->where('transactions.status', '!=', 'completed');

        $this->escoparLocais($query, $locationId);

        return $this->documentos($query, 'ref_no', $pagina);
    }

    /** Vendas com expedição não entregue — filtro `only_pending_shipments` do SellController. */
    private function expedicoesPendentes(int $businessId, ?int $locationId, int $pagina): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->leftJoin('contacts as c', 'transactions.contact_id', '=', 'c.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->whereNotNull('transactions.shipping_status')
            ->where('transactions.shipping_status', '!=', 'delivered');

        $this->escoparLocais($query, $locationId);

        return $query
            ->select([
                'transactions.id',
                'transactions.invoice_no',
                'transactions.transaction_date',
                'transactions.shipping_status',
                'transactions.delivered_to',
                'c.name as contato',
            ])
            ->orderByDesc('transactions.transaction_date')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina)
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'documento' => (string) $row->invoice_no,
                'contato' => (string) ($row->contato ?: $row->delivered_to ?: ''),
                'data' => $this->dia($row->transaction_date),
                'situacao' => (string) $row->shipping_status,
                'state' => null,
            ]);
    }

    /** Shape comum de pedido / ordem / requisição. */
    private function documentos(Builder $query, string $colunaNumero, int $pagina): LengthAwarePaginator
    {
        return $query
            ->select([
                'transactions.id',
                'transactions.'.$colunaNumero.' as numero',
                'transactions.transaction_date',
                'transactions.status',
                'transactions.final_total',
                'c.name as contato',
                'c.supplier_business_name',
            ])
            ->orderByDesc('transactions.transaction_date')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina)
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'documento' => (string) $row->numero,
                'contato' => (string) ($row->supplier_business_name ?: $row->contato ?: ''),
                'data' => $this->dia($row->transaction_date),
                'situacao' => (string) $row->status,
                'total' => round((float) $row->final_total, 2),
                'state' => null,
            ]);
    }

    /**
     * Locais permitidos + filtro de loja da tela. Mesma ordem do legado: a permissão
     * do usuário primeiro, o filtro da UI depois — filtro de UI nunca amplia o que a
     * permissão restringe.
     */
    private function escoparLocais(Builder $query, ?int $locationId): void
    {
        $permitidos = auth()->user()->permitted_locations();

        if ($permitidos !== 'all') {
            $query->whereIn('transactions.location_id', $permitidos);
        }

        if ($locationId !== null) {
            $query->where('transactions.location_id', $locationId);
        }
    }

    /** Data ISO pra UI formatar — o backend não decide formato de exibição. */
    private function dia($valor): ?string
    {
        return $valor === null ? null : date('Y-m-d', strtotime((string) $valor));
    }

    private function venceu($valor): bool
    {
        return $valor !== null && strtotime((string) $valor) < strtotime('today');
    }

    private function settingLigado(string $chave): bool
    {
        if ($chave === 'enable_product_expiry') {
            return (int) session('business.enable_product_expiry') === 1;
        }

        $common = session('business.common_settings');

        return ! empty(is_array($common) ? ($common[$chave] ?? null) : null);
    }
}
