<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Busca ⌘K e timeline de eventos × permissão da tela dona do dado
 * (auditoria Fiscal 2026-11-05, lote 1.3).
 *
 * ÂNCORA (regra escrita, não o código): R-FISCAL-003 · memory/requisitos/Fiscal/SPEC.md —
 * gate POR SUB-FEATURE. Ver notas exige `fiscal.nfe.view` (/fiscal/nfe) e ver DF-e exige
 * `fiscal.dfe.manage` (/fiscal/dfe). Uma porta lateral (busca, timeline, CSV) que
 * entrega o mesmo dado só com `fiscal.access` desfaz o gate da tela.
 *
 * E o isolamento Tier 0 (ADR 0093): a busca de uma empresa nunca acha documento de outra.
 *
 * Tenants fictícios: 98 (o de teste, ADR 0358) e 99. Nunca biz=4. Os dados ficam numa
 * transação revertida ao fim de cada caso.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL (ADR 0358)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_eventos')) {
        $this->markTestSkipped('Tabelas NfeBrasil ausentes — rode as migrations do NfeBrasil');
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    // Marcador único por execução: a busca casa por ele, e nada de outro run interfere.
    $this->marca = 'Zq' . substr(md5(uniqid('', true)), 0, 10);
});

/** Uma NF-e e uma DF-e com o marcador no texto buscável, no business indicado. */
function buscaSemear(int $businessId, string $marca): array
{
    $emissao = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $businessId,
        'modelo'      => '55',
        'serie'       => '9',
        'numero'      => random_int(800000, 899999),
        'status'      => 'autorizada',
        'motivo'      => "Autorizado {$marca} biz{$businessId}",
        'valor_total' => 10.00,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $dfe = DB::table('nfe_dfe_recebidos')->insertGetId([
        'business_id'   => $businessId,
        'chave_44'      => str_pad((string) random_int(1, PHP_INT_MAX), 44, '7', STR_PAD_LEFT),
        'nsu'           => random_int(1, 999999999),
        'cnpj_emitente' => '00000000000191',
        'nome_emitente' => "Fornecedor {$marca} biz{$businessId}",
        'data_emissao'  => now(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return ['emissao' => $emissao, 'dfe' => $dfe];
}

function buscaEntrar(\App\User $user, int $businessId): void
{
    test()->actingAs($user);
    session(['business.id' => $businessId, 'user.business_id' => $businessId]);
}

it('empresa A não acha documento da empresa B na busca, e acha o próprio (controle)', function () {
    $do98 = buscaSemear($this->b98, $this->marca);
    $do99 = buscaSemear($this->b99, $this->marca);

    $u = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view', 'fiscal.dfe.manage'], $this->seededTenant());
    buscaEntrar($u, $this->b98);

    $json = $this->getJson('/fiscal/palette/search?q=' . $this->marca)->assertOk()->json();

    $notas = collect($json['notas'])->pluck('id')->all();
    $dfe = collect($json['dfe'])->pluck('id')->all();

    expect($notas)->toContain($do98['emissao'])
        ->and($notas)->not->toContain($do99['emissao'])
        ->and($dfe)->toContain($do98['dfe'])
        ->and($dfe)->not->toContain($do99['dfe']);
});

it('só fiscal.access: a busca responde, mas sem notas nem DF-e', function () {
    buscaSemear($this->b98, $this->marca);

    $u = $this->usuarioComPermissoes(['fiscal.access'], $this->seededTenant());
    buscaEntrar($u, $this->b98);

    $json = $this->getJson('/fiscal/palette/search?q=' . $this->marca)->assertOk()->json();

    expect($json['notas'])->toBe([])
        ->and($json['dfe'])->toBe([]);
});

it('cada categoria da busca segue a permissão da tela dona', function () {
    $do98 = buscaSemear($this->b98, $this->marca);

    $soNotas = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view'], $this->seededTenant());
    buscaEntrar($soNotas, $this->b98);
    $a = $this->getJson('/fiscal/palette/search?q=' . $this->marca)->assertOk()->json();

    $soDfe = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.dfe.manage'], $this->seededTenant());
    buscaEntrar($soDfe, $this->b98);
    $b = $this->getJson('/fiscal/palette/search?q=' . $this->marca)->assertOk()->json();

    expect(collect($a['notas'])->pluck('id')->all())->toContain($do98['emissao'])
        ->and($a['dfe'])->toBe([])
        ->and($b['notas'])->toBe([])
        ->and(collect($b['dfe'])->pluck('id')->all())->toContain($do98['dfe']);
});

it('timeline e CSV de eventos exigem fiscal.nfe.view: só fiscal.access recebe 403', function () {
    $u = $this->usuarioComPermissoes(['fiscal.access'], $this->seededTenant());
    buscaEntrar($u, $this->b98);

    $this->get('/fiscal/eventos')->assertStatus(403);
    $this->get('/fiscal/eventos/export')->assertStatus(403);
});

it('controle: com fiscal.nfe.view a timeline e o CSV abrem', function () {
    $u = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view'], $this->seededTenant());
    buscaEntrar($u, $this->b98);

    $this->get('/fiscal/eventos')->assertOk();
    $this->get('/fiscal/eventos/export')->assertOk();
});
