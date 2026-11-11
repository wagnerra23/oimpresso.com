{{--
    Folha para imprimir do programa do paciente (B-12; UC-FPS-37). Base comum dos impressos
    (pdf/_base): cabeçalho com a clínica e o telefone, que é o que o paciente precisa no papel.
    Letra grande (corpo 14pt): boa parte de quem pede papel é idoso. Sem dado clínico além do
    programa — ver Services/FolhaPrograma.php (UC-FPS-38). Sem QR: o link do portal não é
    guardado em claro.
--}}
@extends('fisio::pdf._base', ['documento' => [
    'titulo' => 'Programa de exercícios',
    'numero' => 'Programa nº '.($programa_id ?? '—'),
]])

@section('estilos')
<style>
    body { font-size: 14pt; line-height: 1.35; }
    .titulo-doc { font-size: 22pt; }
    .paciente { font-size: 16pt; margin-bottom: 5mm; }
    .parada { border: 1.5pt solid #1d1a15; padding: 3mm 4mm; margin-bottom: 6mm; page-break-inside: avoid; }
    .parada .cab { font-size: 15pt; font-weight: bold; margin-bottom: 1mm; }
    .exercicio { border: 0.75pt solid #c9c7c2; margin-bottom: 5mm; page-break-inside: avoid; }
    .exercicio .topo { background: #f4f3f0; padding: 2.5mm 4mm; border-bottom: 0.75pt solid #c9c7c2; }
    .exercicio .topo table { width: 100%; border-collapse: collapse; }
    .exercicio .topo td { padding: 0; vertical-align: middle; }
    .exercicio .n { width: 11mm; font-size: 17pt; font-weight: bold; color: #5e3d9e; }
    .exercicio .nome { font-size: 17pt; font-weight: bold; line-height: 1.2; }
    .exercicio .corpo { padding: 3mm 4mm 3.5mm; }
    .dose { font-size: 15pt; font-weight: bold; margin-bottom: 2mm; }
    .foto { float: right; width: 55mm; margin: 0 0 2mm 4mm; }
    ol, ul { margin: 1mm 0 0 7mm; padding: 0; }
    li { margin-bottom: 0.8mm; }
    .rotulo { font-weight: bold; margin-top: 2.5mm; }
    .portal { font-size: 12pt; border-top: 0.75pt dashed #c9c7c2; padding-top: 3mm; margin-top: 4mm; }
</style>
@endsection

@section('conteudo')
<h1 class="titulo-doc">Meus exercícios</h1>
<div class="paciente">{{ $paciente['nome'] ?? '' }}</div>

@if($parada)
    <div class="parada">
        <div class="cab">Quando parar</div>
        {{ $parada }}
        @if($crise)
            <div class="rotulo">Em dia de dor, faça só:</div>
            <ul>@foreach($crise as $c)<li>{{ $c }}</li>@endforeach</ul>
        @endif
    </div>
@endif

@forelse($itens as $n => $item)
    <div class="exercicio">
        <div class="topo">
            <table><tr>
                <td class="n">{{ $n + 1 }}.</td>
                <td class="nome">{{ $item['nome'] }}</td>
            </tr></table>
        </div>
        <div class="corpo">
            @if(($item['midia']['tipo'] ?? null) === 'foto')
                <img class="foto" src="{{ $item['midia']['src'] }}" alt="">
            @endif
            <div class="dose">{{ $item['dose'] }}</div>
            @if($item['passos'])
                <ol>@foreach($item['passos'] as $p)<li>{{ $p }}</li>@endforeach</ol>
            @endif
            @if($item['observacao'])
                <div class="rotulo">Observação</div>
                <div>{{ $item['observacao'] }}</div>
            @endif
            @if($item['cuidados'])
                <div class="rotulo">Cuidado</div>
                <ul>@foreach($item['cuidados'] as $c)<li>{{ $c }}</li>@endforeach</ul>
            @endif
            @if(($item['midia']['tipo'] ?? null) === 'video')
                <div class="rotulo">Este exercício tem vídeo no portal.</div>
            @endif
            <div style="clear: both"></div>
        </div>
    </div>
@empty
    <p>O programa ainda não tem exercícios.</p>
@endforelse

<div class="portal">
    @if($portal_ativo)
        Para marcar o que fez e ver os vídeos, use no celular o link e o PIN que a clínica enviou.
    @else
        Para acompanhar pelo celular, peça à clínica o link e o PIN do portal.
    @endif
</div>
@endsection
