<?php

declare(strict_types=1);

// Cobre UC-PCAD-24 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-24
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Campos personalizados com o nome que a empresa deu, no Novo e no Editar.
 *
 * ÂNCORA (contrato, não implementação): protótipo do [W] (`produto-blade-forms.jsx`, nota D7 de
 * 2026-10-05: "rótulo vem do cadastro do negócio; sem rótulo = oculto"), pedido do [F] em
 * 2026-11-16, e a tela de rótulos da empresa (`settings_custom_labels.blade.php`), que guarda o
 * nome em `custom_labels.product.custom_field_N` e o tipo em `custom_labels.product_cf_details.N`.
 *
 * O que cada caso trava:
 *   · só os campos com nome chegam à tela, com o número, o nome e o tipo (texto, data ou lista);
 *   · a lista chega com as opções, uma por linha, sem linha vazia; lista sem opções vira texto;
 *   · empresa sem nenhum nome não manda campo nenhum.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pccpVisitaInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

/** Os rótulos como a tela de Configurações → Rótulos grava (JSON na coluna `business.custom_labels`). */
function pccpRotulos(): string
{
    return (string) json_encode([
        'product' => ['custom_field_1' => '', 'custom_field_3' => 'Cor da lona', 'custom_field_7' => 'Validade do lote', 'custom_field_12' => 'Acabamento', 'custom_field_15' => 'Sem opções'],
        'product_cf_details' => [
            3 => ['type' => 'text'],
            7 => ['type' => 'date'],
            12 => ['type' => 'dropdown', 'dropdown_options' => "Fosco\r\nBrilho\r\n\r\n"],
            15 => ['type' => 'dropdown', 'dropdown_options' => ''],
        ],
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    try {
        $this->business = $this->seededTenant(); // biz=98 (ADR 0358 — nunca biz=4).
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }
    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
        'business.id' => $this->business->id,
        'business.custom_labels' => pccpRotulos(),
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);
});

$esperado = [
    ['numero' => 3, 'rotulo' => 'Cor da lona', 'tipo' => 'text', 'opcoes' => []],
    ['numero' => 7, 'rotulo' => 'Validade do lote', 'tipo' => 'date', 'opcoes' => []],
    ['numero' => 12, 'rotulo' => 'Acabamento', 'tipo' => 'dropdown', 'opcoes' => ['Fosco', 'Brilho']],
    ['numero' => 15, 'rotulo' => 'Sem opções', 'tipo' => 'text', 'opcoes' => []],
];

it('UC-PCAD-24 · o Novo recebe só os campos com nome, com o tipo e as opções', function () use ($esperado) {
    $this->withHeaders(pccpVisitaInertia())->get('/products/create')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Create')
        ->assertJsonPath('props.camposDaEmpresa', $esperado);
});

it('UC-PEDIT-24 · o Editar recebe só os campos com nome, com o tipo e as opções', function () use ($esperado) {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);

    $this->withHeaders(pccpVisitaInertia())->get('/products/' . $p->productId . '/edit')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Edit')
        ->assertJsonPath('props.camposDaEmpresa', $esperado);
});

it('UC-PCAD-24 · empresa sem nenhum nome não manda campo nenhum', function () {
    session(['business.custom_labels' => (string) json_encode(['product' => ['custom_field_1' => '', 'custom_field_2' => '  ']])]);

    $this->withHeaders(pccpVisitaInertia())->get('/products/create')
        ->assertOk()
        ->assertJsonPath('props.camposDaEmpresa', []);
});
