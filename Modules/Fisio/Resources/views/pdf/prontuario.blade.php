{{--
    Prontuário do paciente em PDF (UC-FPS-19; Res. COFFITO 414/2012). Renderizado pelo dompdf
    (barryvdh/laravel-dompdf, mesmo padrão de financeiro::pdf.dre). Fonte DejaVu Sans: cobre
    acentos sem fonte externa. "Página X de Y" é desenhado pelo controller via page_text do
    canvas, porque o total de páginas só existe depois do layout.
    Cores: documento impresso fora do app — o dompdf não recebe o CSS nem os tokens do DS.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Prontuário — {{ $paciente['nome'] ?? 'Paciente' }}</title>
    <style>
        @page { margin: 16mm 14mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1c1917; }
        h1 { font-size: 13pt; margin: 0 0 1mm; font-weight: bold; }
        h2 { font-size: 10pt; margin: 6mm 0 2mm; font-weight: bold; border-bottom: 1px solid #e7e5e4; padding-bottom: 1mm; }
        .sub { font-size: 8pt; color: #57534e; margin-bottom: 4mm; }
        dl { margin: 0; }
        dt { font-size: 7.5pt; color: #57534e; margin-top: 1.5mm; }
        dd { margin: 0; }
        .registro { border: 1px solid #e7e5e4; padding: 3mm; margin-bottom: 3mm; page-break-inside: avoid; }
        .registro .cab { font-weight: bold; }
        .carimbo { font-size: 8pt; color: #57534e; margin-top: 2mm; border-top: 1px dashed #e7e5e4; padding-top: 1.5mm; }
        .aviso { font-size: 8pt; color: #57534e; }
        #rodape { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #57534e; }
    </style>
</head>
<body>

<div id="rodape">{{ $empresa }} · emitido em {{ $emitido_em }}</div>

<h1>Prontuário fisioterapêutico</h1>
<div class="sub">Documento gerado pelo sistema a partir dos registros assinados. Registro assinado não se altera; correções aparecem como adendo.</div>

<h2>Identificação do paciente</h2>
<dl>
    <dt>Nome</dt><dd>{{ $paciente['nome'] ?? '—' }}</dd>
    <dt>Telefone</dt><dd>{{ $paciente['telefone'] ?: '—' }}</dd>
    @if($paciente['bloqueado'])
        <dt>Situação</dt><dd>Bloqueado — prontuário guardado</dd>
    @endif
</dl>

<h2>Perfil clínico</h2>
<dl>
    <dt>Queixa principal</dt><dd>{{ $paciente['queixa_principal'] ?: '—' }}</dd>
    <dt>Objetivos</dt><dd>{{ $paciente['objetivos'] ? implode('; ', $paciente['objetivos']) : '—' }}</dd>
    <dt>Dias por semana</dt><dd>{{ $paciente['sessoes_por_semana'] }}</dd>
    <dt>Marcadores de condição</dt><dd>{{ $paciente['condicoes'] ? implode('; ', $paciente['condicoes']) : '—' }}</dd>
</dl>

<h2>Registros ({{ count($registros) }})</h2>
@forelse($registros as $r)
    <div class="registro">
        <div class="cab">
            {{ $r['tipo'] }} · registro nº {{ $r['id'] }}
            @if($r['corrige']) — corrige o registro nº {{ $r['corrige'] }} @endif
        </div>
        @if($r['modalidade'] || $r['canal'])
            <div class="aviso">Modalidade: {{ $r['modalidade'] ?? '—' }} · canal: {{ $r['canal'] ?? '—' }}</div>
        @endif
        <dl>
            @foreach($r['campos'] as $c)
                <dt>{{ $c['rotulo'] }}</dt><dd>{{ $c['valor'] }}</dd>
            @endforeach
        </dl>
        <div class="carimbo">
            Assinado por {{ $r['assinado_por'] ?: 'Usuário' }} · {{ $r['crefito'] ?? '—' }} · {{ $r['assinado_em'] }}
        </div>
    </div>
@empty
    <p class="aviso">Nenhum registro assinado no prontuário.</p>
@endforelse

</body>
</html>
