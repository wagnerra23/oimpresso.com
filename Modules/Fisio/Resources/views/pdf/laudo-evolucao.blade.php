{{--
    Laudo de evolução fisioterapêutica (Lote 2; protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/Laudo.dc.html).
    Síntese de uma página para quem encaminhou — não substitui o prontuário (pdf.prontuario).
    Conteúdo montado por LaudoEvolucao a partir de registros assinados; nada é escrito na hora.
    Mesmo padrão do prontuário: dompdf, DejaVu Sans, cores fixas (o dompdf não recebe os tokens do DS).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Laudo de evolução</title>
    <style>
        @page { margin: 16mm 16mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1c1917; }
        .cab { border-bottom: 1px solid #e7e5e4; padding-bottom: 3mm; margin-bottom: 4mm; }
        .empresa { font-weight: bold; }
        h1 { font-size: 14pt; margin: 4mm 0 1mm; }
        h2 { font-size: 10pt; margin: 5mm 0 1.5mm; }
        .dim { color: #57534e; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 1.2mm 2mm 1.2mm 0; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        th { font-size: 8pt; color: #57534e; font-weight: normal; }
        .assinatura { margin-top: 14mm; width: 70mm; border-top: 1px solid #1c1917; padding-top: 1.5mm; }
        #rodape { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #57534e; }
    </style>
</head>
<body>

<div id="rodape">Documento gerado a partir do prontuário (Res. COFFITO 414/2012). Contém dados de saúde; compartilhar só com o destinatário indicado.</div>

<div class="cab">
    <div class="empresa">{{ $empresa }}</div>
    <h1>Laudo de evolução fisioterapêutica</h1>
    <div class="dim">Emitido em {{ $emitido_em }}</div>
</div>

<table>
    <tr><th>Paciente</th><td>{{ $paciente ?: '—' }}</td></tr>
    <tr><th>Período</th><td>{{ $periodo ?? 'sem registro assinado' }}</td></tr>
    <tr><th>Registros</th><td>{{ $contagem['avaliacoes'] }} avaliação(ões) · {{ $contagem['evolucoes'] }} evolução(ões) · {{ $contagem['telemonitoramentos'] }} telemonitoramento(s)</td></tr>
    <tr><th>Destinatário</th><td>&nbsp;</td></tr>
</table>

<h2>Diagnóstico fisioterapêutico</h2>
<div>{{ $diagnostico ?? 'Não registrado na avaliação.' }}</div>

<h2>Medidas</h2>
@if($medidas)
    <table>
        <tr><th>Questionário</th><th>Primeira aplicação</th><th>Mais recente</th></tr>
        @foreach($medidas as $m)
            <tr><td>{{ $m['instrumento'] }}</td><td>{{ $m['primeira'] ?? '—' }} ({{ $m['primeira_em'] }})</td><td>{{ $m['atual'] ?? '—' }} ({{ $m['atual_em'] }})</td></tr>
        @endforeach
    </table>
    <div class="dim">Pontuação como calculada no questionário; a leitura clínica é do profissional.</div>
@else
    <div class="dim">Nenhum questionário aplicado.</div>
@endif

<h2>Conduta realizada</h2>
<div>{{ $conduta ?? 'Não registrada na última evolução.' }}</div>
@if($exercicios)
    <div class="dim" style="margin-top: 1.5mm">Programa domiciliar ativo: {{ implode(' · ', $exercicios) }}</div>
@endif

<h2>Objetivos e plano</h2>
@if($objetivos)<div>{{ $objetivos }}</div>@endif
<div>{{ $plano ?? 'Plano não registrado na avaliação.' }}</div>

<div class="assinatura">
    {{ $emitido_por ?: 'Fisioterapeuta' }}<br>
    <span class="dim">Fisioterapeuta · CREFITO {{ $crefito ?? '__________' }}</span>
</div>

</body>
</html>
