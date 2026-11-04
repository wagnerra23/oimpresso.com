<?php

declare(strict_types=1);
// @covers-us US-FISIO-023
// Cobre UC-FFI-01..05 (Fisio/Fiscal/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\ReciboSaude;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Documentos fiscais do dia (US-FISIO-023). A fila lê pagamentos do núcleo e não copia valor;
 * o Fisio grava só a marca do Receita Saúde. Vendas e pagamentos entram por DB::table (sem
 * observer do Financeiro). Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_health_receipts', 'transactions', 'transaction_payments'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
});

/** Venda paga hoje ao contato, gravada direto no núcleo. @return int id do pagamento */
function fffPagamento(int $biz, int $contato, int $user, string $valor): int
{
    $venda = (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'contact_id' => $contato, 'created_by' => $user, 'essentials_duration' => 0,
        'transaction_date' => now(), 'final_total' => $valor, 'total_before_tax' => $valor,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $biz, 'transaction_id' => $venda, 'amount' => $valor, 'method' => 'cash',
        'is_return' => 0, 'paid_on' => now(), 'created_by' => $user, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function fffRecibos($t, $user): array
{
    return F::props($t, $user, '/fisio/fiscal', 'Fisio/Fiscal/Index', 'recibos');
}

it('UC-FFI-01 · a fila traz o pagamento do dia de paciente, e não o de cliente que não é paciente', function () {
    $pac = F::paciente($this->b98, $this->u98->id);
    DB::table('contacts')->where('id', $pac->contact_id)->update(['cpf_cnpj' => '123.456.789-09']);
    $doPaciente = fffPagamento($this->b98, $pac->contact_id, $this->u98->id, '150.00');
    fffPagamento($this->b98, F::contato($this->b98, $this->u98->id, 'Cliente da loja'), $this->u98->id, '80.00');

    $fila = fffRecibos($this, $this->u98);

    expect(array_column($fila, 'payment_id'))->toBe([$doPaciente])
        ->and($fila[0]['status'])->toBe('pendente')
        ->and($fila[0]['cpf_ok'])->toBeTrue()
        ->and($fila[0]['cpf_fim'])->toBe('09')
        ->and($fila[0]['valor'])->toBe('150.0000');
});

it('UC-FFI-02 · sem CPF do pagador o recibo aparece "falta CPF" e o CPF nunca vai inteiro para a tela', function () {
    $pac = F::paciente($this->b98, $this->u98->id);
    fffPagamento($this->b98, $pac->contact_id, $this->u98->id, '90.00');

    $fila = fffRecibos($this, $this->u98);

    expect($fila[0]['cpf_ok'])->toBeFalse()->and($fila[0]['cpf_fim'])->toBeNull()
        ->and(array_key_exists('cpf_cnpj', $fila[0]))->toBeFalse();
});

it('UC-FFI-03 · marcar enviado é idempotente e não copia valor', function () {
    $pac = F::paciente($this->b98, $this->u98->id);
    $pg = fffPagamento($this->b98, $pac->contact_id, $this->u98->id, '120.00');
    F::como($this, $this->u98);

    $this->post("/fisio/fiscal/recibos/{$pg}/enviado", ['receipt_number' => 'RS-1'])->assertRedirect();
    $this->post("/fisio/fiscal/recibos/{$pg}/enviado", ['receipt_number' => 'RS-2'])->assertRedirect();

    $marcas = ReciboSaude::query()->where('payment_id', $pg)->get();
    expect($marcas)->toHaveCount(1)->and($marcas[0]->receipt_number)->toBe('RS-2')
        ->and(collect(DB::getSchemaBuilder()->getColumnListing('fisio_health_receipts'))->intersect(['amount', 'valor', 'paid_on'])->all())->toBe([])
        ->and(fffRecibos($this, $this->u98)[0]['status'])->toBe('enviado');
});

it('UC-FFI-04 · sem fisio.editar: vê a fila, não marca (403)', function () {
    $pac = F::paciente($this->b98, $this->u98->id);
    $pg = fffPagamento($this->b98, $pac->contact_id, $this->u98->id, '60.00');
    F::como($this, $this->leitor98);

    $this->get('/fisio/fiscal')->assertOk();
    $this->post("/fisio/fiscal/recibos/{$pg}/enviado")->assertForbidden();
    expect(ReciboSaude::query()->where('payment_id', $pg)->count())->toBe(0);
});

it('UC-FFI-05 · 98 contra 99: pagamento da 99 não entra na fila da 98 nem aceita marca dela', function () {
    $pac99 = F::paciente($this->b99, $this->u99->id);
    $pg99 = fffPagamento($this->b99, $pac99->contact_id, $this->u99->id, '200.00');

    expect(array_column(fffRecibos($this, $this->u98), 'payment_id'))->not->toContain($pg99);

    F::como($this, $this->u98);
    $this->post("/fisio/fiscal/recibos/{$pg99}/enviado")->assertNotFound();

    // Controle negativo: para a 99 o mesmo pagamento está na fila.
    expect(array_column(fffRecibos($this, $this->u99), 'payment_id'))->toContain($pg99);
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(ReciboSaude::withoutGlobalScopes()->where('payment_id', $pg99)->count())->toBe(0);
});
