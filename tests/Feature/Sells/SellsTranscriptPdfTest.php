<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R4-C1 — PDF server-side do Transcript de venda.
 *
 * Cobertura estrutural (file_get_contents) — implementação Browsershot exige
 * Chrome headless instalado (CT 100 Proxmox), NÃO funciona em Hostinger shared.
 * Tests rodam local + CI sem dependência runtime do Chrome graças ao fallback
 * 503 estruturado quando Browsershot ausente.
 *
 * Garante:
 *  - Controller existe nos paths canônicos
 *  - Rota registrada com FQCN (rule .claude/rules/routes.md)
 *  - Multi-tenant Tier 0 enforced no Controller (needle business_id + ADR 0093)
 *  - Fallback 503 quando Browsershot ausente (graceful Hostinger)
 *  - Blade view existe + contém marcadores canon (header brand, items table, footer)
 *  - SaleTranscriptPDF.tsx tem botão "Baixar PDF" complementar
 *
 * Refs:
 *  - app/Http/Controllers/SellTranscriptPdfController.php
 *  - resources/views/sells/transcript.blade.php
 *  - resources/js/Pages/Sells/_components/SaleTranscriptPDF.tsx
 */

const C1_CONTROLLER_PATH = 'app/Http/Controllers/SellTranscriptPdfController.php';
const C1_BLADE_PATH = 'resources/views/sells/transcript.blade.php';
const C1_TSX_PATH = 'resources/js/Pages/Sells/_components/SaleTranscriptPDF.tsx';
const C1_ROUTES_PATH = 'routes/web.php';

function c1Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Existência dos arquivos canon ────────────────────────────────────

it('SellTranscriptPdfController existe no path canônico', function () {
    expect(file_exists(base_path(C1_CONTROLLER_PATH)))->toBeTrue();
});

it('Blade transcript.blade.php existe', function () {
    expect(file_exists(base_path(C1_BLADE_PATH)))->toBeTrue();
});

it('SellTranscriptPdfController classe carrega via autoload (class_exists)', function () {
    expect(class_exists(\App\Http\Controllers\SellTranscriptPdfController::class))->toBeTrue();
});

// ─── Multi-tenant Tier 0 explícito no Controller (ADR 0093) ──────────

it('Controller filtra business_id explicitamente antes de findOrFail', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    // Needle quebrado em chunks pra evitar qualquer interpolação espúria — confirma
    // que a query Eloquent escopa por business_id explicitamente (Tier 0 ADR 0093).
    // Aceita tanto Transaction::where (estático, primeira chamada) quanto ->where (chained).
    $businessIdVar = chr(36).'businessId';
    expect($source)
        ->toContain('business_id')
        ->toContain("where('business_id', {$businessIdVar})")
        ->toContain('findOrFail')
        ->toContain("session()->get('user.business_id')")
        // Tier 0 — type='sell' lock (não permite buscar purchase via mesma rota)
        ->toContain("->where('type', 'sell')");
});

it('Controller declara namespace + classe canônica', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    expect($source)
        ->toContain('namespace App\\Http\\Controllers;')
        ->toContain('class SellTranscriptPdfController extends Controller')
        ->toContain('public function show(Request $request, int $saleId)');
});

it('Controller cita ADR 0093 multi-tenant na documentação', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    expect($source)
        ->toContain('ADR 0093')
        ->toContain('Tier 0');
});

// ─── Fallback 503 graceful (Hostinger sem Chrome) ────────────────────

it('Controller faz fallback 503 quando Browsershot ausente', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    expect($source)
        ->toContain('class_exists(\\Spatie\\Browsershot\\Browsershot::class)')
        ->toContain('503')
        ->toContain('browsershot_not_installed');
});

it('Controller captura exceção do render Browsershot (Chrome crashou)', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    expect($source)
        ->toContain('try {')
        ->toContain('catch (\\Throwable $e)')
        ->toContain('browsershot_render_failed');
});

it('Controller emite PDF com Content-Type + Content-Disposition attachment', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    expect($source)
        ->toContain("'Content-Type' => 'application/pdf'")
        ->toContain('Content-Disposition')
        ->toContain('attachment; filename=')
        ->toContain('venda-');
});

it('Controller configura Browsershot A4 + margins + showBackground', function () {
    $source = c1Read(C1_CONTROLLER_PATH);
    expect($source)
        ->toContain("->format('A4')")
        ->toContain('->margins(20, 15, 20, 15)')
        ->toContain('->showBackground()')
        ->toContain('->pdf()');
});

// ─── Rota registrada com FQCN obrigatório (rule routes.md) ───────────

it('Rota sells.transcript-pdf registrada com FQCN no routes/web.php', function () {
    $source = c1Read(C1_ROUTES_PATH);
    expect($source)
        ->toContain('/sells/{sale}/transcript.pdf')
        ->toContain('\\App\\Http\\Controllers\\SellTranscriptPdfController::class')
        ->toContain("'sells.transcript-pdf'")
        ->toContain('whereNumber');
});

it('Rota registrada e nomeada corretamente (artisan route:list canon)', function () {
    // Validação runtime — confirma que Laravel resolve a rota sem ReflectionException.
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if ($route->getName() === 'sells.transcript-pdf') {
            $found = true;
            expect($route->uri())->toBe('sells/{sale}/transcript.pdf');
            expect($route->methods())->toContain('GET');
            break;
        }
    }
    expect($found)->toBeTrue();
});

// ─── Blade template canônico ─────────────────────────────────────────

it('Blade transcript.blade.php tem @page A4 + CSS print-friendly', function () {
    $source = c1Read(C1_BLADE_PATH);
    expect($source)
        ->toContain('@page')
        ->toContain('size: A4')
        ->toContain('<!DOCTYPE html>')
        ->toContain('lang="pt-BR"');
});

it('Blade renderiza header brand + 4-grid + items table + assinaturas + footer', function () {
    $source = c1Read(C1_BLADE_PATH);
    expect($source)
        ->toContain('vd-tr-h')          // header brand
        ->toContain('vd-tr-grid')       // 4-grid info
        ->toContain('vd-tr-items')      // items table
        ->toContain('vd-tr-sigs')       // assinaturas
        ->toContain('vd-tr-sig-line')   // linha assinatura
        ->toContain('vd-tr-f')          // footer
        ->toContain('Transcript de venda');
});

it('Blade espelha status label paid/partial/due idêntico ao .tsx', function () {
    $source = c1Read(C1_BLADE_PATH);
    expect($source)
        ->toContain("'paid' => 'PAGA'")
        ->toContain("'partial' => 'PARCIAL'")
        ->toContain("'due' => 'PENDENTE'");
});

it('Blade formata chave NFe 4-em-4 + moeda BRL', function () {
    $source = c1Read(C1_BLADE_PATH);
    expect($source)
        ->toContain("preg_replace('/(\\d{4})/', '\$1 '")
        ->toContain('R$')
        ->toContain("number_format((float) \$n, 2, ',', '.')");
});

it('Blade exibe assinaturas Cliente + Atendente', function () {
    $source = c1Read(C1_BLADE_PATH);
    expect($source)
        ->toContain('Cliente')
        ->toContain('Atendente');
});

// ─── Botão "Baixar PDF" no SaleTranscriptPDF.tsx ──────────────────────

it('SaleTranscriptPDF.tsx tem botão complementar "Baixar PDF" linkado pra rota', function () {
    $source = c1Read(C1_TSX_PATH);
    expect($source)
        ->toContain('Baixar PDF')
        ->toContain('transcript.pdf')
        ->toContain('Download')
        ->toContain('vd-transcript-pdf');
});

it('SaleTranscriptPDF.tsx degrada gracioso quando 503 (esconde botão)', function () {
    $source = c1Read(C1_TSX_PATH);
    expect($source)
        ->toContain('pdfAvailable')
        ->toContain('setPdfAvailable')
        ->toContain('503');
});

it('SaleTranscriptPDF.tsx preserva botão Imprimir + Fechar legacy (sem regressão Onda 4)', function () {
    $source = c1Read(C1_TSX_PATH);
    expect($source)
        ->toContain('window.print()')
        ->toContain('vd-transcript-print')
        ->toContain('vd-transcript-close')
        ->toContain('Printer')
        ->toContain('Imprimir');
});

// ─── composer.json suggest (Browsershot opt-in) ──────────────────────

it('composer.json sugere spatie/browsershot como dependência opt-in', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($composer)->toHaveKey('suggest');
    expect($composer['suggest'])->toHaveKey('spatie/browsershot');
    expect($composer['suggest']['spatie/browsershot'])
        ->toContain('Hostinger')
        ->toContain('CT 100');
});
