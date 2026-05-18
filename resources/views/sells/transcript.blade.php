{{--
  resources/views/sells/transcript.blade.php
  Template A4 print-friendly do Transcript de venda — renderizado pelo
  SellTranscriptPdfController::show() via Spatie Browsershot Chrome headless.

  Espelha o HTML/dados de resources/js/Pages/Sells/_components/SaleTranscriptPDF.tsx
  (Onda 4 R4 Distribuição) mas STANDALONE — sem AppShellV2, sem Inertia, sem React.
  CSS inline @page A4 + print-friendly pra Chrome renderizar idêntico ao preview.

  Variáveis esperadas: $venda (array shape igual TranscriptVenda do .tsx).
--}}
@php
    $fmt = function ($n) {
        return 'R$ '.number_format((float) $n, 2, ',', '.');
    };
    $fmtDate = function ($iso) {
        if (! $iso) return '';
        try {
            return \Carbon\Carbon::parse($iso)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $iso;
        }
    };
    $fmtChave = function ($k) {
        if (! $k) return '';
        return trim(preg_replace('/(\d{4})/', '$1 ', (string) $k));
    };
    $statusLabel = [
        'paid' => 'PAGA',
        'partial' => 'PARCIAL',
        'due' => 'PENDENTE',
    ];
    $status = $statusLabel[$venda['payment_status'] ?? 'due'] ?? strtoupper((string) ($venda['payment_status'] ?? ''));
    $itemsTotal = array_sum(array_map(fn ($l) => (float) ($l['subtotal'] ?? 0), $venda['lines'] ?? []));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Transcript venda #{{ $venda['invoice_no'] ?? '' }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Arial, sans-serif;
            color: #0f172a;
            font-size: 11pt;
            line-height: 1.45;
            background: #fff;
        }
        .page {
            width: 100%;
            padding: 0;
            background: #fff;
        }
        /* Header brand */
        .vd-tr-h {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-bottom: 12pt;
            border-bottom: 2pt solid #0f172a;
            margin-bottom: 16pt;
        }
        .vd-tr-h h1 { margin: 0; font-size: 16pt; font-weight: 700; letter-spacing: -0.5pt; }
        .vd-tr-h small { display: block; font-size: 9pt; color: #64748b; margin-top: 2pt; }
        .vd-tr-h h2 { margin: 0; font-size: 12pt; font-weight: 600; color: #475569; text-align: right; }
        .vd-tr-h p { margin: 2pt 0 0 0; font-size: 9.5pt; color: #64748b; text-align: right; }

        /* 4-grid info */
        .vd-tr-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12pt;
            margin-bottom: 16pt;
        }
        .vd-tr-grid > div {
            padding: 8pt;
            background: #f8fafc;
            border-left: 2pt solid #0f172a;
            border-radius: 2pt;
            display: flex;
            flex-direction: column;
            gap: 2pt;
        }
        .vd-tr-grid small {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #64748b;
            font-weight: 600;
        }
        .vd-tr-grid b { font-size: 10.5pt; color: #0f172a; }
        .vd-tr-grid span { font-size: 9pt; color: #475569; }
        .vd-tr-total { font-size: 13pt !important; color: #0f172a; }

        /* Items table */
        .vd-tr-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16pt;
            font-size: 10pt;
        }
        .vd-tr-items thead th {
            background: #0f172a;
            color: #fff;
            text-align: left;
            padding: 6pt 8pt;
            font-weight: 600;
            font-size: 9.5pt;
        }
        .vd-tr-items th.num, .vd-tr-items td.num { text-align: right; }
        .vd-tr-items tbody td {
            padding: 6pt 8pt;
            border-bottom: 0.5pt solid #e2e8f0;
            vertical-align: top;
        }
        .vd-tr-items tbody td small {
            display: block;
            color: #94a3b8;
            font-size: 8pt;
            margin-top: 1pt;
        }
        .vd-tr-items tfoot td {
            padding: 8pt;
            font-size: 10.5pt;
            border-top: 2pt solid #0f172a;
        }

        /* Fiscal */
        .vd-tr-fiscal {
            padding: 10pt 12pt;
            background: #f1f5f9;
            border-radius: 3pt;
            margin-bottom: 12pt;
        }
        .vd-tr-fiscal h3 { margin: 0 0 4pt 0; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #64748b; }
        .vd-tr-fiscal p { margin: 0; font-size: 10pt; }
        .vd-tr-chave {
            display: block;
            margin-top: 6pt;
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            background: #fff;
            padding: 6pt;
            border-radius: 2pt;
            word-break: break-all;
        }

        /* Notas */
        .vd-tr-notes {
            padding: 10pt 12pt;
            background: #fffbeb;
            border-left: 2pt solid #f59e0b;
            border-radius: 2pt;
            margin-bottom: 16pt;
        }
        .vd-tr-notes h3 { margin: 0 0 4pt 0; font-size: 9pt; text-transform: uppercase; color: #92400e; letter-spacing: 0.5pt; }
        .vd-tr-notes p { margin: 0; font-size: 10pt; color: #78350f; white-space: pre-wrap; }

        /* Assinaturas */
        .vd-tr-sigs {
            display: flex;
            justify-content: space-around;
            gap: 24pt;
            margin: 28pt 16pt 8pt 16pt;
        }
        .vd-tr-sig {
            flex: 1;
            text-align: center;
        }
        .vd-tr-sig-line {
            display: block;
            border-top: 1pt solid #0f172a;
            margin-bottom: 4pt;
            height: 0;
        }
        .vd-tr-sig small { font-size: 9pt; color: #475569; }

        /* Footer */
        .vd-tr-f {
            margin-top: 16pt;
            padding-top: 8pt;
            border-top: 0.5pt solid #e2e8f0;
            text-align: center;
        }
        .vd-tr-f small { font-size: 8pt; color: #94a3b8; }
    </style>
</head>
<body>
<div class="page">
    {{-- Header brand --}}
    <header class="vd-tr-h">
        <div class="vd-tr-h-l">
            <h1>{{ $venda['business_name'] ?? 'Oimpresso' }}</h1>
            @if (! empty($venda['business_cnpj']))
                <small>CNPJ {{ $venda['business_cnpj'] }}</small>
            @endif
        </div>
        <div class="vd-tr-h-r">
            <h2>Transcript de venda</h2>
            <p>
                #{{ $venda['invoice_no'] ?? '' }} ·
                {{ $fmtDate($venda['transaction_date'] ?? '') }} ·
                {{ $status }}
            </p>
        </div>
    </header>

    {{-- 4-grid info --}}
    <div class="vd-tr-grid">
        <div>
            <small>CLIENTE</small>
            <b>{{ $venda['customer_name'] ?? 'Consumidor Final' }}</b>
            @if (! empty($venda['customer_secondary']))
                <span>{{ $venda['customer_secondary'] }}</span>
            @endif
            @if (! empty($venda['customer_doc']))
                <span>{{ $venda['customer_doc'] }}</span>
            @endif
        </div>
        <div>
            <small>ATENDIDO POR</small>
            <b>{{ $venda['seller_name'] ?? '—' }}</b>
        </div>
        <div>
            <small>PAGAMENTO</small>
            <b>{{ $venda['payments'][0]['method'] ?? '—' }}</b>
            <span>{{ $fmt($venda['total_paid'] ?? 0) }} pago</span>
        </div>
        <div>
            <small>TOTAL</small>
            <b class="vd-tr-total">{{ $fmt($venda['final_total'] ?? 0) }}</b>
        </div>
    </div>

    {{-- Itens table --}}
    <table class="vd-tr-items">
        <thead>
            <tr>
                <th>Produto / serviço</th>
                <th class="num">Qtde</th>
                <th class="num">Unitário</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($venda['lines'] ?? [] as $line)
                <tr>
                    <td>
                        <div>{{ $line['product_name'] ?? '—' }}</div>
                        @if (! empty($line['product_sku']))
                            <small>{{ $line['product_sku'] }}</small>
                        @endif
                    </td>
                    <td class="num">{{ $line['quantity'] ?? 0 }}</td>
                    <td class="num">{{ $fmt($line['unit_price'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($line['subtotal'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 16pt;">
                        Sem itens na venda
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="num"><b>Total</b></td>
                <td class="num"><b>{{ $fmt($itemsTotal) }}</b></td>
            </tr>
        </tfoot>
    </table>

    {{-- Fiscal --}}
    @if (! empty($venda['fiscal_chave']))
        <div class="vd-tr-fiscal">
            <h3>Documento fiscal</h3>
            <p>
                <b>{{ $venda['fiscal_label'] ?? 'NF-e' }}</b>
                nº {{ $venda['fiscal_numero'] ?? '' }}/{{ $venda['fiscal_serie'] ?? '1' }}
            </p>
            <code class="vd-tr-chave">{{ $fmtChave($venda['fiscal_chave']) }}</code>
        </div>
    @endif

    {{-- Notas --}}
    @if (! empty($venda['additional_notes']))
        <div class="vd-tr-notes">
            <h3>Observações</h3>
            <p>{{ $venda['additional_notes'] }}</p>
        </div>
    @endif

    {{-- Assinaturas --}}
    <div class="vd-tr-sigs">
        <div class="vd-tr-sig">
            <span class="vd-tr-sig-line"></span>
            <small>Cliente</small>
        </div>
        <div class="vd-tr-sig">
            <span class="vd-tr-sig-line"></span>
            <small>Atendente
                @if (! empty($venda['seller_name']))
                    ({{ $venda['seller_name'] }})
                @endif
            </small>
        </div>
    </div>

    {{-- Footer --}}
    <footer class="vd-tr-f">
        <small>
            Emitido em {{ $fmtDate(now()->toIso8601String()) }} via Oimpresso ERP.
            Documento não-fiscal — apenas comprovante operacional.
        </small>
    </footer>
</div>
</body>
</html>
