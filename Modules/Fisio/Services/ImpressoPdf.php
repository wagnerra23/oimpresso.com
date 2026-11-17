<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Documento;

/**
 * Gera um impresso do Fisio sobre a base comum (pdf/_base.blade.php): A4 retrato, layout
 * renderizado e "Página X de Y" desenhado no canvas.
 *
 * Por que no canvas: o total de páginas só existe depois do layout, e o dompdf 3 resolve o
 * counter(pages) do CSS como 0 (medido em 2026-11-11 no staging). page_text substitui
 * {PAGE_NUM} e {PAGE_COUNT} em cada página no fim. A posição casa com a faixa do rodapé da
 * base: margem direita de 16 mm, segunda linha abaixo de "Emitido em".
 *
 * Os três controllers (prontuário, laudo de evolução, folha do programa) passam por aqui para
 * que o rodapé seja o mesmo nos três.
 */
class ImpressoPdf
{
    private const PT_POR_MM = 72 / 25.4;

    /** @param array<string, mixed> $dados */
    public function gerar(string $view, array $dados): Documento
    {
        $pdf = Pdf::loadView($view, $dados)->setPaper('a4', 'portrait');
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metricas = $dompdf->getFontMetrics();
        $fonte = $metricas->getFont('DejaVu Sans');
        $tamanho = 7.0;
        $largura = $metricas->getTextWidth('Página 8 de 8', $fonte, $tamanho);

        $canvas->page_text(
            $canvas->get_width() - 16 * self::PT_POR_MM - $largura,
            $canvas->get_height() - 11 * self::PT_POR_MM,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $fonte,
            $tamanho,
            [0.345, 0.333, 0.31],
        );

        return $pdf;
    }
}
