<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\ProntuarioExportacao;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportação do prontuário em PDF (UC-FPS-19, UC-FPS-20).
 *
 * Controller próprio (não o RegistrosController): exportar é leitura, e o RegistrosController
 * é o dono da escrita do prontuário.
 *
 * Acesso: a rota está no grupo do FisioLiberado (pacote `fisio_module` + `fisio.access`, 403
 * sem eles). Paciente de outra empresa não existe para esta — 404 pelo escopo, ANTES de montar
 * qualquer coisa ou registrar log. Exportar não exige `fisio.editar`: quem lê o prontuário na
 * tela pode tirá-lo em papel.
 *
 * Rastro (LGPD art. 11 — dado de saúde): cada exportação grava uma linha no activity_log
 * (spatie, o log que o projeto já usa; a tela /auditoria filtra por business_id) com o paciente
 * como sujeito e o usuário como autor. As propriedades levam só ids e contagem — nunca nome,
 * telefone ou conteúdo clínico. Nada vai para o log de aplicação (laravel.log).
 */
class ProntuarioPdfController extends Controller
{
    public function __construct(private readonly ProntuarioExportacao $exportacao) {}

    public function show(Request $request, int $id): Response
    {
        $paciente = Patient::query()->findOrFail($id);
        $biz = (int) $request->session()->get('user.business_id');

        $dados = $this->exportacao->montar($paciente, $biz);

        $pdf = Pdf::loadView('fisio::pdf.prontuario', $dados)->setPaper('a4', 'portrait');
        $pdf->render();
        $this->numerarPaginas($pdf->getDomPDF());

        activity('fisio')
            ->performedOn($paciente)
            ->causedBy($request->user())
            ->withProperties([
                'business_id' => $biz,
                'patient_id' => (int) $paciente->id,
                'registros' => count($dados['registros']),
            ])
            ->log('prontuario_exportado');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="prontuario-paciente-'.$paciente->id.'-'.now()->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** "Página X de Y" no canto inferior direito — o total só existe depois do render. */
    private function numerarPaginas(\Dompdf\Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $fonte = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 34, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 7.5, [0.34, 0.33, 0.31]);
    }
}
