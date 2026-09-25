<?php

declare(strict_types=1);

/**
 * Contrato da presença do usuário (thread 13 do playbook da sidebar · decisão [W] 2026-09-25).
 *
 * O que cada caso defende:
 *   1. os 4 ids do protótipo (`sidebar.jsx` PRESENCAS) são aceitos e GRAVAM `users.ui_presence`;
 *   2. id fora do enum volta 422 e NÃO toca a coluna — inclusive "nao-perturbe", o estado que
 *      o vivo mostrava e o protótipo não tem;
 *   3. a rota exige login (mesma forma de `/user/preferences/theme`);
 *   4. o enum do backend e a lista do `Sidebar.tsx` são o MESMO conjunto — se um lado ganhar
 *      um estado sem o outro, o menu grava um id que a rota recusa (ou nunca oferece um que
 *      ela aceita), e isso é silencioso: o `fetch` do menu engole o erro de propósito.
 *
 * Banco: no MySQL (CT 100) usa o tenant fictício 98 (ADR 0358). Na lane sqlite `:memory:`
 * (sem migrate) monta uma tabela `users` sintética só com as colunas que a rota toca — por
 * isso os casos 1 e 2 NÃO viram skip-as-pass lá.
 */

use App\Http\Controllers\UserPreferencesController;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('users')) {
        // Lane sqlite: schema sintético (sem FK/ENUM MySQL-only).
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('username')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('user_type')->default('user');
            $t->boolean('allow_login')->default(true);
            $t->rememberToken();
            $t->string('ui_theme', 10)->nullable();
            $t->boolean('ui_sidebar_collapsed')->default(false);
            $t->string('ui_presence', 12)->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        $id = DB::table('users')->insertGetId(['first_name' => 'Teste', 'email' => 'presenca@teste.local']);
        $this->user = User::findOrFail($id);

        return;
    }

    if (! Schema::hasColumn('users', 'ui_presence')) {
        $this->markTestSkipped('users.ui_presence ausente — rode a migration 2026_09_25_140000.');
    }

    $biz = $this->seededTenant();                       // biz=98 fictício (ADR 0358)
    $this->user = User::factory()->create(['business_id' => $biz->id])->fresh();
});

dataset('presencas validas', ['disponivel', 'ocupado', 'ausente', 'invisivel']);

it('grava cada uma das 4 presenças em users.ui_presence', function (string $presenca) {
    $this->actingAs($this->user)
        ->postJson('/user/preferences/presence', ['presence' => $presenca])
        ->assertSessionHasNoErrors();

    expect(DB::table('users')->where('id', $this->user->id)->value('ui_presence'))->toBe($presenca);
})->with('presencas validas');

it('recusa presença fora do enum com 422 e não toca a coluna', function (string $invalida) {
    DB::table('users')->where('id', $this->user->id)->update(['ui_presence' => 'ausente']);

    $this->actingAs($this->user)
        ->postJson('/user/preferences/presence', ['presence' => $invalida])
        ->assertStatus(422)
        ->assertJsonValidationErrors('presence');

    expect(DB::table('users')->where('id', $this->user->id)->value('ui_presence'))->toBe('ausente');
})->with(['nao-perturbe', 'online', '']);

it('exige login', function () {
    $r = $this->post('/user/preferences/presence', ['presence' => 'ocupado']);

    expect($r->getStatusCode())->toBe(302);
    expect($r->headers->get('Location'))->toContain('login');
});

it('o enum do backend é o mesmo conjunto que o menu da sidebar oferece', function () {
    $tsx = (string) file_get_contents(base_path('resources/js/Components/cockpit/Sidebar.tsx'));

    preg_match('/const PRESENCAS = \[(.*?)\] as const;/s', $tsx, $bloco);
    expect($bloco)->not->toBeEmpty();

    preg_match_all("/id: '([a-z]+)'/", $bloco[1], $ids);

    expect($ids[1])->toBe(UserPreferencesController::PRESENCAS);
    // "Não perturbe" saiu do menu: o protótipo não tem esse estado.
    expect($tsx)->not->toContain('Não perturbe');
});
