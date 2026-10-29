<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Models\Cobranca;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P-157 — baixar os arquivos de um boleto emitido por CNAB (BB e demais bancos por arquivo).
 *
 *   GET /settings/payment-gateways/cobrancas/{cobrancaId}/remessa → arquivo .rem para subir no banco
 *   GET /settings/payment-gateways/cobrancas/{cobrancaId}/boleto  → PDF do boleto para o cliente
 *
 * Os caminhos vêm de `cobrancas.payload_gateway` (gravados pelo driver na emissão).
 *
 * Multi-tenant Tier 0 (ADR 0093): a cobrança é buscada pelo business_id da sessão, e o caminho
 * só é servido se estiver dentro da pasta da própria empresa (`.../biz-{id}/...`). Outra empresa
 * ou caminho fora da pasta → 404, sem dizer que existe.
 */
class CobrancaCnabArquivosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function remessa(Request $request, int $cobrancaId): StreamedResponse
    {
        return $this->baixar($request, $cobrancaId, 'cnab_remessa_path', 'cnab-remessas', 'remessa-%s.rem', 'text/plain');
    }

    public function boleto(Request $request, int $cobrancaId): StreamedResponse
    {
        return $this->baixar($request, $cobrancaId, 'boleto_pdf_path', 'cnab-boletos', 'boleto-%s.pdf', 'application/pdf');
    }

    private function baixar(Request $request, int $cobrancaId, string $chave, string $pasta, string $nome, string $mime): StreamedResponse
    {
        $businessId = (int) ($request->session()->get('user.business_id') ?? $request->session()->get('business.id') ?? 0);

        // SUPERADMIN: o filtro de empresa é explícito abaixo (where business_id = sessão), não o scope global.
        $cobranca = Cobranca::query()->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('id', $cobrancaId)
            ->first();

        $path = (string) ($cobranca?->payload_gateway[$chave] ?? '');
        if ($businessId <= 0 || $path === '' || ! str_starts_with($path, "{$pasta}/biz-{$businessId}/")
            || str_contains($path, '..') || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $ref = preg_replace('/[^0-9A-Za-z]/', '', (string) ($cobranca->nosso_numero ?: $cobranca->id));

        return Storage::disk('local')->download($path, sprintf($nome, $ref), ['Content-Type' => $mime]);
    }
}
