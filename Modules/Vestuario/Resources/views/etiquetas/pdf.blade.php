{{-- Etiquetas TAG vestuário - PDF A4 grid (US-VEST-020)
     Grid 4 colunas × 8 linhas = 32 etiquetas por A4 (50×30mm cada + margem 5mm).
     Renderiza nome + tamanho + cor + coleção + preço + EAN-13 (PNG inline) + QR (opcional).
     @see Modules/Vestuario/Http/Controllers/EtiquetaTagController.php
     @see memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas Vestuário</title>
    <style>
        @page { margin: 5mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 0; padding: 0; font-size: 9pt; }
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .row { display: table-row; }
        .label {
            display: table-cell;
            width: 25%;
            height: 30mm;
            border: 0.5pt dashed #999;
            padding: 2mm;
            vertical-align: top;
            page-break-inside: avoid;
            box-sizing: border-box;
        }
        .nome { font-weight: bold; font-size: 9pt; line-height: 1.1; margin-bottom: 1mm; }
        .tam-cor { font-size: 8pt; margin-bottom: 0.5mm; }
        .colecao { font-size: 7pt; color: #555; margin-bottom: 1mm; }
        .preco { font-weight: bold; font-size: 11pt; color: #000; margin-bottom: 1mm; }
        .barcode { text-align: center; margin: 1mm 0; }
        .barcode img { max-width: 100%; height: 8mm; }
        .qr { float: right; margin-left: 1mm; }
        .qr img { width: 10mm; height: 10mm; }
        .sku { font-size: 6pt; color: #777; text-align: right; }
        .meta-header { font-size: 7pt; color: #888; padding: 0 2mm 2mm 2mm; }
    </style>
</head>
<body>

@php
    /** @var array $etiquetas */
    /** @var int|null $business_id */
    $cols = 4;
    $generator = new \Milon\Barcode\DNS1D();
    $qrGenerator = new \Milon\Barcode\DNS2D();
@endphp

<div class="meta-header">
    Vestuário · {{ count($etiquetas) }} etiqueta{{ count($etiquetas) === 1 ? '' : 's' }} ·
    {{ now()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
    @if($business_id) · biz #{{ $business_id }} @endif
</div>

<div class="grid">
    @foreach(array_chunk($etiquetas, $cols) as $chunk)
        <div class="row">
            @foreach($chunk as $e)
                @php
                    $ean13 = $e['ean13'] ?? '';
                    $qrEnabled = (bool) ($e['qr_enabled'] ?? false);
                    $qrTemplate = 'https://oimpresso.com/p/{ean13}';
                    $qrData = str_replace('{ean13}', $ean13, $qrTemplate);
                    $eanPng = $ean13 !== '' ? $generator->getBarcodePNG($ean13, 'EAN13', 1.5, 30) : null;
                    $qrPng = $qrEnabled && $ean13 !== '' ? $qrGenerator->getBarcodePNG($qrData, 'QRCODE', 4, 4) : null;
                @endphp
                <div class="label">
                    @if($qrPng)
                        <div class="qr"><img src="data:image/png;base64,{{ $qrPng }}" alt="QR"></div>
                    @endif
                    <div class="nome">{{ $e['nome'] ?? 'Produto' }}</div>
                    <div class="tam-cor">
                        <strong>TAM:</strong> {{ strtoupper($e['tamanho'] ?? '-') }}
                        @if(!empty($e['cor']))
                            &nbsp; <strong>COR:</strong> {{ $e['cor'] }}
                        @endif
                    </div>
                    @if(!empty($e['colecao']))
                        <div class="colecao">{{ $e['colecao'] }}</div>
                    @endif
                    <div class="preco">R$ {{ number_format((float) ($e['preco'] ?? 0), 2, ',', '.') }}</div>
                    @if($eanPng)
                        <div class="barcode"><img src="data:image/png;base64,{{ $eanPng }}" alt="{{ $ean13 }}"></div>
                    @endif
                    <div class="sku">SKU: {{ $e['sku'] ?? '-' }} · {{ $ean13 }}</div>
                </div>
            @endforeach
            @if(count($chunk) < $cols)
                @for($i = count($chunk); $i < $cols; $i++)
                    <div class="label" style="border: none;"></div>
                @endfor
            @endif
        </div>
    @endforeach
</div>

</body>
</html>
