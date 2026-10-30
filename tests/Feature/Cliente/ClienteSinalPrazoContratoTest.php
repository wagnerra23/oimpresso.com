<?php

declare(strict_types=1);
// Cobre UC-CIDX-09, UC-CIDX-10, UC-CIDX-11 (Cliente/Index.casos.md · pedido P-025).

use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\SinalDaOrdem;

/**
 * P-025 — sinal e prazo por cliente.
 *
 * O prazo do cliente já existia (`contacts.pay_term_number`, "Prazo padrão" no drawer). O sinal
 * era um só para a empresa (`fsm_modelos_empresa.sinal_minimo_pct`, US-PCP-025). Agora o
 * cliente pode ter o próprio (`contacts.sinal_minimo_pct`): vale no lugar do da empresa, 0
 * dispensa, vazio segue a empresa. Quem lê é `SinalDaOrdem::minimo`, usado pelo Liberar da OS e
 * pela liberação automática quando o pagamento entra.
 * Tenant de teste 98 × adversário 99 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('contacts', 'sinal_minimo_pct') || ! Schema::hasColumn('fsm_modelos_empresa', 'sinal_minimo_pct')) {
        $this->markTestSkipped('Migrations P-025 / US-PCP-025 não rodaram — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view', 'customer.update'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);

    DB::table('fsm_modelos_empresa')->updateOrInsert(
        ['business_id' => $this->business->id],
        ['modelo' => 'cv-pequena', 'sinal_minimo_pct' => null, 'updated_at' => now()]
    );
});

function spCliente(int $businessId, int $userId, ?string $sinal, ?int $prazo = null): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'Cliente P025 '.uniqid(),
        'sinal_minimo_pct' => $sinal, 'pay_term_number' => $prazo, 'pay_term_type' => $prazo ? 'days' : null,
        'created_by' => $userId, 'contact_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function spVenda(int $businessId, int $contactId, int $userId, string $total): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'contact_id' => $contactId, 'invoice_no' => 'P025-'.uniqid(), 'transaction_date' => now(),
        'total_before_tax' => $total, 'final_total' => $total, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('UC-CIDX-09 o sinal do cliente vale no lugar do da empresa; vazio segue a empresa; 0 dispensa', function () {
    app(RegrasDaEmpresa::class)->mudarSinalMinimo($this->business->id, '30');
    $sinal = app(SinalDaOrdem::class);
    $b = $this->business->id;

    $com50 = spVenda($b, spCliente($b, $this->user->id, '50.00'), $this->user->id, '1000.00');
    $semCondicao = spVenda($b, spCliente($b, $this->user->id, null), $this->user->id, '1000.00');
    $dispensado = spVenda($b, spCliente($b, $this->user->id, '0.00'), $this->user->id, '1000.00');

    expect($sinal->minimo($b, $com50))->toBe('50.00')
        ->and($sinal->minimo($b, $semCondicao))->toBe('30.00')
        ->and($sinal->minimo($b, $dispensado))->toBeNull()
        ->and($sinal->minimo($b))->toBe('30.00');

    // Dupla prova do valor (Regra Mestre): 1.000,00 × 50% = 500,00 exigido, à mão e pelo MySQL.
    expect($sinal->situacao($b, $com50, (string) $sinal->minimo($b, $com50)))
        ->toBe(['pago' => '0.00', 'exigido' => '500.00', 'ok' => false]);
});

test('UC-CIDX-09 com a regra da empresa desligada, o cliente com sinal ainda exige o dele', function () {
    $b = $this->business->id;
    $venda = spVenda($b, spCliente($b, $this->user->id, '40.00'), $this->user->id, '250.00');

    expect(app(SinalDaOrdem::class)->minimo($b, $venda))->toBe('40.00')
        ->and(app(SinalDaOrdem::class)->minimo($b, spVenda($b, spCliente($b, $this->user->id, null), $this->user->id, '250.00')))->toBeNull();
});

test('UC-CIDX-10 o drawer grava sinal e prazo do cliente e a busca da venda devolve o prazo', function () {
    $cliente = spCliente($this->business->id, $this->user->id, null);

    $r = $this->patchJson("/cliente/{$cliente}/comercial", ['sinal_minimo_pct' => 50, 'pay_term_number' => 28]);
    $r->assertOk();
    expect($r->json('contact.sinal_minimo_pct'))->toEqual(50.0)
        ->and($r->json('contact.pay_term_number'))->toBe(28);

    $linha = collect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->getJson('/contacts/customers')->json())
        ->firstWhere('id', $cliente);
    expect($linha)->not->toBeNull()
        ->and((int) $linha['pay_term_number'])->toBe(28);

    $this->patchJson("/cliente/{$cliente}/comercial", ['sinal_minimo_pct' => 101])->assertStatus(422);
    $this->patchJson("/cliente/{$cliente}/comercial", ['sinal_minimo_pct' => null])->assertOk();
    expect(DB::table('contacts')->where('id', $cliente)->value('sinal_minimo_pct'))->toBeNull();
});

test('UC-CIDX-11 Tier 0 — venda de outra empresa não lê a condição do cliente', function () {
    $outro = $this->seededSupportClientTenant();
    $outroUser = \App\User::factory()->create(['business_id' => $outro->id]);
    $alheia = spVenda($outro->id, spCliente($outro->id, $outroUser->id, '90.00'), $outroUser->id, '100.00');

    // Pedida pela empresa 98, a venda da 99 não existe: vale a regra da 98 (desligada).
    expect(app(SinalDaOrdem::class)->minimo($this->business->id, $alheia))->toBeNull();
});
