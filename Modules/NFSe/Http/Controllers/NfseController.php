<?php

namespace Modules\NFSe\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Jobs\EmitirNfseJob;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseCertificado;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;

/**
 * Rotas operacionais NFSe — US-NFSE-006/008/009.
 */
class NfseController extends Controller
{
    public function __construct(private readonly NfseEmissaoService $service) {}

    // US-NFSE-008: listagem
    public function index(Request $request)
    {
        $this->authorize('nfse.view');

        $filters = $request->only(['status', 'de', 'ate', 'q']);

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

        $notas = $query->paginate(25)->withQueryString();

        return Inertia::render('Nfse/Index', [
            'notas'   => $notas,
            'filters' => $filters,
        ]);
    }

    // US-NFSE-009: formulário de emissão
    public function create()
    {
        $this->authorize('nfse.emit');

        $businessId = session('user.business_id');
        $config = NfseProviderConfig::where('business_id', $businessId)->with('certificado')->first();

        return Inertia::render('Nfse/Emitir', [
            'config'   => $config ? [
                'lc116_codigo_default' => $config->lc116_codigo_default,
                'aliquota_iss'         => $config->aliquota_iss,
                'ambiente'             => $config->ambiente,
                'cert_valido'          => $config->certificado?->isExpirado() === false,
                'cert_expira'          => $config->certificado?->valido_ate?->format('d/m/Y'),
            ] : null,
            'flash'    => session('status'),
        ]);
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

        // Carrega cert do DB para o payload
        $config = NfseProviderConfig::where('business_id', $businessId)->with('certificado')->first();
        $certPfxBase64 = $config?->certificado?->pfxDecriptado()
            ? base64_encode($config->certificado->pfxDecriptado())
            : null;
        $certSenha = $config?->certificado?->senhaDecriptada();

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
            certPfxBase64: $certPfxBase64,
            certSenha: $certSenha,
            prestadorCnpj: $config?->prestador_cnpj,
            prestadorIm: $config?->prestador_im,
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
            ],
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
