<?php

declare(strict_types=1);

namespace App\Services\Purchase;

/**
 * US-COM-005 — monta o layout da grade tam×cor a partir das variações reais de um produto.
 *
 * UltimatePOS guarda variação em 1 eixo (variation.name = 1 valor). Este builder
 * AUTO-DETECTA 2D quando os nomes são compostos e parseáveis (ex "P/Preto", "P-Preto")
 * e cai pra grade de 1 eixo (linhas = variações, 1 coluna "Qtd") quando não dá.
 * Lógica pura (sem DB/auth) — testável isolado. O scope multi-tenant Tier 0 fica no
 * controller (PurchaseController::gradeMatrix resolve o produto por business_id).
 *
 * A resposta casa 1:1 com GradeMatrixInputProps no frontend:
 *  - rows/cols = [{id, label}]
 *  - cellVariationMap keyed `${rowId}__${colId}` (igual ao cellKey do componente)
 */
class GradeLayoutBuilder
{
    /** Delimitadores priorizados; hífen por último (raro como separador tam×cor). */
    private const DELIMITERS = ['/', '|', '·', '×', ' x ', '-'];

    /**
     * @param  string  $productType  'variable' | 'single' | ...
     * @param  array<int, array{id:int, name:string}>  $variations
     * @return array{mode:string, rows:array, cols:array, cellVariationMap:array}
     */
    public function build(string $productType, array $variations): array
    {
        // Produto simples ou 1 variação → input único (sem matriz).
        if ($productType !== 'variable' || count($variations) <= 1) {
            $first = $variations[0] ?? null;

            return [
                'mode' => 'single',
                'rows' => [],
                'cols' => [],
                'cellVariationMap' => $first ? ['single__qtd' => (int) $first['id']] : [],
            ];
        }

        // Tenta 2D parseando nome composto.
        $twoD = $this->tryTwoAxis($variations);
        if ($twoD !== null) {
            return array_merge(['mode' => '2d'], $twoD);
        }

        // Fallback 1 eixo: linhas = variações reais, 1 coluna "Qtd". Sempre funciona.
        $rows = [];
        $map = [];
        foreach ($variations as $v) {
            $rows[] = ['id' => (int) $v['id'], 'label' => (string) $v['name']];
            $map[$v['id'].'__qtd'] = (int) $v['id'];
        }

        return [
            'mode' => 'matrix-1d',
            'rows' => $rows,
            'cols' => [['id' => 'qtd', 'label' => 'Qtd']],
            'cellVariationMap' => $map,
        ];
    }

    /**
     * Monta o layout 2D parseando os nomes. Retorna null se NÃO der pra montar grade
     * 2D limpa (variação sem delimitador / não-2-partes, combinação duplicada, ou 1×1).
     *
     * @param  array<int, array{id:int, name:string}>  $variations
     * @return array{rows:array, cols:array, cellVariationMap:array}|null
     */
    private function tryTwoAxis(array $variations): ?array
    {
        $rowsOrder = [];   // label => true (preserva ordem de 1ª aparição)
        $colsOrder = [];
        $map = [];         // "row__col" => variation_id

        foreach ($variations as $v) {
            $name = trim((string) $v['name']);
            $parts = null;

            foreach (self::DELIMITERS as $d) {
                if (mb_strpos($name, $d) !== false) {
                    $candidate = array_values(array_filter(
                        array_map('trim', explode($d, $name)),
                        fn ($p) => $p !== ''
                    ));
                    if (count($candidate) === 2) {
                        $parts = $candidate;
                        break;
                    }

                    // Achou delimitador mas não deu 2 partes limpas → não é grade 2D.
                    return null;
                }
            }

            if ($parts === null) {
                return null; // variação sem delimitador → não dá 2D
            }

            [$row, $col] = $parts;
            $key = $row.'__'.$col;
            if (isset($map[$key])) {
                return null; // combinação duplicada → ambíguo
            }

            $rowsOrder[$row] = true;
            $colsOrder[$col] = true;
            $map[$key] = (int) $v['id'];
        }

        // 1×1 não é matriz (cai pro 1 eixo).
        if (count($rowsOrder) === 1 && count($colsOrder) === 1) {
            return null;
        }

        return [
            'rows' => array_map(fn ($label) => ['id' => $label, 'label' => $label], array_keys($rowsOrder)),
            'cols' => array_map(fn ($label) => ['id' => $label, 'label' => $label], array_keys($colsOrder)),
            'cellVariationMap' => $map,
        ];
    }
}
