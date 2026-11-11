{{--
    Laudo de avaliação biopsicossocial para fibromialgia (US-FISIO-060), no layout do modelo aprovado
    pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026), versão 1.0
    de 26/10/2026. Montado por LaudoBiopsicossocialDocumento. Campo vazio sai em branco para escrever
    à mão; rascunho sai marcado como documento de apoio. O total da matriz é calculado; a conclusão é
    só o que a equipe marcou. dompdf, DejaVu Sans, cores fixas.
--}}
@php
    $marca = fn (bool $v) => $v ? '☒' : '☐';
    $sn = fn ($v, bool $sim) => $marca($v === $sim);
    $natureza = (array) ($identificacao['natureza'] ?? []);
    $dataBr = fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Laudo de avaliação biopsicossocial</title>
    <style>
        @page { margin: 14mm 14mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1c1917; }
        h1 { font-size: 11pt; text-align: center; margin: 0 0 1mm; }
        .versao { text-align: center; font-size: 8.5pt; margin-bottom: 3mm; }
        .barra { background: #12402f; color: #fff; font-weight: bold; padding: 1mm 2mm; margin-top: 3mm; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #c8ccd0; padding: 1mm 1.5mm; vertical-align: top; text-align: left; }
        th.col, td.col { width: 13mm; text-align: center; }
        .dominio th { background: #12402f; color: #fff; }
        .texto { white-space: pre-line; min-height: 9mm; }
        .apoio { border: 1px solid #a16207; color: #713f12; padding: 1.5mm 2mm; margin-bottom: 2mm; }
        .assin { height: 11mm; }
        .dim { color: #57534e; font-size: 7.5pt; }
        #rodape { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #57534e; }
    </style>
</head>
<body>

<div id="rodape">Modelo aprovado pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026). A emissão do laudo cabe à Atenção Primária do município e a da carteira segue o fluxo da FCEE. Laudo nº {{ $laudo_id }}. Contém dados de saúde.</div>

@unless($finalizado)
    <div class="apoio">Documento de apoio, não finalizado. Os campos em branco são preenchidos pela equipe.</div>
@endunless

<h1>Laudo de avaliação biopsicossocial para fins da emissão da Carteira de Identificação da Pessoa com Fibromialgia – CIPF</h1>
<div class="versao">{{ $versao_modelo }}</div>

<div class="barra">Identificação e condição de saúde</div>
<table>
    <tr><td><b>Paciente:</b> {{ $paciente }}</td><td><b>Nascimento:</b> {{ $dataBr($identificacao['nascimento'] ?? null) }}</td></tr>
    <tr><td><b>Diagnóstico — laudo ACR 2016 (data):</b> {{ $dataBr($identificacao['diagnostico_acr_data'] ?? null) }}</td><td><b>Unidade (APS):</b> {{ $identificacao['unidade_aps'] ?? '' }}</td></tr>
    <tr><td colspan="2"><b>Impedimentos nas funções e estruturas do corpo (descrição):</b><div class="texto">{{ $identificacao['impedimentos'] ?? '' }}</div><b>CID:</b> {{ $identificacao['cid'] ?? '' }}</td></tr>
    <tr><td colspan="2">Natureza: {{ $marca(in_array('fisica', $natureza, true)) }} Física &nbsp; {{ $marca(in_array('mental', $natureza, true)) }} Mental &nbsp; {{ $marca(in_array('intelectual', $natureza, true)) }} Intelectual &nbsp; {{ $marca(in_array('sensorial', $natureza, true)) }} Sensorial</td></tr>
</table>

<div class="barra">Matriz de atividades e participação (IFBr-M – Índice de Funcionalidade Brasileiro Modificado / CIF – Classificação Internacional de Funcionalidade, Incapacidade e Saúde)</div>
<p class="dim">Pontuação: 100 = independente, sem dificuldade · 75 = dificuldade leve/moderada ou com adaptação/tecnologia assistiva · 50 = dificuldade grave ou ajuda parcial de terceiros · 25 = não realiza ou totalmente dependente. Score de até 500 pontos que ateste a deficiência: somatória da linha de menor valor dentro de cada um dos itens de 1 a 8.</p>
@foreach($dominios as $codigo => $d)
    <table style="margin-top: 1.5mm">
        <tr class="dominio"><th>{{ $codigo }}. {{ $d['titulo'] }}</th>@foreach([100, 75, 50, 25] as $v)<th class="col">{{ $v }}</th>@endforeach</tr>
        @foreach($d['itens'] as $item => $rotulo)
            <tr><td>{{ $item }} {{ $rotulo }}</td>@foreach([100, 75, 50, 25] as $v)<td class="col">{{ $marca(($matriz[$item] ?? null) === $v) }}</td>@endforeach</tr>
        @endforeach
        <tr><td class="dim">Menor valor do domínio</td><td class="col" colspan="4">{{ $pontuacao['por_dominio'][$codigo] ?? '' }}</td></tr>
    </table>
@endforeach
<table style="margin-top: 1.5mm"><tr><td><b>Pontuação total dos oito componentes:</b> {{ $pontuacao['total'] ?? '' }}</td></tr></table>

<div class="barra">Fatores contextuais</div>
<table>
    <tr><td><b>Fatores ambientais — barreiras e facilitadores (acessibilidade, tecnologia assistiva, apoio de terceiros, trabalho/estudo, transporte, domicílio):</b><div class="texto">{{ $fatores['ambientais'] ?? '' }}</div></td></tr>
    <tr><td><b>Fatores pessoais e psicológicos:</b><div class="texto">{{ $fatores['pessoais'] ?? '' }}</div></td></tr>
</table>

<div class="barra">Conclusão da equipe</div>
<table>
    <tr><td>Há impedimento de longo prazo? {{ $sn($conclusao['impedimento_longo_prazo'] ?? null, true) }} Sim {{ $sn($conclusao['impedimento_longo_prazo'] ?? null, false) }} Não</td></tr>
    <tr><td>Há barreiras que, na interação com o impedimento, geram limitação de atividades e restrição de participação? {{ $sn($conclusao['barreiras'] ?? null, true) }} Sim {{ $sn($conclusao['barreiras'] ?? null, false) }} Não</td></tr>
    <tr><td><b>Equiparação à pessoa com deficiência (para fins da CIPF): {{ $sn($conclusao['equiparacao'] ?? null, true) }} SIM {{ $sn($conclusao['equiparacao'] ?? null, false) }} NÃO</b></td></tr>
    <tr><td><b>Obs:</b> <span class="texto">{{ $conclusao['obs'] ?? '' }}</span></td></tr>
</table>

<div class="barra">Equipe multiprofissional e interdisciplinar (mínimo de dois profissionais de áreas distintas), sendo um deles o médico</div>
<table>
    @foreach($areas as $a)
        @forelse($a['membros'] as $m)
            <tr><td class="assin">{{ $m['nome'] }}<br><span class="dim">Nome / assinatura e carimbo</span></td><td>Área: {{ $a['rotulo'] }}<br>Conselho/registro nº {{ $m['registro'] }}</td></tr>
        @empty
            <tr><td class="assin">&nbsp;<br><span class="dim">Nome / assinatura e carimbo</span></td><td>Área: {{ $a['rotulo'] }}<br>Conselho/registro nº ____________</td></tr>
        @endforelse
    @endforeach
</table>
<table style="margin-top: 1.5mm"><tr><td><b>Local e data: (obrigatório)</b> {{ $local ?? '________' }}, {{ $data ?? '___/___/______' }}</td></tr></table>
@if($finalizado)
    <p class="dim">Finalizado no sistema em {{ $finalizado_em }}.@if($corrige) Corrige o laudo nº {{ $corrige }}.@endif</p>
@endif

</body>
</html>
