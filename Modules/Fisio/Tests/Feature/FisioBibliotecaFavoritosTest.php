<?php

declare(strict_types=1);
// Cobre UC-FBI-08..UC-FBI-10 (Fisio/Biblioteca/Index.casos.md).
// @covers-us US-FISIO-004

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Favoritos e filtros de equipamento e posição da biblioteca (comparativo Physitrack).
 *
 * Favorito é do PROFISSIONAL dentro da EMPRESA: dois usuários da 98 têm listas separadas, a 99 não vê
 * nada da 98, e favoritar um exercício global não altera o global. Equipamento e posição vêm só das
 * listas fechadas de `config('fisio.biblioteca.*')`; os filtros combinam com região, busca e "só
 * favoritos".
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_exercise_favorites'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    // Colega da mesma clínica, só com acesso (favoritar não exige fisio.editar).
    $this->u98b = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);

    $this->global = F::exercicio(null, 'Global ponte', ['pose' => 'deitado_costas', 'equipment' => ['nenhum']]);
    $this->sentadoCadeira = F::exercicio(null, 'Global sentado', ['pose' => 'sentado', 'equipment' => ['cadeira', 'toalha'], 'region' => 'joelho']);
    $this->de98 = F::exercicio($this->b98, 'Próprio da 98', ['pose' => 'em_pe', 'equipment' => ['cadeira']]);
    $this->de99 = F::exercicio($this->b99, 'Próprio da 99', ['pose' => 'sentado', 'equipment' => ['cadeira']]);
});

/** @return list<array<string, mixed>> */
function fbfLinhas($t, $user, string $qs = ''): array
{
    return array_values(F::props($t, $user, '/fisio/biblioteca'.$qs, 'Fisio/Biblioteca/Index', 'exercicios'));
}

/** @return list<int> */
function fbfIds($t, $user, string $qs = ''): array
{
    // Só os 4 exercícios deste teste: a biblioteca global já vem com os 38 do seed L0
    // (migration 2026_10_19_000090), e os filtros por posição/equipamento também os pegam.
    $doTeste = [$t->global->id, $t->sentadoCadeira->id, $t->de98->id, $t->de99->id];

    return collect(fbfLinhas($t, $user, $qs))->pluck('id')->map('intval')
        ->filter(fn (int $id) => in_array($id, $doTeste, true))->sort()->values()->all();
}

function fbfFavoritos(int $biz, int $userId): array
{
    return DB::table('fisio_exercise_favorites')->where('business_id', $biz)->where('user_id', $userId)
        ->pluck('exercise_id')->map('intval')->sort()->values()->all();
}

it('UC-FBI-08 · favoritar global e próprio grava na empresa e no usuário da sessão; o global não muda', function () {
    $antes = DB::table('fisio_exercises')->where('id', $this->global->id)->first();

    F::como($this, $this->u98);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito", ['business_id' => $this->b99, 'user_id' => $this->u99->id])
        ->assertOk()->assertJson(['favorito' => true]);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->de98->id}/favorito")->assertOk();

    // business_id e user_id do corpo são ignorados: a linha é da 98 e do u98.
    expect(fbfFavoritos($this->b98, $this->u98->id))->toBe(collect([$this->global->id, $this->de98->id])->sort()->values()->all())
        ->and(DB::table('fisio_exercise_favorites')->where('business_id', $this->b99)->count())->toBe(0)
        ->and(DB::table('fisio_exercise_favorites')->whereNull('business_id')->count())->toBe(0);

    // O exercício global continua idêntico (nenhuma coluna tocada).
    expect((array) DB::table('fisio_exercises')->where('id', $this->global->id)->first())->toBe((array) $antes);

    $linhas = collect(fbfLinhas($this, $this->u98))->keyBy('id');
    expect($linhas[$this->global->id]['favorito'])->toBeTrue()
        ->and($linhas[$this->de98->id]['favorito'])->toBeTrue()
        ->and($linhas[$this->sentadoCadeira->id]['favorito'])->toBeFalse();
});

it('UC-FBI-08 · "só favoritos" e o colega da mesma clínica tem a própria lista', function () {
    F::como($this, $this->u98);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();

    F::como($this, $this->u98b);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->sentadoCadeira->id}/favorito")->assertOk();

    expect(fbfIds($this, $this->u98, '?fav=1'))->toBe([$this->global->id])
        ->and(fbfIds($this, $this->u98b, '?fav=1'))->toBe([$this->sentadoCadeira->id]);

    $doColega = collect(fbfLinhas($this, $this->u98b))->keyBy('id');
    expect($doColega[$this->global->id]['favorito'])->toBeFalse();

    // O colega desfavoritar o que ele não favoritou não apaga o favorito do u98.
    F::como($this, $this->u98b);
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();
    expect(fbfFavoritos($this->b98, $this->u98->id))->toBe([$this->global->id]);
});

it('UC-FBI-08 · 98 × 99: a 99 não vê favoritos da 98 e não favorita o próprio da 98', function () {
    F::como($this, $this->u98);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();

    expect(fbfIds($this, $this->u99, '?fav=1'))->toBe([]);
    $vistos99 = collect(fbfLinhas($this, $this->u99))->keyBy('id');
    expect($vistos99[$this->global->id]['favorito'])->toBeFalse();

    F::como($this, $this->u99);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->de98->id}/favorito")->assertNotFound();
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->de98->id}/favorito")->assertNotFound();
    expect(DB::table('fisio_exercise_favorites')->where('exercise_id', $this->de98->id)->count())->toBe(0);

    // A 99 desfavoritar o mesmo global não apaga a linha da 98.
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();
    expect(fbfFavoritos($this->b98, $this->u98->id))->toBe([$this->global->id]);

    // O mesmo global favoritado pela 99 vira uma linha da 99, separada da linha da 98.
    $this->postJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();
    expect(fbfFavoritos($this->b99, $this->u99->id))->toBe([$this->global->id])
        ->and(fbfFavoritos($this->b98, $this->u98->id))->toBe([$this->global->id]);
});

it('UC-FBI-08 · favoritar é idempotente, desfavoritar remove, e o banco recusa duplicata', function () {
    F::como($this, $this->u98);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();
    $this->postJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();
    expect(fbfFavoritos($this->b98, $this->u98->id))->toBe([$this->global->id]);

    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk()->assertJson(['favorito' => false]);
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->global->id}/favorito")->assertOk();
    expect(fbfFavoritos($this->b98, $this->u98->id))->toBe([]);

    $linha = ['business_id' => $this->b98, 'user_id' => $this->u98->id, 'exercise_id' => $this->global->id, 'created_at' => now(), 'updated_at' => now()];
    DB::table('fisio_exercise_favorites')->insert($linha);
    expect(fn () => DB::table('fisio_exercise_favorites')->insert($linha))->toThrow(QueryException::class);
});

it('UC-FBI-09 · filtro por posição e por equipamento, só com valores da lista fechada', function () {
    expect(fbfIds($this, $this->u98, '?pose=sentado'))->toBe([$this->sentadoCadeira->id])
        ->and(fbfIds($this, $this->u98, '?equipment=cadeira'))->toBe(collect([$this->sentadoCadeira->id, $this->de98->id])->sort()->values()->all())
        ->and(fbfIds($this, $this->u98, '?equipment=nenhum'))->toBe([$this->global->id]);

    // Valor fora da lista é ignorado: mesma lista de sem filtro, e o estado volta vazio.
    $todos = fbfIds($this, $this->u98);
    expect(fbfIds($this, $this->u98, '?pose=pendurado&equipment=haltere'))->toBe($todos);
    expect(F::props($this, $this->u98, '/fisio/biblioteca?pose=pendurado&equipment=haltere', 'Fisio/Biblioteca/Index', 'filtros'))
        ->toMatchArray(['pose' => '', 'equipment' => '', 'fav' => false]);

    // As opções da tela são as do config, na ordem do config.
    $facetas = F::props($this, $this->u98, '/fisio/biblioteca', 'Fisio/Biblioteca/Index', 'facetas');
    expect(array_column($facetas['posicoes'], 'value'))->toBe(array_keys(config('fisio.biblioteca.posicoes')))
        ->and(array_column($facetas['equipamentos'], 'value'))->toBe(array_keys(config('fisio.biblioteca.equipamentos')));
});

it('UC-FBI-09 · os filtros combinam com região, busca e "só favoritos", e o estado volta pela URL', function () {
    F::como($this, $this->u98);
    $this->postJson("/fisio/biblioteca/exercicios/{$this->de98->id}/favorito")->assertOk();

    expect(fbfIds($this, $this->u98, '?equipment=cadeira&region=joelho'))->toBe([$this->sentadoCadeira->id])
        ->and(fbfIds($this, $this->u98, '?equipment=cadeira&q=Próprio'))->toBe([$this->de98->id])
        ->and(fbfIds($this, $this->u98, '?equipment=cadeira&fav=1'))->toBe([$this->de98->id])
        ->and(fbfIds($this, $this->u98, '?equipment=cadeira&pose=sentado&fav=1'))->toBe([]);

    expect(F::props($this, $this->u98, '/fisio/biblioteca?equipment=cadeira&pose=sentado&fav=1&region=joelho&q=x', 'Fisio/Biblioteca/Index', 'filtros'))
        ->toBe(['region' => 'joelho', 'q' => 'x', 'pose' => 'sentado', 'equipment' => 'cadeira', 'fav' => true]);

    // O próprio da 99 também é "sentado com cadeira", e não aparece para a 98.
    expect(fbfIds($this, $this->u98, '?pose=sentado&equipment=cadeira'))->not->toContain($this->de99->id);
});

it('UC-FBI-10 · exercício próprio aceita posição e equipamento da lista e recusa o resto', function () {
    F::como($this, $this->u98);
    $base = ['name' => 'Sentar e levantar da clínica', 'region' => 'joelho', 'type' => 'fortalecimento', 'steps' => ['Sente', 'Levante']];

    $id = $this->postJson('/fisio/biblioteca/exercicios', [...$base, 'pose' => 'sentado', 'equipment' => ['cadeira', 'toalha']])
        ->assertCreated()->json('id');
    // SUPERADMIN: verificação do que foi gravado.
    $gravado = Exercise::withoutGlobalScopes()->findOrFail($id);
    expect($gravado->pose)->toBe('sentado')->and($gravado->equipment)->toBe(['cadeira', 'toalha']);

    $this->postJson('/fisio/biblioteca/exercicios', [...$base, 'equipment' => ['haltere']])
        ->assertStatus(422)->assertJsonValidationErrors(['equipment.0']);
    $this->postJson('/fisio/biblioteca/exercicios', [...$base, 'equipment' => ['nenhum', 'cadeira']])
        ->assertStatus(422)->assertJsonValidationErrors(['equipment']);
    $this->postJson('/fisio/biblioteca/exercicios', [...$base, 'pose' => 'deitado'])
        ->assertStatus(422)->assertJsonValidationErrors(['pose']);

    // Editar o próprio troca o equipamento; lista vazia volta a "não declarado".
    $this->putJson("/fisio/biblioteca/exercicios/{$id}", ['equipment' => ['bola']])->assertOk();
    expect(Exercise::withoutGlobalScopes()->findOrFail($id)->equipment)->toBe(['bola']);
    $this->putJson("/fisio/biblioteca/exercicios/{$id}", ['equipment' => []])->assertOk();
    expect(Exercise::withoutGlobalScopes()->findOrFail($id)->equipment)->toBeNull();

    // O global segue não editável, com ou sem equipamento.
    $this->putJson("/fisio/biblioteca/exercicios/{$this->global->id}", ['equipment' => ['bola']])->assertForbidden();
});
