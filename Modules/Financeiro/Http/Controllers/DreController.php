<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Exports\DreExport;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
use Modules\Financeiro\Services\DreService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tela /financeiro/dre — DRE gerencial hierárquica (Cockpit V2).
 *
 * Origem: protótipo Cowork `TelaDRE` (linha 361-483 de
 * `public/cowork-preview/erp-shell/financeiro-telas-extras.jsx`) aprovado por
 * [W] 2026-05-20 + decisões Q1-Q8b aprovadas em
 * `memory/requisitos/Financeiro/dre-visual-comparison.md`.
 *
 * Persona-foco: Wagner [W] (dono, decisão estratégica) + Eliana [E] (financeiro).
 * Stories: US-FIN-014a.
 *
 * Read-only puro: NÃO faz mutação. Agregação em `DreService::montar()`.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *  - business_id lido de session('user.business_id')
 *  - middleware can:financeiro.relatorios.view (mesma permission do Relatorios)
 *  - Service recebe businessId explicitamente como 1º arg
 *  - NUNCA aceita business_id via query param
 *
 * Endpoints:
 *  - GET  /financeiro/dre              → Inertia render
 *  - GET  /financeiro/dre/export-pdf   → dompdf (view `financeiro::pdf.dre`)
 *  - GET  /financeiro/dre/export-xlsx  → maatwebsite/excel (DreExport)
 *  - GET  /financeiro/dre/export-csv   → StreamedResponse BOM UTF-8
 */
class DreController extends Controller
{
    use RendersMockCowork;

    public function __construct(private DreService $service)
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.relatorios.view');
    }

    public function index(Request $request): Response|IlluminateResponse
    {
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

        $businessId = (int) session('user.business_id');
        [$periodoTipo, $anchorMes] = $this->parseQuery($request);

        return OtelHelper::spanBiz('financeiro.dre.index', function () use ($businessId, $periodoTipo, $anchorMes) {
            $shape = $this->service->montar($businessId, $periodoTipo, $anchorMes);

            return Inertia::render('Financeiro/Dre/Index', $shape);
        }, ['op' => 'index', 'periodo_tipo' => $periodoTipo]);
    }

    /**
     * Export PDF via dompdf (barryvdh/laravel-dompdf, já no projeto).
     *
     * Lazy require pra suportar smoke tests em ambientes sem PdfFacade
     * registrado (skip gracioso → IlluminateResponse 501).
     */
    public function exportPdf(Request $request): IlluminateResponse|BinaryFileResponse
    {
        $businessId = (int) session('user.business_id');
        [$periodoTipo, $anchorMes] = $this->parseQuery($request);
        $shape = $this->service->montar($businessId, $periodoTipo, $anchorMes);

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response('PDF export indisponível neste ambiente.', 501);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('financeiro::pdf.dre', $shape)
            ->setPaper('a4', 'portrait');

        $filename = 'dre_'.$shape['meta']['anchor_mes'].'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export Excel via maatwebsite/excel (já no projeto).
     */
    public function exportXlsx(Request $request): IlluminateResponse|BinaryFileResponse
    {
        $businessId = (int) session('user.business_id');
        [$periodoTipo, $anchorMes] = $this->parseQuery($request);
        $shape = $this->service->montar($businessId, $periodoTipo, $anchorMes);

        if (! class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return response('XLSX export indisponível neste ambiente.', 501);
        }

        $filename = 'dre_'.$shape['meta']['anchor_mes'].'_'.now()->format('Ymd_His').'.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(new DreExport($shape), $filename);
    }

    /**
     * Export CSV streaming com BOM UTF-8 (Excel BR abre certo).
     *
     * Espelha o pattern `RelatoriosController::exportCsv()` linha 56-86.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $businessId = (int) session('user.business_id');
        [$periodoTipo, $anchorMes] = $this->parseQuery($request);
        $shape = $this->service->montar($businessId, $periodoTipo, $anchorMes);

        $filename = 'dre_'.$shape['meta']['anchor_mes'].'_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($shape): void {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 pra Excel BR abrir certo
            fwrite($out, "\xEF\xBB\xBF");

            $meta = $shape['meta'];
            $labelAtual = $meta['periodo_label'];
            $labelPrev = $meta['periodo_label_prev'];

            fputcsv($out, ['DRE Gerencial — '.($meta['business_name'] ?? '').' — '.$labelAtual]);
            fputcsv($out, []);
            fputcsv($out, ['Conta', $labelAtual, '% RL', $labelPrev, 'Δ%']);

            $baseRL = (float) ($meta['base_rl'] ?? 0.0);

            foreach ($shape['linhas'] as $l) {
                $label = $this->labelComIndent($l);
                $v = (float) ($l['v'] ?? 0.0);
                $prev = (float) ($l['prev'] ?? 0.0);
                $pct = $baseRL > 0.0 ? round(($v / $baseRL) * 100.0, 1) : 0.0;
                $delta = $prev != 0.0 ? round((($v - $prev) / abs($prev)) * 100.0, 1) : 0.0;

                fputcsv($out, [
                    $label,
                    number_format($v, 2, ',', '.'),
                    $pct,
                    number_format($prev, 2, ',', '.'),
                    $delta,
                ]);
            }

            // Cards bottom (margem operacional + top categorias)
            fputcsv($out, []);
            fputcsv($out, ['Margem operacional']);
            fputcsv($out, ['Atual %', 'Meta %', 'Anterior %', 'Δ pp']);
            $mo = $shape['margem_operacional'];
            fputcsv($out, [$mo['atual_pct'], $mo['meta_pct'], $mo['prev_pct'], $mo['delta_pp']]);

            fputcsv($out, []);
            fputcsv($out, ['Top categorias de receita — '.$labelAtual]);
            fputcsv($out, ['Categoria', 'Valor', '% RL']);
            foreach (($shape['top_categorias_receita'] ?? []) as $cat) {
                fputcsv($out, [
                    $cat['label'],
                    number_format((float) $cat['valor'], 2, ',', '.'),
                    $cat['pct'],
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * Lê query string e devolve [periodoTipo, anchorMes].
     *
     * `periodo` aceita 'mes'|'trimestre'|'ano'|'12m'. F1 só 'mes' funcional;
     * outros são aceitos mas Service entrega vazio (Q4 aprovado 2026-05-20).
     *
     * `anchor` aceita 'YYYY-MM'. Default null → Service usa now().
     *
     * @return array{0: string, 1: string|null}
     */
    private function parseQuery(Request $request): array
    {
        $periodo = (string) $request->query('periodo', 'mes');
        if (! in_array($periodo, ['mes', 'trimestre', 'ano', '12m'], true)) {
            $periodo = 'mes';
        }

        $anchor = $request->query('anchor');
        if (is_string($anchor) && preg_match('/^\d{4}-\d{2}$/', $anchor)) {
            $anchorMes = $anchor;
        } else {
            $anchorMes = null;
        }

        return [$periodo, $anchorMes];
    }

    /**
     * Adiciona indent visual no label pro CSV.
     */
    private function labelComIndent(array $linha): string
    {
        $type = $linha['type'] ?? '';
        $label = (string) ($linha['label'] ?? '');

        if ($type === 'i') {
            $indent = (int) ($linha['indent'] ?? 1);

            return str_repeat('  ', max(1, $indent)).$label;
        }

        return $label;
    }
}
