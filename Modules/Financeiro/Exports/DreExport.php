<?php

declare(strict_types=1);

namespace Modules\Financeiro\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * DreExport — exporta payload do DreService::montar() pro maatwebsite/excel.
 *
 * 6 colunas: Conta · {mes atual} · % RL · {mes anterior} · Δ% · Tipo
 *
 * Multi-tenant Tier 0 (ADR 0093): recebe `$shape` já filtrado pelo Service,
 * que aplica `where business_id` defensivo. Esta classe NUNCA toca DB.
 */
class DreExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    /**
     * @param  array{
     *   meta: array{periodo_label: string, periodo_label_prev: string, base_rl: float, business_name: string, anchor_mes: string},
     *   linhas: array,
     *   margem_operacional: array{atual_pct: float, meta_pct: float, prev_pct: float, delta_pp: float},
     *   top_categorias_receita: array<int, array{label: string, valor: float, pct: float}>,
     * } $shape
     */
    public function __construct(private array $shape)
    {
    }

    public function array(): array
    {
        $meta = $this->shape['meta'];
        $baseRL = (float) ($meta['base_rl'] ?? 0.0);
        $rows = [];

        foreach ($this->shape['linhas'] as $l) {
            $type = $l['type'] ?? '';
            $label = (string) ($l['label'] ?? '');
            $v = (float) ($l['v'] ?? 0.0);
            $prev = (float) ($l['prev'] ?? 0.0);
            $pct = $baseRL > 0.0 ? round(($v / $baseRL) * 100.0, 1) : 0.0;
            $delta = $prev != 0.0 ? round((($v - $prev) / abs($prev)) * 100.0, 1) : 0.0;

            $tipoLegivel = match ($type) {
                'h'        => 'Cabeçalho',
                'i'        => 'Item',
                'subtotal' => ! empty($l['highlight']) ? 'Resultado' : 'Subtotal',
                default    => '',
            };

            // Indent visual em items
            if ($type === 'i') {
                $indent = (int) ($l['indent'] ?? 1);
                $label = str_repeat('  ', max(1, $indent)).$label;
            }

            $rows[] = [
                $label,
                round($v, 2),
                $pct,
                round($prev, 2),
                $delta,
                $tipoLegivel,
            ];
        }

        // Margem operacional + Top categorias como linhas extras
        $rows[] = ['', '', '', '', '', ''];
        $mo = $this->shape['margem_operacional'];
        $rows[] = ['Margem operacional atual', $mo['atual_pct'], '', $mo['prev_pct'], $mo['delta_pp'], 'Métrica'];
        $rows[] = ['Margem operacional meta', $mo['meta_pct'], '', '', '', 'Métrica'];

        $rows[] = ['', '', '', '', '', ''];
        $rows[] = ['Top categorias de receita — '.$meta['periodo_label'], '', '', '', '', ''];
        foreach (($this->shape['top_categorias_receita'] ?? []) as $cat) {
            $rows[] = [
                $cat['label'],
                round((float) $cat['valor'], 2),
                $cat['pct'],
                '',
                '',
                'Top categoria',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        $meta = $this->shape['meta'];

        return [
            'Conta',
            $meta['periodo_label'] ?? '',
            '% RL',
            $meta['periodo_label_prev'] ?? '',
            'Δ%',
            'Tipo',
        ];
    }

    public function title(): string
    {
        return 'DRE '.($this->shape['meta']['anchor_mes'] ?? '');
    }
}
