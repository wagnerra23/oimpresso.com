<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Fisio\Services\Social\RelatorioAssociacao;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatório agregado para a associação (US-FISIO-062): tela e PDF por período.
 *
 * Exige `fisio.relatorio` (além do `fisio.access` do grupo). Só contagens e médias da empresa
 * da sessão; nunca uma linha por paciente; grupo com menos de 5 sai "<5". Cada PDF gerado grava
 * uma linha no activity_log com a empresa e o período, sem nada de paciente.
 */
class RelatorioAssociacaoController extends Controller
{
    public function __construct(private readonly RelatorioAssociacao $relatorio) {}

    public function index(Request $request): InertiaResponse
    {
        [$de, $ate] = $this->periodo($request);
        $biz = $this->businessId($request);

        return Inertia::render('Fisio/Relatorio/Associacao', [
            'filtros' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
            'relatorio' => Inertia::defer(fn () => $this->relatorio->gerar($biz, $de, $ate)),
        ]);
    }

    public function pdf(Request $request): Response
    {
        [$de, $ate] = $this->periodo($request);
        $biz = $this->businessId($request);

        $dados = [
            'r' => $this->relatorio->gerar($biz, $de, $ate),
            'empresa' => (string) DB::table('business')->where('id', $biz)->value('name'),
            'emitido_em' => now()->format('d/m/Y H:i'),
        ];
        $pdf = Pdf::loadView('fisio::pdf.relatorio-associacao', $dados)->setPaper('a4', 'portrait');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $fonte = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 34, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 7.5, [0.34, 0.33, 0.31]);

        activity('fisio')
            ->causedBy($request->user())
            ->withProperties(['business_id' => $biz, 'de' => $de->toDateString(), 'ate' => $ate->toDateString()])
            ->log('relatorio_associacao_exportado');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="relatorio-associacao-'.$de->format('Ymd').'-'.$ate->format('Ymd').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} padrão: do 1º de janeiro até hoje */
    private function periodo(Request $request): array
    {
        abort_unless((bool) $request->user()?->can('fisio.relatorio'), 403);
        $dados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);
        $ate = isset($dados['ate']) ? CarbonImmutable::parse($dados['ate']) : CarbonImmutable::today();
        $de = isset($dados['de']) ? CarbonImmutable::parse($dados['de']) : $ate->startOfYear();

        return [$de, $ate];
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }
}
