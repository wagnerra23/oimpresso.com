<?php

namespace Modules\NFSe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Http\Requests\CancelarNfseRequest;
use Modules\NFSe\Http\Requests\IndexNfseRequest;
use Modules\NFSe\Http\Requests\StoreNfseRequest;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Nfse\ListaServicoNacional;
use Modules\NfeBrasil\Services\SeparacaoNotasDaVenda;

/**
 * Rotas operacionais NFSe — US-NFSE-006/008/009.
 */
class NfseController extends Controller
{
    public function __construct(private readonly NfseEmissaoService $service) {}

    // US-NFSE-008: listagem. `notas` vai deferida (paginate + filtro): a tela abre com o
    // esqueleto e a lista chega no 2º pedido. Index.tsx envolve a tabela em <Deferred>.
    public function index(IndexNfseRequest $request)
    {
        // D8.c Security Wave 17 — IndexNfseRequest valida whitelist status + date_format
        // de/ate + maxlen q. RBAC `nfse.view` segue via $this->authorize() (gate).
        $this->authorize('nfse.view');

        $filters = $request->validated();

        return Inertia::render('Nfse/Index', [
            'notas'   => Inertia::defer(fn () => $this->buildNotasPayload($filters)),
            'filters' => $filters,
        ]);
    }

    private function buildNotasPayload(array $filters)
    {
        // Só as colunas que a tabela mostra. xml_envio/xml_retorno (a DPS e a resposta da
        // Receita) e o resto da nota ficam fora da prop — charter Non-Goal "NÃO mostra
        // XML/RPS bruto na listagem" (UC-NFSL-05).
        $query = NfseEmissao::query()
            ->select([
                'id', 'numero', 'status', 'tomador_nome', 'valor_servicos', 'valor_iss',
                'competencia', 'pdf_url', 'erro_mensagem', 'created_at',
            ])
            ->latest('competencia')
            ->latest('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        // Competência é mês: o filtro chega como AAAA-MM (o <input type="month"> da tela).
        if (!empty($filters['de'])) {
            $query->where('competencia', '>=', Carbon::createFromFormat('!Y-m', $filters['de'])->startOfMonth()->toDateString());
        }
        if (!empty($filters['ate'])) {
            $query->where('competencia', '<=', Carbon::createFromFormat('!Y-m', $filters['ate'])->endOfMonth()->toDateString());
        }
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('tomador_nome', 'like', "%{$q}%")
                    ->orWhere('numero', 'like', "%{$q}%");
            });
        }

        return $query->paginate(25)->withQueryString()->through(fn (NfseEmissao $n) => [
            // valor_iss é anulável na coluna; o cast decimal:2 esconde isso do Larastan,
            // por isso a leitura crua.
            'id'             => $n->id,
            'numero'         => $n->numero,
            'status'         => $n->status,
            'tomador_nome'   => $n->tomador_nome,
            'valor_servicos' => (float) $n->valor_servicos,
            'valor_iss'      => $n->getRawOriginal('valor_iss') === null ? null : (float) $n->getRawOriginal('valor_iss'),
            'competencia'    => $n->competencia?->format('m/Y'),
            'pdf_url'        => $n->pdf_url,
            'erro_mensagem'  => $n->erro_mensagem,
            'created_at'     => $n->created_at?->format('d/m/Y H:i'),
        ]);
    }

    // US-NFSE-009: formulário de emissão
    public function create(Request $request)
    {
        $this->authorize('nfse.emit');

        $businessId = session('user.business_id');
        $config = NfseProviderConfig::where('business_id', $businessId)->first();
        // Certificado ativo da empresa — o mesmo da NF-e (/fiscal/config?tab=cert).
        $certificado = app(CertificadoService::class)->certificadoAtivo((int) $businessId);

        $venda = null;
        if ($request->filled('transaction_id')) {
            $tx = Transaction::with('contact')
                ->where('business_id', $businessId)
                ->where('type', 'sell')
                ->find((int) $request->transaction_id);

            if ($tx) {
                $taxRaw   = $tx->contact?->tax_number ?? $tx->contact?->cpf_cnpj ?? '';
                $taxDigits = preg_replace('/\D/', '', $taxRaw);
                $venda = [
                    'transaction_id'  => $tx->id,
                    'invoice_no'      => $tx->invoice_no,
                    'transaction_date'=> optional($tx->transaction_date)->format('Y-m'),
                    'contact_nome'    => $tx->contact?->name ?? $tx->contact?->supplier_business_name ?? '',
                    'contact_cnpj'    => strlen($taxDigits) === 14 ? $taxRaw : null,
                    'contact_cpf'     => strlen($taxDigits) === 11 ? $taxRaw : null,
                    'contact_email'   => $tx->contact?->email,
                    'final_total'     => (float) $tx->final_total,
                ];
            }
        }

        return Inertia::render('Nfse/Emitir', [
            'config'   => $config ? [
                'lc116_codigo_default' => $config->lc116_codigo_default,
                'aliquota_iss'         => $config->aliquota_iss,
                'ambiente'             => $config->ambiente,
                'cert_valido'          => $certificado?->isVencido() === false,
                'cert_expira'          => $certificado?->valido_ate?->format('d/m/Y'),
            ] : null,
            'venda'    => $venda,
            'flash'    => session('status'),
            // R-NFSE-006: subitens LC 116 com mais de um desdobro nacional (Anexo B). Arquivo
            // estático versionado, sem consulta ao banco — prop leve, não precisa de defer.
            'desdobrosNacionais' => app(ListaServicoNacional::class)->subitensComVariosDesdobros(),
        ]);
    }

    // US-NFSE-006: recebe POST, valida, dispara job assíncrono
    public function store(StoreNfseRequest $request)
    {
        // Autorização via gate `nfse.emit` + rules ABRASF/Município espelhadas no FormRequest.
        $data = $request->validated();

        $businessId = (int) session('user.business_id');

        // Service monta DTO (cert + RPS) + despacha job — Controller fica thin (apenas HTTP/auth/validate)
        $payload = $this->service->montarPayload($data, $businessId);
        $this->service->despacharEmissaoAsync($payload);

        return redirect()->route('nfse.index')
            ->with('status', [
                'success' => true,
                'msg'     => 'NFSe enviada para processamento. Acompanhe o status na listagem.',
            ]);
    }

    /**
     * TRAVA-SEGUNDA CU-4 — emissão NFS-e a partir de uma venda (JSON), simétrico ao
     * NfeEmissaoController::emitir. Chamado pelo botão "Emitir NFS-e" da tela de venda
     * (VdNfseEmitModal). Monta o payload a partir da Transaction + defaults do
     * NfseProviderConfig (mesmos campos do StoreNfseRequest), reusa o serviço real
     * (montarPayload + despacharEmissaoAsync) — emite em homologação por config.
     *
     * POST /nfse/transactions/{tx}/emitir
     * Body opcional: descricao, lc116_codigo, valor_servicos, aliquota_iss, iss_retido.
     */
    public function emitirParaTransaction(Request $request, int $tx)
    {
        $this->authorize('nfse.emit');

        $businessId = (int) session('user.business_id');
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        $transaction = Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->find($tx);
        if (! $transaction) {
            return response()->json(['error' => 'transaction_not_found'], 404);
        }

        $config = NfseProviderConfig::where('business_id', $businessId)->first();
        if (! $config) {
            return response()->json([
                'error'   => 'nfse_nao_configurada',
                'message' => 'Configure o provedor NFS-e antes de emitir.',
            ], 422);
        }

        // Tomador via query builder (contact_id → contacts) — evita a relação
        // dinâmica que o Larastan não enxerga no legado App\Transaction.
        $tomador   = \Illuminate\Support\Facades\DB::table('contacts')->where('id', $transaction->contact_id)->first();
        $taxRaw    = $tomador?->tax_number ?? $tomador?->cpf_cnpj ?? '';
        $taxDigits = preg_replace('/\D/', '', (string) $taxRaw) ?? '';

        // US-NFE-069 · UC-FLX-27: venda separada emite só a parte serviço, com o total e o código
        // LC 116 que vêm da separação — o valor do corpo é ignorado (regra mestre de valor).
        $separacao = null;
        if ($request->boolean('separada')) {
            $separacao = $this->parteServico($businessId, $transaction->id);
            if ($separacao instanceof \Illuminate\Http\JsonResponse) {
                return $separacao;
            }
        }

        // Payload no MESMO formato validado do StoreNfseRequest — venda + defaults config.
        $data = [
            'competencia'    => optional($transaction->transaction_date)->format('Y-m') ?? now()->format('Y-m'),
            'tomador_nome'   => $tomador?->name ?? $tomador?->supplier_business_name ?? 'Consumidor',
            'tomador_cnpj'   => strlen($taxDigits) === 14 ? $taxRaw : null,
            'tomador_cpf'    => strlen($taxDigits) === 11 ? $taxRaw : null,
            'tomador_email'  => $tomador?->email,
            'descricao'      => $request->input('descricao') ?: 'Serviços referentes à venda '.$transaction->invoice_no,
            'lc116_codigo'   => $request->input('lc116_codigo') ?: ($config->lc116_codigo_default ?? '1.01'),
            'valor_servicos' => (float) ($request->input('valor_servicos') ?: $transaction->final_total),
            'aliquota_iss'   => (float) ($request->input('aliquota_iss') ?: ($config->aliquota_iss ?? 0.05)),
            'iss_retido'     => (bool) $request->input('iss_retido', false),
            'transaction_id' => $transaction->id,
        ];
        if ($separacao !== null) {
            $data['valor_servicos'] = $separacao['total'];
            $data['descricao']      = $request->input('descricao') ?: $separacao['descricao'];
            $data['lc116_codigo']   = $separacao['lc116'] ?? $data['lc116_codigo'];
        }

        $payload = $this->service->montarPayload($data, $businessId);
        $this->service->despacharEmissaoAsync($payload);

        return response()->json([
            'status'         => 'processando',
            'message'        => 'NFS-e enviada para processamento. Acompanhe o status na listagem.',
            'rps'            => $payload->rpsNumero,
            'transaction_id' => $transaction->id,
        ], 202);
    }

    /**
     * Parte serviço da venda separada, ou a resposta de recusa. Recusa (422/409) quando:
     * natureza pendente (o contador decide antes — D-NATUREZA), venda sem serviço, serviços
     * com códigos LC 116 diferentes (uma NFS-e tem um código só), ou NFS-e já emitida/em curso.
     *
     * @return array{total:float,descricao:string,lc116:?string}|\Illuminate\Http\JsonResponse
     */
    private function parteServico(int $businessId, int $transactionId)
    {
        try {
            $sep = app(SeparacaoNotasDaVenda::class)->separar($businessId, $transactionId);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'separacao_invalida', 'message' => $e->getMessage()], 422);
        }
        if ($sep['pendentes'] !== []) {
            return response()->json([
                'error'   => 'natureza_pendente',
                'message' => 'Produto(s) ' . implode(', ', $sep['pendentes']) . ' com natureza fiscal a decidir: o contador decide antes de faturar.',
            ], 422);
        }
        $parte = $sep[SeparacaoNotasDaVenda::SERVICO];
        if ($parte['total'] <= 0) {
            return response()->json(['error' => 'sem_servico', 'message' => 'A venda não tem parte serviço — emita só a NF-e.'], 422);
        }
        $codigos = array_values(array_unique(array_filter(array_column($parte['linhas'], 'codigo_servico'))));
        if (count($codigos) > 1) {
            return response()->json([
                'error'   => 'codigos_servico_diferentes',
                'message' => 'Os serviços da venda têm códigos LC 116 diferentes (' . implode(', ', $codigos) . '): uma NFS-e leva um código só.',
            ], 422);
        }
        $emCurso = NfseEmissao::where('business_id', $businessId)->where('transaction_id', $transactionId)->get()
            ->contains(fn (NfseEmissao $n) => $n->isPendente() || $n->isEmitida());
        if ($emCurso) {
            return response()->json(['error' => 'nfse_ja_existe', 'message' => 'Esta venda já tem NFS-e emitida ou em processamento.'], 409);
        }

        return [
            'total'     => (float) $parte['total'],
            'descricao' => 'Serviços da venda: ' . implode('; ', array_map(
                fn (array $l) => $l['descricao'] . ' (' . rtrim(rtrim(number_format((float) $l['quantidade'], 3, ',', ''), '0'), ',') . ')',
                $parte['linhas'],
            )),
            'lc116'     => $codigos[0] ?? null,
        ];
    }

    // US-NFSE-006: detalhe + status em tempo real
    public function show(NfseEmissao $nfse)
    {
        $this->authorize('nfse.view');

        // Tier 0: Transaction não tem escopo de empresa. A venda só entra na tela se for da
        // mesma empresa da nota — nota antiga gravada com venda alheia não a expõe.
        $tx = $nfse->transaction_id
            ? Transaction::query()
                ->where('business_id', $nfse->business_id)
                ->find($nfse->transaction_id)
            : null;

        $vendaData = null;
        if ($tx) {
            $contato = $tx->contact_id
                ? \App\Contact::where('business_id', $nfse->business_id)->find($tx->contact_id)
                : null;
            $vendaData = [
                'id'              => $tx->id,
                'invoice_no'      => $tx->invoice_no,
                'transaction_date'=> optional($tx->transaction_date)->format('d/m/Y'),
                'final_total'     => (float) $tx->final_total,
                'contact_nome'    => $contato?->name ?? $contato?->supplier_business_name ?? null,
            ];
        }

        // Ambiente do provedor da empresa da nota. A nota não grava o ambiente em que saiu
        // (xml_envio nunca é preenchido), então a tela mostra o configurado HOJE e diz isso.
        $ambiente = NfseProviderConfig::where('business_id', $nfse->business_id)->value('ambiente');

        return Inertia::render('Nfse/Show', [
            'nfse' => [
                'id'             => $nfse->id,
                'numero'         => $nfse->numero,
                'status'         => $nfse->status,
                'tomador_nome'   => $nfse->tomador_nome,
                'tomador_cnpj'   => $nfse->tomador_cnpj,
                'tomador_cpf'    => $nfse->tomador_cpf,
                'tomador_email'  => $nfse->tomador_email,
                'valor_servicos' => $nfse->valor_servicos,
                'valor_iss'      => $nfse->valor_iss,
                'competencia'    => $nfse->competencia?->format('m/Y'),
                'lc116_codigo'   => $nfse->lc116_codigo,
                'descricao'      => $nfse->descricao,
                'pdf_url'        => $nfse->pdf_url,
                'erro_mensagem'  => $nfse->erro_mensagem,
                'created_at'     => $nfse->created_at?->format('d/m/Y H:i'),
                // Cancelada é terminal: depois da troca de status nada mais grava na linha, então
                // o updated_at é o momento do cancelamento. O MOTIVO não é guardado em coluna
                // nenhuma (só no log do canal nfse) — fica para o evento de cancelamento (lote 2.4).
                'cancelada_em'   => $nfse->isCancelada() ? $nfse->updated_at?->format('d/m/Y H:i') : null,
                'transaction_id' => $nfse->transaction_id,
            ],
            'venda'    => $vendaData,
            'ambiente' => $ambiente,
            // Sem 'flash' próprio: o aviso de sucesso/erro do cancelamento sai pelo flash
            // compartilhado (HandleInertiaRequests) e vira toast no app.tsx, como nas outras telas.
        ]);
    }

    // US-NFSE-006: cancelamento
    public function cancelar(CancelarNfseRequest $request, NfseEmissao $nfse)
    {
        // D8.c Security Wave 17 — CancelarNfseRequest valida motivo (ABRASF 15..255).
        // RBAC `nfse.cancel` checado via FormRequest::authorize() (compat com gate).
        try {
            $this->service->cancelar($nfse, $request->validated()['motivo']);
        } catch (NfseException $e) {
            return back()->with('status', ['success' => false, 'msg' => $e->getMessage()]);
        }

        return back()->with('status', ['success' => true, 'msg' => 'NFSe cancelada com sucesso.']);
    }

    // US-NFSE-006: proxy DANFSE
    public function pdf(NfseEmissao $nfse)
    {
        $this->authorize('nfse.view');

        if (! $nfse->pdf_url) {
            abort(404, 'PDF não disponível para esta nota.');
        }

        return redirect($nfse->pdf_url);
    }
}
