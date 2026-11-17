{{--
    Prontuário do paciente em PDF (UC-FPS-19; Res. COFFITO 414/2012). Renderizado pelo dompdf
    sobre a base comum dos impressos (pdf/_base): cabeçalho da clínica, rodapé com empresa,
    emissão e "Página X de Y" em toda página. Cada registro é um bloco que não quebra no meio,
    fechado pela assinatura de quem assinou (nome, CREFITO, data e hora). O documento só lê:
    registro assinado não se altera, correção aparece como adendo.
--}}
@extends('fisio::pdf._base', ['documento' => [
    'titulo' => 'Prontuário fisioterapêutico',
    'numero' => 'Paciente nº '.($paciente['id'] ?? '—'),
]])

@section('estilos')
<style>
    .registro { border: 0.5pt solid #dfdedb; margin-bottom: 4mm; page-break-inside: avoid; }
    .registro .topo { background: #f4f3f0; padding: 1.8mm 3mm; border-bottom: 0.5pt solid #dfdedb; }
    .registro .topo table { width: 100%; border-collapse: collapse; }
    .registro .topo td { padding: 0; vertical-align: middle; }
    .registro .tipo { font-weight: bold; font-size: 9.5pt; }
    .registro .ref { font-size: 7.5pt; color: #58554f; text-align: right; }
    .registro .adendo { color: #5e3d9e; }
    .registro .corpo { padding: 2mm 3mm 0; }
    .registro .corpo table.ficha th { width: 42mm; }
    .registro .corpo table.ficha tr:last-child { border-bottom: none; }
    .registro .assinado { margin: 2mm 3mm 2.5mm; padding-top: 1.5mm; border-top: 0.5pt dashed #c9c7c2; font-size: 7.5pt; color: #58554f; }
    .registro .assinado b { color: #1d1a15; }
</style>
@endsection

@section('conteudo')
<h1 class="titulo-doc">Prontuário fisioterapêutico</h1>
<div class="subtitulo-doc">Documento gerado pelo sistema a partir dos registros assinados. Registro assinado não se altera; correções aparecem como adendo.</div>

<div class="secao">Identificação do paciente</div>
<table class="ficha">
    <tr><th>Nome</th><td>{{ $paciente['nome'] ?? '—' }}</td></tr>
    <tr><th>Telefone</th><td>{{ $paciente['telefone'] ?: '—' }}</td></tr>
    @if($paciente['bloqueado'])
        <tr><th>Situação</th><td>Bloqueado — prontuário guardado</td></tr>
    @endif
</table>

<div class="secao">Perfil clínico</div>
<table class="ficha">
    <tr><th>Queixa principal</th><td>{{ $paciente['queixa_principal'] ?: '—' }}</td></tr>
    <tr><th>Objetivos</th><td>{{ $paciente['objetivos'] ? implode('; ', $paciente['objetivos']) : '—' }}</td></tr>
    <tr><th>Dias por semana</th><td>{{ $paciente['sessoes_por_semana'] }}</td></tr>
    <tr><th>Marcadores de condição</th><td>{{ $paciente['condicoes'] ? implode('; ', $paciente['condicoes']) : '—' }}</td></tr>
</table>

<div class="secao">Registros ({{ count($registros) }})</div>
@forelse($registros as $r)
    <div class="registro">
        <div class="topo">
            <table><tr>
                <td class="tipo">{{ $r['tipo'] }}@if($r['corrige']) <span class="adendo">— corrige o registro nº {{ $r['corrige'] }}</span>@endif</td>
                <td class="ref">Registro nº {{ $r['id'] }}@if($r['modalidade'] || $r['canal'])<br>Modalidade: {{ $r['modalidade'] ?? '—' }} · canal: {{ $r['canal'] ?? '—' }}@endif</td>
            </tr></table>
        </div>
        <div class="corpo">
            <table class="ficha">
                @foreach($r['campos'] as $c)
                    <tr><th>{{ $c['rotulo'] }}</th><td>{{ $c['valor'] }}</td></tr>
                @endforeach
            </table>
        </div>
        <div class="assinado">
            Assinado por <b>{{ $r['assinado_por'] ?: 'Usuário' }}</b> · {{ $r['crefito'] ?? 'CREFITO não informado' }} · em {{ $r['assinado_em'] }}
        </div>
    </div>
@empty
    <p class="nota">Nenhum registro assinado no prontuário.</p>
@endforelse
@endsection
