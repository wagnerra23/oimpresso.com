<?php

namespace App\Http\Controllers\Restaurant;

use App\Services\FeatureFlagService;
use App\TransactionSellLine;
use App\User;
use App\Utils\RestaurantUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Flag do caminho React (thread restaurante/playbook/05, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista: default OFF,
     * a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Restaurante/RUNBOOK-pedidos.md
     */
    private const FLAG_V2 = 'useV2RestaurantePedidos';

    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $restUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @param  RestaurantUtil  $restUtil
     * @return void
     */
    public function __construct(Util $commonUtil, RestaurantUtil $restUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        // if (!auth()->user()->can('sell.view')) {
        //     abort(403, 'Unauthorized action.');
        // }
        $business_id = request()->session()->get('user.business_id');
        $user_id = request()->session()->get('user.id');

        // `?classico=1` mantém a Blade alcançável com a flag ligada (imprimir item segue nela).
        if (! request()->boolean('classico') && (! request()->ajax() || request()->inertia())
            && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            // Mesma escolha de atendente da Blade: o atendente vê os dele; outro usuário escolhe pelo seletor.
            $e_atendente = $this->restUtil->is_service_staff($user_id);
            $atendente = $e_atendente ? $user_id : (request()->service_staff ?: null);

            return Inertia::render('Restaurante/Pedidos/Index', [
                'e_atendente' => $e_atendente,
                'atendente_escolhido' => $e_atendente || empty($atendente) ? null : (int) $atendente,
                'atendentes' => $e_atendente ? [] : collect($this->restUtil->service_staff_dropdown($business_id))
                    ->map(fn ($nome, $id) => ['id' => (int) $id, 'nome' => $nome])->values()->all(),
                'itens' => Inertia::defer(fn () => $atendente ? $this->itensPorServir((int) $business_id, $atendente) : []),
                'pedidos' => Inertia::defer(fn () => $atendente ? $this->pedidosDoAtendente((int) $business_id, $atendente) : []),
            ]);
        }

        $is_service_staff = false;
        $orders = [];
        $service_staff = [];
        $line_orders = [];
        if ($this->restUtil->is_service_staff($user_id)) {
            $is_service_staff = true;
            $orders = $this->restUtil->getAllOrders($business_id, ['waiter_id' => $user_id]);

            $line_orders = $this->restUtil->getLineOrders($business_id, ['waiter_id' => $user_id]);
        } elseif (! empty(request()->service_staff)) {
            $orders = $this->restUtil->getAllOrders($business_id, ['waiter_id' => request()->service_staff]);

            $line_orders = $this->restUtil->getLineOrders($business_id, ['waiter_id' => request()->service_staff]);
        }

        if (! $is_service_staff) {
            $service_staff = $this->restUtil->service_staff_dropdown($business_id);
        }

        return view('restaurant.orders.index', compact('orders', 'is_service_staff', 'service_staff', 'line_orders'));
    }

    /**
     * Itens do atendente ainda não servidos, como os `line_orders` da Blade (RestaurantUtil::getLineOrders).
     *
     * @return list<array<string, mixed>>
     */
    private function itensPorServir(int $business_id, $atendente): array
    {
        return collect($this->restUtil->getLineOrders($business_id, ['waiter_id' => $atendente]))->map(fn ($l) => [
            'id' => $l->id,
            'pedido_id' => $l->transaction_id,
            'nota' => $l->invoice_no,
            'feito_em' => (string) $l->created_at,
            'cliente' => $l->customer_name,
            'mesa' => $l->table_name,
            'local' => $l->business_location,
            'produto' => $l->product_type === 'variable'
                ? trim($l->product_name.' - '.$l->product_variation_name.' - '.$l->variation_name)
                : $l->product_name,
            'quantidade' => (float) $l->quantity,
            'observacao' => $l->sell_line_note,
            'situacao' => $l->res_line_order_status ?? 'received',
        ])->values()->all();
    }

    /**
     * Pedidos do atendente ainda não servidos, como os `orders` da Blade (RestaurantUtil::getAllOrders), com a situação
     * pela regra do partials/show_orders.blade.php (RestaurantUtil::situacaoDoPedido).
     *
     * @return list<array<string, mixed>>
     */
    private function pedidosDoAtendente(int $business_id, $atendente): array
    {
        $pedidos = $this->restUtil->getAllOrders($business_id, ['waiter_id' => $atendente])->load('sell_lines.product:id,name');

        return $pedidos->map(fn ($p) => [
            'id' => $p->id,
            'nota' => $p->invoice_no,
            'feito_em' => (string) $p->created_at,
            'cliente' => $p->customer_name,
            'mesa' => $p->table_name,
            'local' => $p->business_location,
            'situacao' => $this->restUtil->situacaoDoPedido($p->sell_lines),
            'itens' => $p->sell_lines->map(fn ($l) => [
                'id' => $l->id,
                'nome' => $l->product?->name,
                'quantidade' => (float) $l->quantity,
                'situacao' => $l->res_line_order_status ?? 'received',
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Marks an order as served
     *
     * @return json $output
     */
    public function markAsServed($id)
    {
        // if (!auth()->user()->can('sell.update')) {
        //     abort(403, 'Unauthorized action.');
        // }
        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $query = TransactionSellLine::leftJoin('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
                        ->where('t.business_id', $business_id)
                        ->where('transaction_id', $id);

            if ($this->restUtil->is_service_staff($user_id)) {
                $query->where('res_waiter_id', $user_id);
            }

            $query->update(['res_line_order_status' => 'served']);

            $output = ['success' => 1,
                'msg' => trans('restaurant.order_successfully_marked_served'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Marks an line order as served
     *
     * @return json $output
     */
    public function markLineOrderAsServed($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $query = TransactionSellLine::where('id', $id);

            if ($this->restUtil->is_service_staff($user_id)) {
                $query->where('res_service_staff_id', $user_id);
            }
            $sell_line = $query->first();

            if (! empty($sell_line)) {
                $sell_line->res_line_order_status = 'served';
                $sell_line->save();
                $output = ['success' => 1,
                    'msg' => trans('restaurant.order_successfully_marked_served'),
                ];
            } else {
                $output = ['success' => 0,
                    'msg' => trans('messages.something_went_wrong'),
                ];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    public function printLineOrder(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $waiter_id = request()->session()->get('user.id');
            $line_id = $request->input('line_id');
            if (! empty($request->input('service_staff_id'))) {
                $waiter_id = $request->input('service_staff_id');
            }

            $line_orders = $this->restUtil->getLineOrders($business_id, ['waiter_id' => $waiter_id, 'line_id' => $line_id]);
            $order = $line_orders[0];
            $html_content = view('restaurant.partials.print_line_order', compact('order'))->render();
            $output = [
                'success' => 1,
                'msg' => trans('lang_v1.success'),
                'html_content' => $html_content,
            ];
        } catch (Exception $e) {
            $output = [
                'success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
}
