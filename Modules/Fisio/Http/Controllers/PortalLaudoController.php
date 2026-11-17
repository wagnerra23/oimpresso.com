<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\ImpressoPdf;
use Modules\Fisio\Services\Laudo\LaudoBiopsicossocialDocumento;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Services\SessaoDoPortal;
use Symfony\Component\HttpFoundation\Response;

/**
 * O paciente baixa o próprio laudo biopsicossocial pelo portal (US-FISIO-060, UC-FBP-16; pedido do
 * [W] em 2026-11-12). Ele leva o laudo à unidade de saúde para pedir a carteira de fibromialgia.
 *
 * Portões, nesta ordem: link válido (o business vem assinado no token), PIN aceito (ou aparelho
 * lembrado) e consentimento de teleatendimento na versão vigente. Só sai laudo FINALIZADO do próprio
 * paciente e, numa cadeia de correções, só o mais recente: rascunho, laudo corrigido, laudo de outro
 * paciente ou de outra empresa dão 404, sem dizer qual foi o motivo. É o mesmo PDF que a clínica
 * baixa na ficha. Cada download grava no activity_log só ids e a origem `portal`.
 */
class PortalLaudoController extends Controller
{
    public function __construct(
        private readonly PortalTokenService $tokens,
        private readonly Consentimentos $consentimentos,
        private readonly LaudoBiopsicossocialDocumento $documento,
    ) {}

    public function pdf(Request $request, string $token, int $laudo, ImpressoPdf $impresso): Response
    {
        $linha = $this->tokens->resolver($token);
        abort_if($linha === null, 404);
        abort_unless(app(SessaoDoPortal::class)->pinAceito($request, $linha), 403);
        $paciente = $linha->getRelation('patient');
        abort_unless($paciente instanceof Patient, 404);
        abort_unless($this->consentimentos->vigente($paciente, Consentimentos::TELEATENDIMENTO), 403);

        $permitidos = array_column($this->documento->finalizadosDoPortal($paciente), 'id');
        abort_unless(in_array($laudo, $permitidos, true), 404);

        $biz = (int) $paciente->business_id;
        $doc = LaudoBiopsicossocial::withoutGlobalScopes() // SUPERADMIN: portal sem sessão; filtra pela empresa do paciente.
            ->where('business_id', $biz)->where('patient_id', (int) $paciente->id)->with('patient')->findOrFail($laudo);

        $dados = $this->documento->montar($doc, $biz);
        $pdf = $impresso->gerar('fisio::pdf.laudo-biopsicossocial', $dados);

        activity('fisio')
            ->performedOn($paciente)
            ->withProperties(['business_id' => $biz, 'patient_id' => (int) $paciente->id, 'laudo_bps_id' => (int) $doc->id,
                'finalizado' => true, 'origem' => 'portal'])
            ->log('laudo_bps_exportado');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laudo-biopsicossocial-'.$doc->finalizado_em->format('Ymd').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
