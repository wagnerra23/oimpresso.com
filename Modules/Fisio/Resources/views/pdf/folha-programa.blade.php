{{--
    Folha para imprimir do programa do paciente (B-12; UC-FPS-37). dompdf, como o prontuário.
    Letra grande (corpo 14pt): boa parte de quem pede papel é idoso. Sem dado clínico além do
    programa — ver Services/FolhaPrograma.php. Sem QR: o link do portal não é guardado em claro.
    Cores: documento impresso fora do app — o dompdf não recebe o CSS nem os tokens do DS.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Meus exercícios — {{ $paciente['nome'] ?? 'Paciente' }}</title>
    <style>
        @page { margin: 14mm 14mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 14pt; color: #1c1917; line-height: 1.35; }
        h1 { font-size: 22pt; margin: 0 0 1mm; }
        .nome { font-size: 16pt; margin-bottom: 4mm; }
        .parada { border: 2px solid #1c1917; padding: 3mm 4mm; margin-bottom: 5mm; }
        .parada b { display: block; font-size: 15pt; margin-bottom: 1mm; }
        .exercicio { border: 1px solid #a8a29e; padding: 4mm; margin-bottom: 5mm; page-break-inside: avoid; }
        .exercicio h2 { font-size: 17pt; margin: 0 0 1mm; }
        .dose { font-size: 15pt; font-weight: bold; margin-bottom: 2mm; }
        .foto { float: right; width: 55mm; margin: 0 0 2mm 4mm; }
        ol, ul { margin: 1mm 0 0 6mm; padding: 0; }
        .rotulo { font-weight: bold; margin-top: 2mm; }
        .portal { font-size: 12pt; border-top: 1px dashed #a8a29e; padding-top: 3mm; margin-top: 4mm; }
        #rodape { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 9pt; color: #57534e; }
    </style>
</head>
<body>

<div id="rodape">{{ $empresa }} · impresso em {{ $emitido_em }}</div>

<h1>Meus exercícios</h1>
<div class="nome">{{ $paciente['nome'] ?? '' }}</div>

@if($parada)
    <div class="parada">
        <b>Quando parar</b>
        {{ $parada }}
        @if($crise)
            <div class="rotulo">Em dia de dor, faça só:</div>
            <ul>@foreach($crise as $c)<li>{{ $c }}</li>@endforeach</ul>
        @endif
    </div>
@endif

@forelse($itens as $n => $item)
    <div class="exercicio">
        @if(($item['midia']['tipo'] ?? null) === 'foto')
            <img class="foto" src="{{ $item['midia']['src'] }}" alt="">
        @endif
        <h2>{{ $n + 1 }}. {{ $item['nome'] }}</h2>
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

</body>
</html>
