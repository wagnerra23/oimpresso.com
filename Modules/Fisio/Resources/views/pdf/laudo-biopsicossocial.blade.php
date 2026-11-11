{{--
    Laudo de avaliação biopsicossocial para fibromialgia (US-FISIO-060), no layout do modelo aprovado
    pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026), versão 1.0
    de 26/10/2026. Montado por LaudoBiopsicossocialDocumento. Campo vazio sai em branco para escrever
    à mão; rascunho sai marcado como documento de apoio. O total da matriz é calculado; a conclusão é
    só o que a equipe marcou.

    Base comum dos impressos (pdf/_base): cabeçalho da clínica (IdentidadeClinica), rodapé e
    "Página X de Y" (Services/ImpressoPdf). Aqui ficam só os estilos do formulário.

    Seções na ordem do modelo: 1 identificação · 2 matriz IFBr-M · 3 fatores contextuais ·
    4 conclusão da equipe · 5 equipe · 6 local e data.
--}}
@php
    $marca = fn (bool $v) => $v ? '☒' : '☐';
    $sn = fn ($v, bool $sim) => $marca($v === $sim);
    $natureza = (array) ($identificacao['natureza'] ?? []);
    $dataBr = fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';
    $situacao = $finalizado ? 'finalizado em '.$finalizado_em : 'rascunho';
@endphp
@extends('fisio::pdf._base', ['documento' => [
    'titulo' => 'Laudo biopsicossocial',
    'numero' => 'Laudo nº '.$laudo_id.' · '.$situacao.($corrige ? ' · corrige o nº '.$corrige : ''),
]])

@section('aviso')
Pontuação calculada pelo sistema; a conclusão é da equipe e do fluxo oficial. Contém dados de saúde.
@endsection

@section('estilos')
<style>
    body { font-size: 8.5pt; }
    .titulo-doc { text-align: center; text-transform: uppercase; font-size: 13pt; letter-spacing: 0.3pt; }
    .subtitulo-doc { text-align: center; margin-bottom: 4mm; }
    .secao .num { padding-right: 1.5mm; }

    .apoio { border: 0.75pt solid #a16207; background: #fffbeb; color: #713f12; padding: 1.8mm 2.5mm; margin-bottom: 3mm; font-size: 8pt; }

    table.campos, table.matriz, table.resumo, table.conclusao, table.equipe { width: 100%; border-collapse: collapse; }
    table.campos td, table.matriz td, table.matriz th, table.resumo td, table.resumo th,
    table.conclusao td, table.equipe td, table.equipe th { vertical-align: top; text-align: left; }

    .campos td { border: 0.5pt solid #dfdedb; padding: 1.2mm 2mm 1.6mm; }
    .rot { display: block; font-size: 6.8pt; text-transform: uppercase; letter-spacing: 0.3pt; color: #58554f; margin-bottom: 0.6mm; }
    .val { min-height: 4mm; }
    .texto { white-space: pre-line; min-height: 10mm; }

    .legenda { font-size: 7.3pt; color: #58554f; margin: 0 0 2mm; }
    .dominio { page-break-inside: avoid; margin-bottom: 1.8mm; }
    .matriz th, .matriz td { border: 0.5pt solid #dfdedb; padding: 0.7mm 1.8mm; }
    .matriz th { background: #f4f3f0; font-size: 8pt; }
    .matriz th.col, .matriz td.col { width: 11mm; text-align: center; }
    .matriz tr.par td { background: #faf9f7; }
    .matriz td.cod { width: 8mm; color: #58554f; }
    .matriz td.menor { text-align: right; color: #58554f; font-size: 7.5pt; }

    .resumo th, .resumo td { border: 0.5pt solid #dfdedb; padding: 1.2mm 1mm; text-align: center; }
    .resumo th { background: #f4f3f0; font-size: 7.3pt; font-weight: normal; color: #58554f; }
    .resumo td { font-size: 9pt; }
    .resumo .total { background: #5e3d9e; color: #fff; font-weight: bold; }
    .resumo td.total { font-size: 10pt; }
    .nota { margin-top: 1.2mm; }

    .conclusao td { border: 0.5pt solid #dfdedb; padding: 1.6mm 2mm; }
    .conclusao td.resp { width: 32mm; white-space: nowrap; }
    .conclusao tr.destaque td { background: #f4f3f0; font-weight: bold; }

    .equipe th, .equipe td { border: 0.5pt solid #dfdedb; padding: 1.2mm 2mm; }
    .equipe th { background: #f4f3f0; font-size: 7.3pt; font-weight: normal; color: #58554f; text-transform: uppercase; }
    .equipe td.assin { width: 52mm; height: 10mm; vertical-align: bottom; }
    .equipe .linha { border-top: 0.5pt solid #1d1a15; font-size: 6.8pt; color: #58554f; padding-top: 0.5mm; }
    .equipe { page-break-inside: avoid; }

    .final { margin-top: 3mm; font-size: 7.5pt; color: #58554f; }
    .fluxo { margin-top: 4mm; }
</style>
@endsection

@section('conteudo')
@unless($finalizado)
    <div class="apoio"><b>Documento de apoio, não finalizado.</b> Os campos em branco são preenchidos pela equipe.</div>
@endunless

<h1 class="titulo-doc">Laudo de avaliação biopsicossocial</h1>
<div class="subtitulo-doc">para fins da emissão da Carteira de Identificação da Pessoa com Fibromialgia – CIPF<br>Modelo SES-SC · Deliberação 500/CIB/2026 · {{ $versao_modelo }}</div>

<div class="secao"><span class="num">1.</span>Identificação e condição de saúde</div>
<table class="campos">
    <tr>
        <td style="width:62%"><span class="rot">Paciente</span><div class="val">{{ $paciente }}</div></td>
        <td><span class="rot">Data de nascimento</span><div class="val">{{ $dataBr($identificacao['nascimento'] ?? null) }}</div></td>
    </tr>
    <tr>
        <td><span class="rot">Unidade de Atenção Primária (APS)</span><div class="val">{{ $identificacao['unidade_aps'] ?? '' }}</div></td>
        <td><span class="rot">Diagnóstico — laudo ACR 2016 (data)</span><div class="val">{{ $dataBr($identificacao['diagnostico_acr_data'] ?? null) }}</div></td>
    </tr>
    <tr>
        <td colspan="2"><span class="rot">Impedimentos nas funções e estruturas do corpo (descrição)</span><div class="texto">{{ $identificacao['impedimentos'] ?? '' }}</div></td>
    </tr>
    <tr>
        <td><span class="rot">CID</span><div class="val">{{ $identificacao['cid'] ?? '' }}</div></td>
        <td><span class="rot">Natureza</span>
            {{ $marca(in_array('fisica', $natureza, true)) }} Física &nbsp; {{ $marca(in_array('mental', $natureza, true)) }} Mental<br>
            {{ $marca(in_array('intelectual', $natureza, true)) }} Intelectual &nbsp; {{ $marca(in_array('sensorial', $natureza, true)) }} Sensorial
        </td>
    </tr>
</table>

<div class="secao"><span class="num">2.</span>Matriz de atividades e participação (IFBr-M / CIF)</div>
<p class="legenda">IFBr-M – Índice de Funcionalidade Brasileiro Modificado · CIF – Classificação Internacional de Funcionalidade, Incapacidade e Saúde.<br>
    <b>100</b> independente, sem dificuldade · <b>75</b> dificuldade leve/moderada ou com adaptação/tecnologia assistiva · <b>50</b> dificuldade grave ou ajuda parcial de terceiros · <b>25</b> não realiza ou totalmente dependente.</p>
@foreach($dominios as $codigo => $d)
    <div class="dominio">
        <table class="matriz">
            <tr><th colspan="2">{{ $codigo }}. {{ $d['titulo'] }}</th>@foreach([100, 75, 50, 25] as $v)<th class="col">{{ $v }}</th>@endforeach</tr>
            @foreach($d['itens'] as $item => $rotulo)
                <tr class="{{ $loop->even ? 'par' : '' }}"><td class="cod">{{ $item }}</td><td>{{ $rotulo }}</td>@foreach([100, 75, 50, 25] as $v)<td class="col">{{ $marca(($matriz[$item] ?? null) === $v) }}</td>@endforeach</tr>
            @endforeach
            <tr><td colspan="6" class="menor">Menor valor do domínio {{ $codigo }}: <b>{{ $pontuacao['por_dominio'][$codigo] ?? '____' }}</b></td></tr>
        </table>
    </div>
@endforeach

<table class="resumo" style="margin-top:2mm; page-break-inside: avoid">
    <tr>@foreach($dominios as $codigo => $d)<th>Domínio {{ $codigo }}</th>@endforeach<th class="total">Pontuação total</th></tr>
    <tr>@foreach($dominios as $codigo => $d)<td>{{ $pontuacao['por_dominio'][$codigo] ?? '' }}</td>@endforeach<td class="total">{{ $pontuacao['total'] ?? '' }}</td></tr>
</table>
<p class="nota"><b>Pontuação total dos oito componentes:</b> {{ $pontuacao['total'] ?? '____' }} — somatória da linha de menor valor dentro de cada um dos itens de 1 a 8 (de 200 a 800). Texto do modelo: score de até 500 pontos que ateste a deficiência.@if(! ($pontuacao['completo'] ?? false)) Matriz incompleta: o total só aparece com os 30 itens pontuados.@endif</p>

<div class="secao"><span class="num">3.</span>Fatores contextuais</div>
<table class="campos">
    <tr><td><span class="rot">Fatores ambientais — barreiras e facilitadores (acessibilidade, tecnologia assistiva, apoio de terceiros, trabalho/estudo, transporte, domicílio)</span><div class="texto">{{ $fatores['ambientais'] ?? '' }}</div></td></tr>
    <tr><td><span class="rot">Fatores pessoais e psicológicos</span><div class="texto">{{ $fatores['pessoais'] ?? '' }}</div></td></tr>
</table>

<div class="secao"><span class="num">4.</span>Conclusão da equipe</div>
<table class="conclusao" style="page-break-inside: avoid">
    <tr><td>Há impedimento de longo prazo?</td><td class="resp">{{ $sn($conclusao['impedimento_longo_prazo'] ?? null, true) }} Sim &nbsp; {{ $sn($conclusao['impedimento_longo_prazo'] ?? null, false) }} Não</td></tr>
    <tr><td>Há barreiras que, na interação com o impedimento, geram limitação de atividades e restrição de participação?</td><td class="resp">{{ $sn($conclusao['barreiras'] ?? null, true) }} Sim &nbsp; {{ $sn($conclusao['barreiras'] ?? null, false) }} Não</td></tr>
    <tr class="destaque"><td>Equiparação à pessoa com deficiência (para fins da CIPF)</td><td class="resp">{{ $sn($conclusao['equiparacao'] ?? null, true) }} SIM &nbsp; {{ $sn($conclusao['equiparacao'] ?? null, false) }} NÃO</td></tr>
    <tr><td colspan="2"><span class="rot">Observação</span><div class="texto" style="min-height:8mm">{{ $conclusao['obs'] ?? '' }}</div></td></tr>
</table>

<div class="secao"><span class="num">5.</span>Equipe multiprofissional e interdisciplinar</div>
<p class="legenda">Mínimo de dois profissionais de áreas distintas, sendo um deles o médico.</p>
<table class="equipe">
    <tr><th style="width:26mm">Área</th><th>Nome</th><th style="width:34mm">Conselho/registro nº</th><th>Assinatura e carimbo</th></tr>
    @foreach($areas as $a)
        @forelse($a['membros'] as $m)
            <tr><td>{{ $a['rotulo'] }}</td><td>{{ $m['nome'] }}</td><td>{{ $m['registro'] }}</td><td class="assin"><div class="linha">{{ $m['nome'] }}</div></td></tr>
        @empty
            <tr><td>{{ $a['rotulo'] }}</td><td>&nbsp;</td><td>____________</td><td class="assin"><div class="linha">&nbsp;</div></td></tr>
        @endforelse
    @endforeach
</table>

<div style="page-break-inside: avoid">
<div class="secao"><span class="num">6.</span>Local e data</div>
<table class="campos">
    <tr><td><span class="rot">Local e data (obrigatório)</span><div class="val">{{ $local ?? '______________________' }}, {{ $data ?? '___/___/______' }}</div></td></tr>
</table>
@if($finalizado)
    <p class="final">Finalizado no sistema em {{ $finalizado_em }}.@if($corrige) Corrige o laudo nº {{ $corrige }}.@endif Laudo finalizado não é alterado; correção é um laudo novo.</p>
@endif
</div>

<p class="nota fluxo">A pontuação da matriz é calculada pelo sistema a partir do que a equipe marcou; a conclusão e a decisão são da equipe multiprofissional e do fluxo oficial. Modelo aprovado pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026): a emissão do laudo cabe à Atenção Primária do município e a da carteira segue o fluxo da FCEE.</p>
@endsection
