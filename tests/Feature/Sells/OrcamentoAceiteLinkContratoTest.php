<?php
// @covers-us US-SELL-074

declare(strict_types=1);

use App\Services\Sells\AceiteDoOrcamentoVenda;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Link para o cliente aprovar o orçamento da venda comum — US-SELL-074, fatia 1 (gerar + o que o
 * cliente vê). Casos UC-OAC-01..04 de `resources/js/Pages/Sells/Quotations.casos.md`.
 *
 * Conta à mão (relógio congelado em 2026-10-27 10:00, validade 7 dias):
 *   orçamento O: 2 un × 150,00 + 1 un × 49,90 · frete 10,00 · final_total 359,90
 *   gerar → token de 48 caracteres · expira 2026-11-03 10:00 · banco guarda só o sha256
 *   gerar de novo → o 1º link fecha (expira_em = agora) · o 2º segue aberto
 *   cliente vê: 2 itens · subtotais 300,00 e 49,90 · frete 10,00 · total 359,90 (lido, não recalculado)
 *   orçamento do 99 pelo usuário do 98 → 404 · venda final → 422 · flag desligada → 404
 *
 * Não muda valor nem estoque. Tenants: 98 (ADR 0358) × 99. Nunca biz=4.
 * Não roda local (proibicoes.md). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function oacUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'OAC Vendedor', 'username' => 'oac_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/** @param array<int, array{0: float, 1: float}> $linhas qtd × preço */
function oacOrcamento(int $bizId, array $linhas, float $frete, string $status = 'draft', ?string $sub = 'quotation'): int
{
    $soma = array_sum(array_map(fn ($l) => $l[0] * $l[1], $linhas));
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => (int) DB::table('contacts')->insertGetId([
            'business_id' => $bizId, 'type' => 'customer', 'name' => 'OAC Cliente', 'contact_status' => 'active',
            'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
        ]),
        'type' => 'sell', 'status' => $status, 'sub_status' => $sub, 'is_quotation' => $sub === 'quotation' ? 1 : 0,
        'is_direct_sale' => 1, 'payment_status' => 'due', 'invoice_no' => 'OAC-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => $soma, 'shipping_charges' => $frete,
        'final_total' => $soma + $frete, 'created_by' => EstoqueFixture::userId($bizId),
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Um produto por linha: `uk_tsl_dup_prevent` recusa dois produtos iguais na mesma venda.
    foreach ($linhas as [$qtd, $preco]) {
        $produto = EstoqueFixture::singleProduct($bizId);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $id, 'product_id' => $produto->productId,
            'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => $qtd, 'quantity_returned' => 0,
            'unit_price' => $preco, 'unit_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_sell_lines', 'transaction_orcamento_aceites'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — roda na lane MySQL / CT 100.");
        }
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00'));
    config(['sells.orcamento_aceite.enabled' => true, 'sells.orcamento_aceite.dias_validade' => 7]);

    $this->orcamento = oacOrcamento($this->bizId, [[2, 150.00], [1, 49.90]], 10.00);
    $this->usuario = oacUsuario($this->bizId, ['quotation.update']);
    $this->actingAs($this->usuario)->withSession([
        'user' => ['id' => $this->usuario->id, 'business_id' => $this->bizId],
        'business' => ['id' => $this->bizId, 'date_format' => 'd/m/Y', 'time_format' => 24, 'currency_precision' => 2, 'quantity_precision' => 2],
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-OAC-01 gerar devolve o link e guarda só o sha256 do token, com validade de 7 dias', function () {
    $r = $this->postJson("/sells/{$this->orcamento}/orcamento-link")->assertOk();
    $token = substr((string) $r->json('url'), -48);
    expect($token)->toMatch('/^[A-Za-z0-9]{48}$/')
        ->and((string) $r->json('url'))->toEndWith('/orcamento/' . $token);

    $link = DB::table('transaction_orcamento_aceites')->where('transaction_id', $this->orcamento)->sole();
    expect((int) $link->business_id)->toBe($this->bizId)
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->token_hash)->not->toContain($token)
        ->and((string) $link->expira_em)->toBe('2026-11-03 10:00:00')
        ->and($link->decisao)->toBeNull();
});

it('UC-OAC-02 gerar de novo fecha o link anterior e só o novo fica aberto', function () {
    $s = app(AceiteDoOrcamentoVenda::class);
    $primeiro = $s->gerar($this->bizId, $this->orcamento)['token'];
    Carbon::setTestNow(Carbon::parse('2026-10-27 11:00:00'));
    $segundo = $s->gerar($this->bizId, $this->orcamento)['token'];

    expect($s->paraCliente($primeiro)['situacao'])->toBe('vencido')
        ->and($s->paraCliente($segundo)['situacao'])->toBe('aberto');
});

it('UC-OAC-03 o cliente vê itens, frete e o total gravado, sem custo nem dado do cliente', function () {
    $token = app(AceiteDoOrcamentoVenda::class)->gerar($this->bizId, $this->orcamento)['token'];
    $v = app(AceiteDoOrcamentoVenda::class)->paraCliente($token);

    expect(array_column($v['itens'], 'subtotal'))->toBe([300.0, 49.9])
        ->and($v['frete'])->toBe(10.0)
        ->and($v['total'])->toBe(359.9)
        ->and(json_encode($v))->not->toContain('OAC Cliente')
        ->and(array_keys($v['itens'][0]))->toBe(['descricao', 'quantidade', 'preco_unitario', 'subtotal'])
        ->and(app(AceiteDoOrcamentoVenda::class)->paraCliente(str_repeat('a', 48)))->toBeNull();
});

it('UC-OAC-04 [T0] não gera link de orçamento de outra empresa, de venda final nem com a flag desligada', function () {
    $doOutro = oacOrcamento($this->outroBizId, [[1, 10.0]], 0.0);
    $final = oacOrcamento($this->bizId, [[1, 10.0]], 0.0, 'final', null);

    $this->postJson("/sells/{$doOutro}/orcamento-link")->assertNotFound();
    $this->postJson("/sells/{$final}/orcamento-link")->assertStatus(422);
    expect(DB::table('transaction_orcamento_aceites')->whereIn('transaction_id', [$doOutro, $final])->count())->toBe(0);

    config(['sells.orcamento_aceite.enabled' => false]);
    $this->postJson("/sells/{$this->orcamento}/orcamento-link")->assertNotFound();
    expect(DB::table('transaction_orcamento_aceites')->where('transaction_id', $this->orcamento)->count())->toBe(0);
});
