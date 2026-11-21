{{--
    Protocolo da instalação (US-COMVIS-007 · UC-PCV-25). Montado por Services/ProtocoloInstalacao, que só o
    gera para instalação concluída. Imagens chegam como data URI (o dompdf não busca nada na rede).
    Cores fixas de propósito: é papel, não segue o tema da tela.
--}}
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Protocolo de instalação {{ $instalacao['os_numero'] ?? '#'.$instalacao['id'] }}</title>
<style>
    @page { margin: 16mm 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #222; }
    h1 { font-size: 15pt; margin: 0 0 2mm; }
    .sub { color: #555; margin: 0 0 6mm; }
    table.dados { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    table.dados th { text-align: left; width: 32mm; color: #555; font-weight: normal; padding: 1.5mm 0; vertical-align: top; }
    table.dados td { padding: 1.5mm 0; vertical-align: top; }
    h2 { font-size: 11pt; margin: 5mm 0 2mm; border-bottom: 0.3mm solid #bbb; padding-bottom: 1mm; }
    table.fotos { width: 100%; border-collapse: collapse; }
    table.fotos td { width: 50%; vertical-align: top; padding-right: 3mm; }
    .foto { max-width: 85mm; max-height: 90mm; border: 0.3mm solid #ccc; }
    .assinatura { max-width: 80mm; max-height: 35mm; border-bottom: 0.3mm solid #222; }
    .nota { color: #777; font-style: italic; }
    .rodape { margin-top: 8mm; color: #777; font-size: 8pt; }
</style>
</head>
<body>
    <h1>Protocolo de instalação</h1>
    <p class="sub">{{ $empresa }}</p>

    <table class="dados">
        <tr><th>OS</th><td>{{ $instalacao['os_numero'] ?? 'sem OS' }}</td></tr>
        <tr><th>Cliente</th><td>{{ $instalacao['cliente'] ?? 'não informado' }}</td></tr>
        <tr><th>Endereço</th><td>{{ $instalacao['endereco'] ?? 'não cadastrado no cliente' }}</td></tr>
        <tr><th>Agendada</th><td>{{ $agendada ?? 'sem horário' }}</td></tr>
        <tr><th>Concluída</th><td>{{ $concluida ?? '—' }}</td></tr>
        <tr><th>Equipe</th><td>{{ implode(' + ', $instalacao['equipe']) ?: '—' }}</td></tr>
        <tr><th>Veículo</th><td>{{ $instalacao['veiculo'] ?? '—' }}</td></tr>
    </table>

    <h2>Fotos</h2>
    <table class="fotos">
        <tr>
            @foreach (['antes' => 'Antes', 'depois' => 'Depois'] as $chave => $rotulo)
                <td>
                    <b>{{ $rotulo }}</b>@if ($fotos[$chave]['em']) · {{ $fotos[$chave]['em'] }}@endif<br>
                    @if ($fotos[$chave]['src'])
                        <img class="foto" src="{{ $fotos[$chave]['src'] }}" alt="Foto {{ strtolower($rotulo) }}">
                    @else
                        <span class="nota">{{ $fotos[$chave]['nota'] }}</span>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <h2>Assinatura do cliente</h2>
    @if ($assinatura['imagem']['src'])
        <img class="assinatura" src="{{ $assinatura['imagem']['src'] }}" alt="Assinatura do cliente"><br>
    @else
        <span class="nota">{{ $assinatura['imagem']['nota'] }}</span><br>
    @endif
    Assinado por {{ $assinatura['assinado_por'] ?? '—' }}@if ($assinatura['imagem']['em']) em {{ $assinatura['imagem']['em'] }}@endif.<br>
    @if ($assinatura['aceite_em'])
        O cliente aceitou que a assinatura fique guardada como comprovante em {{ $assinatura['aceite_em'] }}.
    @endif

    <p class="rodape">Gerado em {{ $gerado_em }} pelo oimpresso.</p>
</body>
</html>
