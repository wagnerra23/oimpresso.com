<?php

namespace App\Http\Controllers;

use App\Barcode;
use App\Product;
use App\SellingPriceGroup;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabelsController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $transactionUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  TransactionUtil  $TransactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Display labels
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (! auth()->user()->can('print_labels.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $purchase_id = $request->get('purchase_id', false);
        $product_id = $request->get('product_id', false);

        //Get products for the business
        $products = [];
        $price_groups = [];
        if ($purchase_id) {
            $products = $this->transactionUtil->getPurchaseProducts($business_id, $purchase_id);
        } elseif ($product_id) {
            $products = $this->productUtil->getDetailsFromProduct($business_id, $product_id);
        }

        //get price groups
        $price_groups = [];
        if (! empty($purchase_id) || ! empty($product_id)) {
            $price_groups = SellingPriceGroup::where('business_id', $business_id)
                                    ->active()
                                    ->pluck('name', 'id');
        }

        $barcode_settings = Barcode::where('business_id', $business_id)
                                ->orWhereNull('business_id')
                                ->select(DB::raw('CONCAT(name, ", ", COALESCE(description, "")) as name, id, is_default'))
                                ->get();
        $default = $barcode_settings->where('is_default', 1)->first();
        $barcode_settings = $barcode_settings->pluck('name', 'id');

        return view('labels.show')
            ->with(compact('products', 'barcode_settings', 'default', 'price_groups'));
    }

    /**
     * Returns the html for product row (JSON para a tela Inertia — playbook Produto thread 04)
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View|null
     */
    public function addProductRow(Request $request)
    {
        if (! auth()->user()->can('print_labels.access')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $product_id = $request->input('product_id');
            $variation_id = $request->input('variation_id');
            $business_id = $request->session()->get('user.business_id');

            if (! empty($product_id)) {
                $index = $request->input('row_count');
                $products = $this->productUtil->getDetailsFromProduct($business_id, $product_id, $variation_id);

                $price_groups = SellingPriceGroup::where('business_id', $business_id)
                                            ->active()
                                            ->pluck('name', 'id');

                // Playbook Produto · thread 04: a tela Inertia pede a linha em JSON, com o preço
                // que a impressão sairia por grupo e por tipo. A Blade segue recebendo o HTML.
                if ($request->wantsJson()) {
                    return response()->json(['linhas' => $products
                        ->map(fn ($p) => $this->linhaEtiqueta((int) $business_id, (int) $p->variation_id, $price_groups->keys()))
                        ->values()]);
                }

                return view('labels.partials.show_table_rows')
                        ->with(compact('products', 'index', 'price_groups'));
            }
        }

        // Sem ajax ou sem produto: resposta vazia, como sempre foi (agora explícito).
        return null;
    }

    /**
     * Returns the html for labels preview
     *
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        if (! auth()->user()->can('print_labels.access')) {
            abort(403, 'Unauthorized action.');
        }

        // Tier 0 (ADR 0093): a configuracao de etiqueta e do negocio ou do sistema
        // (business_id NULL — o mesmo recorte que show() oferece no select). `find()` cru
        // aceitava o id de uma configuracao de OUTRO negocio. Fora do try: o catch abaixo
        // engoliria o 404.
        $business_id = $request->session()->get('user.business_id');
        $barcode_details = Barcode::where(function ($q) use ($business_id) {
            $q->where('business_id', $business_id)->orWhereNull('business_id');
        })->find($request->get('barcode_setting'));

        if (! $barcode_details) {
            abort(404);
        }

        try {
            $products = $request->get('products');
            $print = $request->get('print');
            $barcode_details->stickers_in_one_sheet = $barcode_details->is_continuous ? $barcode_details->stickers_in_one_row : $barcode_details->stickers_in_one_sheet;
            $barcode_details->paper_height = $barcode_details->is_continuous ? $barcode_details->height : $barcode_details->paper_height;
            if ($barcode_details->stickers_in_one_row == 1) {
                $barcode_details->col_distance = 0;
                $barcode_details->row_distance = 0;
            }
            // if($barcode_details->is_continuous){
            //     $barcode_details->row_distance = 0;
            // }

            $business_name = $request->session()->get('business.name');

            $product_details_page_wise = [];
            $total_qty = 0;
            foreach ($products as $value) {
                $details = $this->productUtil->getDetailsFromVariation($value['variation_id'], $business_id, null, false);

                if (! empty($value['exp_date'])) {
                    $details->exp_date = $value['exp_date'];
                }
                if (! empty($value['packing_date'])) {
                    $details->packing_date = $value['packing_date'];
                }
                if (! empty($value['lot_number'])) {
                    $details->lot_number = $value['lot_number'];
                }

                if (! empty($value['price_group_id'])) {
                    $tax_id = $print['price_type'] == 'inclusive' ?: $details->tax_id;

                    $group_prices = $this->productUtil->getVariationGroupPrice($value['variation_id'], $value['price_group_id'], $tax_id);

                    $details->sell_price_inc_tax = $group_prices['price_inc_tax'];
                    $details->default_sell_price = $group_prices['price_exc_tax'];
                }

                for ($i = 0; $i < $value['quantity']; $i++) {
                    $page = intdiv($total_qty, $barcode_details->stickers_in_one_sheet);

                    if ($total_qty % $barcode_details->stickers_in_one_sheet == 0) {
                        $product_details_page_wise[$page] = [];
                    }

                    $product_details_page_wise[$page][] = $details;
                    $total_qty++;
                }
            }

            $margin_top = $barcode_details->is_continuous ? 0 : $barcode_details->top_margin * 1;
            $margin_left = $barcode_details->is_continuous ? 0 : $barcode_details->left_margin * 1;
            $paper_width = $barcode_details->paper_width * 1;
            $paper_height = $barcode_details->paper_height * 1;

            // print_r($paper_height);
            // echo "==";
            // print_r($margin_left);exit;

            // $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8',
            //             'format' => [$paper_width, $paper_height],
            //             'margin_top' => $margin_top,
            //             'margin_bottom' => $margin_top,
            //             'margin_left' => $margin_left,
            //             'margin_right' => $margin_left,
            //             'autoScriptToLang' => true,
            //             // 'disablePrintCSS' => true,
            // 'autoLangToFont' => true,
            // 'autoVietnamese' => true,
            // 'autoArabic' => true
            //             ]
            //         );
            //print_r($mpdf);exit;

            $i = 0;
            $len = count($product_details_page_wise);
            $is_first = false;
            $is_last = false;

            //$original_aspect_ratio = 4;//(w/h)
            $factor = (($barcode_details->width / $barcode_details->height)) / ($barcode_details->is_continuous ? 2 : 4);
            $html = '';
            foreach ($product_details_page_wise as $page => $page_products) {
                if ($i == 0) {
                    $is_first = true;
                }

                if ($i == $len - 1) {
                    $is_last = true;
                }

                $output = view('labels.partials.preview_2')
                            ->with(compact('print', 'page_products', 'business_name', 'barcode_details', 'margin_top', 'margin_left', 'paper_width', 'paper_height', 'is_first', 'is_last', 'factor'))->render();
                print_r($output);
                //$mpdf->WriteHTML($output);

                // if($i < $len - 1){
                //     // '', '', '', '', '', '', $margin_left, $margin_left, $margin_top, $margin_top, '', '', '', '', '', '', 0, 0, 0, 0, '', [$barcode_details->paper_width*1, $barcode_details->paper_height*1]
                //     $mpdf->AddPage();
                // }

                $i++;
            }

            print_r('<script>window.print()</script>');
            exit;
            //return $output;

            //$mpdf->Output();

            // $page_height = null;
            // if ($barcode_details->is_continuous) {
            //     $rows = ceil($total_qty/$barcode_details->stickers_in_one_row) + 0.4;
            //     $barcode_details->paper_height = $barcode_details->top_margin + ($rows*$barcode_details->height) + ($rows*$barcode_details->row_distance);
            // }

            // $output = view('labels.partials.preview')
            //     ->with(compact('print', 'product_details', 'business_name', 'barcode_details', 'product_details_page_wise'))->render();

            // $output = ['html' => $html,
            //                 'success' => true,
            //                 'msg' => ''
            //             ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = __('lang_v1.barcode_label_error');
        }

        //return $output;
    }

    /**
     * Uma linha da folha de etiquetas, com o preço que preview() imprimiria.
     *
     * ⛔ Regra mestre de valor: isto NÃO calcula preço. São as MESMAS duas chamadas de preview():
     * getDetailsFromVariation() (preço sem grupo) e getVariationGroupPrice() (preço do grupo), com
     * o mesmo tax_id que preview() passa no tipo "Sem imposto". No tipo "Com imposto" preview() passa
     * `true` como tax_id, mas o price_inc_tax não depende dele. A impressão continua em preview().
     *
     * @param  iterable<int>  $grupoIds  grupos de preço ATIVOS do negócio
     */
    private function linhaEtiqueta(int $business_id, int $variation_id, iterable $grupoIds): array
    {
        $d = $this->productUtil->getDetailsFromVariation($variation_id, $business_id, null, false);

        $precos = ['0' => [
            'inclusive' => $this->precoImpresso($d->sell_price_inc_tax),
            'exclusive' => $this->precoImpresso($d->default_sell_price),
        ]];
        foreach ($grupoIds as $gid) {
            $g = $this->productUtil->getVariationGroupPrice($variation_id, $gid, $d->tax_id);
            $precos[(string) $gid] = [
                'inclusive' => $this->precoImpresso($g['price_inc_tax']),
                'exclusive' => $this->precoImpresso($g['price_exc_tax']),
            ];
        }

        return [
            'product_id' => (int) $d->product_id,
            'variation_id' => (int) $d->variation_id,
            'nome' => (string) $d->product_actual_name,
            'variacao' => $d->is_dummy == 1 ? null : $d->product_variation_name.': '.$d->variation_name,
            'sku' => (string) $d->sub_sku,
            'precos' => $precos,
        ];
    }

    /**
     * O texto do preço como preview_2.blade.php imprime: símbolo da moeda + @num_format.
     * Grupo sem preço cadastrado volta '' de getVariationGroupPrice(): aqui vira null ("sem preço").
     */
    private function precoImpresso($valor): ?string
    {
        if ($valor === '' || $valor === null) {
            return null;
        }
        $moeda = session('currency') ?? [];

        return trim(($moeda['symbol'] ?? '').' '.number_format(
            (float) $valor,
            (int) session('business.currency_precision', 2),
            $moeda['decimal_separator'] ?? ',',
            $moeda['thousand_separator'] ?? '.'
        ));
    }
}
