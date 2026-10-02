<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Jobs\ImportarVendasJob;
use App\Services\Sells\ImportSalesService;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;

/**
 * Importação de vendas por planilha (`/import-sales`).
 *
 * O cálculo mora em `App\Services\Sells\ImportSalesService` desde a thread 05 do playbook
 * de Vendas: o mesmo serviço roda aqui (planilha até o limite) e no
 * `App\Jobs\ImportarVendasJob` (acima do limite — decisão D2 de [W], 2026-10-02).
 */
class ImportSalesController extends Controller
{
    public function __construct(
        protected BusinessUtil $businessUtil,
        protected TransactionUtil $transactionUtil,
        protected ImportSalesService $importSalesService,
    ) {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $imported_sales = Transaction::where('business_id', $business_id)
                            ->where('type', 'sell')
                            ->whereNotNull('import_batch')
                            ->with(['sales_person'])
                            ->select('id', 'import_batch', 'import_time', 'invoice_no', 'created_by')
                            ->orderBy('import_batch', 'desc')
                            ->get();

        $imported_sales_array = [];
        foreach ($imported_sales as $sale) {
            $imported_sales_array[$sale->import_batch]['import_time'] = $sale->import_time;
            $imported_sales_array[$sale->import_batch]['created_by'] = $sale->sales_person->user_full_name;
            $imported_sales_array[$sale->import_batch]['invoices'][] = $sale->invoice_no;
        }

        $import_fields = $this->importSalesService->campos((int) $business_id);

        return view('import_sales.index')->with(compact('imported_sales_array', 'import_fields'));
    }

    /**
     * Preview imported data and map columns with sale fields
     *
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->businessUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $business_id = request()->session()->get('user.business_id');

        if ($request->hasFile('sales')) {
            // basename: o nome volta do formulário no POST /import-sales e compõe um caminho.
            $file_name = time().'_'.basename($request->sales->getClientOriginalName());
            $request->sales->storeAs('temp', $file_name);

            $parsed_array = $this->importSalesService->lerPlanilha($this->caminhoTemporario($file_name));

            $import_fields = $this->importSalesService->campos((int) $business_id);
            foreach ($import_fields as $key => $value) {
                $import_fields[$key] = $value['label'];
            }

            //Evaluate highest matching field with the header to pre select from dropdown
            $match_array = $this->importSalesService->preMapear($parsed_array[0], $import_fields);

            $business_locations = BusinessLocation::forDropdown($business_id);

            return view('import_sales.preview')->with(compact('parsed_array', 'import_fields', 'file_name', 'business_locations', 'match_array'));
        }
    }

    /**
     * Import sales to database
     *
     * Até `limiteSincrono()` linhas importa na hora, como o legado. Acima, despacha
     * `ImportarVendasJob` (D2) e devolve o usuário à tela, que mostra o progresso.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function import(Request $request)
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $file_name = basename((string) $request->input('file_name'));
        $file_path = $this->caminhoTemporario($file_name);
        $business_id = (int) $request->session()->get('user.business_id');
        $location_id = (int) $request->input('location_id');
        $import_fields = (array) $request->input('import_fields');
        $group_by = $request->input('group_by');

        // Tier 0 (ADR 0093): a baixa de estoque vai para este local — ele tem de ser do negócio.
        $localDoNegocio = BusinessLocation::where('business_id', $business_id)
            ->where('id', $location_id)
            ->exists();

        $transacaoAberta = false;
        try {
            if (! $localDoNegocio) {
                throw new \Exception(__('messages.something_went_wrong'));
            }

            $parsed_array = $this->importSalesService->lerPlanilha($file_path);

            if ($this->importSalesService->contarLinhas($parsed_array) > $this->importSalesService->limiteSincrono()) {
                // Valida o mapeamento antes de enfileirar: erro de planilha (R6) aparece
                // já, não minutos depois na fila.
                $dados = $parsed_array;
                unset($dados[0]);
                $this->importSalesService->formatar($dados, $import_fields, $group_by);

                ImportarVendasJob::publicarEstado($business_id, [
                    'estado' => 'na_fila',
                    'arquivo' => $file_name,
                    'feitas' => 0,
                    'total' => null,
                ]);

                ImportarVendasJob::dispatch(
                    $business_id,
                    (int) auth()->user()->id,
                    $location_id,
                    $file_path,
                    $import_fields,
                    $group_by,
                    $file_name,
                );

                return redirect('import-sales')->with('status', [
                    'success' => 1,
                    'msg' => 'Planilha grande: a importação foi para a fila e roda no servidor. Acompanhe o andamento nesta tela.',
                ]);
            }

            DB::beginTransaction();
            $transacaoAberta = true;

            //Remove header row
            unset($parsed_array[0]);
            $formatted_sales_data = $this->importSalesService->formatar($parsed_array, $import_fields, $group_by);

            $this->importSalesService->importar($formatted_sales_data, $business_id, (int) auth()->user()->id, $location_id);

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.sales_imported_successfully'),
            ];
        } catch (\Exception $e) {
            if ($transacaoAberta) {
                DB::rollBack();
            }
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            @unlink($file_path);

            return redirect('import-sales')->with('notification', $output);
        }

        @unlink($file_path);

        return redirect('import-sales')->with('status', $output);
    }

    /** Onde o `storeAs('temp', …)` da prévia grava (disco `local` = public/uploads). */
    private function caminhoTemporario(string $file_name): string
    {
        return public_path('uploads/temp/'.$file_name);
    }

    /**
     * Apaga todas as vendas de um lote importado — tudo ou nada, com retrato no log.
     *
     * D3 de [W], 2ª rodada (2026-10-02, "opção 2 no D3"): o reverter continua APAGANDO,
     * como no legado, mas (1) grava antes um retrato completo do lote no log e (2) recusa o
     * lote INTEIRO quando qualquer venda não pode ser apagada, dizendo qual e por quê. O
     * legado pulava essa venda e respondia "sucesso". Revoga a resposta "cancelar sem
     * apagar" da 1ª rodada — ver prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/_DECISOES-W-2026-10-02b.md.
     * A regra mora em `ImportSalesService::reverterLote()`.
     *
     * ⚠️ Achado conhecido, fora deste PR: a rota é GET e apaga (`routes/web.php`).
     *
     * ⚠️ D3 de [W] (2026-10-02) pede CANCELAR em vez de apagar. Não implementado nesta
     * thread: o projeto não tem hoje um "cancelar venda" que tire a venda dos totais sem
     * efeito externo — ver prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/_saida-05.md.
     * Até a decisão, o comportamento é o do legado.
     *
     * @return \Illuminate\Http\Response
     */
    public function revertSaleImport($batch)
    {
        if (! auth()->user()->can('sell.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = (int) request()->session()->get('user.business_id');

            $resultado = $this->importSalesService->reverterLote($business_id, (int) $batch);

            if ($resultado['ok']) {
                $output = ['success' => 1, 'msg' => __('lang_v1.import_reverted_successfully')];
            } else {
                $motivos = array_map(
                    fn (array $i) => 'venda '.$i['invoice_no'].' (#'.$i['id'].'): '.$i['motivo'],
                    $resultado['impedidas']
                );
                $output = [
                    'success' => 0,
                    'msg' => 'Lote '.$resultado['lote'].' não foi revertido e nada foi apagado. '
                        .'Vendas que impediram: '.implode('; ', $motivos).'.',
                    'impedidas' => $resultado['impedidas'],
                ];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return redirect('import-sales')->with('status', $output);
    }
}
