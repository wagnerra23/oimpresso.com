<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Models\NfeInutilizacao;

uses(Tests\TestCase::class);

/**
 * US-NFE-040 foundation · Models de domínio NfeBrasil.
 *
 * Pattern dual-mode (PR #486 reference):
 *   - SQLite (CI sanity): drop+create as 4 tabelas isolado em :memory:
 *   - MySQL (Pest local — gate Wagner): preserva schema real;
 *     limpa rows biz=1/99 com FK_CHECKS=0 nas tabelas tocadas.
 *     `nfe_certificados` cascateia em nfse_provider_configs.cert_id.
 *     `nfe_emissoes` cascateia em nfe_eventos.emissao_id.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['nfe_eventos', 'nfe_emissoes', 'nfe_certificados', 'nfe_inutilizacoes', 'activity_log'] as $t) {
            Schema::dropIfExists($t);
        }

        // Spatie LogsActivity em NfeEmissao/NfeEvento/NfeInutilizacao dispara
        // INSERT em activity_log. Sem tabela → SQLSTATE no such table.
        Schema::create('activity_log', function ($table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->text('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->timestamps();
        });

        Schema::create('nfe_certificados', function ($table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->uuid('uuid')->unique();
            $table->string('cnpj_titular', 14)->index();
            $table->date('valido_ate')->index();
            $table->text('encrypted_password');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nfe_emissoes', function ($table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('transaction_id')->nullable();
            $table->string('modelo', 2);
            $table->string('serie', 3);
            $table->unsignedInteger('numero');
            $table->string('chave_44', 44)->nullable()->index();
            $table->string('status', 20)->default('pendente')->index();
            $table->string('cstat', 5)->nullable();
            $table->text('motivo')->nullable();
            $table->string('xml_path', 255)->nullable();
            $table->string('danfe_path', 255)->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->dateTime('emitido_em')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'transaction_id'], 'biz_tx_unq');
            $table->unique(['business_id', 'modelo', 'serie', 'numero'], 'biz_seq_unq');
        });

        Schema::create('nfe_eventos', function ($table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('emissao_id')->constrained('nfe_emissoes')->cascadeOnDelete();
            $table->string('tipo', 6)->index();
            $table->text('justificativa')->nullable();
            $table->string('status', 20)->default('pendente')->index();
            $table->string('cstat_evento', 5)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('nfe_inutilizacoes', function ($table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('modelo', 2);
            $table->string('serie', 3);
            $table->unsignedInteger('numero_de');
            $table->unsignedInteger('numero_ate');
            $table->text('justificativa');
            $table->string('status', 20)->default('pendente')->index();
            $table->string('cstat', 5)->nullable();
            $table->dateTime('autorizada_em')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();
        });
    } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['nfe_eventos', 'nfe_inutilizacoes'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->whereIn('business_id', [1, 5, 99])->delete();
            }
        }
        if (Schema::hasTable('nfe_emissoes')) {
            DB::table('nfe_emissoes')->whereIn('business_id', [1, 5, 99])->delete();
        }
        if (Schema::hasTable('nfe_certificados')) {
            if (Schema::hasTable('nfse_provider_configs')) {
                DB::table('nfse_provider_configs')->whereIn('business_id', [1, 5, 99])->delete();
            }
            DB::table('nfe_certificados')->whereIn('business_id', [1, 5, 99])->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['nfe_eventos', 'nfe_emissoes', 'nfe_certificados', 'nfe_inutilizacoes', 'activity_log'] as $t) {
            Schema::dropIfExists($t);
        }
    } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['nfe_eventos', 'nfe_inutilizacoes'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->whereIn('business_id', [1, 5, 99])->delete();
            }
        }
        if (Schema::hasTable('nfe_emissoes')) {
            DB::table('nfe_emissoes')->whereIn('business_id', [1, 5, 99])->delete();
        }
        if (Schema::hasTable('nfe_certificados')) {
            if (Schema::hasTable('nfse_provider_configs')) {
                DB::table('nfse_provider_configs')->whereIn('business_id', [1, 5, 99])->delete();
            }
            DB::table('nfe_certificados')->whereIn('business_id', [1, 5, 99])->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
});

it('NfeCertificado esconde encrypted_password em toArray()', function () {
    $cert = NfeCertificado::create([
        'business_id'        => 1,
        'uuid'               => '550e8400-e29b-41d4-a716-446655440000',
        'cnpj_titular'       => '12345678000199',
        'valido_ate'         => now()->addYear()->toDateString(),
        'encrypted_password' => Crypt::encryptString('senha-secreta'),
        'ativo'              => true,
    ]);

    $array = $cert->toArray();

    expect($array)->not()->toHaveKey('encrypted_password')
        ->and($cert->cnpj_titular)->toBe('12345678000199')
        ->and($cert->ativo)->toBeTrue();
});

it('NfeCertificado::diasAteVencimento() calcula corretamente', function () {
    $cert = NfeCertificado::create([
        'business_id' => 1, 'uuid' => 'a',
        'cnpj_titular' => '11', 'valido_ate' => now()->addDays(30)->toDateString(),
        'encrypted_password' => 'x', 'ativo' => true,
    ]);

    expect($cert->diasAteVencimento())->toBeGreaterThanOrEqual(29)
        ->and($cert->diasAteVencimento())->toBeLessThanOrEqual(30);
});

it('NfeCertificado::isVencido() true quando passou', function () {
    $cert = NfeCertificado::create([
        'business_id' => 1, 'uuid' => 'b',
        'cnpj_titular' => '11', 'valido_ate' => now()->subDays(1)->toDateString(),
        'encrypted_password' => 'x', 'ativo' => false,
    ]);

    expect($cert->isVencido())->toBeTrue();
});

it('NfeEmissao UNIQUE(business_id, transaction_id) garante idempotência', function () {
    NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 100,
        'modelo' => '65', 'serie' => '001', 'numero' => 1,
        'valor_total' => 50, 'status' => 'pendente',
    ]);

    expect(fn () => NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 100,
        'modelo' => '65', 'serie' => '001', 'numero' => 2,
        'valor_total' => 50, 'status' => 'pendente',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('NfeEmissao UNIQUE(biz, modelo, serie, numero) impede duplicar sequência', function () {
    NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 1,
        'modelo' => '65', 'serie' => '001', 'numero' => 100,
        'valor_total' => 10, 'status' => 'autorizada',
    ]);

    expect(fn () => NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 2,
        'modelo' => '65', 'serie' => '001', 'numero' => 100,
        'valor_total' => 20, 'status' => 'pendente',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('NfeEmissao mesma serie+numero em business diferente é OK (multi-tenant)', function () {
    NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 1,
        'modelo' => '65', 'serie' => '001', 'numero' => 100,
        'valor_total' => 10, 'status' => 'autorizada',
    ]);
    NfeEmissao::create([
        'business_id' => 5, 'transaction_id' => 1,
        'modelo' => '65', 'serie' => '001', 'numero' => 100,
        'valor_total' => 20, 'status' => 'autorizada',
    ]);

    expect(NfeEmissao::count())->toBe(2);
});

it('NfeEmissao::isCancelavel — NFC-e dentro de 24h', function () {
    $emi = NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 10,
        'modelo' => '65', 'serie' => '001', 'numero' => 1,
        'valor_total' => 10, 'status' => 'autorizada',
        'emitido_em' => now()->subHours(20),
    ]);

    expect($emi->isCancelavel())->toBeTrue();
});

it('NfeEmissao::isCancelavel — NFC-e após 24h não é cancelável', function () {
    $emi = NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 11,
        'modelo' => '65', 'serie' => '001', 'numero' => 2,
        'valor_total' => 10, 'status' => 'autorizada',
        'emitido_em' => now()->subHours(25),
    ]);

    expect($emi->isCancelavel())->toBeFalse();
});

it('NfeEmissao::isCancelavel — NFe modelo 55 tem 168h (7 dias)', function () {
    $emi = NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 12,
        'modelo' => '55', 'serie' => '001', 'numero' => 3,
        'valor_total' => 100, 'status' => 'autorizada',
        'emitido_em' => now()->subHours(100),
    ]);

    expect($emi->isCancelavel())->toBeTrue();
});

it('NfeEmissao::isCancelavel — apenas autorizada (rejeitada/cancelada não)', function () {
    foreach (['pendente', 'rejeitada', 'cancelada', 'denegada'] as $st) {
        $emi = NfeEmissao::create([
            'business_id' => 1, 'transaction_id' => 100 + array_search($st, ['pendente','rejeitada','cancelada','denegada']),
            'modelo' => '55', 'serie' => '001', 'numero' => 200 + array_search($st, ['pendente','rejeitada','cancelada','denegada']),
            'valor_total' => 10, 'status' => $st,
            'emitido_em' => now()->subHour(),
        ]);
        expect($emi->isCancelavel())->toBeFalse("status={$st} não pode ser cancelável");
    }
});

it('NfeEvento é append-only (sem UPDATED_AT)', function () {
    $emi = NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 1,
        'modelo' => '55', 'serie' => '001', 'numero' => 1,
        'valor_total' => 100, 'status' => 'autorizada',
    ]);

    NfeEvento::create([
        'business_id' => 1, 'emissao_id' => $emi->id,
        'tipo' => '110111', 'status' => 'autorizado',
        'justificativa' => 'Erro na descrição do produto, exige correção',
        'cstat_evento' => '135',
    ]);

    expect(NfeEvento::UPDATED_AT)->toBeNull()
        ->and($emi->eventos)->toHaveCount(1)
        ->and($emi->eventos->first()->tipo)->toBe('110111');
});

it('NfeEvento.payload_json roundtrip via array cast', function () {
    $emi = NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 1,
        'modelo' => '55', 'serie' => '001', 'numero' => 1,
        'valor_total' => 100, 'status' => 'autorizada',
    ]);

    $payload = ['request' => ['xml' => '<x/>'], 'response' => ['cstat' => '135']];

    $ev = NfeEvento::create([
        'business_id' => 1, 'emissao_id' => $emi->id,
        'tipo' => '110111', 'status' => 'autorizado',
        'payload_json' => $payload,
    ]);

    expect($ev->fresh()->payload_json)->toBe($payload);
});

it('NfeInutilizacao::quantidadeNumeros calcula corretamente', function () {
    $inut = NfeInutilizacao::create([
        'business_id' => 1, 'modelo' => '65', 'serie' => '001',
        'numero_de' => 100, 'numero_ate' => 105,
        'justificativa' => 'Erro de impressão consecutivo',
        'status' => 'autorizado',
    ]);

    expect($inut->quantidadeNumeros())->toBe(6); // 100..105 inclusive
});

it('cascade delete: deletar NfeEmissao apaga NfeEvento dependentes', function () {
    $emi = NfeEmissao::create([
        'business_id' => 1, 'transaction_id' => 50,
        'modelo' => '55', 'serie' => '001', 'numero' => 50,
        'valor_total' => 100, 'status' => 'autorizada',
    ]);
    NfeEvento::create([
        'business_id' => 1, 'emissao_id' => $emi->id,
        'tipo' => '110110', 'status' => 'autorizado',
    ]);
    NfeEvento::create([
        'business_id' => 1, 'emissao_id' => $emi->id,
        'tipo' => '210200', 'status' => 'autorizado',
    ]);

    expect(NfeEvento::count())->toBe(2);

    // Force delete (não soft) pra disparar cascade do FK
    $emi->forceDelete();

    expect(NfeEvento::count())->toBe(0);
});
