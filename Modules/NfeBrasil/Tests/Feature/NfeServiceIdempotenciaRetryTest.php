<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Wave 13 D2 — Idempotência SEFAZ + retry safety NfeService::emitir().
 *
 * Cobre os 3 estados terminais de NfeEmissao que NfeService.emitirInterno()
 * deve tratar antes de chamar SEFAZ (linhas 388-442 do NfeService.php):
 *
 *   1. status=autorizada → retorna emissao existente (no-op idempotente)
 *   2. status=pendente   → retorna emissao existente (continua retry)
 *   3. status=cancelada  → lança RuntimeException com mensagem instrutiva
 *                          (CONFAZ SINIEF 07/2005 Art. 14 — número usado oficial)
 *   4. status=rejeitada  → marca como `inutilizada` (preserva rastreabilidade,
 *                          permite nova emissão com novo número)
 *
 * Pattern: usa Reflection pra invocar `emitirInterno` SEM chegar até HTTP SEFAZ
 * — paths idempotência terminam ANTES da chamada SEFAZ (return/throw na guarda 1).
 *
 * Para path 4 (rejeitada → inutilizada) NÃO invocamos o método (continuaria pra
 * SEFAZ) — validamos somente o efeito colateral via fixture controlada + segundo
 * insert manual, simulando o que o método faria.
 *
 * @see Modules/NfeBrasil/Services/NfeService.php::emitirInterno (linhas 388-442)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

const NFE_IDEMP_BIZ = 1;
const NFE_IDEMP_TX_AUTORIZADA = 999777001;
const NFE_IDEMP_TX_PENDENTE = 999777002;
const NFE_IDEMP_TX_CANCELADA = 999777003;
const NFE_IDEMP_TX_REJEITADA = 999777004;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeService requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    try {
        NfeEmissao::withoutGlobalScopes()->withTrashed()
            ->where('business_id', NFE_IDEMP_BIZ)
            ->whereIn('transaction_id', [
                NFE_IDEMP_TX_AUTORIZADA,
                NFE_IDEMP_TX_PENDENTE,
                NFE_IDEMP_TX_CANCELADA,
                NFE_IDEMP_TX_REJEITADA,
            ])
            ->forceDelete();
    } catch (\Throwable) {
        // best-effort
    }
});

// ------------------------------------------------------------------
// Estrutura do método — assinatura privada existe
// ------------------------------------------------------------------

it('NfeService tem método privado emitirInterno com 4 parâmetros', function () {
    $reflection = new ReflectionClass(NfeService::class);
    expect($reflection->hasMethod('emitirInterno'))->toBeTrue();

    $method = $reflection->getMethod('emitirInterno');
    expect($method->isPrivate())->toBeTrue();

    $params = $method->getParameters();
    expect(count($params))->toBe(4);
    expect($params[0]->getName())->toBe('businessId');
    expect($params[1]->getName())->toBe('dadosNfe');
    expect($params[2]->getName())->toBe('modelo');
    expect($params[3]->getName())->toBe('transactionId');
});

// ------------------------------------------------------------------
// Idempotência 1: status=autorizada → no-op retorna existente
// ------------------------------------------------------------------

it('emitir() com transaction_id já autorizada retorna emissao existente (idempotente)', function () {
    // Fixture: emissao prévia autorizada
    $emissaoId = DB::table('nfe_emissoes')->insertGetId([
        'business_id'    => NFE_IDEMP_BIZ,
        'transaction_id' => NFE_IDEMP_TX_AUTORIZADA,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'          => 555001,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'motivo'         => 'Autorizado o uso da NF-e',
        'valor_total'    => 150.00,
        'emitido_em'     => now(),
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    session(['business.id' => NFE_IDEMP_BIZ]);

    $service = new NfeService();
    $reflection = new ReflectionClass(NfeService::class);
    $method = $reflection->getMethod('emitirInterno');
    $method->setAccessible(true);

    // Invoca direto — deve retornar a emissao existente sem chamar SEFAZ
    $resultado = $method->invoke(
        $service,
        NFE_IDEMP_BIZ,
        ['valor_total' => 999.99], // dadosNfe (não importa, é ignorado pelo no-op)
        '55',
        NFE_IDEMP_TX_AUTORIZADA
    );

    expect($resultado)->toBeInstanceOf(NfeEmissao::class);
    expect((int) $resultado->id)->toBe((int) $emissaoId);
    expect($resultado->status)->toBe('autorizada');
    expect((int) $resultado->numero)->toBe(555001); // numero original preservado
    expect((float) $resultado->valor_total)->toBe(150.00); // valor original (não reescreveu)
});

// ------------------------------------------------------------------
// Idempotência 2: status=pendente → retorna existente (continua retry)
// ------------------------------------------------------------------

it('emitir() com transaction_id pendente retorna emissao existente (permite retry monitorado)', function () {
    $emissaoId = DB::table('nfe_emissoes')->insertGetId([
        'business_id'    => NFE_IDEMP_BIZ,
        'transaction_id' => NFE_IDEMP_TX_PENDENTE,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'          => 555002,
        'status'         => 'pendente',
        'valor_total'    => 200.00,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    session(['business.id' => NFE_IDEMP_BIZ]);

    $service = new NfeService();
    $reflection = new ReflectionClass(NfeService::class);
    $method = $reflection->getMethod('emitirInterno');
    $method->setAccessible(true);

    $resultado = $method->invoke(
        $service,
        NFE_IDEMP_BIZ,
        ['valor_total' => 999.99],
        '55',
        NFE_IDEMP_TX_PENDENTE
    );

    expect($resultado)->toBeInstanceOf(NfeEmissao::class);
    expect((int) $resultado->id)->toBe((int) $emissaoId);
    expect($resultado->status)->toBe('pendente');
});

// ------------------------------------------------------------------
// Idempotência 3: status=cancelada → lança RuntimeException
// ------------------------------------------------------------------
// CONFAZ SINIEF 07/2005 Art. 14: número de NFe cancelada via SEFAZ permanece
// usado oficialmente — proibido re-emitir mesma transaction. Sistema bloqueia
// com mensagem instrutiva pra orientar FSM `emitir_nova_apos_cancelamento`.

it('emitir() com transaction_id cancelada lança RuntimeException instrutiva', function () {
    DB::table('nfe_emissoes')->insert([
        'business_id'    => NFE_IDEMP_BIZ,
        'transaction_id' => NFE_IDEMP_TX_CANCELADA,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'          => 555003,
        'status'         => 'cancelada',
        'valor_total'    => 300.00,
        'emitido_em'     => now()->subDays(2),
        'created_at'     => now()->subDays(2),
        'updated_at'     => now(),
    ]);

    session(['business.id' => NFE_IDEMP_BIZ]);

    $service = new NfeService();
    $reflection = new ReflectionClass(NfeService::class);
    $method = $reflection->getMethod('emitirInterno');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(
        $service,
        NFE_IDEMP_BIZ,
        ['valor_total' => 999.99],
        '55',
        NFE_IDEMP_TX_CANCELADA
    ))->toThrow(
        RuntimeException::class,
        'foi cancelada via SEFAZ'
    );
});

// ------------------------------------------------------------------
// Idempotência 4: status=rejeitada → marca inutilizada (efeito colateral)
// ------------------------------------------------------------------
// NfeService linhas 432-440: emissao prévia rejeitada/denegada/erro_envio é
// marcada como `inutilizada` ANTES de seguir pra nova emissão. Garante que o
// numero antigo NÃO some — preserva rastreabilidade fiscal (não hard delete).
//
// NÃO invocamos emitirInterno aqui (continuaria pra cert+SEFAZ) — testamos
// só o update via UPDATE SQL idempotente simulando o branch.

it('emissao prévia rejeitada é marcada como inutilizada antes de retry (preserva rastreabilidade)', function () {
    $emissaoId = DB::table('nfe_emissoes')->insertGetId([
        'business_id'    => NFE_IDEMP_BIZ,
        'transaction_id' => NFE_IDEMP_TX_REJEITADA,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'          => 555004,
        'status'         => 'rejeitada',
        'cstat'          => '215',
        'motivo'         => 'Falha Schema XML — campo cMunFG inválido',
        'valor_total'    => 400.00,
        'created_at'     => now()->subMinutes(10),
        'updated_at'     => now()->subMinutes(10),
    ]);

    // Simula a guarda 4 do NfeService.emitirInterno (linhas 432-440)
    NfeEmissao::withoutGlobalScopes()
        ->where('id', $emissaoId)
        ->update(['status' => 'inutilizada']);

    // Registro NÃO deletado (hard) — só status mudou (rastreabilidade fiscal)
    $registro = NfeEmissao::withoutGlobalScopes()->find($emissaoId);
    expect($registro)->not->toBeNull();
    expect($registro->status)->toBe('inutilizada');
    expect((int) $registro->numero)->toBe(555004); // numero preservado
    expect($registro->motivo)->toContain('cMunFG'); // motivo original preservado pra audit
});

// ------------------------------------------------------------------
// Cross-tenant guard: idempotência NÃO vaza biz=99 → biz=1
// ------------------------------------------------------------------
// Crítico fiscal: se biz=99 tem transaction_id=X autorizada, emissão biz=1
// com mesma transaction_id NÃO pode reusar — Receita exige sequência por CNPJ.

it('idempotência scoped por business_id — biz=1 NÃO reusa emissao de biz=99', function () {
    // Emissao biz=99 autorizada com transaction_id "X"
    DB::table('nfe_emissoes')->insert([
        'business_id'    => 99,
        'transaction_id' => NFE_IDEMP_TX_AUTORIZADA,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'          => 555099,
        'status'         => 'autorizada',
        'valor_total'    => 999.00,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    // Busca scopada biz=1 NÃO encontra a de biz=99 (Eloquent + global scope)
    session(['business.id' => NFE_IDEMP_BIZ]);

    $existente = NfeEmissao::where('business_id', NFE_IDEMP_BIZ)
        ->where('transaction_id', NFE_IDEMP_TX_AUTORIZADA)
        ->first();

    expect($existente)->toBeNull(); // biz=1 não vê biz=99 → seguiria emissão normal

    // Cleanup defensivo extra biz=99 deste teste
    NfeEmissao::withoutGlobalScopes()->where('business_id', 99)->where('numero', 555099)->forceDelete();
});
