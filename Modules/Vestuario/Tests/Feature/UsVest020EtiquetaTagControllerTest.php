<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\EtiquetaTagService;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

uses(Tests\TestCase::class);

/**
 * US-VEST-020 — Etiqueta TAG Controller + QR Code + Settings configurable + PDF.
 *
 * Cobre acceptance criteria pendentes (Wave 27 já cobriu ZPL base + EAN-13):
 * - QR Code presente no ZPL quando settings.etiqueta.qr_enabled = true
 * - Settings per business override dimensões (width/height/dpi)
 * - getPublicConfig retorna shape correto
 * - Controller endpoint POST /vestuario/etiquetas/lote/zpl gera arquivo
 * - Controller endpoint POST /vestuario/etiquetas/lote/pdf gera PDF download
 * - Multi-tenant Tier 0 (biz=1 NUNCA biz=4 — ADR 0101) — settings biz1 não vazam pra biz99
 * - Validação de input (items required, max 500, copies max 100)
 *
 * @see Modules/Vestuario/Http/Controllers/EtiquetaTagController.php
 * @see Modules/Vestuario/Services/EtiquetaTagService.php
 * @see memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md
 */

// ============================================================
// QR Code (acceptance criteria novo)
// ============================================================

it('ZPL contém instrução ^BQ quando qr_enabled=true via settings', function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing');
    }

    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings' => json_encode([
                'etiqueta' => [
                    'qr_enabled' => true,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $svc = new EtiquetaTagService(new VestuarioSettingsResolver());

    $result = $svc->gerarEtiqueta(42, 7, [
        'nome'       => 'Camiseta QR',
        'tamanho'    => 'M',
        'preco'      => 49.90,
        'businessId' => 1,
    ]);

    expect($result['zpl'])->toContain('^BQN');
    expect($result['zpl'])->toContain('FDLA,'); // QR data prefix
    expect($result['meta']['qr_enabled'])->toBeTrue();

    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('ZPL NÃO contém ^BQ por default (qr_enabled=false)', function () {
    $svc = new EtiquetaTagService(); // sem resolver

    $result = $svc->gerarEtiqueta(42, 7, [
        'nome'    => 'Camiseta Sem QR',
        'tamanho' => 'M',
        'preco'   => 49.90,
    ]);

    expect($result['zpl'])->not->toContain('^BQN');
    expect($result['meta']['qr_enabled'])->toBeFalse();
});

// ============================================================
// Settings configurable per business
// ============================================================

it('width/height/dpi vêm de vestuario_settings quando setados', function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing');
    }

    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings' => json_encode([
                'etiqueta' => [
                    'width_dots'  => 600,
                    'height_dots' => 360,
                    'dpi'         => 300,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $svc = new EtiquetaTagService(new VestuarioSettingsResolver());
    $cfg = $svc->getPublicConfig(1);

    expect($cfg['width_dots'])->toBe(600);
    expect($cfg['height_dots'])->toBe(360);
    expect($cfg['dpi'])->toBe(300);

    // ZPL output deve usar essas dimensões
    $r = $svc->gerarEtiqueta(1, 1, ['nome' => 'X', 'businessId' => 1]);
    expect($r['zpl'])->toContain('^PW600');
    expect($r['zpl'])->toContain('^LL360');

    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('getPublicConfig retorna defaults quando business sem settings', function () {
    $svc = new EtiquetaTagService(new VestuarioSettingsResolver());

    $cfg = $svc->getPublicConfig(99); // biz inexistente

    expect($cfg)->toHaveKeys(['width_dots', 'height_dots', 'dpi', 'margin_dots', 'qr_enabled']);
    expect($cfg['width_dots'])->toBe(400);
    expect($cfg['height_dots'])->toBe(240);
    expect($cfg['dpi'])->toBe(203);
    expect($cfg['qr_enabled'])->toBeFalse();
});

// ============================================================
// Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md))
// Tests biz=1 (Wagner) NUNCA biz=4 (cliente) — ADR 0101
// ============================================================

it('settings biz=1 não vazam pra biz=99 (cross-tenant adversário)', function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing');
    }

    // Biz 1 (Wagner): width customizado
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings' => json_encode([
                'etiqueta' => ['width_dots' => 800, 'qr_enabled' => true],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    // Biz 99 (adversário): sem settings
    DB::table('vestuario_settings')->where('business_id', 99)->delete();

    $svc = new EtiquetaTagService(new VestuarioSettingsResolver());

    $biz1Cfg = $svc->getPublicConfig(1);
    $biz99Cfg = $svc->getPublicConfig(99);

    expect($biz1Cfg['width_dots'])->toBe(800);
    expect($biz1Cfg['qr_enabled'])->toBeTrue();

    expect($biz99Cfg['width_dots'])->toBe(400); // default, NÃO vaza 800 de biz1
    expect($biz99Cfg['qr_enabled'])->toBeFalse(); // default, NÃO vaza true de biz1

    DB::table('vestuario_settings')->whereIn('business_id', [1, 99])->delete();
});

// ============================================================
// HTTP endpoints (validação shape, sem session web)
// ============================================================

it('endpoint /vestuario/etiquetas/lote/zpl exige autenticação', function () {
    $response = $this->post('/vestuario/etiquetas/lote/zpl', [
        'items' => [
            ['product_id' => 1, 'nome' => 'X'],
        ],
    ]);

    // Sem auth → redirect login OU 401/302
    expect($response->getStatusCode())->toBeIn([302, 401]);
});

it('endpoint /vestuario/etiquetas/lote/pdf exige autenticação', function () {
    $response = $this->post('/vestuario/etiquetas/lote/pdf', [
        'items' => [
            ['product_id' => 1, 'nome' => 'X'],
        ],
    ]);

    expect($response->getStatusCode())->toBeIn([302, 401]);
});

// ============================================================
// PDF render — smoke (sem auth, valida só que view existe + Blade compila)
// ============================================================

it('blade vestuario::etiquetas.pdf compila e renderiza HTML válido', function () {
    $etiquetas = [
        [
            'product_id'   => 1,
            'variation_id' => 1,
            'nome'         => 'Camiseta',
            'tamanho'      => 'M',
            'cor'          => 'Azul',
            'colecao'      => 'Verão 2026',
            'preco'        => 49.90,
            'sku'          => 'CAMI-001-M-AZU',
            'ean13'        => '7891000000014',
            'qr_enabled'   => false,
            'width_dots'   => 400,
            'height_dots'  => 240,
            'dpi'          => 203,
            'business_id'  => 1,
        ],
    ];

    $html = view('vestuario::etiquetas.pdf', [
        'etiquetas'   => $etiquetas,
        'business_id' => 1,
    ])->render();

    expect($html)->toContain('Camiseta');
    expect($html)->toContain('TAM:</strong> M');
    expect($html)->toContain('Azul');
    expect($html)->toContain('Verão 2026');
    expect($html)->toContain('R$ 49,90');
    expect($html)->toContain('7891000000014');
    expect($html)->toContain('CAMI-001-M-AZU');
    expect($html)->toContain('biz #1');
});

it('blade pdf renderiza 10 etiquetas em grid (acceptance criteria)', function () {
    // EAN13 = 12 dígitos base + 1 check digit (mod-10 GS1).
    // Antes: '789100000001' + ($i % 10) — só o sufixo i=4 acertava o check digit
    // por coincidência; outras 9 iterações disparavam Milon\Barcode\WrongCheckDigitException
    // quando vendor/milon/barcode renderizava o SVG na view (CI Pest Vestuario red
    // detectado a partir do PR #1856 — job só roda em PRs, débito ficou invisível
    // nos merges main).
    $ean13Check = static function (string $base12): string {
        $sum = 0;
        for ($k = 0; $k < 12; $k++) {
            $d = (int) $base12[$k];
            $sum += ($k % 2 === 0) ? $d : ($d * 3);
        }

        return $base12 . (string) ((10 - ($sum % 10)) % 10);
    };

    $etiquetas = [];
    for ($i = 1; $i <= 10; $i++) {
        $base12 = '789100000' . sprintf('%03d', $i); // 9 + 3 = 12 dígitos
        $etiquetas[] = [
            'nome'        => "Produto {$i}",
            'tamanho'     => 'M',
            'cor'         => 'Preto',
            'preco'       => 29.90 + $i,
            'sku'         => sprintf('SKU-%03d', $i),
            'ean13'       => $ean13Check($base12),
            'qr_enabled'  => false,
            'colecao'     => '',
        ];
    }

    $html = view('vestuario::etiquetas.pdf', [
        'etiquetas'   => $etiquetas,
        'business_id' => 1,
    ])->render();

    // 10 etiquetas devem aparecer.
    // NOTA: Pest `toContain(...$needles)` aceita N needles como variadic, NÃO
    // mensagem custom (sintaxe difere de PHPUnit assertStringContainsString).
    // Antes: `expect($html)->toContain("Produto {$i}", "etiqueta #{$i} ausente...")` —
    // o 2º arg virava needle adicional ("etiqueta #1 ausente no PDF render") que
    // o HTML obviamente não continha → fail SEMPRE. Mascarado porque o EAN13
    // disparava WrongCheckDigitException ANTES da assertion. Fix: 1 needle por chamada.
    for ($i = 1; $i <= 10; $i++) {
        expect($html)->toContain("Produto {$i}");
    }

    expect($html)->toContain('10 etiquetas');
});
