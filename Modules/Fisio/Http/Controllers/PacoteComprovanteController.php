<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Services\ImpressoPdf;
use Modules\Fisio\Services\Pacotes\ComprovantePacote;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprovante (voucher) do pacote de sessões em PDF (US-FISIO-021, UC-FPK-10). Mesmo desenho da
 * folha do programa: rota no grupo do FisioLiberado (pacote + `fisio.access`), pacote de outra
 * empresa 404 pelo escopo antes de montar ou registrar log. Imprimir não exige `fisio.editar`.
 *
 * Rastro: uma linha no activity_log por impressão, com o paciente como sujeito e só ids.
 */
class PacoteComprovanteController extends Controller
{
    public function __construct(
        private readonly ComprovantePacote $comprovante,
        private readonly ImpressoPdf $impresso,
    ) {}

    public function show(Request $request, int $pacote): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $p = Pacote::query()->where('business_id', $biz)->with('patient')->findOrFail($pacote);

        $dados = $this->comprovante->montar($p, $biz);
        $pdf = $this->impresso->gerar('fisio::pdf.comprovante-pacote', $dados);

        activity('fisio')
            ->performedOn($p->patient)
            ->causedBy($request->user())
            ->withProperties(['business_id' => $biz, 'patient_id' => (int) $p->patient_id, 'package_id' => (int) $p->id])
            ->log('pacote_comprovante_impresso');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comprovante-pacote-'.$p->id.'-'.now()->format('Ymd').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
