<?php

namespace Modules\NFSe\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Jobs\EmitirNfseJob;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Services\NfseEmissaoService;

/**
 * Rotas operacionais NFSe — US-NFSE-006.
 * UI (index/create): US-NFSE-008/009 (Inertia/React — pendente).
 */
class NfseController extends Controller
{
    public function __construct(private readonly NfseEmissaoService $service) {}

    // US-NFSE-008: listagem — stub Inertia pendente
    public function index()
    {
        $this->authorize('nfse.view');

        $notas = NfseEmissao::latest('competencia')
            ->paginate(25);

        // TODO US-008: return Inertia::render('Nfse/Index', compact('notas'));
        return response()->json($notas);
    }

    // US-NFSE-009: formulário — stub Inertia pendente
    public function create()
    {
        $this->authorize('nfse.emit');

        // TODO US-009: return Inertia::render('Nfse/Emitir', [...]);
        return response()->json(['message' => 'Formulário de emissão — UI pendente (US-NFSE-009)'], 501);
    }

    // US-NFSE-006: recebe POST, valida, dispara job assíncrono
    public function store(Request $request)
    {
        $this->authorize('nfse.emit');

        $data = $request->validate([
            'competencia'     => ['required', 'date_format:Y-m'],
            'tomador_nome'    => ['required', 'string', 'max:150'],
            'tomador_cnpj'    => ['nullable', 'string'],
            'tomador_cpf'     => ['nullable', 'string'],
            'tomador_email'   => ['nullable', 'email'],
            'descricao'       => ['required', 'string', 'max:2000'],
            'lc116_codigo'    => ['required', 'string', 'max:5'],
            'valor_servicos'  => ['required', 'numeric', 'min:0.01'],
            'aliquota_iss'    => ['required', 'numeric', 'min:0', 'max:1'],
            'iss_retido'      => ['boolean'],
        ]);

        $businessId = session('user.business_id');

        static $rpsCounter = 0;
        $rpsNumero = now()->format('YmdHis') . str_pad(++$rpsCounter, 4, '0', STR_PAD_LEFT);

        $payload = new NfseEmissaoPayload(
            businessId: $businessId,
            rpsNumero: $rpsNumero,
            competencia: Carbon::createFromFormat('Y-m', $data['competencia']),
            tomadorNome: $data['tomador_nome'],
            tomadorCnpj: $data['tomador_cnpj'] ?? null,
            tomadorCpf: $data['tomador_cpf'] ?? null,
            tomadorEmail: $data['tomador_email'] ?? null,
            descricao: $data['descricao'],
            lc116Codigo: $data['lc116_codigo'],
            valorServicos: (float) $data['valor_servicos'],
            aliquotaIss: (float) $data['aliquota_iss'],
            issRetido: (bool) ($data['iss_retido'] ?? false),
        );

        EmitirNfseJob::dispatch($payload)->onQueue('nfse');

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

        // TODO US-008: return Inertia::render('Nfse/Show', compact('nfse'));
        return response()->json([
            'id'             => $nfse->id,
            'numero'         => $nfse->numero,
            'status'         => $nfse->status,
            'status_label'   => $nfse->statusLabel(),
            'status_color'   => $nfse->statusColor(),
            'tomador_nome'   => $nfse->tomador_nome,
            'valor_servicos' => $nfse->valor_servicos,
            'valor_iss'      => $nfse->valor_iss,
            'competencia'    => $nfse->competencia?->format('m/Y'),
            'pdf_url'        => $nfse->pdf_url,
            'erro_mensagem'  => $nfse->erro_mensagem,
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
