<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DRE — {{ $meta['periodo_label'] ?? '' }} — {{ $meta['business_name'] ?? '' }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1c1917; }
        h1 { font-size: 12pt; margin: 0 0 2mm; font-weight: 600; }
        .sub { font-size: 8pt; color: #78716c; margin-bottom: 5mm; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.06em; color: #78716c; font-weight: 500; text-align: left; padding: 3mm 1mm; border-bottom: 1px solid #e7e5e4; background: #fafaf9; }
        td { padding: 1.5mm 1mm; font-size: 9pt; vertical-align: top; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr.h td { font-weight: 600; color: #1c1917; border-bottom: 1px solid #f5f5f4; }
        tr.i td { color: #57534e; border-bottom: 1px solid #fafaf9; }
        tr.subtotal td { font-weight: 600; background: #fafaf9; border-top: 1.5px solid #e7e5e4; border-bottom: 1.5px solid #e7e5e4; }
        tr.highlight td { background: #1c1917; color: #fff !important; font-weight: 700; }
        tr.highlight td.num { color: #fff; }
        .pos { color: #047857; }
        .neg { color: #be123c; }
        .meta-cards { margin-top: 6mm; }
        .meta-cards table { width: 100%; }
        .meta-cards td { padding: 2mm; border: 1px solid #e7e5e4; }
        .meta-cards .label { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.06em; color: #78716c; }
        .meta-cards .big { font-size: 18pt; font-weight: 600; margin-top: 1mm; }
    </style>
</head>
<body>

<h1>Demonstração de Resultado · {{ $meta['periodo_label'] ?? '' }}</h1>
<div class="sub">
    {{ $meta['business_name'] ?? '' }}
    @if(!empty($meta['aviso_sem_mapping']))
        · <strong style="color:#b45309">Aviso: plano de contas não está mapeado por código hierárquico — DRE detalhada indisponível.</strong>
    @endif
</div>

@php
    $baseRL = (float) ($meta['base_rl'] ?? 0.0);
    $fmt = function(float $v): string {
        return number_format($v, 2, ',', '.');
    };
    $pctFn = function(float $v) use ($baseRL): string {
        if ($baseRL <= 0) return '0,0%';
        return number_format(($v / $baseRL) * 100.0, 1, ',', '.').'%';
    };
    $deltaFn = function(float $v, float $prev): string {
        if ($prev == 0.0) return '—';
        $d = (($v - $prev) / abs($prev)) * 100.0;
        $sign = $d > 0 ? '+' : '';
        return $sign . number_format($d, 0, ',', '.').'%';
    };
@endphp

<table>
    <thead>
        <tr>
            <th style="width: 45%">Conta</th>
            <th class="num" style="width: 15%">{{ $meta['periodo_label'] ?? '' }}</th>
            <th class="num" style="width: 10%">% RL</th>
            <th class="num" style="width: 15%">{{ $meta['periodo_label_prev'] ?? '' }}</th>
            <th class="num" style="width: 15%">Δ%</th>
        </tr>
    </thead>
    <tbody>
        @foreach($linhas as $l)
            @php
                $type = $l['type'] ?? '';
                $v = (float) ($l['v'] ?? 0.0);
                $prev = (float) ($l['prev'] ?? 0.0);
                $highlight = !empty($l['highlight']);
                $indent = (int) ($l['indent'] ?? 0);
                $deltaVal = $prev != 0.0 ? (($v - $prev) / abs($prev)) * 100.0 : 0.0;
                $deltaClass = $deltaVal > 0 ? 'pos' : ($deltaVal < 0 ? 'neg' : '');
                $vClass = $v >= 0 ? 'pos' : 'neg';
                $rowClass = $type . ($highlight ? ' highlight' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td style="padding-left: {{ ($indent * 5) + 1 }}mm">{{ $l['label'] ?? '' }}</td>
                <td class="num @if($type === 'subtotal' && !$highlight) {{ $vClass }} @endif">{{ $fmt($v) }}</td>
                <td class="num">{{ $pctFn($v) }}</td>
                <td class="num">{{ $fmt($prev) }}</td>
                <td class="num @if(!$highlight) {{ $deltaClass }} @endif">{{ $deltaFn($v, $prev) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="meta-cards">
    <table>
        <tr>
            <td style="width: 50%">
                <div class="label">Margem operacional</div>
                <div class="big">{{ number_format($margem_operacional['atual_pct'], 1, ',', '.') }}%</div>
                <div style="font-size: 8pt; color: #78716c; margin-top: 1mm">
                    vs {{ number_format($margem_operacional['prev_pct'], 1, ',', '.') }}% em {{ $meta['periodo_label_prev'] ?? '' }} ·
                    <span class="{{ $margem_operacional['delta_pp'] >= 0 ? 'pos' : 'neg' }}">
                        {{ $margem_operacional['delta_pp'] >= 0 ? '+' : '' }}{{ number_format($margem_operacional['delta_pp'], 1, ',', '.') }}pp
                    </span>
                    · meta {{ number_format($margem_operacional['meta_pct'], 0, ',', '.') }}%
                </div>
            </td>
            <td style="width: 50%">
                <div class="label">Top categorias receita · {{ $meta['periodo_label'] ?? '' }}</div>
                <table style="margin-top: 1.5mm; border: 0">
                    @foreach(($top_categorias_receita ?? []) as $cat)
                        <tr>
                            <td style="border: 0; padding: 0.8mm 0; font-size: 8.5pt; color: #57534e">{{ $cat['label'] }}</td>
                            <td style="border: 0; padding: 0.8mm 0; font-size: 8.5pt; text-align: right; font-variant-numeric: tabular-nums">
                                R$ {{ $fmt((float) $cat['valor']) }} <span style="color: #a8a29e">· {{ number_format((float) $cat['pct'], 1, ',', '.') }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
