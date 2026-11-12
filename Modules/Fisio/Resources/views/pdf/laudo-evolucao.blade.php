{{--
    Laudo de evolução fisioterapêutica (Lote 2; protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/Laudo.dc.html).
    Síntese de uma página para quem encaminhou — não substitui o prontuário (pdf.prontuario).
    Conteúdo montado por LaudoEvolucao a partir de registros assinados; nada é escrito na hora.
    Base comum dos impressos (pdf/_base): cabeçalho da clínica, rodapé com o aviso de dado de saúde.
--}}
@extends('fisio::pdf._base', ['documento' => ['titulo' => 'Laudo de evolução fisioterapêutica']])

@section('aviso')
Documento gerado a partir do prontuário (Res. COFFITO 414/2012). Contém dados de saúde; compartilhar só com o destinatário indicado.
@endsection

@section('estilos')
<style>
    .texto { margin: 0; }
    .assinatura .data { margin-top: 6mm; font-size: 8pt; color: #58554f; }
</style>
@endsection

@section('conteudo')
<h1 class="titulo-doc">Laudo de evolução fisioterapêutica</h1>
<div class="subtitulo-doc">Síntese do acompanhamento a partir dos registros assinados no prontuário.</div>

<table class="ficha">
    <tr><th>Paciente</th><td>{{ $paciente ?: '—' }}</td></tr>
    <tr><th>Período</th><td>{{ $periodo ?? 'sem registro assinado' }}</td></tr>
    <tr><th>Registros</th><td>{{ $contagem['avaliacoes'] }} avaliação(ões) · {{ $contagem['evolucoes'] }} evolução(ões) · {{ $contagem['telemonitoramentos'] }} telemonitoramento(s)</td></tr>
    <tr><th>Destinatário</th><td>&nbsp;</td></tr>
</table>

<div class="secao">Diagnóstico fisioterapêutico</div>
<p class="texto">{{ $diagnostico ?? 'Não registrado na avaliação.' }}</p>

<div class="secao">Medidas</div>
@if($medidas)
    <table class="grade bloco">
        <tr><th>Questionário</th><th class="num">Primeira aplicação</th><th class="num">Mais recente</th></tr>
        @foreach($medidas as $m)
            <tr>
                <td>{{ $m['instrumento'] }}</td>
                <td class="num">{{ $m['primeira'] ?? '—' }} <span class="nota">({{ $m['primeira_em'] }})</span></td>
                <td class="num"><b>{{ $m['atual'] ?? '—' }}</b> <span class="nota">({{ $m['atual_em'] }})</span></td>
            </tr>
        @endforeach
    </table>
    <p class="nota" style="margin-top: 1.5mm">Pontuação como calculada no questionário; a leitura clínica é do profissional.</p>
@else
    <p class="nota">Nenhum questionário aplicado.</p>
@endif

<div class="secao">Conduta realizada</div>
<p class="texto">{{ $conduta ?? 'Não registrada na última evolução.' }}</p>
@if($exercicios)
    <p class="nota" style="margin-top: 1.5mm">Programa domiciliar ativo: {{ implode(' · ', $exercicios) }}</p>
@endif

<div class="secao">Objetivos e plano</div>
@if($objetivos)<p class="texto">{{ $objetivos }}</p>@endif
<p class="texto">{{ $plano ?? 'Plano não registrado na avaliação.' }}</p>

<div class="assinatura">
    <div class="linha">
        <div class="nome">{{ $emitido_por ?: 'Fisioterapeuta' }}</div>
        <div class="dados">Fisioterapeuta · {{ $crefito ?? 'CREFITO __________' }}</div>
    </div>
    <div class="data">Emitido em {{ $emitido_em }}</div>
</div>
@endsection
