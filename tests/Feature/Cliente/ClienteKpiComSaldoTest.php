<?php

declare(strict_types=1);
// Cobre UC-CIDX-27 (Index.casos.md) — "Com saldo" conta quem tem saldo a receber, não só os vencidos.

use App\Http\Controllers\ContactController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * A-15 (medição 2026-11-25, `memory/requisitos/Crm/cliente-index-visual-comparison.md`): o subtítulo
 * "N com saldo" e o KPI "Com saldo" liam `kpis.com_atraso`, que conta só venda VENCIDA
 * (`due_date < now`). O protótipo (`clientes-page.jsx`, `comSaldo` = clientes com saldo > 0) conta
 * quem tem saldo a receber, vencido ou não. A chave nova é `com_saldo`; `com_atraso` segue igual.
 *
 * Só contagem de contatos — nenhum valor muda. Tenant 98 (ADR 0358). Transação revertida por caso.
 * Não roda local (proibicoes.md): lane `PHP / Pest (Cliente · MySQL)`.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS MySQL ausente — roda na lane cliente-pest.yml.');
    }

    $this->bizId = EstoqueFixture::businessId();
    expect($this->bizId)->toBe(98);
    $this->outroBizId = EstoqueFixture::secondBusinessId();

    $this->user = $this->usuarioComPermissoes(['customer.view'], $this->seededTenant());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);
    $this->prefixo = 'CSAL'.strtoupper(substr(uniqid(), -6)).'-';
});

function csalCliente(object $t, string $nome): int
{
    $linha = [
        'business_id' => $t->bizId, 'type' => 'customer', 'name' => $t->prefixo.$nome,
        'contact_status' => 'active', 'created_by' => $t->user->id,
        'created_at' => now(), 'updated_at' => now(),
    ];
    if (Schema::hasColumn('contacts', 'is_customer')) {
        $linha['is_customer'] = 1;
    }

    return (int) DB::table('contacts')->insertGetId($linha);
}

function csalVenda(object $t, int $bizId, int $contactId, string $paymentStatus, string $dueDate, ?string $subType = null): void
{
    DB::table('transactions')->insert([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId, 'CSAL'),
        'contact_id' => $contactId,
        'created_by' => $t->user->id,
        'type' => 'sell',
        'sub_type' => $subType,
        'status' => 'final',
        'payment_status' => $paymentStatus,
        'is_direct_sale' => 1,
        'invoice_no' => $t->prefixo.uniqid(),
        'transaction_date' => now(),
        'due_date' => $dueDate,
        'total_before_tax' => 100,
        'final_total' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function csalKpis(int $bizId): array
{
    $m = new ReflectionMethod(ContactController::class, 'buildClienteIndexKpis');
    $m->setAccessible(true);

    return $m->invoke(app(ContactController::class), $bizId, 'customer');
}

it('UC-CIDX-27 "Com saldo" conta quem tem saldo a receber, vencido ou não; "em atraso" segue só os vencidos', function () {
    $antes = csalKpis($this->bizId);

    $aVencer = csalCliente($this, 'AVencer');
    csalVenda($this, $this->bizId, $aVencer, 'due', now()->addDays(30)->toDateTimeString());

    $vencido = csalCliente($this, 'Vencido');
    csalVenda($this, $this->bizId, $vencido, 'partial', now()->subDays(10)->toDateTimeString());

    $pago = csalCliente($this, 'Pago');
    csalVenda($this, $this->bizId, $pago, 'paid', now()->subDays(10)->toDateTimeString());

    // Bonificação não é dívida (UC-SBON-09): o cliente só com ela não tem saldo.
    $soBonif = csalCliente($this, 'SoBonif');
    csalVenda($this, $this->bizId, $soBonif, 'due', now()->subDays(10)->toDateTimeString(), 'bonificacao');

    $depois = csalKpis($this->bizId);

    // Caso que discrimina: o cliente "a vencer" entra em com_saldo e NÃO em com_atraso.
    expect((int) $depois['com_saldo'] - (int) $antes['com_saldo'])->toBe(2)
        ->and((int) $depois['com_atraso'] - (int) $antes['com_atraso'])->toBe(1);
});

it('UC-CIDX-27 [T0] venda de outra empresa não põe o cliente em "Com saldo"', function () {
    if ($this->outroBizId === null) {
        $this->markTestSkipped('Segunda empresa não semeada — o caso cross-tenant precisa dela.');
    }

    $antes = csalKpis($this->bizId);

    // Contato desta empresa sem venda aqui; a única venda em aberto está gravada na outra empresa.
    $cliente = csalCliente($this, 'SoOutraEmpresa');
    csalVenda($this, $this->outroBizId, $cliente, 'due', now()->addDays(30)->toDateTimeString());

    expect((int) csalKpis($this->bizId)['com_saldo'] - (int) $antes['com_saldo'])->toBe(0);

    // Controle positivo: a mesma venda nesta empresa conta.
    csalVenda($this, $this->bizId, $cliente, 'due', now()->addDays(30)->toDateTimeString());
    expect((int) csalKpis($this->bizId)['com_saldo'] - (int) $antes['com_saldo'])->toBe(1);
});
