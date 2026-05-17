<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\EtiquetaTagService;
use Modules\Vestuario\Services\GradeCurvaService;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

uses(Tests\TestCase::class);

/**
 * W27 — EtiquetaTagService + GradeCurvaService smoke tests
 *
 * Cobertura:
 * - EAN-13 check digit válido (algoritmo GS1 — vetores conhecidos)
 * - ZPL gerado contém campos obrigatórios (TAM, COR, EAN, SKU)
 * - gerarLote concatena múltiplas etiquetas
 * - Curva aplicada gera matriz tamanho × cor corretamente
 * - Proporção respeita curva
 * - Multi-tenant: forBusiness override funciona
 *
 * @see Modules/Vestuario/Services/EtiquetaTagService.php
 * @see Modules/Vestuario/Services/GradeCurvaService.php
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA-2026-05-13.md §G1 §G3
 */

// ============================================================
// EtiquetaTagService — EAN-13
// ============================================================

it('generateEan13 calcula check digit correto (vetor GS1 conhecido)', function () {
    $svc = new EtiquetaTagService();

    // Vetor 1: 789100000001 → algoritmo GS1
    //  ímpares (×1): 7+9+0+0+0+0 = 16
    //  pares   (×3): (8+1+0+0+0+1)*3 = 30
    //  total = 46 → check = (10 - 46%10) % 10 = 4
    expect($svc->generateEan13('789100000001'))->toBe(4);

    // Vetor 2: 590123412345 (ex didático Wikipedia EAN-13) → check 7
    expect($svc->generateEan13('590123412345'))->toBe(7);

    // Vetor 3: 000000000000 → 0 (caso degenerate)
    expect($svc->generateEan13('000000000000'))->toBe(0);
});

it('generateEan13 rejeita input com tamanho errado', function () {
    $svc = new EtiquetaTagService();
    $svc->generateEan13('1234567890'); // só 10 dígitos
})->throws(InvalidArgumentException::class, 'precisa de exatamente 12');

it('validateEan13 aceita EAN-13 válido e rejeita inválido', function () {
    $svc = new EtiquetaTagService();

    expect($svc->validateEan13('7891000000014'))->toBeTrue();
    expect($svc->validateEan13('7891000000017'))->toBeFalse(); // check errado (correto é 4)
    expect($svc->validateEan13('123'))->toBeFalse(); // curto
});

it('generateEan13FromSku produz EAN-13 sempre válido (round-trip)', function () {
    $svc = new EtiquetaTagService();

    $skus = ['VST-1-M-C01', 'ABC123', '999999', 'COR-AZUL-G', '42'];
    foreach ($skus as $sku) {
        $ean = $svc->generateEan13FromSku($sku);
        expect(strlen($ean))->toBe(13, "EAN gerado de '{$sku}' deveria ter 13 chars: '{$ean}'");
        expect($svc->validateEan13($ean))->toBeTrue("EAN gerado de '{$sku}' deveria validar: '{$ean}'");
        expect(str_starts_with($ean, '789'))->toBeTrue("EAN deveria começar com 789 (BR): '{$ean}'");
    }
});

it('normalizeEan13 aceita 12 ou 13 digitos', function () {
    $svc = new EtiquetaTagService();

    expect($svc->normalizeEan13('789100000001'))->toBe('7891000000014');     // 12 → completa check
    expect($svc->normalizeEan13('7891000000014'))->toBe('7891000000014');    // 13 válido → unchanged
    expect($svc->normalizeEan13('789-100-000-001'))->toBe('7891000000014');  // separadores ignorados
});

// ============================================================
// EtiquetaTagService — ZPL output
// ============================================================

it('gerarEtiqueta retorna ZPL com campos obrigatórios', function () {
    $svc = new EtiquetaTagService();

    $result = $svc->gerarEtiqueta(
        productId: 42,
        variationId: 7,
        opts: [
            'nome'    => 'Camiseta Básica',
            'tamanho' => 'M',
            'cor'     => 'Azul Marinho',
            'colecao' => 'Verão 2026',
            'preco'   => 89.90,
            'sku'     => 'CAMI-042-M-AZU',
            'businessId' => 4,
        ]
    );

    expect($result)->toHaveKeys(['zpl', 'ean13', 'sku', 'meta']);
    expect($result['zpl'])->toStartWith('^XA')->toEndWith('^XZ');
    expect($result['zpl'])->toContain('Camiseta Básica');
    expect($result['zpl'])->toContain('TAM: M');
    expect($result['zpl'])->toContain('COR: Azul Marinho');
    expect($result['zpl'])->toContain('Verão 2026');
    expect($result['zpl'])->toContain('R$ 89,90');
    expect($result['zpl'])->toContain('^BEN'); // EAN-13 barcode marker
    expect($result['zpl'])->toContain($result['ean13']);
    expect($result['zpl'])->toContain('CAMI-042-M-AZU');

    expect(strlen($result['ean13']))->toBe(13);
    expect($svc->validateEan13($result['ean13']))->toBeTrue();

    expect($result['meta']['business_id'])->toBe(4);
    expect($result['meta']['product_id'])->toBe(42);
});

it('gerarEtiqueta aceita ean13 customizado', function () {
    $svc = new EtiquetaTagService();

    // Calcula EAN-13 válido em runtime (evita vetores hard-coded errados)
    $eanCustom = (new EtiquetaTagService())->normalizeEan13('789100000001');

    $result = $svc->gerarEtiqueta(1, 1, [
        'ean13' => $eanCustom,
        'sku'   => 'TEST-001',
    ]);

    expect($result['ean13'])->toBe($eanCustom);
    expect($result['zpl'])->toContain($eanCustom);
});

it('gerarLote concatena múltiplas etiquetas ZPL', function () {
    $svc = new EtiquetaTagService();

    $items = [
        ['product_id' => 1, 'variation_id' => 1, 'opts' => ['nome' => 'P1', 'tamanho' => 'P']],
        ['product_id' => 2, 'variation_id' => 1, 'opts' => ['nome' => 'P2', 'tamanho' => 'M']],
        ['product_id' => 3, 'variation_id' => 1, 'opts' => ['nome' => 'P3', 'tamanho' => 'G']],
    ];

    $zpl = $svc->gerarLote($items);

    expect(substr_count($zpl, '^XA'))->toBe(3, '3 etiquetas = 3 ^XA');
    expect(substr_count($zpl, '^XZ'))->toBe(3, '3 etiquetas = 3 ^XZ');
    expect($zpl)->toContain('P1')->toContain('P2')->toContain('P3');
});

it('gerarLote rejeita array vazio', function () {
    (new EtiquetaTagService())->gerarLote([]);
})->throws(InvalidArgumentException::class, 'items vazio');

// ============================================================
// GradeCurvaService
// ============================================================

it('listarCurvas retorna defaults BR quando sem customs', function () {
    $resolver = new VestuarioSettingsResolver();
    $svc      = new GradeCurvaService($resolver);

    $curvas = $svc->listarCurvas(businessId: 1);

    expect($curvas)->toHaveKeys([
        'adulto_basico',
        'adulto_extendido',
        'infantil_idade',
        'feminino_numerico',
        'masculino_numerico',
    ]);

    expect($curvas['adulto_basico']['tamanhos'])->toBe(['PP', 'P', 'M', 'G', 'GG']);
    expect($curvas['adulto_basico']['proporcao'])->toBe([1, 2, 3, 3, 2]);
});

it('aplicarCurva gera matriz tamanho × cor correta', function () {
    $svc = new GradeCurvaService(new VestuarioSettingsResolver());

    $matrix = $svc->aplicarCurva(
        productId: 100,
        tamanhos: ['P', 'M', 'G'],
        cores: ['Azul', 'Preto'],
        quantidades: 5
    );

    // 3 tamanhos × 2 cores = 6 variations
    expect($matrix)->toHaveCount(6);

    // Cada item tem keys obrigatórias
    foreach ($matrix as $row) {
        expect($row)->toHaveKeys(['tamanho', 'cor', 'quantidade', 'sku']);
        expect($row['quantidade'])->toBe(5);
        expect($row['sku'])->toStartWith('VST-100-');
    }

    // SKUs únicos
    $skus = array_column($matrix, 'sku');
    expect(array_unique($skus))->toHaveCount(6);
});

it('aplicarCurva respeita quantidades paralelas a tamanhos', function () {
    $svc = new GradeCurvaService(new VestuarioSettingsResolver());

    $matrix = $svc->aplicarCurva(
        productId: 200,
        tamanhos: ['PP', 'P', 'M', 'G', 'GG'],
        cores: ['Branco'],
        quantidades: [1, 2, 3, 3, 2] // curva adulto_basico
    );

    expect($matrix)->toHaveCount(5);
    expect($matrix[0])->toMatchArray(['tamanho' => 'PP', 'quantidade' => 1]);
    expect($matrix[1])->toMatchArray(['tamanho' => 'P', 'quantidade' => 2]);
    expect($matrix[2])->toMatchArray(['tamanho' => 'M', 'quantidade' => 3]);
});

it('aplicarCurva rejeita quantidades com tamanho diferente de tamanhos', function () {
    $svc = new GradeCurvaService(new VestuarioSettingsResolver());

    $svc->aplicarCurva(1, ['P', 'M'], ['Azul'], [1, 2, 3]); // 3 quantidades pra 2 tamanhos
})->throws(InvalidArgumentException::class, 'quantidades deve ter mesmo tamanho');

it('calcularProporcao distribui pecas conforme curva', function () {
    $svc = new GradeCurvaService(new VestuarioSettingsResolver());

    // Curva adulto_basico: proporção [1,2,3,3,2] soma 11
    // total=110 → exato: PP=10, P=20, M=30, G=30, GG=20
    $dist = $svc->calcularProporcao('adulto_basico', totalPecas: 110, businessId: 1);

    expect($dist)->toBe([
        'PP' => 10,
        'P'  => 20,
        'M'  => 30,
        'G'  => 30,
        'GG' => 20,
    ]);
    expect(array_sum($dist))->toBe(110);
});

it('calcularProporcao distribui resto pros tamanhos populares', function () {
    $svc = new GradeCurvaService(new VestuarioSettingsResolver());

    // total=12, curva soma 11 → resto 1 vai pro M ou G (proporção 3, maior)
    $dist = $svc->calcularProporcao('adulto_basico', totalPecas: 12, businessId: 1);

    expect(array_sum($dist))->toBe(12);
    // 1 unidade extra vai pro primeiro tam com maior proporção (M=3)
    expect($dist['M'])->toBeGreaterThanOrEqual(3);
});

it('calcularProporcao rejeita curva inexistente', function () {
    $svc = new GradeCurvaService(new VestuarioSettingsResolver());
    $svc->calcularProporcao('curva_que_nao_existe', 100, 1);
})->throws(InvalidArgumentException::class, 'não encontrada');

// ============================================================
// Multi-tenant Tier 0
// ============================================================

it('listarCurvas merge customs per-business sem cross-tenant leak', function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing');
    }

    // Biz 1: adiciona curva custom
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings' => json_encode([
                'grades' => [
                    'curvas' => [
                        'biz1_only' => [
                            'nome'      => 'Custom Biz 1',
                            'tamanhos'  => ['ÚNICO'],
                            'proporcao' => [1],
                        ],
                    ],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    // Biz 99: sem customs
    DB::table('vestuario_settings')->where('business_id', 99)->delete();

    $svc = new GradeCurvaService(new VestuarioSettingsResolver());

    $biz1Curvas = $svc->listarCurvas(1);
    $biz99Curvas = $svc->listarCurvas(99);

    expect($biz1Curvas)->toHaveKey('biz1_only');
    expect($biz99Curvas)->not->toHaveKey('biz1_only');

    // Defaults sempre presentes
    expect($biz1Curvas)->toHaveKey('adulto_basico');
    expect($biz99Curvas)->toHaveKey('adulto_basico');

    // Cleanup
    DB::table('vestuario_settings')->whereIn('business_id', [1, 99])->delete();
});
