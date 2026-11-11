<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\ImpressoPdf;
use Modules\Fisio\Services\LaudoEvolucao;
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
    public function __construct(
        private readonly ProntuarioExportacao $exportacao,
        private readonly ImpressoPdf $impresso,
    ) {}

    public function show(Request $request, int $id): Response
    {
        $paciente = Patient::query()->findOrFail($id);
        $biz = (int) $request->session()->get('user.business_id');

        $dados = $this->exportacao->montar($paciente, $biz);

        $pdf = $this->impresso->gerar('fisio::pdf.prontuario', $dados);

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

    /**
     * Laudo de evolução: síntese de uma página para médico ou convênio (2º documento, não um modo
     * do prontuário — destinatário e conteúdo são outros). Mesmo acesso e mesmo rastro da
     * exportação do prontuário: 404 entre empresas antes de montar, uma linha no activity_log só
     * com ids. Leitura; nada é gravado no prontuário.
     */
    public function laudo(Request $request, int $id, LaudoEvolucao $laudo): Response
    {
        $paciente = Patient::query()->findOrFail($id);
        $biz = (int) $request->session()->get('user.business_id');

        $dados = $laudo->montar($paciente, $biz, $request->user()?->id);
        $pdf = $this->impresso->gerar('fisio::pdf.laudo-evolucao', $dados);

        activity('fisio')
            ->performedOn($paciente)
            ->causedBy($request->user())
            ->withProperties(['business_id' => $biz, 'patient_id' => (int) $paciente->id])
            ->log('laudo_exportado');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laudo-paciente-'.$paciente->id.'-'.now()->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
