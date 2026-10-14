<?php

declare(strict_types=1);

// @covers-us US-NFE-068 — natureza ICMS × ISS por produto e DIFAL do Simples por UF (playbook Fiscal thread 29).
// @covers R-NFE-034

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeNaturezaProduto;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\NaturezaFiscalService;
use Modules\NfeBrasil\Services\Tributacao\SaudeFiscalService;

uses(Tests\TestCase::class);

/**
 * Thread 29 · D-NATUREZA ([W] 2026-10-06): banner/placa/adesivo personalizado — ICMS ou ISS —
 * decide o contador, por produto, com fonte; pendente emite pelo padrão da UF; a Jana nunca decide.
 *
 * MySQL-only · tenant fictício 98 e cliente fictício 99 (ADR 0358). O "contador" é um `User` cujo
 * `can()` é fixado no teste: aqui se prova a regra do serviço, não o cadastro de papéis.
 */

function t29Biz(): int
{
    return test()->seededTenant()->id;
}

function t29Produto(int $biz, string $nome = 'Banner personalizado T29'): int
{
    return (int) DB::table('products')->insertGetId([
        'name' => $nome, 'business_id' => $biz, 'type' => 'single', 'tax_type' => 'exclusive',
        'sku' => 'T29-' . uniqid(), 'ncm' => '49111090', 'is_inactive' => 0,
        'created_by' => DB::table('business')->where('id', $biz)->value('owner_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function t29Usuario(int $biz, bool $podeGerir): User
{
    $u = new class extends User {
        public bool $pode = false;

        public function can($abilities, $arguments = []): bool
        {
            return $this->pode;
        }

        // O log de auditoria grava o tipo do autor: classe anônima não serve de tipo.
        public function getMorphClass()
        {
            return User::class;
        }
    };
    $u->id = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $u->business_id = $biz;
    $u->pode = $podeGerir;

    return $u;
}

function t29Limpar(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_naturezas_produto')) {
        return;
    }
    $bizs = [test()->seededTenant()->id, test()->seededSupportClientTenant()->id];
    DB::table('nfe_naturezas_produto')->whereIn('business_id', $bizs)->delete();
    DB::table('products')->whereIn('business_id', $bizs)->where('sku', 'like', 'T29-%')->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: products do UltimatePOS + nfe_naturezas_produto.');
    }
    if (! Schema::hasTable('nfe_naturezas_produto')) {
        $this->markTestSkipped('Migração 2026_10_14_000003 não rodou.');
    }
    t29Limpar();
});

afterEach(function () {
    t29Limpar();
});

it('R-NFE-034 · natureza decidida pelo contador vale para o produto', function () {
    $biz = t29Biz();
    $svc = app(NaturezaFiscalService::class);
    $banner = t29Produto($biz);
    $linhas = [['product_id' => $banner]];

    // Pendente: emite pelo padrão da UF (a NF-e não é barrada) e a Saúde fiscal avisa.
    $svc->marcarPendente($biz, $banner);
    app(NfeService::class)->recusarItensDeServico($biz, $linhas);   // não lança
    $itens = collect(app(SaudeFiscalService::class)->avaliar($biz)['itens'])->keyBy('tipo');
    expect($itens->has('natureza_a_decidir'))->toBeTrue()
        ->and($itens['natureza_a_decidir']['ids'])->toContain($banner)
        ->and($itens['natureza_a_decidir']['bloqueante'])->toBeFalse();

    // O contador decide "serviço 24.01": a decisão fica com autor, data e fonte, e a NF-e passa a recusar.
    $contador = t29Usuario($biz, true);
    $r = $svc->decidir($biz, $banner, 'servico', '24.01', 'Súmula 156 do STJ', $contador);
    expect($r->natureza)->toBe('servico')
        ->and($r->codigo_servico)->toBe('24.01')
        ->and($r->fonte)->toBe('Súmula 156 do STJ')
        ->and($r->decidido_por)->toBe($contador->id)
        ->and($r->decidido_em)->not->toBeNull();
    expect(fn () => app(NfeService::class)->recusarItensDeServico($biz, $linhas))
        ->toThrow(RuntimeException::class, 'emita NFS-e');

    // CONTROLE POSITIVO — produto já decidido não volta pra fila (marcar pendente de novo não desfaz).
    $svc->marcarPendente($biz, $banner);
    expect(NfeNaturezaProduto::withoutGlobalScopes()->where('product_id', $banner)->value('natureza'))->toBe('servico');
    $itens = collect(app(SaudeFiscalService::class)->avaliar($biz)['itens'])->keyBy('tipo');
    expect($itens->has('natureza_a_decidir'))->toBeFalse();
})->group('nfe', 'R-NFE-034');

it('R-NFE-034 · só o contador decide: sem permissão, de outra empresa, sem fonte ou sem item LC 116 recusa', function () {
    $biz = t29Biz();
    $svc = app(NaturezaFiscalService::class);
    $banner = t29Produto($biz);

    // A Jana (ou qualquer fluxo sem a permissão) não grava natureza — no máximo marca pendente.
    expect(fn () => $svc->decidir($biz, $banner, 'servico', '24.01', 'fonte', t29Usuario($biz, false)))
        ->toThrow(RuntimeException::class, 'Só o contador');
    expect(fn () => $svc->decidir($biz, $banner, 'servico', '24.01', 'fonte', t29Usuario(test()->seededSupportClientTenant()->id, true)))
        ->toThrow(RuntimeException::class, 'Só o contador');
    expect(fn () => $svc->decidir($biz, $banner, 'servico', '24.01', '  ', t29Usuario($biz, true)))
        ->toThrow(InvalidArgumentException::class, 'fonte');
    expect(fn () => $svc->decidir($biz, $banner, 'servico', null, 'Súmula 156', t29Usuario($biz, true)))
        ->toThrow(InvalidArgumentException::class, 'LC 116');
    expect(fn () => $svc->decidir($biz, $banner, 'pendente', null, 'x', t29Usuario($biz, true)))
        ->toThrow(InvalidArgumentException::class, 'mercadoria ou servico');
    expect(NfeNaturezaProduto::withoutGlobalScopes()->where('product_id', $banner)->exists())->toBeFalse();

    // Tier 0: produto de outra empresa não recebe decisão nem pendência.
    $deOutro = t29Produto(test()->seededSupportClientTenant()->id);
    expect(fn () => $svc->decidir($biz, $deOutro, 'mercadoria', null, 'SEFAZ-SP Consulta 10406/2016', t29Usuario($biz, true)))
        ->toThrow(RuntimeException::class, 'não encontrado nesta empresa');
    expect(fn () => $svc->marcarPendente($biz, $deOutro))->toThrow(RuntimeException::class, 'não encontrado');

    // Mercadoria decidida emite normalmente (controle).
    $svc->decidir($biz, $banner, 'mercadoria', '24.01', 'SEFAZ-SP Consulta 10406/2016', t29Usuario($biz, true));
    expect(NfeNaturezaProduto::withoutGlobalScopes()->where('product_id', $banner)->first(['natureza', 'codigo_servico'])->toArray())
        ->toBe(['natureza' => 'mercadoria', 'codigo_servico' => null]);
    app(NfeService::class)->recusarItensDeServico($biz, [['product_id' => $banner]]);   // não lança
})->group('nfe', 'R-NFE-034');
