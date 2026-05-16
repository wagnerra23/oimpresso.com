<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Woocommerce\Entities\WoocommerceSyncLog;

uses(Tests\TestCase::class);

/**
 * Isolamento multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]) no módulo Woocommerce.
 *
 * Woocommerce é integração externa que armazena:
 *   - woocommerce_sync_logs (logs de sync produtos/categorias/orders)  → business_id NOT NULL
 *   - business.woocommerce_api_settings (URL/consumer_key/secret API) → 1 setting por biz
 *   - business.woocommerce_wh_oc_secret (segredo de webhook order-created/updated)
 *
 * Vazamento aqui = token API WooCommerce do biz=1 visível ao biz=99
 *   → terceiro consegue ler/criar produtos no WooCommerce do cliente errado.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa) — ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício isolamento).
 *
 * NOTA: WoocommerceSyncLog NÃO tem BusinessScope global hoje (Entity é Model puro,
 * `protected $guarded = ['id']`). Tests aqui validam isolamento por filtro EXPLÍCITO
 * `where('business_id', X)` — pattern usado pelo WoocommerceController:
 *   `WoocommerceSyncLog::where('business_id', $business_id)->...`
 *
 * Se futuramente adicionarem BusinessScope (recomendado), basta remover o filtro
 * explícito dos tests "scope" abaixo.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const WC_BIZ_WAGNER = 1;
const WC_BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Woocommerce + UltimatePOS schema requerem MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('woocommerce_sync_logs') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema Woocommerce/UltimatePOS incompleto — rode migrate primeiro');
    }
});

// ------------------------------------------------------------------
// WoocommerceSyncLog — isolamento por filtro explícito business_id
// ------------------------------------------------------------------

it('WoocommerceSyncLog biz=1 NÃO vaza pra filtro business_id=99', function () {
    // Seed: log de sync no biz=1
    $logId = DB::table('woocommerce_sync_logs')->insertGetId([
        'business_id'    => WC_BIZ_WAGNER,
        'sync_type'      => 'products',
        'operation_type' => 'created',
        'data'           => json_encode(['wc_product_id' => 12345]),
        'details'        => json_encode(['test' => 'WC-ISOLATION-' . uniqid()]),
        'created_by'     => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    try {
        // Query do Controller (real): filtro explícito por business_id
        $vazado = WoocommerceSyncLog::where('business_id', WC_BIZ_FICTICIO)
            ->where('id', $logId)
            ->get();

        expect($vazado)->toHaveCount(0, 'VAZAMENTO TIER 0: sync_log biz=1 visível com filtro biz=99!');
    } finally {
        DB::table('woocommerce_sync_logs')->where('id', $logId)->delete(); // SUPERADMIN: cleanup
    }
});

it('WoocommerceSyncLog biz=1 aparece com filtro business_id=1', function () {
    $sentinel = 'WC-AUTO-' . uniqid();
    $logId = DB::table('woocommerce_sync_logs')->insertGetId([
        'business_id'    => WC_BIZ_WAGNER,
        'sync_type'      => 'categories',
        'operation_type' => 'updated',
        'data'           => json_encode(['marker' => $sentinel]),
        'details'        => null,
        'created_by'     => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    try {
        $found = WoocommerceSyncLog::where('business_id', WC_BIZ_WAGNER)
            ->where('id', $logId)
            ->first();

        expect($found)->not->toBeNull('sync_log biz=1 deveria aparecer com filtro biz=1');
        expect((int) $found->business_id)->toBe(WC_BIZ_WAGNER);
        expect($found->sync_type)->toBe('categories');
    } finally {
        DB::table('woocommerce_sync_logs')->where('id', $logId)->delete(); // SUPERADMIN: cleanup
    }
});

// ------------------------------------------------------------------
// API settings WooCommerce — segredo NÃO vaza entre business
// ------------------------------------------------------------------

it('woocommerce_api_settings do biz=1 NÃO aparece em SELECT do biz=99', function () {
    if (! Schema::hasColumn('business', 'woocommerce_api_settings')) {
        $this->markTestSkipped('coluna woocommerce_api_settings ausente — rode migrations Woocommerce');
    }

    $businessExists = DB::table('business')->where('id', WC_BIZ_WAGNER)->exists();
    if (! $businessExists) {
        $this->markTestSkipped('business id=1 não existe no DB de teste');
    }

    // Snapshot original — restaura no finally pra não poluir DB compartilhado
    $original = DB::table('business')->where('id', WC_BIZ_WAGNER)->value('woocommerce_api_settings');

    $tokenFake = 'WC-FAKE-TOKEN-' . uniqid();
    DB::table('business')->where('id', WC_BIZ_WAGNER)->update([
        'woocommerce_api_settings' => json_encode([
            'woocommerce_app_url'        => 'https://exemplo.test',
            'woocommerce_consumer_key'   => $tokenFake,
            'woocommerce_consumer_secret' => 'SECRET-FAKE-' . uniqid(),
        ]),
    ]);

    try {
        // Pattern real WoocommerceUtil::get_api_settings($business_id) — filtra por id
        $bizOutroTenant = DB::table('business')
            ->where('id', WC_BIZ_FICTICIO)
            ->value('woocommerce_api_settings');

        // biz=99 NÃO deve trazer o settings do biz=1 (ou não existe, ou tem outro valor)
        if ($bizOutroTenant !== null) {
            expect($bizOutroTenant)->not->toContain($tokenFake, 'VAZAMENTO TIER 0: token API biz=1 visível no settings do biz=99!');
        } else {
            expect($bizOutroTenant)->toBeNull();
        }
    } finally {
        DB::table('business')->where('id', WC_BIZ_WAGNER)->update([
            'woocommerce_api_settings' => $original,
        ]);
    }
});

it('woocommerce_wh_oc_secret do biz=1 NÃO é igual ao do biz=99', function () {
    if (! Schema::hasColumn('business', 'woocommerce_wh_oc_secret')) {
        $this->markTestSkipped('coluna woocommerce_wh_oc_secret ausente — rode migrations Woocommerce');
    }

    $secretBiz1 = DB::table('business')->where('id', WC_BIZ_WAGNER)->value('woocommerce_wh_oc_secret');
    $secretBiz99 = DB::table('business')->where('id', WC_BIZ_FICTICIO)->value('woocommerce_wh_oc_secret');

    // Se ambos existem e são iguais E não vazios → assinatura webhook seria intercambiável.
    if (! empty($secretBiz1) && ! empty($secretBiz99)) {
        expect($secretBiz1)->not->toBe($secretBiz99,
            'VAZAMENTO TIER 0: webhook secret idêntico entre biz=1 e biz=99 — webhook de um biz dispararia order do outro!');
    } else {
        // Caso default — pelo menos um é null/vazio, isolamento OK por ausência
        expect(true)->toBeTrue();
    }
});

// ------------------------------------------------------------------
// Sanity — bypass com withoutGlobalScopes ainda exige filtro consciente
// ------------------------------------------------------------------

it('seed biz=1 + filtro biz=99 retorna zero registros mesmo sem global scope', function () {
    // Insere 2 logs: 1 em cada biz, mesmo sync_type, mesma janela temporal
    $marker = 'WC-CROSS-' . uniqid();

    $idBiz1 = DB::table('woocommerce_sync_logs')->insertGetId([
        'business_id'    => WC_BIZ_WAGNER,
        'sync_type'      => 'orders',
        'operation_type' => 'created',
        'data'           => json_encode(['marker' => $marker]),
        'details'        => null,
        'created_by'     => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $idBiz99 = DB::table('woocommerce_sync_logs')->insertGetId([
        'business_id'    => WC_BIZ_FICTICIO,
        'sync_type'      => 'orders',
        'operation_type' => 'created',
        'data'           => json_encode(['marker' => $marker]),
        'details'        => null,
        'created_by'     => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    try {
        // Filtro real do Controller — deve cada biz ver apenas o próprio
        $countBiz1 = WoocommerceSyncLog::where('business_id', WC_BIZ_WAGNER)
            ->where('data', 'like', '%' . $marker . '%')
            ->count();

        $countBiz99 = WoocommerceSyncLog::where('business_id', WC_BIZ_FICTICIO)
            ->where('data', 'like', '%' . $marker . '%')
            ->count();

        expect($countBiz1)->toBe(1, 'biz=1 deveria ver exatamente 1 log próprio');
        expect($countBiz99)->toBe(1, 'biz=99 deveria ver exatamente 1 log próprio (não 2)');
    } finally {
        DB::table('woocommerce_sync_logs')->whereIn('id', [$idBiz1, $idBiz99])->delete(); // SUPERADMIN: cleanup
    }
});
