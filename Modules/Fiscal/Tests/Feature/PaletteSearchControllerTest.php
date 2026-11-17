<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-015 — Palette ⌘K cross-fiscal search (PR #7 Wave).
 *
 * Até 2026-11-06 este arquivo montava um `validator()` local com as regras reescritas à mão,
 * conferia a classe e a rota por reflexão e contava `->limit(5)` no fonte do controller.
 * Nada disso caía se o controller mudasse. Agora cada caso chama GET /fiscal/palette/search.
 *
 * O isolamento entre empresas e o gate por categoria estão em BuscaEventosPermissaoTest
 * (98 × 99); aqui ficam a validação de `q` e o teto de 5 por categoria.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL (ADR 0358)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('Tabelas NfeBrasil ausentes — rodar migrate primeiro');
    }

    $this->tenant = $this->seededTenant();
    $this->biz = (int) $this->tenant->id;
    $this->marca = 'Pl' . substr(md5(uniqid('', true)), 0, 10);

    $user = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view', 'fiscal.dfe.manage'], $this->tenant);
    $this->actingAs($user);
    session(['business.id' => $this->biz, 'user.business_id' => $this->biz]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    DB::table('nfe_emissoes')->where('motivo', 'like', "%{$this->marca}%")->delete();
    DB::table('nfe_dfe_recebidos')->where('nome_emitente', 'like', "%{$this->marca}%")->delete();
});

it('search rejeita query < 3 chars (anti-DOS leading wildcard — GAP-FISCAL-002)', function () {
    foreach (['a', 'ab'] as $q) {
        $this->getJson('/fiscal/palette/search?q=' . $q)
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }
});

it('search rejeita query > 50 chars (defesa anti-abuse)', function () {
    $this->getJson('/fiscal/palette/search?q=' . str_repeat('x', 51))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

it('search aceita query válida de 3 a 50 chars', function () {
    foreach (['abc', 'numero 123', str_repeat('x', 50)] as $q) {
        $this->getJson('/fiscal/palette/search?q=' . urlencode($q))
            ->assertOk()
            ->assertJsonStructure(['q', 'notas', 'dfe']);
    }
});

it('cada categoria devolve no máximo 5 resultados, os mais recentes primeiro', function () {
    $notas = [];
    $dfes = [];
    for ($i = 1; $i <= 6; $i++) {
        $notas[] = DB::table('nfe_emissoes')->insertGetId([
            'business_id' => $this->biz, 'modelo' => '55', 'serie' => '936',
            'numero' => random_int(800000, 899999), 'status' => 'autorizada',
            'motivo' => "Autorizado {$this->marca} {$i}", 'valor_total' => 10.00,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $dfes[] = DB::table('nfe_dfe_recebidos')->insertGetId([
            'business_id' => $this->biz,
            'chave_44' => str_pad((string) random_int(1, PHP_INT_MAX), 44, '7', STR_PAD_LEFT),
            'nsu' => random_int(1, 999999999), 'cnpj_emitente' => '00000000000191',
            'nome_emitente' => "Fornecedor {$this->marca} {$i}",
            'data_emissao' => now()->subDays(6 - $i),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $json = $this->getJson('/fiscal/palette/search?q=' . $this->marca)->assertOk()->json();

    // 6 casam; o teto corta em 5 e deixa de fora o mais antigo de cada categoria.
    expect(collect($json['notas'])->pluck('id')->all())->toBe(array_reverse(array_slice($notas, 1)));
    expect(collect($json['dfe'])->pluck('id')->all())->toBe(array_reverse(array_slice($dfes, 1)));
});
