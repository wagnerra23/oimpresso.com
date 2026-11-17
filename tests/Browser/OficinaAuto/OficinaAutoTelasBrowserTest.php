<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — comportamento + acessibilidade das telas da Oficina que o cliente e a
 * equipe usam: aprovação pública do orçamento, Quadro de OS e detalhe da OS.
 *
 * O que nenhuma suíte Feature alcança é o render real: o servidor destas telas já está
 * coberto em Modules/OficinaAuto/Tests/Feature, mas ninguém abria a tela num browser nem
 * rodava axe nela (auditoria 2026-11-05, eixo 1, pendência 6).
 *
 * Sem comparação de pixel (ADR 0445): zero `screenshot()`, e estas telas ficam fora de
 * `tests/Browser/visreg-screens.json`. O critério é comportamento + axe.
 *
 * Tenant 98 (ADR 0358): o `VisregEmptyTenantSeeder` cria o business 98 e o admin id=98. A
 * fixture nasce aqui porque o tenant é vazio por construção, e sai no afterEach.
 *
 * Lane: step "E2E de comportamento · OficinaAuto" do visual-regression.yml. Pest Browser é
 * CI-only (o container do CT 100 não tem chromium).
 */

use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\AprovacaoOsService;

const BRW_BIZ = 98;
const BRW_ADMIN = 98;
const BRW_PLACA = 'BRW98A1';
const BRW_ITEM = 'Troca de pastilha de freio';

// O phpunit.xml fixa sqlite e o job não exporta DB_CONNECTION (idioma das suítes de browser).
beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');

    if (! DB::table('users')->where('id', BRW_ADMIN)->where('business_id', BRW_BIZ)->exists()) {
        $this->markTestSkipped('admin do tenant 98 ausente — VisregEmptyTenantSeeder não rodou.');
    }

    session(['user.business_id' => BRW_BIZ]);
    (new OficinaAutoFsmSeeder())->runForBusiness(BRW_BIZ);

    $veiculo = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture no tenant de teste
        'business_id'    => BRW_BIZ,
        'plate'          => BRW_PLACA,
        'vehicle_type'   => 'caminhao',
        'current_status' => 'manutencao',
    ]);
    $this->os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture no tenant de teste
        'business_id' => BRW_BIZ,
        'vehicle_id'  => $veiculo->id,
        'order_type'  => 'mecanica',
        'status'      => 'orcamento',
        'entered_at'  => now()->subDay(),
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([ // SUPERADMIN: fixture no tenant de teste
        'business_id'      => BRW_BIZ,
        'service_order_id' => $this->os->id,
        'tipo'             => 'peca',
        'descricao'        => BRW_ITEM,
        'quantidade'       => 2,
        'valor_unitario'   => 50,
        'valor_total'      => 100,
    ]);
});

afterEach(function () {
    $ids = Vehicle::withoutGlobalScopes()->where('plate', BRW_PLACA)->pluck('id')->all(); // SUPERADMIN: limpeza da placa do teste
    if ($ids === []) {
        return;
    }
    $osIds = ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $ids)->pluck('id')->all();
    ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
    Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->update(['current_rental_id' => null]);
    ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
});

function brwAbrir(string $rota)
{
    return visit('/_visreg-login/'.BRW_ADMIN.'?to='.urlencode($rota))->resize(1280, 800);
}

// ── Aprovação pública (sem login) ────────────────────────────────────────────

it('aprovação pública · antes do PIN mostra só o campo, sem itens nem total', function () {
    $g = app(AprovacaoOsService::class)->gerarTokenAprovacao($this->os);

    visit('/aprovar-os/'.$g['token'])
        ->resize(390, 780)
        ->assertSee('Ver orçamento')
        ->assertVisible('#pin')
        ->assertDontSee(BRW_ITEM)
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('aprovação pública · com o PIN certo mostra itens e total, e os botões de decisão', function () {
    $g = app(AprovacaoOsService::class)->gerarTokenAprovacao($this->os);

    visit('/aprovar-os/'.$g['token'])
        ->resize(390, 780)
        ->assertSee('Ver orçamento')
        ->fill('#pin', $g['pin'])
        ->press('Ver orçamento')
        ->assertSee(BRW_ITEM)
        ->assertSee('100,00')
        ->assertSee('Recusar')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('aprovação pública · link inválido não mostra dado da OS', function () {
    visit('/aprovar-os/token-que-nao-existe')
        ->resize(390, 780)
        ->assertSee('expirou')
        ->assertDontSee(BRW_ITEM)
        ->assertNoAccessibilityIssues(level: 1);
});

// ── Quadro de OS ─────────────────────────────────────────────────────────────

it('Quadro · a OS aparece no kanban e a tela passa no axe', function () {
    brwAbrir('/oficina-auto/ordens-servico/board')
        ->assertSee(BRW_PLACA)
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('Quadro · não há rolagem horizontal da página a 1280px', function () {
    $largura = (string) brwAbrir('/oficina-auto/ordens-servico/board')
        ->assertSee(BRW_PLACA)
        ->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth ? "cabe" : "vaza"');

    expect($largura)->toBe('cabe');
});

// ── Detalhe da OS ────────────────────────────────────────────────────────────

it('Detalhe da OS · mostra placa e o item lançado, e passa no axe', function () {
    brwAbrir('/oficina-auto/ordens-servico/'.$this->os->id)
        ->assertSee(BRW_PLACA)
        ->assertSee(BRW_ITEM)
        ->assertVisible('[data-testid="add-item-button"]')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});
