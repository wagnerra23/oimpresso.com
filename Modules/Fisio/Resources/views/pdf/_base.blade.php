{{--
    Base visual dos impressos do Fisio (prontuário, laudo de evolução, folha do programa; o laudo
    biopsicossocial pode adotar depois). Linguagem do protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/Laudo.dc.html:
    nome da clínica com documento, endereço e contato; faixa de acento sob o cabeçalho; títulos de
    seção em versalete; tabela com cabeçalho cinza claro e linhas finas.

    Motor: dompdf 3 (barryvdh/laravel-dompdf). Por isso: sem flex nem grid (o alinhamento é por
    tabela), fonte DejaVu (acentos sem fonte externa), cores fixas em hex (o dompdf não recebe os
    tokens do DS nem entende oklch — os valores são os do protótipo convertidos), cabeçalho e
    rodapé em position: fixed (repetem em toda página). "Página X de Y" NÃO sai daqui: o dompdf 3
    resolve counter(pages) como 0 (medido em 2026-11-11), então o número é desenhado no canvas
    depois do layout por Services/ImpressoPdf, na faixa do rodapé, abaixo da emissão.

    Uso:
        @extends('fisio::pdf._base', ['documento' => ['titulo' => '...', 'numero' => '...']])
        @section('estilos') ...css do documento... @endsection
        @section('aviso') texto à esquerda do rodapé (opcional) @endsection
        @section('conteudo') ... @endsection

    Variáveis: $clinica (IdentidadeClinica::montar; sem ela cai em $empresa), $documento
    (titulo, numero opcional), $emitido_em.

    Classes para o conteúdo: .titulo-doc, .secao, table.ficha (rótulo/valor), table.grade (lista
    com cabeçalho), .bloco (caixa que não quebra), .assinatura, .nota.
--}}
@php
    $clinica = $clinica ?? ['nome' => $empresa ?? '', 'documento' => null, 'endereco' => null, 'contato' => null, 'logo' => null];
    $documento = $documento ?? ['titulo' => ''];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $documento['titulo'] }}@if(!empty($clinica['nome'])) — {{ $clinica['nome'] }}@endif</title>
    <style>
        @page { margin: 34mm 16mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; line-height: 1.35; color: #1d1a15; }

        /* Cabeçalho e rodapé: repetem em toda página. */
        #cabecalho { position: fixed; top: -26mm; left: 0; right: 0; height: 21mm; border-bottom: 1.5pt solid #795bbf; }
        #cabecalho table { width: 100%; border-collapse: collapse; }
        #cabecalho td { vertical-align: top; padding: 0; }
        #cabecalho .logo { width: 20mm; padding-right: 3mm; }
        #cabecalho .logo img { max-width: 20mm; max-height: 15mm; }
        #cabecalho .clinica-nome { font-size: 12.5pt; font-weight: bold; line-height: 1.2; }
        #cabecalho .clinica-dados { font-size: 7pt; color: #58554f; line-height: 1.35; margin-top: 0.8mm; }
        #cabecalho .doc { width: 62mm; text-align: right; }
        #cabecalho .doc-titulo { font-size: 8pt; font-weight: bold; color: #5e3d9e; text-transform: uppercase; letter-spacing: 0.4pt; line-height: 1.25; }
        #cabecalho .doc-dados { font-size: 7pt; color: #58554f; margin-top: 0.8mm; line-height: 1.35; }

        #rodape { position: fixed; bottom: -13mm; left: 0; right: 0; height: 9mm; border-top: 0.5pt solid #dfdedb; padding-top: 1.5mm; font-size: 7pt; color: #58554f; line-height: 1.3; }
        #rodape table { width: 100%; border-collapse: collapse; }
        #rodape td { vertical-align: top; padding: 0; }
        #rodape .emissao { width: 52mm; text-align: right; white-space: nowrap; }

        /* Conteúdo. */
        .titulo-doc { font-size: 15pt; font-weight: bold; margin: 0 0 1mm; line-height: 1.2; }
        .subtitulo-doc { font-size: 8.5pt; color: #58554f; margin: 0 0 5mm; }
        .secao { font-size: 8.5pt; font-weight: bold; color: #5e3d9e; text-transform: uppercase; letter-spacing: 0.5pt; margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 0.5pt solid #dfdedb; page-break-after: avoid; }
        .nota { font-size: 7.5pt; color: #58554f; }
        .bloco { page-break-inside: avoid; }

        table.ficha { width: 100%; border-collapse: collapse; }
        table.ficha th { width: 34mm; text-align: left; font-weight: normal; font-size: 7.5pt; color: #58554f; padding: 1.4mm 3mm 0.9mm 0; vertical-align: top; }
        table.ficha td { padding: 0.9mm 0; vertical-align: top; }
        table.ficha tr { border-bottom: 0.5pt solid #ecebe8; }

        table.grade { width: 100%; border-collapse: collapse; }
        table.grade th { background: #f4f3f0; text-align: left; font-size: 7.5pt; font-weight: bold; color: #58554f; padding: 1.6mm 2mm; border-bottom: 0.5pt solid #dfdedb; }
        table.grade td { padding: 1.6mm 2mm; border-bottom: 0.5pt solid #ecebe8; vertical-align: top; }
        table.grade .num { text-align: right; white-space: nowrap; }

        .assinatura { margin-top: 12mm; width: 78mm; page-break-inside: avoid; }
        .assinatura .linha { border-top: 0.75pt solid #1d1a15; padding-top: 1.5mm; }
        .assinatura .nome { font-weight: bold; }
        .assinatura .dados { font-size: 7.5pt; color: #58554f; }
    </style>
    @yield('estilos')
</head>
<body>

<div id="cabecalho">
    <table>
        <tr>
            @if(!empty($clinica['logo']))
                <td class="logo"><img src="{{ $clinica['logo'] }}" alt=""></td>
            @endif
            <td>
                <div class="clinica-nome">{{ $clinica['nome'] }}</div>
                <div class="clinica-dados">
                    @if(!empty($clinica['documento'])){{ $clinica['documento'] }}<br>@endif
                    @if(!empty($clinica['endereco'])){{ $clinica['endereco'] }}<br>@endif
                    @if(!empty($clinica['contato'])){{ $clinica['contato'] }}@endif
                </div>
            </td>
            <td class="doc">
                <div class="doc-titulo">{{ $documento['titulo'] }}</div>
                <div class="doc-dados">
                    @if(!empty($documento['numero'])){{ $documento['numero'] }}<br>@endif
                    Emitido em {{ $emitido_em }}
                </div>
            </td>
        </tr>
    </table>
</div>

<div id="rodape">
    <table>
        <tr>
            <td>{{ $clinica['nome'] }}@hasSection('aviso') · @yield('aviso')@endif</td>
            <td class="emissao">Emitido em {{ $emitido_em }}</td>
        </tr>
    </table>
</div>

@yield('conteudo')

</body>
</html>
