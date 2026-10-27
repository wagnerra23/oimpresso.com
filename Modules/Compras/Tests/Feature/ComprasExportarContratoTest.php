<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * UC-CMP-11 — o botão "Exportar" do cockpit /compras baixa a lista de compras.
 * Contrato: SDD-tela-cockpit-compras-v1.0 §0.2 + F6 e CU-COM-07 item 4. Casos:
 * resources/js/Pages/Compras/Index.casos.md (UC-CMP-11).
 *
 * Histórico: até 2026-10-27 o botão abria `/purchases?classico=1`, a Blade da lista, que
 * exportava pelos botões do DataTable. O cutover apagou essa Blade; a exportação virou
 * `PurchaseController@exportar` (GET /purchases/exportar), que reaproveita os filtros e os
 * escopos da lista React (`listaFiltrada`).
 *
 * Como mede: lê o endereço que o `Index.tsx` passa ao `window.open` e faz a MESMA navegação
 * da nova aba. Exige CSV com BOM e o cabeçalho em pt-BR. Com o endereço antigo o path não
 * bate e o 1º assert cai — o caso discrimina (§5 2026-09-05).
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

it('UC-CMP-11 · o endereço do Exportar baixa o CSV da lista de compras', function () {
    $endereco = cexpEnderecoDoExportar();

    // Controle anti-vácuo: o endereço tem de ser a exportação da lista, não outra rota.
    // Antes do cutover de 2026-10-27 era `/purchases?classico=1` (a Blade com DataTable).
    expect(parse_url($endereco, PHP_URL_PATH))->toBe('/purchases/exportar');

    $resp = $this->actingAs($this->user)->get($endereco);
    $resp->assertOk();
    expect((string) $resp->headers->get('Content-Type'))->toContain('text/csv');

    $csv = $resp->streamedContent();
    // BOM UTF-8 + cabeçalho em pt-BR separado por `;` (o Excel em pt-BR abre certo).
    expect(bin2hex(substr($csv, 0, 3)))->toBe('efbbbf');
    $cabecalho = strtok(substr($csv, 3), PHP_EOL);
    expect(trim($cabecalho))->toBe('Data;Referência;Local;Fornecedor;Status;Pagamento;Total;Pago;"A pagar"');
});

it('UC-CMP-11 · sem permissão de ver compras a exportação dá 403', function () {
    $this->user->syncPermissions([]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user)->get('/purchases/exportar')->assertForbidden();
});
