<?php

namespace Modules\NFSe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Http\Requests\StoreNfseRequest;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;

/**
 * Rotas operacionais NFSe — US-NFSE-006/008/009.
 */
class NfseController extends Controller
{
    public function __construct(private readonly NfseEmissaoService $service) {}

    // US-NFSE-008: listagem
    //
    // `notas` (paginate 25) usa `Inertia::defer()` pra pular execução em
    // partial reloads que pedem `only:['filters']`. Skill `inertia-defer-default`.
    public function index(Request $request)
    {
        $this->authorize('nfse.view');

        $filters = $request->only(['status', 'de', 'ate', 'q']);

        return Inertia::render('Nfse/Index', [
            'notas'   => Inertia::defer(fn () => $this->buildNotasPayload($filters)),
            'filters' => $filters,
        ]);
    }

    private function buildNotasPayload(array $filters)
    {
        $query = NfseEmissao::latest('competencia');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['de'])) {
            $query->where('competencia', '>=', Carbon::parse($filters['de'])->startOfMonth());
        }
        if (!empty($filters['ate'])) {
            $query->where('competencia', '<=', Carbon::parse($filters['ate'])->endOfMonth());
        }
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('tomador_nome', 'like', "%{$q}%")
                    ->orWhere('numero', 'like', "%{$q}%");
            });
        }

        return $query->paginate(25)->withQueryString();
    }

    // US-NFSE-009: formulário de emissão
    public function create(Request $request)
    {
        $this->authorize('nfse.emit');

        $businessId = session('user.business_id');
        $config = NfseProviderConfig::where('business_id', $businessId)->with('certificado')->first();

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
                'cert_valido'          => $config->certificado?->isExpirado() === false,
                'cert_expira'          => $config->certificado?->valido_ate?->format('d/m/Y'),
            ] : null,
            'venda'    => $venda,
            'flash'    => session('status'),
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

    // US-NFSE-006: detalhe + status em tempo real
    public function show(NfseEmissao $nfse)
    {
        $this->authorize('nfse.view');

        $nfse->load('transaction.contact');

        $vendaData = null;
        if ($nfse->transaction) {
            $tx = $nfse->transaction;
            $vendaData = [
                'id'              => $tx->id,
                'invoice_no'      => $tx->invoice_no,
                'transaction_date'=> optional($tx->transaction_date)->format('d/m/Y'),
                'final_total'     => (float) $tx->final_total,
                'contact_nome'    => $tx->contact?->name ?? $tx->contact?->supplier_business_name ?? null,
            ];
        }

        return Inertia::render('Nfse/Show', [
            'nfse' => [
                'id'             => $nfse->id,
                'numero'         => $nfse->numero,
                'status'         => $nfse->status,
                'status_label'   => $nfse->statusLabel(),
                'status_color'   => $nfse->statusColor(),
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
                'transaction_id' => $nfse->transaction_id,
            ],
            'venda' => $vendaData,
            'flash' => session('status'),
        ]);
    }

    // US-NFSE-006: cancelamento
    public function cancelar(Request $request, NfseEmissao $nfse)
    {
        $this->authorize('nfse.cancel');

        $request->validate([
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        try {
            $this->service->cancelar($nfse, $request->motivo);
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
