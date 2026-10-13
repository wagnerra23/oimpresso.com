<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningStockController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $transactionUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function add($product_id)
    {
        if (! auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Get the product
        $product = Product::where('business_id', $business_id)
                            ->where('id', $product_id)
                            ->with(['variations',
                                'variations.product_variation',
                                'unit',
                                'product_locations',
                                'second_unit',
                            ])
                            ->first();
        if (! empty($product) && $product->enable_stock == 1) {
            //Get Opening Stock Transactions for the product if exists
            $transactions = Transaction::where('business_id', $business_id)
                                ->where('opening_stock_product_id', $product_id)
                                ->where('type', 'opening_stock')
                                ->with(['purchase_lines'])
                                ->get();

            $purchases = [];
            $purchase_lines = [];
            foreach ($transactions as $transaction) {
                foreach ($transaction->purchase_lines as $purchase_line) {
                    if (! empty($purchase_lines[$purchase_line->variation_id])) {
                        $k = count($purchase_lines[$purchase_line->variation_id]);
                    } else {
                        $k = 0;
                        $purchase_lines[$purchase_line->variation_id] = [];
                    }

                    //Show only remaining quantity for editing opening stock.
                    $purchase_lines[$purchase_line->variation_id][$k]['quantity'] = $purchase_line->quantity_remaining;
                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_price'] = $purchase_line->purchase_price;
                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_line_id'] = $purchase_line->id;
                    $purchase_lines[$purchase_line->variation_id][$k]['exp_date'] = $purchase_line->exp_date;
                    $purchase_lines[$purchase_line->variation_id][$k]['lot_number'] = $purchase_line->lot_number;
                    $purchase_lines[$purchase_line->variation_id][$k]['transaction_date'] = $this->productUtil->format_date($transaction->transaction_date, true);

                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_line_note'] = $transaction->additional_notes;
                    $purchase_lines[$purchase_line->variation_id][$k]['location_id'] = $transaction->location_id;
                    $purchase_lines[$purchase_line->variation_id][$k]['secondary_unit_quantity'] = $purchase_line->secondary_unit_quantity;
                }
            }

            foreach ($purchase_lines as $v_id => $pls) {
                foreach ($pls as $pl) {
                    $purchases[$pl['location_id']][$v_id][] = $pl;
                }
            }

            $locations = BusinessLocation::forDropdown($business_id);

            //Unset locations where product is not available
            $available_locations = $product->product_locations->pluck('id')->toArray();
            foreach ($locations as $key => $value) {
                if (! in_array($key, $available_locations)) {
                    unset($locations[$key]);
                }
            }

            $enable_expiry = request()->session()->get('business.enable_product_expiry');
            $enable_lot = request()->session()->get('business.enable_lot_number');

            if (request()->ajax()) {
                return view('opening_stock.ajax_add')
                    ->with(compact(
                        'product',
                        'locations',
                        'purchases',
                        'enable_expiry',
                        'enable_lot'
                    ));
            }

            return view('opening_stock.add')
                    ->with(compact(
                        'product',
                        'locations',
                        'purchases',
                        'enable_expiry',
                        'enable_lot'
                    ));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request)
    {
        if (! auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $opening_stocks = $request->input('stocks');
            $product_id = $request->input('product_id');

            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            $product = Product::where('business_id', $business_id)
                                ->where('id', $product_id)
                                ->with(['variations', 'product_tax'])
                                ->first();

            $locations = BusinessLocation::forDropdown($business_id)->toArray();

            if (! empty($product) && $product->enable_stock == 1) {
                //Get product tax
                $tax_percent = ! empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
                $tax_id = ! empty($product->product_tax->id) ? $product->product_tax->id : null;

                //Get start date for financial year.
                $transaction_date = request()->session()->get('financial_year.start');
                $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

                DB::beginTransaction();

                //$key_os is the location_id
                foreach ($opening_stocks as $location_id => $value) {
                    $new_purchase_lines = [];
                    $edit_purchase_lines = [];
                    $new_transaction_data = [];
                    $edit_transaction_data = [];
                    //Check if valid location
                    if (array_key_exists($location_id, $locations)) {
                        foreach ($value as $vid => $purchase_lines_data) {
                            //create purchase_lines array
                            foreach ($purchase_lines_data as $k => $pl) {
                                $purchase_price = $this->productUtil->num_uf(trim($pl['purchase_price']));
                                $item_tax = $this->productUtil->calc_percentage($purchase_price, $tax_percent);
                                $purchase_price_inc_tax = $purchase_price + $item_tax;
                                $qty_remaining = $this->productUtil->num_uf(trim($pl['quantity']));
                                $secondary_unit_quantity = isset($pl['secondary_unit_quantity']) ? $this->productUtil->num_uf(trim($pl['secondary_unit_quantity'])) : 0;

                                $exp_date = null;
                                if (! empty($pl['exp_date'])) {
                                    $exp_date = $this->productUtil->uf_date($pl['exp_date']);
                                }

                                $lot_number = null;
                                if (! empty($pl['lot_number'])) {
                                    $lot_number = $pl['lot_number'];
                                }

                                $purchase_line_note = ! empty($pl['purchase_line_note']) ? $pl['purchase_line_note'] : null;
                                $transaction_date = ! empty($pl['transaction_date']) ? $this->productUtil->uf_date($pl['transaction_date'], true) : $transaction_date;

                                $purchase_line = null;

                                if (isset($pl['purchase_line_id'])) {
                                    $purchase_line = PurchaseLine::findOrFail($pl['purchase_line_id']);
                                    //Quantity = remaining + used
                                    $qty_remaining = $qty_remaining + $purchase_line->quantity_used;

                                    if ($qty_remaining != 0) {
                                        //Calculate transaction total
                                        $old_qty = $purchase_line->quantity;

                                        $this->productUtil->updateProductQuantity($location_id, $product->id, $vid, $qty_remaining, $old_qty, null, false);
                                    }
                                } else {
                                    if ($qty_remaining != 0) {

                                        //create newly added purchase lines
                                        $purchase_line = new PurchaseLine();
                                        $purchase_line->product_id = $product->id;
                                        $purchase_line->variation_id = $vid;

                                        $this->productUtil->updateProductQuantity($location_id, $product->id, $vid, $qty_remaining, 0, null, false);
                                    }
                                }
                                if (! is_null($purchase_line)) {
                                    $purchase_line->item_tax = $item_tax;
                                    $purchase_line->tax_id = $tax_id;
                                    $purchase_line->quantity = $qty_remaining;
                                    $purchase_line->pp_without_discount = $purchase_price;
                                    $purchase_line->purchase_price = $purchase_price;
                                    $purchase_line->purchase_price_inc_tax = $purchase_price_inc_tax;
                                    $purchase_line->exp_date = $exp_date;
                                    $purchase_line->lot_number = $lot_number;
                                    $purchase_line->secondary_unit_quantity = $secondary_unit_quantity;
                                }

                                if (! empty($purchase_line->transaction_id)) {
                                    $edit_purchase_lines[$purchase_line->transaction_id][] = $purchase_line;

                                    $purchase_line->save();

                                    $edit_transaction_data[$purchase_line->transaction_id] = [
                                        'transaction_date' => $transaction_date,
                                        'additional_notes' => $purchase_line_note,
                                    ];
                                } else {
                                    $new_purchase_lines[] = $purchase_line;
                                    $new_transaction_data[] = [
                                        'transaction_date' => $transaction_date,
                                        'additional_notes' => $purchase_line_note,
                                    ];
                                }
                            }
                        }

                        //edit existing transactions & purchase lines
                        $updated_transaction_ids = [];
                        if (! empty($edit_purchase_lines)) {
                            foreach ($edit_purchase_lines as $t_id => $purchase_lines) {
                                $purchase_total = 0;
                                $updated_purchase_line_ids = [];
                                foreach ($purchase_lines as $purchase_line) {
                                    $purchase_total = $purchase_line->purchase_price_inc_tax * $purchase_line->quantity;
                                    $updated_purchase_line_ids[] = $purchase_line->id;
                                }

                                $transaction = Transaction::where('type', 'opening_stock')
                                    ->where('business_id', $business_id)
                                    ->where('location_id', $location_id)
                                    ->find($t_id);

                                $transaction->total_before_tax = $purchase_total;
                                $transaction->final_total = $purchase_total;

                                $transaction->transaction_date = $edit_transaction_data[$transaction->id]['transaction_date'];
                                $transaction->additional_notes = $edit_transaction_data[$transaction->id]['additional_notes'];
                                $transaction->update();

                                $updated_transaction_ids[] = $transaction->id;
                                //unset deleted purchase lines
                                $delete_purchase_line_ids = [];
                                $delete_purchase_lines = null;
                                $delete_purchase_lines = PurchaseLine::where('transaction_id', $transaction->id)
                                            ->whereNotIn('id', $updated_purchase_line_ids)
                                            ->get();

                                if ($delete_purchase_lines->count()) {
                                    foreach ($delete_purchase_lines as $delete_purchase_line) {
                                        $delete_purchase_line_ids[] = $delete_purchase_line->id;

                                        //decrease deleted only if previous status was received
                                        $this->productUtil->decreaseProductQuantity(
                                            $delete_purchase_line->product_id,
                                            $delete_purchase_line->variation_id,
                                            $transaction->location_id,
                                            $delete_purchase_line->quantity
                                        );
                                    }
                                    //Delete deleted purchase lines
                                    PurchaseLine::where('transaction_id', $transaction->id)
                                                ->whereIn('id', $delete_purchase_line_ids)
                                                ->delete();
                                }

                                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $transaction, $delete_purchase_lines);

                                //Adjust stock over selling if found
                                $this->productUtil->adjustStockOverSelling($transaction);
                            }
                        }

                        //Delete transaction if all purchase line quantity is 0 (Only if transaction exists)
                        $delete_transactions = Transaction::where('type', 'opening_stock')
                            ->where('business_id', $business_id)
                            ->where('opening_stock_product_id', $product->id)
                            ->where('location_id', $location_id)
                            ->with(['purchase_lines'])
                            ->whereNotIn('id', $updated_transaction_ids)
                            ->get();

                        if (count($delete_transactions) > 0) {
                            foreach ($delete_transactions as $delete_transaction) {
                                $delete_purchase_lines = $delete_transaction->purchase_lines;

                                foreach ($delete_purchase_lines as $delete_purchase_line) {
                                    $this->productUtil->decreaseProductQuantity($product->id, $delete_purchase_line->variation_id, $location_id, $delete_purchase_line->quantity);
                                    $delete_purchase_line->delete();
                                }

                                //Update mapping of purchase & Sell.
                                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $delete_transaction, $delete_purchase_lines);

                                $delete_transaction->delete();
                            }
                        }

                        //create transaction & purchase lines
                        if (! empty($new_purchase_lines)) {
                            foreach ($new_purchase_lines as $key => $new_purchase_line) {
                                if (empty($new_purchase_line)) {
                                    continue;
                                }
                                $transaction = Transaction::create(
                                    [
                                        'type' => 'opening_stock',
                                        'opening_stock_product_id' => $product->id,
                                        'status' => 'received',
                                        'business_id' => $business_id,
                                        'transaction_date' => $new_transaction_data[$key]['transaction_date'],
                                        'additional_notes' => $new_transaction_data[$key]['additional_notes'],
                                        'total_before_tax' => $new_purchase_line->purchase_price_inc_tax,
                                        'location_id' => $location_id,
                                        'final_total' => $new_purchase_line->purchase_price_inc_tax * $new_purchase_line->quantity,
                                        'payment_status' => 'paid',
                                        'created_by' => $user_id,
                                    ]
                                );

                                $transaction->purchase_lines()->saveMany([$new_purchase_line]);

                                //Adjust stock over selling if found
                                $this->productUtil->adjustStockOverSelling($transaction);
                            }
                        }
                    }
                }

                DB::commit();
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.opening_stock_added_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return back()->with('status', $output);
        }

        if (request()->ajax()) {
            return $output;
        }

        return redirect('products')->with('status', $output);
    }

    // ── Estoque inicial em janela, na lista de produtos (React) ─────────────────────────────────
    // Protótipo `produto-blade.jsx`, `ModalEstoqueInicial`, com TUDO o que a tela Blade
    // (`opening_stock/form-part.blade.php`) tem: linha por variação, várias linhas por variação,
    // validade, lote, data, observação e quantidade na segunda unidade.
    //
    // Nada aqui grava estoque: `salvarDaJanela` monta o MESMO pedido que o formulário Blade manda
    // e entrega ao `save()` acima, que continua sendo o único que grava.

    /** Produto desta empresa com controle de estoque, com as relações que a janela usa — ou null. */
    private function produtoDoEstoqueInicial(int $business_id, int $product_id): ?Product
    {
        $product = Product::where('business_id', $business_id)
            ->where('id', $product_id)
            ->with(['variations', 'variations.product_variation', 'unit', 'product_locations', 'second_unit'])
            ->first();

        return $product !== null && (int) $product->getAttribute('enable_stock') === 1 ? $product : null;
    }

    /**
     * Locais em que o estoque inicial do produto pode ser lançado: os permitidos a quem está logado
     * E em que o produto está disponível — a mesma lista da tela Blade (`add()`).
     *
     * @return array<int, string>
     */
    private function locaisDoEstoqueInicial(int $business_id, Product $product): array
    {
        $disponiveis = collect($product->getRelationValue('product_locations'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $locais = [];
        foreach (collect(BusinessLocation::forDropdown($business_id))->all() as $id => $nome) {
            if (in_array((int) $id, $disponiveis, true)) {
                $locais[(int) $id] = (string) $nome;
            }
        }

        return $locais;
    }

    /**
     * As linhas de estoque inicial já lançadas para o produto, como a `add()` as lê: a quantidade é a
     * que RESTA (lançada − usada). Datas saem em ISO e sem deslocamento — o que está gravado.
     *
     * @return array<int, array<string, mixed>>  por id da linha de compra
     */
    private function linhasDoEstoqueInicial(int $business_id, int $product_id): array
    {
        $transacoes = Transaction::where('business_id', $business_id)
            ->where('opening_stock_product_id', $product_id)
            ->where('type', 'opening_stock')
            ->with(['purchase_lines'])
            ->get();

        $linhas = [];
        foreach ($transacoes as $transacao) {
            foreach ($transacao->getRelationValue('purchase_lines') as $pl) {
                $linhas[(int) $pl->id] = [
                    'purchase_line_id' => (int) $pl->id,
                    'location_id' => (int) $transacao->getAttribute('location_id'),
                    'variation_id' => (int) $pl->variation_id,
                    'quantidade' => (float) $pl->quantity_remaining,
                    'custo' => (float) $pl->purchase_price,
                    'validade' => ! empty($pl->exp_date) ? \Carbon::parse($pl->exp_date)->format('Y-m-d') : null,
                    'lote' => $pl->lot_number !== null ? (string) $pl->lot_number : null,
                    'data' => ! empty($transacao->getAttribute('transaction_date')) ? \Carbon::parse($transacao->getAttribute('transaction_date'))->format('Y-m-d\TH:i') : null,
                    'observacao' => $transacao->getAttribute('additional_notes') !== null ? (string) $transacao->getAttribute('additional_notes') : null,
                    'segunda_unidade_qtd' => (float) $pl->secondary_unit_quantity,
                ];
            }
        }

        return $linhas;
    }

    /**
     * Dados da janela de estoque inicial de um produto (JSON) — os mesmos que a `add()` entrega à
     * tela Blade: por local, por variação, as linhas já lançadas.
     */
    public function dadosDaJanela($product_id)
    {
        if (! auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = (int) request()->session()->get('user.business_id');
        $product = $this->produtoDoEstoqueInicial($business_id, (int) $product_id);
        if ($product === null) {
            return response()->json(['msg' => 'Produto não encontrado ou sem controle de estoque.'], 404);
        }

        $existentes = $this->linhasDoEstoqueInicial($business_id, (int) $product->id);
        $variavel = (string) $product->getAttribute('type') === 'variable';
        $locais = [];
        foreach ($this->locaisDoEstoqueInicial($business_id, $product) as $location_id => $nome) {
            $variacoes = [];
            foreach ($product->getRelationValue('variations') as $variacao) {
                $doPar = [];
                foreach ($existentes as $linha) {
                    if ($linha['location_id'] === $location_id && $linha['variation_id'] === (int) $variacao->id) {
                        unset($linha['location_id'], $linha['variation_id']);
                        $doPar[] = $linha;
                    }
                }
                $variacoes[] = [
                    'id' => (int) $variacao->id,
                    'nome' => $variavel ? trim(($variacao->product_variation->name ?? '') . ' : ' . $variacao->name, ' :') : '',
                    'custo_padrao' => (float) $variacao->default_purchase_price,
                    'linhas' => $doPar,
                ];
            }
            $locais[] = ['id' => $location_id, 'nome' => $nome, 'variacoes' => $variacoes];
        }

        $unidade = $product->getRelationValue('unit');
        $segunda = $product->getRelationValue('second_unit');

        return response()->json([
            'produto' => [
                'id' => (int) $product->id,
                'nome' => (string) $product->name,
                'unidade' => (string) ($unidade->short_name ?? ''),
                'segunda_unidade' => $segunda !== null ? (string) $segunda->short_name : null,
            ],
            'usa_validade' => (int) request()->session()->get('business.enable_product_expiry') === 1,
            'usa_lote' => (int) request()->session()->get('business.enable_lot_number') === 1,
            'casas' => [
                'quantidade' => (int) request()->session()->get('business.quantity_precision', 2),
                'preco' => (int) request()->session()->get('business.currency_precision', 2),
            ],
            'locais' => $locais,
        ]);
    }

    /**
     * Salva a janela de estoque inicial: confere o pedido, monta o do formulário Blade e entrega ao
     * `save()`. O que este método acrescenta ao caminho Blade é só conferência ANTES de gravar:
     *   · números só como texto com vírgula decimal e sem milhar ("12.125" não é adivinhado);
     *   · local tem de ser permitido e do produto; variação tem de ser do produto; linha existente
     *     tem de ser estoque inicial DESTE produto, DESTA empresa, naquele local e variação;
     *   · o `save()` APAGA, em cada local enviado, toda linha de estoque inicial que não vier no
     *     pedido (e desconta o saldo). Por isso o pedido tem de trazer TODAS as linhas existentes
     *     dos locais que cita — se outra pessoa lançou uma linha depois que a janela abriu, recusa.
     * Com validade ou lote desligados na empresa, a linha existente mantém o que já tinha (o
     * formulário Blade, sem o campo, apagava).
     */
    public function salvarDaJanela(Request $request)
    {
        if (! auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = (int) $request->session()->get('user.business_id');

        $numero = ['string', 'regex:/^\d{1,13}(,\d{1,4})?$/'];
        $base = 'locais.*.variacoes.*.linhas.*.';
        $dados = $request->validate([
            'product_id' => ['required', 'integer'],
            'locais' => ['required', 'array', 'min:1'],
            'locais.*.location_id' => ['required', 'integer', 'distinct'],
            'locais.*.variacoes' => ['required', 'array', 'min:1'],
            'locais.*.variacoes.*.variation_id' => ['required', 'integer'],
            'locais.*.variacoes.*.linhas' => ['required', 'array', 'min:1'],
            $base . 'purchase_line_id' => ['nullable', 'integer'],
            $base . 'quantidade' => array_merge(['required'], $numero),
            $base . 'custo' => array_merge(['required'], $numero),
            $base . 'validade' => ['nullable', 'date_format:Y-m-d'],
            $base . 'lote' => ['nullable', 'string', 'max:191'],
            $base . 'data' => ['nullable', 'date_format:Y-m-d\TH:i'],
            $base . 'observacao' => ['nullable', 'string', 'max:5000'],
            $base . 'segunda_unidade_qtd' => array_merge(['nullable'], $numero),
        ], [
            'regex' => 'Número inválido: use vírgula para os centavos e não use ponto.',
        ]);

        $product = $this->produtoDoEstoqueInicial($business_id, (int) $dados['product_id']);
        if ($product === null) {
            return response()->json(['msg' => 'Produto não encontrado ou sem controle de estoque.'], 404);
        }
        $recusar = fn (string $motivo) => throw \Illuminate\Validation\ValidationException::withMessages(['locais' => $motivo]);

        $locais = $this->locaisDoEstoqueInicial($business_id, $product);
        $variacoesDoProduto = collect($product->getRelationValue('variations'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $existentes = $this->linhasDoEstoqueInicial($business_id, (int) $product->id);
        $temSegundaUnidade = $product->getRelationValue('second_unit') !== null;
        $usaValidade = (int) $request->session()->get('business.enable_product_expiry') === 1;
        $usaLote = (int) $request->session()->get('business.enable_lot_number') === 1;
        $formatoData = (string) $request->session()->get('business.date_format');
        $formatoDataHora = $formatoData . ((int) $request->session()->get('business.time_format') === 12 ? ' h:i A' : ' H:i');

        $stocks = [];
        $idsEnviados = [];
        foreach ($dados['locais'] as $local) {
            $location_id = (int) $local['location_id'];
            if (! array_key_exists($location_id, $locais)) {
                $recusar('Local não permitido para este produto.');
            }
            foreach ($local['variacoes'] as $variacao) {
                $variation_id = (int) $variacao['variation_id'];
                if (! in_array($variation_id, $variacoesDoProduto, true) || isset($stocks[$location_id][$variation_id])) {
                    $recusar('Variação inválida para este produto.');
                }
                foreach ($variacao['linhas'] as $linha) {
                    $id = isset($linha['purchase_line_id']) ? (int) $linha['purchase_line_id'] : null;
                    $gravada = $id !== null ? ($existentes[$id] ?? null) : null;
                    if ($id !== null && ($gravada === null || $gravada['location_id'] !== $location_id || $gravada['variation_id'] !== $variation_id || in_array($id, $idsEnviados, true))) {
                        $recusar('Linha de estoque inicial inválida para este produto.');
                    }

                    // O bloco de uma linha do formulário Blade (`form-part.blade.php`).
                    $bloco = [
                        'quantity' => $linha['quantidade'],
                        'purchase_price' => $linha['custo'],
                        'purchase_line_note' => $linha['observacao'] ?? null,
                        'transaction_date' => ! empty($linha['data']) && $formatoData !== ''
                            ? \Carbon::createFromFormat('Y-m-d\TH:i', $linha['data'])->format($formatoDataHora)
                            : '',
                    ];
                    if ($id !== null) {
                        $bloco['purchase_line_id'] = $id;
                        $idsEnviados[] = $id;
                    }
                    $validade = $usaValidade ? ($linha['validade'] ?? null) : ($gravada['validade'] ?? null);
                    if (! empty($validade) && $formatoData !== '') {
                        $bloco['exp_date'] = \Carbon::createFromFormat('Y-m-d', $validade)->format($formatoData);
                    }
                    $lote = $usaLote ? ($linha['lote'] ?? null) : ($gravada['lote'] ?? null);
                    if ($lote !== null && $lote !== '') {
                        $bloco['lot_number'] = $lote;
                    }
                    if ($temSegundaUnidade) {
                        $bloco['secondary_unit_quantity'] = $linha['segunda_unidade_qtd'] ?? '0';
                    }
                    $stocks[$location_id][$variation_id][] = $bloco;
                }
            }
        }

        foreach ($existentes as $id => $gravada) {
            if (isset($stocks[$gravada['location_id']]) && ! in_array($id, $idsEnviados, true)) {
                $recusar('Este estoque inicial foi alterado depois que a janela abriu. Feche e abra de novo.');
            }
        }

        // Só os campos montados aqui seguem ao `save()`, e como pedido "ajax" (ele devolve o resultado).
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->query->replace([]);
        $request->replace(['product_id' => (int) $product->id, 'stocks' => $stocks]);

        $saida = $this->save($request);
        if (is_array($saida) && ! empty($saida['success'])) {
            return response()->json(['success' => true, 'msg' => 'Estoque inicial lançado.']);
        }
        // Na falha o `save()` devolve um redirecionamento com a mensagem na sessão.
        $msg = is_array($saida) ? (string) ($saida['msg'] ?? '') : (string) data_get($request->session()->get('status'), 'msg', '');
        $request->session()->forget('status');

        return response()->json(['success' => false, 'msg' => $msg !== '' ? $msg : 'Não foi possível lançar o estoque inicial.'], 500);
    }
}
