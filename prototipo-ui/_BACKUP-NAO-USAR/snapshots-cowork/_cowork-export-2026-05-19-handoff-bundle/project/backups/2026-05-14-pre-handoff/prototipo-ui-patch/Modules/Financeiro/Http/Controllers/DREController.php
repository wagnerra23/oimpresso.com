<?php

// @memcofre
//   controller: DREController
//   tela: /financeiro/dre
//   module: Financeiro
//   status: stub-mock-data
//   tests: Modules/Financeiro/Tests/Feature/DREControllerTest

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DREController extends Controller
{
    public function index(Request $request): Response
    {
        $granularidade = $request->string('granularidade', 'mes')->toString();

        // TODO[CL]: substituir por DREService->montarDRE(tenantId, periodo, granularidade)
        $lines = [
            ['type' => 'h', 'label' => 'Receita operacional bruta', 'valor' => 14860, 'valor_anterior' => 12340, 'indent' => 0, 'highlight' => false],
            ['type' => 'i', 'label' => 'Banner / Lona / Adesivo',   'valor' => 9580,  'valor_anterior' => 7920,  'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Gráfica rápida',            'valor' => 2940,  'valor_anterior' => 2580,  'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Fachada / Placa',           'valor' => 2340,  'valor_anterior' => 1840,  'indent' => 1, 'highlight' => false],
            ['type' => 'h', 'label' => '(−) Deduções',              'valor' => -1260, 'valor_anterior' => -980,  'indent' => 0, 'highlight' => false],
            ['type' => 'i', 'label' => 'Impostos sobre vendas (Simples)', 'valor' => -1260, 'valor_anterior' => -980, 'indent' => 1, 'highlight' => false],
            ['type' => 'subtotal', 'label' => 'Receita líquida',    'valor' => 13600, 'valor_anterior' => 11360, 'indent' => 0, 'highlight' => false],
            ['type' => 'h', 'label' => '(−) Custos diretos',        'valor' => -5180, 'valor_anterior' => -4220, 'indent' => 0, 'highlight' => false],
            ['type' => 'i', 'label' => 'Insumos',                   'valor' => -3420, 'valor_anterior' => -2680, 'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Acabamento terceirizado',   'valor' => -1120, 'valor_anterior' => -940,  'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Frete / Instalação',        'valor' => -640,  'valor_anterior' => -600,  'indent' => 1, 'highlight' => false],
            ['type' => 'subtotal', 'label' => 'Lucro bruto',        'valor' => 8420,  'valor_anterior' => 7140,  'indent' => 0, 'highlight' => false],
            ['type' => 'h', 'label' => '(−) Despesas operacionais', 'valor' => -7042, 'valor_anterior' => -6680, 'indent' => 0, 'highlight' => false],
            ['type' => 'i', 'label' => 'Folha + encargos',          'valor' => -2800, 'valor_anterior' => -2800, 'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Aluguel + IPTU',            'valor' => -5390, 'valor_anterior' => -5390, 'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Energia / água / internet', 'valor' => -1500, 'valor_anterior' => -1480, 'indent' => 1, 'highlight' => false],
            ['type' => 'i', 'label' => 'Manutenção',                'valor' => -780,  'valor_anterior' => -260,  'indent' => 1, 'highlight' => false],
            ['type' => 'subtotal', 'label' => 'Resultado operacional', 'valor' => 1378, 'valor_anterior' => 460, 'indent' => 0, 'highlight' => true],
        ];

        return Inertia::render('Financeiro/DRE/Index', [
            'periodo' => 'Maio 2026',
            'periodo_anterior' => 'Abril 2026',
            'receita_liquida' => 13600,
            'resultado_operacional' => 1378,
            'margem_pct' => 10.1,
            'meta_margem_pct' => 12,
            'delta_pp' => 6.1,
            'granularidade' => $granularidade,
            'lines' => $lines,
            'top_categorias_receita' => [
                ['label' => 'Banner / Lona / Adesivo', 'valor' => 9580, 'pct' => 64],
                ['label' => 'Gráfica rápida',          'valor' => 2940, 'pct' => 20],
                ['label' => 'Fachada / Placa',         'valor' => 2340, 'pct' => 16],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $formato = $request->string('formato', 'pdf')->toString();
        // TODO[CL]: DREExportService->{toPdf|toExcel}(periodo, granularidade)
        abort(501, "Export {$formato} ainda não implementado.");
    }
}
