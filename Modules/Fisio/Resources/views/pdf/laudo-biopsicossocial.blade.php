{{--
    Laudo biopsicossocial integrado (US-FISIO-060). Lei 15.176/2025 (art. 1º-C da Lei 14.705/2023)
    e Lei 13.146/2015, art. 2º, §1º: quatro seções, uma por inciso, cada uma com autor e assinatura.
    Conteúdo montado por LaudoBiopsicossocialDocumento só com seção REGISTRADA; seção sem registro
    sai em branco para o profissional escrever e assinar à mão. O documento descreve; não enquadra.
    Mesmo padrão do laudo de evolução: dompdf, DejaVu Sans, cores fixas.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Laudo biopsicossocial</title>
    <style>
        @page { margin: 16mm 16mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1c1917; }
        .cab { border-bottom: 1px solid #e7e5e4; padding-bottom: 3mm; margin-bottom: 4mm; }
        .empresa { font-weight: bold; }
        h1 { font-size: 14pt; margin: 4mm 0 1mm; }
        h2 { font-size: 10pt; margin: 0 0 1.5mm; }
        .dim { color: #57534e; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 1.2mm 2mm 1.2mm 0; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        th { font-size: 8pt; color: #57534e; font-weight: normal; width: 32mm; }
        .secao { margin-top: 6mm; page-break-inside: avoid; }
        .texto { white-space: pre-line; }
        .branco { border: 1px solid #a8a29e; height: 42mm; }
        .assinatura { margin-top: 12mm; width: 80mm; border-top: 1px solid #1c1917; padding-top: 1.5mm; }
        #rodape { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #57534e; }
    </style>
</head>
<body>

<div id="rodape">Avaliação biopsicossocial nos termos do art. 2º, §1º, da Lei 13.146/2015 e da Lei 15.176/2025. Este documento descreve impedimentos e limitações; o enquadramento cabe à perícia. Contém dados de saúde: compartilhar só com o destinatário indicado.</div>

<div class="cab">
    <div class="empresa">{{ $empresa }}</div>
    <h1>Laudo biopsicossocial integrado</h1>
    <div class="dim">Laudo nº {{ $laudo_id }} · aberto em {{ $criado_em }} · emitido em {{ $emitido_em }}</div>
</div>

<table>
    <tr><th>Paciente</th><td>{{ $paciente ?: '—' }}</td></tr>
    <tr><th>Finalidade</th><td>Avaliação biopsicossocial por equipe multiprofissional, para os fins do art. 1º-C da Lei 14.705/2023. Diagnóstico e CID, quando houver, constam da seção I, escrita pelo médico.</td></tr>
</table>

@foreach($secoes as $s)
    <div class="secao">
        <h2>{{ $s['inciso'] }} · {{ $s['titulo'] }}</h2>
        @if($s['registrada'])
            <div class="texto">{{ $s['registrada']['texto'] }}</div>
            <div class="assinatura">
                {{ $s['registrada']['autor'] }}<br>
                <span class="dim">{{ $s['registrada']['conselho'] }} {{ $s['registrada']['registro'] }}</span><br>
                @if($s['registrada']['externa'])
                    <span class="dim">Assinatura do profissional (manuscrita ou por certificado digital). Texto transcrito pela clínica em {{ $s['registrada']['registrado_em'] }}.</span>
                @else
                    <span class="dim">Registrado no sistema em {{ $s['registrada']['registrado_em'] }}.</span>
                @endif
                @if($s['registrada']['corrige'])
                    <br><span class="dim">Esta seção corrige uma versão anterior, que segue guardada.</span>
                @endif
            </div>
        @else
            <div class="branco">&nbsp;</div>
            <div class="assinatura">
                Nome: ________________________________<br>
                <span class="dim">{{ $s['conselho_sugerido'] }} (ou outro conselho): ______________ · Data: ___/___/______</span><br>
                <span class="dim">Assinatura (manuscrita ou por certificado digital)</span>
            </div>
        @endif
    </div>
@endforeach

</body>
</html>
