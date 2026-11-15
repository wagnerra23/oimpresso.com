{{--
    Relatório agregado para a associação (US-FISIO-062). Só contagem e média; grupo com menos de 5
    pessoas sai "<5". Montado por Services/Social/RelatorioAssociacao; nada de paciente aparece aqui.
    Mesmo padrão dos impressos atuais (dompdf, DejaVu Sans, cores fixas). Quando a base comum dos
    impressos (#10782) estiver no main, este arquivo passa a estendê-la.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório da associação</title>
    <style>
        @page { margin: 16mm 16mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1c1917; }
        .cab { border-bottom: 1px solid #e7e5e4; padding-bottom: 3mm; margin-bottom: 4mm; }
        .empresa { font-weight: bold; }
        h1 { font-size: 14pt; margin: 4mm 0 1mm; }
        h2 { font-size: 10.5pt; margin: 6mm 0 1.5mm; }
        h3 { font-size: 9pt; margin: 3mm 0 1mm; color: #44403c; }
        .dim { color: #57534e; font-size: 8pt; }
        .grande { font-size: 18pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 1.2mm 2mm 1.2mm 0; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        th { font-size: 8pt; color: #57534e; font-weight: normal; }
        td.n { text-align: right; width: 22mm; }
        .colunas td { border: 0; width: 50%; padding-right: 6mm; }
        #rodape { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #57534e; }
    </style>
</head>
<body>

<div id="rodape">Números somados, sem identificação de pacientes. Grupos com menos de {{ $r['minimo'] }} pessoas aparecem como "&lt;{{ $r['minimo'] }}" (LGPD).</div>

<div class="cab">
    <div class="empresa">{{ $empresa }}</div>
    <h1>Relatório de atendimento da associação</h1>
    <div class="dim">Período: {{ \Carbon\Carbon::parse($r['periodo']['de'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($r['periodo']['ate'])->format('d/m/Y') }} · emitido em {{ $emitido_em }}</div>
</div>

<table>
    <tr><th>Pessoas atendidas</th><td class="grande">{{ $r['atendidos'] }}</td></tr>
    @if (! $r['suprimido'])
        <tr><th>Atendimentos com presença</th><td>{{ $r['presenca']['presentes'] }}</td></tr>
        <tr><th>Presença</th><td>{{ $r['presenca']['taxa'] !== null ? $r['presenca']['taxa'].'%' : '—' }} ({{ $r['presenca']['faltas'] }} falta(s) em {{ $r['presenca']['agendados'] }} agendado(s))</td></tr>
        <tr><th>Adesão aos exercícios em casa</th><td>{{ $r['adesao']['media'] !== null ? $r['adesao']['media'].'%' : '—' }} (média de {{ $r['adesao']['n'] }} pessoa(s) com programa)</td></tr>
    @endif
</table>

@if ($r['suprimido'])
    <p class="dim">Com menos de {{ $r['minimo'] }} pessoas atendidas no período, o perfil e os resultados não são mostrados para não identificar ninguém.</p>
@else
    <h2>Perfil das pessoas atendidas</h2>
    @php
        $blocos = ['faixa_etaria' => 'Faixa etária', 'sexo' => 'Sexo', 'municipio' => 'Município', 'renda_familiar' => 'Renda familiar por mês', 'situacao_trabalho' => 'Situação de trabalho'];
    @endphp
    <table class="colunas"><tr>
    @foreach (array_chunk(array_keys($blocos), 3) as $coluna)
        <td>
        @foreach ($coluna as $chave)
            <h3>{{ $blocos[$chave] }}</h3>
            <table>
                @foreach ($r['perfil'][$chave] as $linha)
                    <tr><td>{{ $linha['rotulo'] }}</td><td class="n">{{ $linha['n'] }}</td></tr>
                @endforeach
            </table>
        @endforeach
        </td>
    @endforeach
    </tr></table>

    <h2>Resultados: primeira e última aplicação</h2>
    <table>
        <tr><th>Questionário ou teste</th><th>Pessoas</th><th>Média na 1ª</th><th>Média na última</th><th>Diferença</th></tr>
        @foreach ($r['evolucao'] as $e)
            <tr>
                <td>{{ $e['rotulo'] }}<br><span class="dim">melhora = número {{ $e['melhora'] }}</span></td>
                <td>{{ $e['n'] }}</td>
                <td>{{ $e['primeira'] ?? '—' }}</td>
                <td>{{ $e['ultima'] ?? '—' }}</td>
                <td>{{ $e['diferenca'] !== null ? ($e['diferenca'] > 0 ? '+' : '').$e['diferenca'] : '—' }}</td>
            </tr>
        @endforeach
    </table>
    <p class="dim">Só entra quem respondeu ao menos duas vezes até o fim do período. Média e diferença só aparecem com {{ $r['minimo'] }} pessoas ou mais.</p>
@endif

</body>
</html>
