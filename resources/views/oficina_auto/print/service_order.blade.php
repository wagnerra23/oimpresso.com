{{--
    Template A4 nota-fiscal-like — Imprimir OS profissional (Gap 3 US-OFICINA-037).

    Espelha pattern resources/views/sale_pos/receipts/* — CSS INLINE em <style>
    pra funcionar dentro de IFRAME cross-origin (printServiceOrder.ts injeta via
    srcdoc). Tailwind utilitário NÃO funciona em IFRAME isolado — só CSS inline.

    Restrições Tier 0:
    - Multi-tenant (ADR 0093): payload já vem scopado pelo Controller (defensive guard).
    - LGPD: cliente leva papel; PII física-protegida pelo dono. CPF/CNPJ render completo.
    - NÃO auto-print on-mount aqui — frontend dispara via window.print() no IFRAME.

    Layout 4 zonas:
    1. Cabeçalho empresa (razão social + CNPJ + endereço) + OS number/data
    2. Cliente + Veículo (2 colunas)
    3. Tabela items (descrição/qtd/unit/total) + total geral
    4. Observações + DVI opcional + assinatura cliente

    @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php@printInvoice
    @see resources/js/Lib/printServiceOrder.ts (helper IFRAME)
    @see memory/sessions/2026-05-26-plano-gap-3-imprimir-os-pdf-profissional.md
--}}
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) ($v ?? 0), 2, ',', '.');
    $br_date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
    $br_date_time = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y H:i') : '—';
    $br_dec = fn ($v) => number_format((float) ($v ?? 0), 2, ',', '.');

    $totalItems = (float) ($order->total_items ?? 0);
    $hasItems = $order->items && $order->items->count() > 0;
    $hasDvi = $order->dviInspectionItems && $order->dviInspectionItems->count() > 0;
    // F3 OS-V2-1 — fotos do laudo OS-level (relação polimórfica arquivos()).
    $laudoFotos = $order->relationLoaded('arquivos') ? $order->arquivos : collect();
    $hasFotos = $laudoFotos->count() > 0;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $orderNumber }}</title>
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background: #fff;
            line-height: 1.4;
        }
        .os-doc { max-width: 100%; }
        /* ─── Zone 1: Cabeçalho empresa + OS meta ─── */
        .os-header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .os-header-left, .os-header-right {
            display: table-cell;
            vertical-align: top;
        }
        .os-header-left { width: 65%; }
        .os-header-right { width: 35%; text-align: right; }
        .os-business-name {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 3px 0;
        }
        .os-business-meta {
            font-size: 9pt;
            color: #444;
            line-height: 1.5;
        }
        .os-number {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .os-date {
            font-size: 9pt;
            color: #444;
            margin-top: 4px;
        }
        .os-status-badge {
            display: inline-block;
            font-size: 9pt;
            padding: 2px 8px;
            border-radius: 3px;
            background: #f0f0f0;
            color: #333;
            text-transform: uppercase;
            margin-top: 4px;
            border: 1px solid #ccc;
        }
        /* ─── Zone 2: Cliente + Veículo grid ─── */
        .os-grid-2 {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            border-collapse: separate;
            border-spacing: 8px 0;
        }
        .os-grid-2 > div {
            display: table-cell;
            width: 50%;
            border: 1px solid #ccc;
            padding: 8px 10px;
            vertical-align: top;
        }
        .os-grid-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 3px;
        }
        .os-kv-line {
            font-size: 10pt;
            margin: 2px 0;
        }
        .os-kv-line strong { font-weight: bold; }
        .os-empty-value { color: #999; font-style: italic; }
        /* ─── Zone 3: Tabela items ─── */
        .os-items-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin: 4px 0 6px 0;
        }
        .os-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .os-items-table th, .os-items-table td {
            padding: 5px 6px;
            font-size: 9.5pt;
            border-bottom: 1px solid #e0e0e0;
        }
        .os-items-table thead th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: left;
            color: #333;
            border-bottom: 2px solid #aaa;
            font-size: 8.5pt;
            text-transform: uppercase;
        }
        .os-items-table .col-num { width: 4%; text-align: center; }
        .os-items-table .col-tipo { width: 14%; }
        .os-items-table .col-desc { width: 44%; }
        .os-items-table .col-qty { width: 8%; text-align: right; }
        .os-items-table .col-unit { width: 15%; text-align: right; }
        .os-items-table .col-total { width: 15%; text-align: right; }
        .os-items-table tfoot td {
            font-weight: bold;
            font-size: 11pt;
            border-top: 2px solid #1a1a1a;
            border-bottom: none;
            padding-top: 8px;
        }
        .os-items-empty {
            text-align: center;
            color: #999;
            font-style: italic;
            font-size: 10pt;
            padding: 14px 0;
            border: 1px dashed #ccc;
            margin-bottom: 12px;
        }
        /* ─── Zone 4: Obs + DVI + assinatura ─── */
        .os-section { margin-top: 10px; }
        .os-section-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .os-notes-box {
            border: 1px solid #ccc;
            padding: 8px 10px;
            font-size: 10pt;
            min-height: 32px;
            white-space: pre-wrap;
        }
        .os-dvi-list {
            list-style: none;
            margin: 0;
            padding: 0;
            font-size: 9.5pt;
        }
        .os-dvi-list li {
            padding: 3px 0;
            border-bottom: 1px solid #f0f0f0;
            display: table;
            width: 100%;
        }
        .os-dvi-list li > span {
            display: table-cell;
            vertical-align: top;
        }
        .os-dvi-sev {
            width: 90px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5pt;
        }
        .os-dvi-sev.ok { color: #166534; }
        .os-dvi-sev.atencao { color: #b45309; }
        .os-dvi-sev.critico { color: #b91c1c; }
        .os-dvi-desc {
            font-size: 10pt;
        }
        .os-dvi-value {
            width: 100px;
            text-align: right;
            font-size: 9.5pt;
        }
        /* ─── Fotos da vistoria (F3 OS-V2-1) ─── */
        .os-photos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        .os-photo {
            margin: 0;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .os-photo img {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            display: block;
            border: 1px solid #ccc;
            border-radius: 2px;
        }
        .os-photo figcaption {
            font-size: 8pt;
            color: #555;
            margin-top: 2px;
            word-break: break-word;
        }
        .os-signature {
            margin-top: 28px;
            border-top: 1px solid #aaa;
            padding-top: 10px;
            font-size: 10pt;
        }
        .os-signature-line {
            margin-top: 36px;
            border-top: 1px solid #1a1a1a;
            width: 60%;
            padding-top: 4px;
            font-size: 9pt;
            color: #555;
        }
        .os-footer {
            margin-top: 18px;
            padding-top: 6px;
            border-top: 1px solid #e0e0e0;
            font-size: 8pt;
            color: #888;
            text-align: center;
        }
        /* ─── Print specifics ─── */
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11pt; }
            .os-items-table { page-break-inside: auto; }
            .os-items-table tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="os-doc">

    {{-- ════════════════════════════════════════════════════════════════
         Zone 1 — Cabeçalho empresa + OS meta
         ════════════════════════════════════════════════════════════════ --}}
    <div class="os-header">
        <div class="os-header-left">
            <h1 class="os-business-name">{{ $business->name ?? 'Empresa' }}</h1>
            <div class="os-business-meta">
                @if (!empty($business->tax_number_1))
                    CNPJ {{ $business->tax_number_1 }}<br>
                @endif
                @if ($location)
                    {{ trim(
                        ($location->landmark ?? '')
                        . (!empty($location->city) ? ', ' . $location->city : '')
                        . (!empty($location->state) ? ' - ' . $location->state : '')
                        . (!empty($location->zip_code) ? ' · CEP ' . $location->zip_code : ''),
                        ', '
                    ) }}
                    @if (!empty($location->mobile))
                        <br>Tel {{ $location->mobile }}
                    @endif
                @endif
            </div>
        </div>
        <div class="os-header-right">
            <div class="os-number">{{ $orderNumber }}</div>
            <div class="os-date">Emitido em {{ $br_date_time($generatedAt) }}</div>
            <div class="os-status-badge">{{ $order->status ?? 'aberta' }}</div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         Zone 2 — Cliente + Veículo (2 colunas)
         ════════════════════════════════════════════════════════════════ --}}
    <div class="os-grid-2">
        <div>
            <div class="os-grid-title">Cliente</div>
            @if ($order->contact)
                <div class="os-kv-line"><strong>{{ $order->contact->name }}</strong></div>
                @if (!empty($order->contact->tax_number))
                    <div class="os-kv-line">CPF/CNPJ {{ $order->contact->tax_number }}</div>
                @endif
                @if (!empty($order->contact->mobile))
                    <div class="os-kv-line">Tel {{ $order->contact->mobile }}</div>
                @endif
                @if (!empty($order->contact->address_line_1))
                    <div class="os-kv-line">
                        {{ $order->contact->address_line_1 }}@if (!empty($order->contact->city)), {{ $order->contact->city }} @endif
                        @if (!empty($order->contact->state)) - {{ $order->contact->state }} @endif
                    </div>
                @endif
            @else
                <div class="os-kv-line os-empty-value">Cliente não informado</div>
            @endif
        </div>
        <div>
            <div class="os-grid-title">Veículo</div>
            @if ($order->vehicle)
                <div class="os-kv-line">
                    <strong>
                        @if (!empty($order->vehicle->vehicle_type)){{ ucfirst($order->vehicle->vehicle_type) }}@endif
                        @if (!empty($order->vehicle->model_year)) {{ $order->vehicle->model_year }}@endif
                    </strong>
                </div>
                <div class="os-kv-line">Placa <strong>{{ $order->vehicle->plate ?? '—' }}</strong>
                    @if (!empty($order->vehicle->secondary_plate)) + {{ $order->vehicle->secondary_plate }}@endif
                </div>
                @if (!empty($order->vehicle->chassi))
                    <div class="os-kv-line">Chassi {{ $order->vehicle->chassi }}</div>
                @endif
                @if (!empty($order->vehicle->capacity_m3))
                    <div class="os-kv-line">Capacidade {{ $br_dec($order->vehicle->capacity_m3) }} m³</div>
                @endif
                @if ($order->mileage_at_service !== null)
                    <div class="os-kv-line">KM entrada: {{ number_format((float) $order->mileage_at_service, 0, ',', '.') }}</div>
                @endif
            @else
                <div class="os-kv-line os-empty-value">Veículo não vinculado</div>
            @endif

            @if ($order->order_type === 'manutencao')
                @if ($order->assignedUser)
                    <div class="os-kv-line" style="margin-top:5px;">
                        Mecânico:
                        <strong>{{ trim(
                            ($order->assignedUser->surname ?? '') . ' '
                            . ($order->assignedUser->first_name ?? '') . ' '
                            . ($order->assignedUser->last_name ?? '')
                        ) }}</strong>
                    </div>
                @endif
                @if (!empty($order->box_label))
                    <div class="os-kv-line">Box: <strong>{{ $order->box_label }}</strong></div>
                @endif
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         Zone 3 — Tabela items + total
         ════════════════════════════════════════════════════════════════ --}}
    <div class="os-items-title">Itens da OS</div>
    @if ($hasItems)
        <table class="os-items-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-tipo">Tipo</th>
                    <th class="col-desc">Descrição</th>
                    <th class="col-qty">Qtd</th>
                    <th class="col-unit">V. Unit</th>
                    <th class="col-total">V. Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $idx => $item)
                    @php
                        $tipoLabel = match ($item->tipo) {
                            'peca' => 'Peça',
                            'mao_obra' => 'Mão de obra',
                            'servico_terceiro' => 'Serviço',
                            default => $item->tipo,
                        };
                    @endphp
                    <tr>
                        <td class="col-num">{{ $idx + 1 }}</td>
                        <td class="col-tipo">{{ $tipoLabel }}</td>
                        <td class="col-desc">{{ $item->descricao }}</td>
                        <td class="col-qty">{{ $br_dec($item->quantidade) }}</td>
                        <td class="col-unit">{{ $brl($item->valor_unitario) }}</td>
                        <td class="col-total">{{ $brl($item->valor_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right;">TOTAL</td>
                    <td class="col-total">{{ $brl($totalItems) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="os-items-empty">Nenhum item lançado nesta OS.</div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════
         Zone 4a — Observações
         ════════════════════════════════════════════════════════════════ --}}
    <div class="os-section">
        <div class="os-section-title">Observações</div>
        <div class="os-notes-box">@if (!empty($order->notes)){{ $order->notes }}@else <span class="os-empty-value">Sem observações.</span>@endif</div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         Zone 4b — DVI Vistoria Digital (opcional · semáforo)
         Wave 3 US-OFICINA-035 — wedge vs RepairShopr/mHelpDesk.
         Renderiza apenas se OS tem items DVI lançados.
         ════════════════════════════════════════════════════════════════ --}}
    @if ($hasDvi)
        <div class="os-section">
            <div class="os-section-title">DVI · Vistoria Digital</div>
            <ul class="os-dvi-list">
                @foreach ($order->dviInspectionItems as $dvi)
                    @php
                        $sevLabel = match ($dvi->severity ?? 'ok') {
                            'atencao' => 'Atenção',
                            'critico' => 'Crítico',
                            default => 'OK',
                        };
                    @endphp
                    <li>
                        <span class="os-dvi-sev {{ $dvi->severity ?? 'ok' }}">{{ $sevLabel }}</span>
                        <span class="os-dvi-desc">
                            <strong>{{ $dvi->descricao ?? 'Item' }}</strong>
                            @if (!empty($dvi->recomendacao)) — {{ $dvi->recomendacao }}@endif
                        </span>
                        <span class="os-dvi-value">
                            @if (!empty($dvi->valor_recomendado) && (float) $dvi->valor_recomendado > 0)
                                {{ $brl($dvi->valor_recomendado) }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════
         Zone 4b-bis — Fotos da vistoria (F3 OS-V2-1)
         Fotos do laudo OS-level (anexo polimórfico da OS). Grid 3 colunas,
         legenda (original_name) abaixo de cada foto. break-inside avoid pra
         não cortar uma foto no meio entre páginas A4.
         ════════════════════════════════════════════════════════════════ --}}
    @if ($hasFotos)
        <div class="os-section">
            <div class="os-section-title">Fotos da vistoria</div>
            <div class="os-photos">
                @foreach ($laudoFotos as $foto)
                    <figure class="os-photo">
                        <img src="{{ $foto->display_url }}" alt="{{ $foto->original_name }}">
                        <figcaption>{{ $foto->original_name }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════
         Zone 4c — Assinatura cliente
         Martinho sub-vertical 4 (CNAE 4520) — papel pra ressarcir
         transportadora 3ª / seguradora. Campo manuscrito obrigatório.
         ════════════════════════════════════════════════════════════════ --}}
    <div class="os-signature">
        <div>Recebi os serviços descritos acima em ____ /____ /__________</div>
        <div class="os-signature-line">Assinatura do cliente</div>
    </div>

    <div class="os-footer">
        {{ $orderNumber }} · Gerado em {{ $br_date_time($generatedAt) }}
        @if (!empty($business->name)) · {{ $business->name }}@endif
    </div>

</div>
</body>
</html>
