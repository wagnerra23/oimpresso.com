<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PaymentGateway\Jobs\CnabRetornoProcessor;
use Modules\PaymentGateway\Models\CnabRetornoUpload;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Upload + histórico de arquivos de RETORNO CNAB por credencial.
 *
 * Tela: /settings/payment-gateways/{credentialId}/cnab-retorno
 *   - GET: histórico de uploads + form de upload
 *   - POST: recebe arquivo, valida, salva em Storage, dispatch Job
 *
 * Permission canon: `system_settings.access` (UPOS canon — Wagner/superadmin).
 * Permission granular `paymentgateway.cnab_retorno.upload` fica em backlog.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL: lookup PaymentGatewayCredential SEMPRE
 * filtra business_id da sessão (HasBusinessScope automático + validação extra).
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
 */
class PaymentGatewaysCnabRetornoController extends Controller
{
    /** Tipos MIME aceitos pra upload CNAB (cliente costuma renomear .txt/.RET/.cnab). */
    private const MIME_ACEITOS = [
        'text/plain',
        'application/octet-stream',
        'application/x-cnab',
    ];

    private const EXT_ACEITAS = ['txt', 'ret', 'cnab', 'rem'];

    private const TAMANHO_MAX_KB = 8192; // 8 MB — CNAB típico < 1 MB

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, int $credentialId): Response
    {
        $businessId = $this->resolveBusinessId($request);
        $cred = $this->findCredential($credentialId, $businessId);

        return Inertia::render('Settings/PaymentGateways/CnabRetorno', [
            'credential' => [
                'id'           => $cred->id,
                'gateway_key'  => $cred->gateway_key,
                'nome_display' => $cred->nome_display,
                'ambiente'     => $cred->ambiente,
                'ativo'        => (bool) $cred->ativo,
            ],
            'uploads' => Inertia::defer(fn () => $this->listarUploads($cred->id, $businessId)),
            'limites' => [
                'tamanho_max_kb' => self::TAMANHO_MAX_KB,
                'extensoes'      => self::EXT_ACEITAS,
            ],
        ]);
    }

    public function store(Request $request, int $credentialId): RedirectResponse
    {
        $businessId = $this->resolveBusinessId($request);
        $cred = $this->findCredential($credentialId, $businessId);

        $request->validate([
            'arquivo' => [
                'required',
                'file',
                'max:' . self::TAMANHO_MAX_KB,
            ],
        ]);

        $arquivo = $request->file('arquivo');
        $ext = strtolower((string) $arquivo->getClientOriginalExtension());

        if (! in_array($ext, self::EXT_ACEITAS, true)) {
            return back()->withErrors([
                'arquivo' => 'Extensão não aceita. Use: ' . implode(', ', self::EXT_ACEITAS),
            ]);
        }

        $path = $arquivo->store(
            sprintf('cnab-retornos/biz-%d/cred-%d', $businessId, $cred->id),
            'local'
        );

        $upload = CnabRetornoUpload::query()->create([
            'business_id'                    => $businessId,
            'payment_gateway_credential_id'  => $cred->id,
            'arquivo_path'                   => $path,
            'arquivo_nome_original'          => $arquivo->getClientOriginalName(),
            'arquivo_tamanho_bytes'          => $arquivo->getSize() ?: 0,
            'processado_por_user_id'         => Auth::id(),
        ]);

        CnabRetornoProcessor::dispatch(
            credentialId: $cred->id,
            arquivoRetornoPath: $path,
            uploadId: $upload->id,
            disk: 'local',
        );

        return back()->with('flash.banner', 'Arquivo enviado. Processamento em background — atualize em alguns segundos.');
    }

    private function resolveBusinessId(Request $request): int
    {
        return (int) ($request->session()->get('user.business_id')
            ?? $request->session()->get('business.id')
            ?? 0);
    }

    private function findCredential(int $credentialId, int $businessId): PaymentGatewayCredential
    {
        // HasBusinessScope filtra por session automaticamente, mas reforçamos
        // o predicate por defesa (cinto + suspensório Tier 0).
        return PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->findOrFail($credentialId);
    }

    /** @return array<int, array<string, mixed>> */
    private function listarUploads(int $credentialId, int $businessId): array
    {
        return CnabRetornoUpload::query()
            ->where('business_id', $businessId)
            ->where('payment_gateway_credential_id', $credentialId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (CnabRetornoUpload $u) => [
                'id'                     => $u->id,
                'arquivo_nome_original'  => $u->arquivo_nome_original,
                'arquivo_tamanho_bytes'  => $u->arquivo_tamanho_bytes,
                'processado_em'          => $u->processado_em?->toIso8601String(),
                'qtd_paga'               => $u->qtd_paga,
                'qtd_cancelada'          => $u->qtd_cancelada,
                'qtd_vencida'            => $u->qtd_vencida,
                'qtd_registrada'         => $u->qtd_registrada,
                'erros'                  => $u->errosArray(),
                'created_at'             => $u->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
