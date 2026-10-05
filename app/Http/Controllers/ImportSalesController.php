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
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

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
     * @return \Inertia\Response|\Illuminate\View\View
     */
    public function index()
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Branch dual MWART (thread 05 · golden Sells/Drafts): o Blade segue como fallback
        // até o cutover F5, que é humano — por empresa, pela flag `mwart.vendas_import_sales`
        // (nasce desligada; a mesma chave vale para a prévia abaixo).
        if (\App\Support\Mwart::telaReact('vendas_import_sales', (int) $business_id)) {
            return Inertia::render('ImportSales/Index', [
                'campos' => collect($this->importSalesService->campos((int) $business_id))
                    ->map(fn ($c, $key) => ['key' => $key, 'label' => $c['label'], 'instrucao' => $c['instruction'] ?? null])
                    ->values(),
                // Lista de lotes pode ser grande (uma linha por fatura importada) — deferred.
                'lotes' => Inertia::defer(fn () => $this->lotesImportados((int) $business_id)),
                // Estado da última importação em fila (D2). A tela recarrega só esta prop
                // enquanto o job anda — sem rota nova.
                'estado' => Cache::get(ImportarVendasJob::chaveDeEstado((int) $business_id)),
                'limiteSincrono' => $this->importSalesService->limiteSincrono(),
                'permissions' => [
                    'importar' => true,
                    'reverter' => auth()->user()->can('sell.delete'),
                ],
                'urls' => [
                    'preview' => '/import-sales/preview',
                    'modelo' => asset('files/import_sales_template.xlsx'),
                    'reverter' => '/revert-sale-import/{lote}',
                    'vendas' => '/sells',
                ],
            ]);
        }

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
     * @return mixed Inertia (X-Inertia), Blade, redirect, ou nada sem arquivo pelo Blade (legado)
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

        // Mesma chave da tela inicial: com a flag ligada a prévia também é React.
        $telaReact = \App\Support\Mwart::telaReact('vendas_import_sales', (int) $business_id);

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

            if ($telaReact) {
                return Inertia::render('ImportSales/Preview', $this->propsDaPrevia($parsed_array, $import_fields, $match_array, $file_name, $business_locations));
            }

            return view('import_sales.preview')->with(compact('parsed_array', 'import_fields', 'file_name', 'business_locations', 'match_array'));
        }

        if ($telaReact) {
            return redirect('import-sales')->with('status', ['success' => 0, 'msg' => 'Escolha a planilha antes de enviar.']);
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

            // A tela React lê o flash de `status` (HandleInertiaRequests); o Blade, o de
            // `notification`. Mesma mensagem — a que cita a linha da planilha (R6).
            return redirect('import-sales')->with($request->header('X-Inertia') ? 'status' : 'notification', $output);
        }

        @unlink($file_path);

        return redirect('import-sales')->with('status', $output);
    }

    /**
     * Lotes importados do negócio (mais novo primeiro) — a mesma consulta do Blade.
     *
     * @return array<int, array<string, mixed>> lote · quando · criadoPor · faturas
     */
    private function lotesImportados(int $business_id): array
    {
        $lotes = [];
        $vendas = Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->whereNotNull('transactions.import_batch')
            ->leftJoin('users as imp_u', 'imp_u.id', '=', 'transactions.created_by')
            ->select(
                'transactions.import_batch',
                'transactions.import_time',
                'transactions.invoice_no',
                DB::raw("TRIM(CONCAT(COALESCE(imp_u.surname, ''), ' ', COALESCE(imp_u.first_name, ''), ' ', COALESCE(imp_u.last_name, ''))) as criado_por")
            )
            ->orderBy('transactions.import_batch', 'desc')
            ->toBase()
            ->get();

        foreach ($vendas as $venda) {
            $lote = (int) $venda->import_batch;
            // 1ª venda do lote abre a entrada; as seguintes só acrescentam a fatura.
            $lotes[$lote] ??= ['lote' => $lote, 'quando' => $venda->import_time, 'criadoPor' => (string) $venda->criado_por, 'faturas' => []];
            $lotes[$lote]['faturas'][] = (string) $venda->invoice_no;
        }

        return array_values($lotes);
    }

    /**
     * Props da prévia (R1–R4): cabeçalho com o índice real da coluna (é ele que o POST
     * devolve em `import_fields[...]` e `group_by`, como no Blade), as 100 primeiras linhas
     * para conferência — o Blade mostrava as mesmas 100 — e quantas vendas cada coluna
     * geraria se usada em "Agrupar por", contada sobre TODAS as linhas.
     *
     * @param  array<int, array<int, mixed>>  $parsed_array
     * @param  array<string, string>  $rotulos
     * @param  array<int|string, string|null>  $mapa
     * @param  mixed  $locais
     * @return array<string, mixed>
     */
    private function propsDaPrevia(array $parsed_array, array $rotulos, array $mapa, string $file_name, $locais): array
    {
        $texto = static fn ($v): string => $v === null ? '' : (is_scalar($v) ? (string) $v : (string) json_encode($v));
        $cabecalho = $parsed_array[0] ?? [];
        $dados = array_slice($parsed_array, 1);

        $grupos = [];
        foreach ($cabecalho as $coluna => $rotulo) {
            $valores = [];
            foreach ($dados as $linha) {
                $valores[$texto($linha[$coluna] ?? null)] = true;
            }
            $grupos[(string) $coluna] = count($valores);
        }

        $linhas = [];
        foreach (array_slice($dados, 0, 100) as $linha) {
            $linhas[] = array_map($texto, array_values($linha));
        }

        $cab = [];
        foreach ($cabecalho as $k => $r) {
            $cab[] = ['coluna' => (string) $k, 'rotulo' => $texto($r)];
        }

        $mapaInicial = [];
        foreach ($mapa as $k => $v) {
            $mapaInicial[(string) $k] = $v;
        }

        $campos = [];
        foreach ($rotulos as $key => $label) {
            $campos[] = ['key' => $key, 'label' => $label];
        }

        return [
            'arquivo' => $file_name,
            'cabecalho' => $cab,
            'linhas' => $linhas,
            'totalLinhas' => count($dados),
            'vendasPorColuna' => $grupos,
            'mapaInicial' => $mapaInicial,
            'campos' => $campos,
            'businessLocations' => $locais,
            'limiteSincrono' => $this->importSalesService->limiteSincrono(),
            'urls' => ['importar' => '/import-sales', 'voltar' => '/import-sales'],
        ];
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
