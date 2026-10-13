<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * UC-CMP-11 — o botão "Exportar" do cockpit /compras abre a tela que EXPORTA.
 *
 * Contrato: SDD-tela-cockpit-compras-v1.0 §0.2 + F6 ("a Blade é o destino de
 * CSV/Excel/PDF/Imprimir do cockpit") e CU-COM-07 item 4 (bridges Blade-only por
 * `window.open`). Casos: resources/js/Pages/Compras/Index.casos.md (UC-CMP-11).
 *
 * Por que existe: o cutover pelo menu (#9095, D1 [W] 2026-10-07) fez GET comum em
 * `/purchases` abrir a Page React `Purchase/Index`, que NÃO exporta (charter dela:
 * "❌ Não exporta CSV/PDF"). O `openExportBlade` abria `/purchases` cru — passou a
 * cair numa tela sem exportação. Só `?classico=1` devolve a Blade com os botões.
 *
 * Como mede: lê o endereço que o `Index.tsx` de fato passa ao `window.open` e faz a
 * MESMA navegação que a nova aba faz (GET sem `X-Inertia`, sem AJAX). Exige a view
 * Blade `purchase.index`. Com o endereço antigo (`/purchases`) a resposta é Inertia
 * e o assert cai — o caso discrimina (§5 2026-09-05). A leitura do .tsx é a ponte
 * (Pest não executa JS); o veredito vem da rota real.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 *
 * @covers-uc UC-CMP-11
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

/** O primeiro argumento de `window.open(...)` dentro de `openExportBlade`. */
function cexpEnderecoDoExportar(): string
{
    $src = file_get_contents(base_path('resources/js/Pages/Compras/Index.tsx'));
    $ini = strpos($src, 'const openExportBlade');
    expect($ini)->not->toBeFalse();

    $corpo = substr($src, $ini, 1200);
    $ok = preg_match("/window\\.open\\(\\s*'([^']+)'/", $corpo, $m);
    expect($ok)->toBe(1);

    return $m[1];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'business', 'business_locations', 'users', 'permissions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $bizId = (int) $this->seededTenant()->id;

    $id = DB::table('users')->insertGetId([
        'first_name' => 'CEXP Exportar',
        'username' => 'cexp_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'user_type' => 'user',
        'allow_login' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['purchase.view', 'access_all_locations'] as $p) {
        Permission::findOrCreate($p, 'web');
        $this->user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    session([
        'user.business_id' => $bizId,
        'user.id' => $this->user->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);
});

it('UC-CMP-11 · o endereço do Exportar abre a Blade da lista de compras, que exporta', function () {
    $endereco = cexpEnderecoDoExportar();

    // Controle anti-vácuo: o endereço tem de ser a lista de compras, não outra rota.
    expect(parse_url($endereco, PHP_URL_PATH))->toBe('/purchases');

    $this->actingAs($this->user)
        ->get($endereco)
        ->assertOk()
        ->assertViewIs('purchase.index');
});
