<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * SellTranscriptPdfController — PDF server-side do Transcript de venda (Onda 4 R4 C1).
 *
 * Substitui `window.print()` do modal SaleTranscriptPDF.tsx por download PDF real
 * via Spatie Browsershot (Chrome headless). O modal HTML continua existindo no
 * SaleSheet pra preview rápido; este endpoint serve o complementar "Baixar PDF".
 *
 * RESTRIÇÕES Tier 0 (memory/proibicoes.md):
 *  - business_id global scope OBRIGATÓRIO (ADR 0093) — query filtra explicitamente
 *    por business_id antes de findOrFail pra blindar contra cross-tenant.
 *  - Browsershot exige Chrome headless instalado. Hostinger shared NÃO suporta;
 *    fallback 503 estruturado quando classe Browsershot ausente OU PDF render
 *    falhar. Frontend trata 503 ocultando botão (graceful degradation).
 *
 * Refs:
 *  - resources/js/Pages/Sells/_components/SaleTranscriptPDF.tsx (espelho HTML/dados)
 *  - resources/views/sells/transcript.blade.php (template A4 print-friendly)
 *  - tests/Feature/Sells/SellsTranscriptPdfTest.php (cobertura estrutural)
 */
class SellTranscriptPdfController extends Controller
{
    /**
     * GET /sells/{sale}/transcript.pdf
     *
     * Renderiza Blade A4 -> Browsershot Chrome -> attachment download.
     */
    public function show(Request $request, int $saleId): Response|JsonResponse
    {
        // Multi-tenant Tier 0 IRREVOGÁVEL — business_id scope explícito (ADR 0093).
        // Mesmo que Transaction tenha global scope, dobrar a barreira aqui é defesa
        // em profundidade contra withoutGlobalScopes acidental upstream.
        $businessId = (int) ($request->session()->get('user.business_id') ?? 0);

        if ($businessId <= 0) {
            return response()->json([
                'error' => 'Sessão sem business_id — login expirado ou contexto inválido.',
            ], 403);
        }

        $sale = Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->with([
                'contact:id,name,supplier_business_name,mobile,tax_number',
                'sell_lines.product:id,name,sku',
                'payment_lines',
                'business:id,name,tax_number_1',
            ])
            ->findOrFail($saleId);

        // Verificar disponibilidade do Browsershot. Hostinger shared não tem Chrome
        // headless; nesse runtime devolvemos 503 estruturado e o frontend esconde
        // o botão de download (degrada pro window.print() do modal HTML).
        if (! class_exists(\Spatie\Browsershot\Browsershot::class)) {
            Log::info('SellTranscriptPdfController: Browsershot indisponível neste runtime', [
                'business_id' => $businessId,
                'sale_id' => $saleId,
            ]);

            return response()->json([
                'error' => 'PDF rendering indisponível neste runtime — use Imprimir do modal Transcript.',
                'reason' => 'browsershot_not_installed',
            ], 503);
        }

        $payload = $this->buildPayload($sale);
        $html = View::make('sells.transcript', ['venda' => $payload])->render();

        try {
            /** @var class-string $browsershot */
            $browsershot = \Spatie\Browsershot\Browsershot::class;
            $pdf = $browsershot::html($html)
                ->format('A4')
                ->margins(20, 15, 20, 15)
                ->showBackground()
                ->emulateMedia('print')
                ->pdf();
        } catch (\Throwable $e) {
            Log::error('SellTranscriptPdfController: render Browsershot falhou', [
                'business_id' => $businessId,
                'sale_id' => $saleId,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Falha ao gerar PDF — Chrome headless indisponível.',
                'reason' => 'browsershot_render_failed',
            ], 503);
        }

        $filename = 'venda-'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $sale->invoice_no).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Sale-Id' => (string) $sale->id,
        ]);
    }

    /**
     * Espelha o shape de TranscriptVenda em SaleTranscriptPDF.tsx pra reuso do mesmo template.
     *
     * @param  Transaction  $sale
     * @return array<string, mixed>
     */
    protected function buildPayload(Transaction $sale): array
    {
        $contact = $sale->contact;
        $business = $sale->business ?? null;

        $lines = collect($sale->sell_lines ?? [])->map(function ($line) {
            $unit = (float) ($line->unit_price_inc_tax ?? $line->unit_price ?? 0);
            $qty = (float) ($line->quantity ?? 0);

            return [
                'id' => (int) $line->id,
                'product_name' => optional($line->product)->name,
                'product_sku' => optional($line->product)->sku,
                'quantity' => $qty,
                'unit_price' => $unit,
                'subtotal' => round($unit * $qty, 2),
            ];
        })->values()->all();

        $payments = collect($sale->payment_lines ?? [])->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'amount' => (float) ($p->amount ?? 0),
                'method' => (string) ($p->method ?? '—'),
                'paid_on' => optional($p->paid_on)->toDateString(),
            ];
        })->values()->all();

        return [
            'id' => (int) $sale->id,
            'invoice_no' => (string) $sale->invoice_no,
            'transaction_date' => (string) $sale->transaction_date,
            'final_total' => (float) $sale->final_total,
            'total_paid' => (float) ($sale->total_paid ?? 0),
            'payment_status' => (string) ($sale->payment_status ?? 'due'),
            'customer_name' => optional($contact)->name,
            'customer_secondary' => optional($contact)->supplier_business_name,
            'customer_doc' => optional($contact)->tax_number,
            'seller_name' => null, // Onda 5 preenche via accessor; mantém compat
            'lines' => $lines,
            'payments' => $payments,
            'fiscal_label' => null,
            'fiscal_numero' => null,
            'fiscal_serie' => null,
            'fiscal_chave' => null,
            'additional_notes' => (string) ($sale->additional_notes ?? ''),
            'business_name' => optional($business)->name,
            'business_cnpj' => optional($business)->tax_number_1,
        ];
    }
}
