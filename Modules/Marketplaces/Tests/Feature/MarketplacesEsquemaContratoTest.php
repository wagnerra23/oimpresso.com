<?php

declare(strict_types=1);
// @covers-us US-MKT-001

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da CAMADA DE DADOS do Marketplaces (US-MKT-001 · ADR 0439 · SPEC §3): tabelas com
 * business_id, escopo dos models entre os tenants 98 e 99 (ADR 0358), token cifrado, pedido
 * único por id externo e log de webhook append-only. Sem rota nem tela (US seguintes).
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS + trigger MySQL (LC-13). A lane é verticais-pest (MySQL).
 */
const MKT_TABELAS_DA_EMPRESA = ['mkt_account_credentials', 'mkt_listings', 'mkt_orders', 'mkt_webhook_log'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS + trigger append-only (ADR 0358).');
    }
    foreach (['mkt_marketplaces', ...MKT_TABELAS_DA_EMPRESA] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — migrations do Modules/Marketplaces não rodaram.");
        }
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes(['marketplaces.access'], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes(['marketplaces.access'], $this->seededSupportClientTenant());
    // Catálogo é somente leitura pelo Model: a fixture entra por DB::table, como o seed.
    DB::table('mkt_marketplaces')->insertOrIgnore(['slug' => 'mercado_livre_teste', 'nome' => 'Mercado Livre (teste)', 'created_at' => now(), 'updated_at' => now()]);
    $this->ml = Marketplace::query()->where('slug', 'mercado_livre_teste')->firstOrFail();
});

it('US-MKT-001 · catálogo global: o Model não cria, não altera e não apaga', function () {
    expect(fn () => Marketplace::query()->create(['slug' => 'outro_'.uniqid(), 'nome' => 'Outro']))->toThrow(LogicException::class);
    expect(fn () => $this->ml->forceFill(['nome' => 'Alterado'])->save())->toThrow(LogicException::class);
    expect(fn () => $this->ml->delete())->toThrow(LogicException::class);
    expect(DB::table('mkt_marketplaces')->where('id', $this->ml->id)->value('nome'))->toBe('Mercado Livre (teste)');
});

function mktComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

/** Conta + pedido + webhook para o business dado. @return array{conta: ContaVendedor, pedido: PedidoMarketplace} */
function mktSemear(int $biz, int $marketplaceId): array
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    $conta = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $biz, 'marketplace_id' => $marketplaceId,
        'seller_id_external' => 'seller-'.uniqid(), 'access_token' => 'tok-acesso-'.$biz,
        'refresh_token' => 'tok-refresh-'.$biz,
    ]);
    $pedido = PedidoMarketplace::withoutGlobalScopes()->create([
        'business_id' => $biz, 'account_id' => $conta->id, 'marketplace_id' => $marketplaceId,
        'order_id_external' => 'ord-'.uniqid(), 'status_external' => 'paid',
    ]);
    WebhookRecebido::withoutGlobalScopes()->create([
        'business_id' => $biz, 'marketplace_id' => $marketplaceId, 'topic' => 'orders_v2',
        'resource_external' => '/orders/'.$pedido->order_id_external, 'received_at' => now(),
    ]);

    return ['conta' => $conta, 'pedido' => $pedido];
}

it('US-MKT-001 · toda tabela da empresa tem business_id com FK para business', function () {
    $fks = collect(DB::select(
        "SELECT TABLE_NAME AS t FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'business_id' AND REFERENCED_TABLE_NAME = 'business'"
    ))->pluck('t')->all();

    foreach (MKT_TABELAS_DA_EMPRESA as $t) {
        expect(Schema::hasColumn($t, 'business_id'))->toBeTrue()
            ->and($fks)->toContain($t);
    }
    // O catálogo é global por desenho: nenhuma coluna business_id.
    expect(Schema::hasColumn('mkt_marketplaces', 'business_id'))->toBeFalse();
});

it('US-MKT-001 · sessão da 98 enxerga só a 98 em conta, pedido e webhook', function () {
    $a98 = mktSemear($this->b98, $this->ml->id);
    $a99 = mktSemear($this->b99, $this->ml->id);

    mktComo($this, $this->u98);
    foreach ([ContaVendedor::class, PedidoMarketplace::class, WebhookRecebido::class] as $model) {
        expect($model::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98]);
    }
    expect(PedidoMarketplace::query()->find($a99['pedido']->id))->toBeNull();

    // Discriminante: como 99, o filtro troca de lado em vez de esvaziar.
    mktComo($this, $this->u99);
    expect(PedidoMarketplace::query()->pluck('id')->all())->toContain($a99['pedido']->id)->not->toContain($a98['pedido']->id);

    // Controle negativo: sem escopo as duas linhas existem — o filtro acima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(PedidoMarketplace::withoutGlobalScopes()->whereIn('id', [$a98['pedido']->id, $a99['pedido']->id])->count())->toBe(2);
});

it('US-MKT-001 · token do vendedor fica cifrado no banco e fora do JSON', function () {
    $conta = mktSemear($this->b98, $this->ml->id)['conta'];

    $cru = DB::table('mkt_account_credentials')->where('id', $conta->id)->value('access_token');
    expect($cru)->not->toBe('tok-acesso-'.$this->b98)
        ->and(str_contains((string) $cru, 'tok-acesso'))->toBeFalse();

    // SUPERADMIN: releitura direta da linha semeada.
    $relida = ContaVendedor::withoutGlobalScopes()->find($conta->id);
    expect($relida->access_token)->toBe('tok-acesso-'.$this->b98)
        ->and($relida->toArray())->not->toHaveKey('access_token')
        ->and($relida->toArray())->not->toHaveKey('refresh_token');
});

it('US-MKT-001 · o mesmo pedido externo não entra duas vezes na mesma empresa', function () {
    $pedido = mktSemear($this->b98, $this->ml->id)['pedido'];

    expect(fn () => PedidoMarketplace::withoutGlobalScopes()->create([ // SUPERADMIN: fixture
        'business_id' => $this->b98, 'account_id' => $pedido->account_id, 'marketplace_id' => $this->ml->id,
        'order_id_external' => $pedido->order_id_external,
    ]))->toThrow(QueryException::class);
});

it('US-MKT-001 · log de webhook é append-only: UPDATE e DELETE são recusados pelo banco', function () {
    mktSemear($this->b98, $this->ml->id);
    $id = (int) DB::table('mkt_webhook_log')->where('business_id', $this->b98)->max('id');

    expect(fn () => DB::table('mkt_webhook_log')->where('id', $id)->update(['processing_status' => 'processed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('mkt_webhook_log')->where('id', $id)->delete())
        ->toThrow(QueryException::class);
    expect(DB::table('mkt_webhook_log')->where('id', $id)->value('processing_status'))->toBe('pending');
});
