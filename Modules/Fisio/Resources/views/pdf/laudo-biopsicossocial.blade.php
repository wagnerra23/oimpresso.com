{{--
    Laudo de avaliação biopsicossocial para fibromialgia (US-FISIO-060), no layout do modelo aprovado
    pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026), versão 1.0
    de 26/10/2026. Montado por LaudoBiopsicossocialDocumento. Campo vazio sai em branco para escrever
    à mão; rascunho sai marcado como documento de apoio. O total da matriz é calculado; a conclusão é
    só o que a equipe marcou. dompdf, DejaVu Sans, cores fixas (o dompdf não recebe os tokens do DS).
    "Laudo nº N · Página X de Y" é desenhado pelo controller (page_text), depois do render.

    Seções na ordem do modelo: 1 identificação · 2 matriz IFBr-M · 3 fatores contextuais ·
    4 conclusão da equipe · 5 equipe · 6 local e data.
--}}
@php
    $marca = fn (bool $v) => $v ? '☒' : '☐';
    $sn = fn ($v, bool $sim) => $marca($v === $sim);
    $natureza = (array) ($identificacao['natureza'] ?? []);
    $dataBr = fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';
    $empresa = $empresa ?? ['nome' => '', 'cnpj' => null, 'cidade' => null, 'telefone' => null];
    $contato = array_filter([
        $empresa['cnpj'] ? 'CNPJ '.$empresa['cnpj'] : null,
        $empresa['cidade'] ?? null,
        $empresa['telefone'] ? 'Tel. '.$empresa['telefone'] : null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Laudo de avaliação biopsicossocial</title>
    <style>
        @page { margin: 14mm 15mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1c1917; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: left; }

        .cab td { padding: 0; }
        .clinica { font-size: 11pt; font-weight: bold; }
        .cab .dir { text-align: right; font-size: 7.5pt; color: #44403c; }
        .regua { border-bottom: 1.5px solid #12402f; margin: 2.5mm 0 4mm; }

        h1 { font-size: 12pt; text-align: center; margin: 0; letter-spacing: 0.3pt; }
        .subtitulo { text-align: center; font-size: 9pt; margin: 1mm 0 0.5mm; }
        .modelo { text-align: center; font-size: 7.5pt; color: #57534e; margin-bottom: 4mm; }

        .apoio { border: 1px solid #a16207; background: #fffbeb; color: #713f12; padding: 1.8mm 2.5mm; margin-bottom: 3mm; font-size: 8pt; }

        h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #12402f; margin: 4mm 0 1.8mm; padding-bottom: 1mm; border-bottom: 0.8px solid #a8b5ae; }
        h2 .num { padding-right: 2mm; }

        .campos td { border: 0.6px solid #c8ccd0; padding: 1.2mm 2mm 1.6mm; }
        .rot { display: block; font-size: 6.8pt; text-transform: uppercase; letter-spacing: 0.3pt; color: #57534e; margin-bottom: 0.6mm; }
        .val { min-height: 4mm; }
        .texto { white-space: pre-line; min-height: 10mm; }

        .legenda { font-size: 7.3pt; color: #44403c; margin: 0 0 2mm; }
        .dominio { page-break-inside: avoid; margin-bottom: 1.8mm; }
        .matriz th, .matriz td { border: 0.6px solid #c8ccd0; padding: 0.7mm 1.8mm; }
        .matriz th { background: #e8efeb; font-size: 8pt; }
        .matriz th.col, .matriz td.col { width: 11mm; text-align: center; }
        .matriz tr.par td { background: #f7f8f7; }
        .matriz td.cod { width: 8mm; color: #57534e; }
        .matriz td.menor { text-align: right; color: #44403c; font-size: 7.5pt; }

        .resumo th, .resumo td { border: 0.6px solid #a8b5ae; padding: 1.2mm 1mm; text-align: center; }
        .resumo th { background: #e8efeb; font-size: 7.3pt; font-weight: normal; color: #44403c; }
        .resumo td { font-size: 9pt; }
        .resumo .total { background: #12402f; color: #fff; font-weight: bold; }
        .resumo td.total { font-size: 10pt; }
        .nota { font-size: 7.3pt; color: #57534e; margin-top: 1.2mm; }

        .conclusao td { border: 0.6px solid #c8ccd0; padding: 1.6mm 2mm; }
        .conclusao td.resp { width: 32mm; white-space: nowrap; }
        .conclusao tr.destaque td { background: #f1f5f3; font-weight: bold; }

        .equipe th, .equipe td { border: 0.6px solid #c8ccd0; padding: 1.2mm 2mm; }
        .equipe th { background: #e8efeb; font-size: 7.3pt; font-weight: normal; color: #44403c; text-transform: uppercase; }
        .equipe td.assin { width: 52mm; height: 10mm; vertical-align: bottom; }
        .equipe .linha { border-top: 0.6px solid #1c1917; font-size: 6.8pt; color: #57534e; padding-top: 0.5mm; }
        .equipe { page-break-inside: avoid; }

        .final { margin-top: 3mm; font-size: 7.5pt; color: #44403c; }
        #rodape { position: fixed; bottom: -18mm; left: 0; right: 42mm; font-size: 6.6pt; color: #57534e; line-height: 1.3; border-top: 0.6px solid #c8ccd0; padding-top: 1mm; }
    </style>
</head>
<body>

<div id="rodape">A pontuação da matriz é calculada pelo sistema a partir do que a equipe marcou; a conclusão e a decisão são da equipe multiprofissional e do fluxo oficial. Modelo aprovado pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026): a emissão do laudo cabe à Atenção Primária do município e a da carteira segue o fluxo da FCEE. Contém dados de saúde.</div>

<table class="cab">
    <tr>
        <td>
            <div class="clinica">{{ $empresa['nome'] }}</div>
            @if($contato)<div style="font-size:7.5pt;color:#44403c">{{ implode(' · ', $contato) }}</div>@endif
        </td>
        <td class="dir">
            Laudo nº {{ $laudo_id }}<br>
            {{ $finalizado ? 'Finalizado em '.$finalizado_em : 'Rascunho' }}@if($corrige) · corrige o nº {{ $corrige }}@endif<br>
            Emitido em {{ $emitido_em ?? '' }}
        </td>
    </tr>
</table>
<div class="regua"></div>

@unless($finalizado)
    <div class="apoio"><b>Documento de apoio, não finalizado.</b> Os campos em branco são preenchidos pela equipe.</div>
@endunless

<h1>LAUDO DE AVALIAÇÃO BIOPSICOSSOCIAL</h1>
<div class="subtitulo">para fins da emissão da Carteira de Identificação da Pessoa com Fibromialgia – CIPF</div>
<div class="modelo">Modelo SES-SC · Deliberação 500/CIB/2026 · {{ $versao_modelo }}</div>

<h2><span class="num">1.</span>Identificação e condição de saúde</h2>
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

<h2><span class="num">2.</span>Matriz de atividades e participação (IFBr-M / CIF)</h2>
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

<h2><span class="num">3.</span>Fatores contextuais</h2>
<table class="campos">
    <tr><td><span class="rot">Fatores ambientais — barreiras e facilitadores (acessibilidade, tecnologia assistiva, apoio de terceiros, trabalho/estudo, transporte, domicílio)</span><div class="texto">{{ $fatores['ambientais'] ?? '' }}</div></td></tr>
    <tr><td><span class="rot">Fatores pessoais e psicológicos</span><div class="texto">{{ $fatores['pessoais'] ?? '' }}</div></td></tr>
</table>

<h2><span class="num">4.</span>Conclusão da equipe</h2>
<table class="conclusao" style="page-break-inside: avoid">
    <tr><td>Há impedimento de longo prazo?</td><td class="resp">{{ $sn($conclusao['impedimento_longo_prazo'] ?? null, true) }} Sim &nbsp; {{ $sn($conclusao['impedimento_longo_prazo'] ?? null, false) }} Não</td></tr>
    <tr><td>Há barreiras que, na interação com o impedimento, geram limitação de atividades e restrição de participação?</td><td class="resp">{{ $sn($conclusao['barreiras'] ?? null, true) }} Sim &nbsp; {{ $sn($conclusao['barreiras'] ?? null, false) }} Não</td></tr>
    <tr class="destaque"><td>Equiparação à pessoa com deficiência (para fins da CIPF)</td><td class="resp">{{ $sn($conclusao['equiparacao'] ?? null, true) }} SIM &nbsp; {{ $sn($conclusao['equiparacao'] ?? null, false) }} NÃO</td></tr>
    <tr><td colspan="2"><span class="rot">Observação</span><div class="texto" style="min-height:8mm">{{ $conclusao['obs'] ?? '' }}</div></td></tr>
</table>

<h2><span class="num">5.</span>Equipe multiprofissional e interdisciplinar</h2>
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
<h2><span class="num">6.</span>Local e data</h2>
<table class="campos">
    <tr><td><span class="rot">Local e data (obrigatório)</span><div class="val">{{ $local ?? '______________________' }}, {{ $data ?? '___/___/______' }}</div></td></tr>
</table>
@if($finalizado)
    <p class="final">Finalizado no sistema em {{ $finalizado_em }}.@if($corrige) Corrige o laudo nº {{ $corrige }}.@endif Laudo finalizado não é alterado; correção é um laudo novo.</p>
@endif
</div>

</body>
</html>
