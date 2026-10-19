<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Services\FolhaPrograma;
use Symfony\Component\HttpFoundation\Response;

/**
 * Folha para imprimir do programa (B-12; UC-FPS-37, UC-FPS-38). Mesmo desenho do
 * ProntuarioPdfController: rota no grupo do FisioLiberado (pacote + `fisio.access`, 403 sem
 * eles); programa de outra empresa responde 404 pelo escopo ANTES de montar qualquer coisa ou
 * registrar log. Imprimir não exige `fisio.editar`: quem vê o programa na ficha pode tirá-lo
 * em papel.
 *
 * Rastro: uma linha no activity_log por folha, com o paciente como sujeito e só ids e contagem
 * nas propriedades. Imprimir NÃO mexe no link do portal (ver FolhaPrograma).
 */
class FolhaProgramaController extends Controller
{
    public function __construct(private readonly FolhaPrograma $folha) {}

    public function show(Request $request, int $id): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);

        $dados = $this->folha->montar($programa, $biz);

        $pdf = Pdf::loadView('fisio::pdf.folha-programa', $dados)->setPaper('a4', 'portrait');

        activity('fisio')
            ->performedOn($programa->patient)
            ->causedBy($request->user())
            ->withProperties([
                'business_id' => $biz,
                'patient_id' => (int) $programa->patient_id,
                'program_id' => (int) $programa->id,
                'itens' => count($dados['itens']),
            ])
            ->log('programa_impresso');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="programa-'.$programa->id.'-'.now()->format('Ymd').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
