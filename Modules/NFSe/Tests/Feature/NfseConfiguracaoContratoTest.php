<?php

declare(strict_types=1);

// Contrato da tela: resources/js/Pages/Fiscal/Config.casos.md — UC-FCFG-13 · 14

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * POST /nfse/configuracao — ambiente da NFS-e e dados do prestador, por empresa.
 *
 * ÂNCORA: achado B4 da auditoria NFSe (2026-11-18, aprovado por [W]): "o ambiente da NFS-e
 * e os dados do prestador não têm tela; a troca para produção exige a mesma permissão e
 * confirmação da NF-e" (cerimônia do UC-FCFG-07: nome do destino digitado + motivo 15+).
 *
 * Tenant 98, vizinho 99 (ADR 0358). Nunca biz=4. MySQL-only (nfse_provider_configs tem FK
 * para business e enum de ambiente).
 */

function ncfA(): int
{
    return test()->seededTenant()->id;
}

function ncfB(): int
{
    return test()->seededSupportClientTenant()->id;
}

function ncfConfig(int $biz): ?object
{
    return DB::table('nfse_provider_configs')->where('business_id', $biz)->whereNull('deleted_at')->first();
}

function ncfLimpar(): void
{
    DB::table('nfse_provider_configs')->whereIn('business_id', [ncfA(), ncfB()])->delete();
}

function ncfComo(\App\User $u, int $biz): void
{
    test()->actingAs($u)->withSession(['business.id' => $biz, 'user.business_id' => $biz]);
}

function ncfDados(array $extra = []): array
{
    return array_merge([
        'ambiente'              => 'homologacao',
        'municipio_codigo_ibge' => '4218707',
        'prestador_cnpj'        => '11.222.333/0001-81',
        'prestador_im'          => '12345',
        'serie_default'         => '1',
        'cnae'                  => '6201-5/00',
    ], $extra);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: nfse_provider_configs tem FK para business e enum de ambiente.');
    }
    if (! Schema::hasTable('nfse_provider_configs')) {
        $this->markTestSkipped('Migrations do NFSe não rodaram.');
    }
    ncfLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfse_provider_configs')) {
        ncfLimpar();
    }
});

// ---------------------------------------------------------------------------------------
// UC-FCFG-13 · Dados do prestador gravam na empresa da sessão  [T0]
// ---------------------------------------------------------------------------------------
it('UC-FCFG-13 · grava na empresa da sessão e ignora business_id do request', function () {
    $semGate = test()->usuarioComPermissoes(['fiscal.access']);
    $editorA = test()->usuarioComPermissoes(['fiscal.config.edit']);

    // Sem fiscal.config.edit → 403, nada gravado.
    ncfComo($semGate, ncfA());
    $this->post('/nfse/configuracao', ncfDados())->assertForbidden();
    expect(ncfConfig(ncfA()))->toBeNull();

    // Município com 6 dígitos → erro no campo, nada gravado.
    ncfComo($editorA, ncfA());
    $this->post('/nfse/configuracao', ncfDados(['municipio_codigo_ibge' => '421870']))
        ->assertSessionHasErrors('municipio_codigo_ibge');
    expect(ncfConfig(ncfA()))->toBeNull();

    // Válido, com business_id da OUTRA empresa no corpo: grava na da sessão, a outra intacta.
    $this->post('/nfse/configuracao', ncfDados(['business_id' => ncfB()]))->assertSessionHasNoErrors();

    $a = ncfConfig(ncfA());
    expect($a)->not->toBeNull();
    expect($a->prestador_cnpj)->toBe('11222333000181');
    expect($a->prestador_im)->toBe('12345');
    expect($a->municipio_codigo_ibge)->toBe('4218707');
    expect($a->ambiente)->toBe('homologacao');
    expect(ncfConfig(ncfB()))->toBeNull();

    // Editor da empresa B só alcança a configuração da B.
    $editorB = test()->usuarioComPermissoes(['fiscal.config.edit'], test()->seededSupportClientTenant());
    ncfComo($editorB, ncfB());
    $this->post('/nfse/configuracao', ncfDados(['prestador_im' => '99999']))->assertSessionHasNoErrors();
    expect(ncfConfig(ncfB())->prestador_im)->toBe('99999');
    expect(ncfConfig(ncfA())->prestador_im)->toBe('12345');
});

// ---------------------------------------------------------------------------------------
// UC-FCFG-14 · Trocar o ambiente da NFS-e tem a trava da NF-e  [T0]
// ---------------------------------------------------------------------------------------
it('UC-FCFG-14 · produção exige fiscal.config.ambiente, destino digitado e motivo', function () {
    $editor = test()->usuarioComPermissoes(['fiscal.config.edit']);
    $comGate = test()->usuarioComPermissoes(['fiscal.config.edit', 'fiscal.config.ambiente']);

    ncfComo($editor, ncfA());
    $this->post('/nfse/configuracao', ncfDados())->assertSessionHasNoErrors();
    expect(ncfConfig(ncfA())->ambiente)->toBe('homologacao');

    // Só fiscal.config.edit: pedir produção é 403 e o ambiente não muda.
    $this->post('/nfse/configuracao', ncfDados([
        'ambiente' => 'producao', 'motivo' => 'fim dos testes com o contador', 'confirmacao' => 'PRODUÇÃO',
    ]))->assertForbidden();
    expect(ncfConfig(ncfA())->ambiente)->toBe('homologacao');

    ncfComo($comGate, ncfA());

    // Sem cerimônia → erro nos dois campos, nada muda.
    $this->post('/nfse/configuracao', ncfDados(['ambiente' => 'producao']))
        ->assertSessionHasErrors(['motivo', 'confirmacao']);
    expect(ncfConfig(ncfA())->ambiente)->toBe('homologacao');

    // "sim" não é o nome do destino → nada muda.
    $this->post('/nfse/configuracao', ncfDados([
        'ambiente' => 'producao', 'motivo' => 'fim dos testes com o contador', 'confirmacao' => 'sim',
    ]))->assertSessionHasErrors('confirmacao');
    expect(ncfConfig(ncfA())->ambiente)->toBe('homologacao');

    // Cerimônia completa (sem acento, minúsculo: a fricção é escrever a palavra) → troca.
    $this->post('/nfse/configuracao', ncfDados([
        'ambiente' => 'producao', 'motivo' => 'fim dos testes com o contador', 'confirmacao' => 'producao',
    ]))->assertSessionHasNoErrors();
    expect(ncfConfig(ncfA())->ambiente)->toBe('producao');

    // A troca vira evento de auditoria com o motivo.
    if (Schema::hasTable('activity_log')) {
        $evento = DB::table('activity_log')
            ->where('log_name', 'nfse.config')
            ->where('description', 'nfse.ambiente_alterado')
            ->where('causer_id', $comGate->id)
            ->latest('id')
            ->first();
        expect($evento)->not->toBeNull();
        expect($evento->properties)->toContain('fim dos testes com o contador');
    }

    // A empresa vizinha segue sem configuração nenhuma.
    expect(ncfConfig(ncfB()))->toBeNull();
});
