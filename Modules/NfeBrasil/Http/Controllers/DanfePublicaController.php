<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;

/**
 * DANFE em PDF por link assinado, sem login (US-WA-321).
 *
 * Existe para o cliente abrir o anexo que chega pelo WhatsApp: o driver busca o arquivo pela URL.
 * O acesso é a assinatura da URL (middleware `signed`, validade definida por quem gera o link);
 * a emissão é sempre lida pela empresa que vem na URL assinada (Tier 0, ADR 0093) — trocar o id
 * ou a empresa invalida a assinatura. Só emissão autorizada tem DANFE.
 */
class DanfePublicaController extends Controller
{
    public function __construct(private readonly DanfeService $danfeService) {}

    public function show(int $business, int $emissao): Response
    {
        $nfe = NfeEmissao::query()
            ->where('business_id', $business)
            ->where('id', $emissao)
            ->where('status', 'autorizada')
            ->first();
        abort_if($nfe === null, 404);

        $pdf = $this->danfeService->lerOuGerar($nfe);
        abort_if($pdf === null, 404);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="danfe-%s.pdf"', $nfe->chave_44 ?: $nfe->id),
            'Cache-Control' => 'private, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
