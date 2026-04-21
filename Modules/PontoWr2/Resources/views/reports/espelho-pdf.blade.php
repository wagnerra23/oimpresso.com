<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Espelho de Ponto — {{ optional($colaborador->user)->first_name }} {{ optional($colaborador->user)->last_name }} — {{ $mes }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        h2 { font-size: 11px; margin: 12px 0 4px 0; border-bottom: 1px solid #aaa; padding-bottom: 2px; }
        .header-block { margin-bottom: 10px; }
        .header-block table { width: 100%; border-collapse: collapse; }
        .header-block td { padding: 2px 4px; vertical-align: top; }
        .header-block .label { font-weight: bold; color: #555; width: 90px; }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.dados th, table.dados td { border: 1px solid #bbb; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.dados th { background: #eee; font-size: 9px; }
        table.dados td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .divergencia { background: #fff3cd; }
        .chip { display: inline-block; padding: 1px 4px; border: 1px solid #888; border-radius: 3px; font-size: 8px; margin-right: 2px; background: #f5f5f5; }
        .totais th, .totais td { padding: 4px 6px; }
        .totais { margin-top: 8px; width: 100%; border-collapse: collapse; }
        .totais tr td:first-child { font-weight: bold; width: 40%; }
        .totais tr td.valor { font-variant-numeric: tabular-nums; text-align: right; }
        .assinaturas { margin-top: 30px; width: 100%; }
        .assinaturas td { width: 50%; text-align: center; padding-top: 40px; border-top: 1px solid #333; }
        .rodape { font-size: 8px; color: #777; margin-top: 10px; text-align: center; }
    </style>
</head>
<body>
@php
    $fmtMin = function ($min) {
        $min = (int) $min;
        $sinal = $min < 0 ? '-' : '';
        $abs = abs($min);
        return $sinal . sprintf('%02d:%02d', intdiv($abs, 60), $abs % 60);
    };
    $diasSemanaPt = [0=>'Dom', 1=>'Seg', 2=>'Ter', 3=>'Qua', 4=>'Qui', 5=>'Sex', 6=>'Sáb'];
    $nomeColab = trim(optional($colaborador->user)->first_name . ' ' . optional($colaborador->user)->last_name);
    if ($nomeColab === '') { $nomeColab = 'Colaborador #' . $colaborador->id; }
@endphp

<h1>Espelho de Ponto Eletrônico</h1>
<div class="header-block">
    <table>
        <tr>
            <td class="label">Colaborador:</td>
            <td>{{ $nomeColab }}</td>
            <td class="label">Matrícula:</td>
            <td>{{ $colaborador->matricula ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">CPF:</td>
            <td>{{ $colaborador->cpf ?: '—' }}</td>
            <td class="label">PIS:</td>
            <td>{{ $colaborador->pis ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Escala:</td>
            <td>{{ optional($colaborador->escalaAtual)->nome ?: '—' }}</td>
            <td class="label">Competência:</td>
            <td>{{ $mes }} ({{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }})</td>
        </tr>
    </table>
</div>

<h2>Apuração diária</h2>
<table class="dados">
    <thead>
    <tr>
        <th>Data</th>
        <th>Dia</th>
        <th>Prev. Entrada</th>
        <th>Prev. Saída</th>
        <th>Real. Entrada</th>
        <th>Real. Saída</th>
        <th>Marcações</th>
        <th class="num">Trab.</th>
        <th class="num">Atraso</th>
        <th class="num">Falta</th>
        <th class="num">HE Diu.</th>
        <th class="num">HE Not.</th>
        <th class="num">BH +</th>
        <th class="num">BH −</th>
        <th>Estado</th>
    </tr>
    </thead>
    <tbody>
    @php $cursor = $inicio->copy(); @endphp
    @while ($cursor <= $fim)
        @php
            $diaStr = $cursor->toDateString();
            $ap = $apuracoes->firstWhere(function ($a) use ($diaStr) {
                return optional($a->data)->toDateString() === $diaStr;
            });
            $marcasDia = isset($marcacoes[$diaStr]) ? $marcacoes[$diaStr] : collect();
            $cls = ($ap && $ap->estado === \Modules\PontoWr2\Entities\ApuracaoDia::ESTADO_DIVERGENCIA) ? 'divergencia' : '';
        @endphp
        <tr class="{{ $cls }}">
            <td>{{ $cursor->format('d/m') }}</td>
            <td>{{ $diasSemanaPt[(int) $cursor->format('w')] }}</td>
            <td>{{ $ap && $ap->prevista_entrada ? substr($ap->prevista_entrada, 0, 5) : '—' }}</td>
            <td>{{ $ap && $ap->prevista_saida   ? substr($ap->prevista_saida,   0, 5) : '—' }}</td>
            <td>{{ $ap && $ap->realizada_entrada ? substr($ap->realizada_entrada, 0, 5) : '—' }}</td>
            <td>{{ $ap && $ap->realizada_saida   ? substr($ap->realizada_saida,   0, 5) : '—' }}</td>
            <td>
                @foreach ($marcasDia as $m)
                    <span class="chip">{{ $m->momento->format('H:i') }}</span>
                @endforeach
                @if ($marcasDia->isEmpty())—@endif
            </td>
            <td class="num">{{ $ap ? $fmtMin($ap->realizada_trabalhada_minutos) : '—' }}</td>
            <td class="num">{{ $ap && $ap->atraso_minutos ? $fmtMin($ap->atraso_minutos) : '—' }}</td>
            <td class="num">{{ $ap && $ap->falta_minutos ? $fmtMin($ap->falta_minutos) : '—' }}</td>
            <td class="num">{{ $ap && $ap->he_diurna_minutos ? $fmtMin($ap->he_diurna_minutos) : '—' }}</td>
            <td class="num">{{ $ap && $ap->he_noturna_minutos ? $fmtMin($ap->he_noturna_minutos) : '—' }}</td>
            <td class="num">{{ $ap && $ap->banco_horas_credito_minutos ? $fmtMin($ap->banco_horas_credito_minutos) : '—' }}</td>
            <td class="num">{{ $ap && $ap->banco_horas_debito_minutos ? $fmtMin($ap->banco_horas_debito_minutos) : '—' }}</td>
            <td>{{ $ap ? $ap->estado : '—' }}</td>
        </tr>
        @php $cursor->addDay(); @endphp
    @endwhile
    </tbody>
</table>

<h2>Totais do mês</h2>
<table class="totais">
    <tr>
        <td>Trabalhado</td><td class="valor">{{ $fmtMin($totais['trabalhado']) }}</td>
        <td>Atrasos</td><td class="valor">{{ $fmtMin($totais['atraso']) }}</td>
    </tr>
    <tr>
        <td>Faltas</td><td class="valor">{{ $fmtMin($totais['falta']) }}</td>
        <td>Adicional Noturno</td><td class="valor">{{ $fmtMin($totais['adicional_not']) }}</td>
    </tr>
    <tr>
        <td>HE Diurna</td><td class="valor">{{ $fmtMin($totais['he_diurna']) }}</td>
        <td>HE Noturna</td><td class="valor">{{ $fmtMin($totais['he_noturna']) }}</td>
    </tr>
    <tr>
        <td>Banco de Horas — Crédito</td><td class="valor">{{ $fmtMin($totais['bh_credito']) }}</td>
        <td>Banco de Horas — Débito</td><td class="valor">{{ $fmtMin($totais['bh_debito']) }}</td>
    </tr>
    <tr>
        <td>DSR (repercussão sobre HE)</td><td class="valor">{{ $fmtMin($totais['dsr_repercussao']) }}</td>
        <td></td><td></td>
    </tr>
</table>

<table class="assinaturas">
    <tr>
        <td>Colaborador</td>
        <td>Responsável RH</td>
    </tr>
</table>

<div class="rodape">
    Gerado em {{ $gerado_em->format('d/m/Y H:i') }} — Portaria MTP 671/2021 Art. 85.
    Dados imutáveis em marcações (append-only) — qualquer divergência destaca em amarelo.
</div>
</body>
</html>
