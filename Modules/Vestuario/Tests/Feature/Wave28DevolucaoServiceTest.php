<?php

declare(strict_types=1);

namespace Modules\Vestuario\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Vestuario\Services\DevolucaoService;

uses(\Tests\TestCase::class);

/**
 * Wave 28 G2 W22 (CAPTERRA Vestuario) — Devolução/Troca CDC + crédito ficha.
 *
 * Cenários cobertos (8+):
 *  1. Cria devolução tipo crédito_ficha → saldo cresce + UPSERT (cliente sem registro)
 *  2. Crédito acumula em registro existente (2ª devolução mesmo cliente)
 *  3. Debita crédito em nova venda → saldo decresce
 *  4. Debita falha quando saldo insuficiente
 *  5. Crédito expira após 6 meses (CDC Art. 50)
 *  6. expirarCreditosVencidos() zera saldos vencidos (cron)
 *  7. Multi-tenant cross-tenant biz=1 vs biz=99 isolation
 *  8. Estorno dinheiro SEM aprovacao_supervisor lança exception
 *  9. Validações payload (tipo inválido, valor zero, motivo vazio)
 * 10. consultarCreditoCliente retorna 0 pra cliente sem registro
 *
 * Tier 0 IRREVOGÁVEL:
 *  - NUNCA biz=4 (ROTA LIVRE prod — ADR 0101). Usado biz=1 e biz=99 fictícios
 *  - business_id explicito em todo método (ADR 0093)
 *  - Append-only: nunca UPDATE devolução, correção via nova linha
 *  - PT-BR comentários
 *
 * @see Modules/Vestuario/Services/DevolucaoService.php
 * @see Modules/Vestuario/Database/Migrations/2026_05_17_000001_create_vestuario_devolucoes_table.php
 * @see Modules/Vestuario/Database/Migrations/2026_05_17_000002_create_vestuario_creditos_cliente_table.php
 */

const WAVE28_BIZ_A = 1;       // fictício A (ADR 0101 — nunca biz=4 Larissa)
const WAVE28_BIZ_B = 99;      // fictício B cross-tenant isolation
const WAVE28_CONTACT_1 = 1001;
const WAVE28_CONTACT_2 = 1002;
const WAVE28_USER = 5001;
const WAVE28_TX = 7001;
const WAVE28_SELL_LINE = 8001;

beforeEach(function () {
    // Cria as duas tabelas Wave 28 em SQLite in-memory (idempotente).
    if (! Schema::hasTable('vestuario_devolucoes')) {
        Schema::create('vestuario_devolucoes', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('transaction_sell_line_id');
            $table->unsignedSmallInteger('quantidade_devolvida');
            $table->decimal('valor_devolvido', 10, 2);
            $table->string('tipo');
            $table->text('motivo');
            $table->unsignedBigInteger('processed_by_user_id');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('business_id');
        });
    }

    if (! Schema::hasTable('vestuario_creditos_cliente')) {
        Schema::create('vestuario_creditos_cliente', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('contact_id');
            $table->decimal('saldo_credito', 10, 2)->default(0);
            $table->timestamp('expira_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'contact_id'], 'uniq_vest_credito_biz_contact');
        });
    }

    // Limpa entre testes (uses Tests\TestCase mas sem RefreshDatabase — manual)
    DB::table('vestuario_devolucoes')->truncate();
    DB::table('vestuario_creditos_cliente')->truncate();
});

function wave28Payload(string $tipo, array $overrides = []): array
{
    return array_merge([
        'transaction_id' => WAVE28_TX,
        'transaction_sell_line_id' => WAVE28_SELL_LINE,
        'contact_id' => WAVE28_CONTACT_1,
        'quantidade_devolvida' => 1,
        'valor_devolvido' => 100.00,
        'tipo' => $tipo,
        'motivo' => 'Cliente trocou tamanho M por G (CDC Art. 26)',
        'processed_by_user_id' => WAVE28_USER,
    ], $overrides);
}

describe('Wave 28 DevolucaoService — crédito ficha (Vale-Trocas Linx)', function () {

    it('cria devolução credito_ficha e UPSERT cria registro saldo cliente', function () {
        $service = new DevolucaoService();

        $result = $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 150.00])
        );

        expect($result['id'])->toBeGreaterThan(0);
        expect($result['saldo_atualizado'])->toBe(150.00);

        $saldo = $service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1);
        expect($saldo)->toBe(150.00);

        // Audit: linha persistida em devolucoes
        $count = DB::table('vestuario_devolucoes')
            ->where('business_id', WAVE28_BIZ_A)
            ->where('tipo', 'credito_ficha')
            ->count();
        expect($count)->toBe(1);
    });

    it('acumula saldo quando cliente já tem crédito (2ª devolução)', function () {
        $service = new DevolucaoService();

        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 100.00])
        );
        $result2 = $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 50.00])
        );

        expect($result2['saldo_atualizado'])->toBe(150.00);
        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1))
            ->toBe(150.00);
    });

    it('debita crédito em nova venda e saldo decresce', function () {
        $service = new DevolucaoService();

        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 200.00])
        );

        $ok = $service->debitarCredito(WAVE28_BIZ_A, WAVE28_CONTACT_1, 80.00, 9999);
        expect($ok)->toBeTrue();
        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1))
            ->toBe(120.00);
    });

    it('debita FALHA quando saldo insuficiente (não fica negativo)', function () {
        $service = new DevolucaoService();

        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 50.00])
        );

        $ok = $service->debitarCredito(WAVE28_BIZ_A, WAVE28_CONTACT_1, 100.00, 9999);
        expect($ok)->toBeFalse();
        // Saldo permanece intacto
        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1))
            ->toBe(50.00);
    });
});

describe('Wave 28 DevolucaoService — expiração CDC Art. 50', function () {

    it('credito expira após 6 meses (consultar retorna 0)', function () {
        $service = new DevolucaoService();

        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 100.00])
        );

        // Força expira_em pra passado (simula 7 meses depois)
        DB::table('vestuario_creditos_cliente')
            ->where('business_id', WAVE28_BIZ_A)
            ->where('contact_id', WAVE28_CONTACT_1)
            ->update(['expira_em' => Carbon::now()->subDay()]);

        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1))
            ->toBe(0.0);

        // Debitar também falha em crédito expirado
        $ok = $service->debitarCredito(WAVE28_BIZ_A, WAVE28_CONTACT_1, 50.00, 9999);
        expect($ok)->toBeFalse();
    });

    it('expirarCreditosVencidos (cron) zera saldos vencidos', function () {
        $service = new DevolucaoService();

        // Cliente 1: vencido. Cliente 2: válido.
        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['contact_id' => WAVE28_CONTACT_1, 'valor_devolvido' => 100.00])
        );
        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['contact_id' => WAVE28_CONTACT_2, 'valor_devolvido' => 200.00])
        );

        // Força contact_1 vencido
        DB::table('vestuario_creditos_cliente')
            ->where('contact_id', WAVE28_CONTACT_1)
            ->update(['expira_em' => Carbon::now()->subDay()]);

        $zerados = $service->expirarCreditosVencidos(WAVE28_BIZ_A);
        expect($zerados)->toBe(1);

        // Saldo cliente 1 zerado, cliente 2 intacto
        $saldo1 = (float) DB::table('vestuario_creditos_cliente')
            ->where('contact_id', WAVE28_CONTACT_1)->value('saldo_credito');
        $saldo2 = (float) DB::table('vestuario_creditos_cliente')
            ->where('contact_id', WAVE28_CONTACT_2)->value('saldo_credito');

        expect($saldo1)->toBe(0.0);
        expect($saldo2)->toBe(200.00);
    });
});

describe('Wave 28 DevolucaoService — multi-tenant Tier 0 (ADR 0093)', function () {

    it('cross-tenant isolation biz=1 NÃO vê crédito de biz=99', function () {
        $service = new DevolucaoService();

        // Mesmo contact_id, business_ids diferentes
        $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('credito_ficha', ['valor_devolvido' => 100.00])
        );
        $service->registrarDevolucao(
            WAVE28_BIZ_B,
            wave28Payload('credito_ficha', ['valor_devolvido' => 999.00])
        );

        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1))
            ->toBe(100.00);
        expect($service->consultarCreditoCliente(WAVE28_BIZ_B, WAVE28_CONTACT_1))
            ->toBe(999.00);

        // Debitar em biz=A NÃO afeta biz=B
        $service->debitarCredito(WAVE28_BIZ_A, WAVE28_CONTACT_1, 30.00, 9999);
        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, WAVE28_CONTACT_1))->toBe(70.00);
        expect($service->consultarCreditoCliente(WAVE28_BIZ_B, WAVE28_CONTACT_1))->toBe(999.00);
    });
});

describe('Wave 28 DevolucaoService — RBAC + validações Tier 0', function () {

    it('estorno_dinheiro SEM aprovacao_supervisor lança InvalidArgumentException', function () {
        $service = new DevolucaoService();

        expect(fn () => $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('estorno_dinheiro')
        ))->toThrow(InvalidArgumentException::class, 'aprovacao_supervisor');
    });

    it('estorno_dinheiro COM aprovacao_supervisor=true passa', function () {
        $service = new DevolucaoService();

        $result = $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('estorno_dinheiro', ['aprovacao_supervisor' => true])
        );
        expect($result['id'])->toBeGreaterThan(0);
        // Estorno dinheiro NÃO cria crédito ficha
        expect($result['saldo_atualizado'])->toBeNull();
    });

    it('tipo inválido lança exception', function () {
        $service = new DevolucaoService();

        expect(fn () => $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('tipo_inexistente')
        ))->toThrow(InvalidArgumentException::class, 'Tipo inválido');
    });

    it('valor_devolvido <= 0 lança exception', function () {
        $service = new DevolucaoService();

        expect(fn () => $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('troca_mesmo_produto', ['valor_devolvido' => 0])
        ))->toThrow(InvalidArgumentException::class, 'valor_devolvido');
    });

    it('motivo vazio lança exception (CDC Art. 26 audit)', function () {
        $service = new DevolucaoService();

        expect(fn () => $service->registrarDevolucao(
            WAVE28_BIZ_A,
            wave28Payload('troca_mesmo_produto', ['motivo' => '   '])
        ))->toThrow(InvalidArgumentException::class, 'Motivo');
    });

    it('credito_ficha SEM contact_id lança exception', function () {
        $service = new DevolucaoService();

        $payload = wave28Payload('credito_ficha');
        unset($payload['contact_id']);

        expect(fn () => $service->registrarDevolucao(WAVE28_BIZ_A, $payload))
            ->toThrow(InvalidArgumentException::class, 'contact_id');
    });

    it('consultarCreditoCliente retorna 0 pra cliente sem registro', function () {
        $service = new DevolucaoService();
        expect($service->consultarCreditoCliente(WAVE28_BIZ_A, 99999))->toBe(0.0);
    });

    it('debitarCredito com valor <= 0 lança exception', function () {
        $service = new DevolucaoService();
        expect(fn () => $service->debitarCredito(WAVE28_BIZ_A, WAVE28_CONTACT_1, 0, 9999))
            ->toThrow(InvalidArgumentException::class);
    });
});
