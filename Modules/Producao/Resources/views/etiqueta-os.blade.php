{{-- PDF A4 da OS com QR (E05 modelo a4_os · PR 07 propostas-app). Preto sobre branco, QR com
     margem branca ≥ 4 módulos, data de entrega em caixa. @see EtiquetaOsController --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta {{ $os->numero }}</title>
    <style>
        @page { margin: 12mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #000; background: #fff; font-size: 11pt; margin: 0; }
        .faixa { border: 1pt solid #000; padding: 6mm; }
        .topo { width: 100%; border-collapse: collapse; }
        .topo td { vertical-align: top; }
        .numero { font-size: 22pt; font-weight: bold; margin: 0 0 2mm; }
        .cliente { font-size: 13pt; margin: 0 0 4mm; }
        .entrega { display: inline-block; border: 1.5pt solid #000; padding: 2mm 4mm; font-size: 13pt; font-weight: bold; }
        .qr { background: #fff; padding: 6mm; text-align: center; }
        .qr img { width: 42mm; height: 42mm; }
        .codigo { font-size: 7pt; word-break: break-all; margin-top: 1mm; }
        h2 { font-size: 12pt; margin: 6mm 0 2mm; }
        ol { margin: 0; padding-left: 6mm; }
        li { margin: 1mm 0; }
    </style>
</head>
<body>
<div class="faixa">
    <table class="topo">
        <tr>
            <td>
                <p class="numero">{{ $os->numero }}</p>
                @if($cliente)<p class="cliente">{{ $cliente }}</p>@endif
                <span class="entrega">Entrega: {{ $os->data_prazo ? \Illuminate\Support\Carbon::parse($os->data_prazo)->format('d/m/Y') : 'sem data' }}</span>
            </td>
            <td style="width: 56mm;">
                <div class="qr">
                    <img src="data:image/png;base64,{{ $qrPng }}" alt="QR da OS {{ $os->numero }}">
                    <div class="codigo">{{ $os->numero }}</div>
                </div>
            </td>
        </tr>
    </table>
    <h2>Leia o QR e escolha sua etapa</h2>
    @if(count($etapas))
        <ol>
            @foreach($etapas as $e)
                <li>{{ $e['posto'] }}@if($e['responsavel']) — {{ $e['responsavel'] }}@endif</li>
            @endforeach
        </ol>
    @else
        <p>Etapas ainda não programadas.</p>
    @endif
</div>
</body>
</html>
