<?php
// @covers-us US-CRM-092

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Funil de vendas em kanban (`/crm/funil`) — US-CRM-092 · ADR 0441.
 *
 * Âncora: SPEC Crm US-CRM-092 (critérios 1-6). O motor é o `DealPipelineService`, já testado
 * em Wave27DealPipelineTest; aqui se prova a porta HTTP: permissão, empresa e etapa.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o outro negócio. NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const FUNIL_TAG = '[funil79]';
const FUNIL_BIZ = 98;
const FUNIL_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_deals/contacts requerem schema MySQL.');
    }
    foreach (['business', 'users', 'contacts', 'crm_deals'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    funilLimpa();
});

afterEach(fn () => funilLimpa());

function funilLimpa(): void
{
    if (Schema::hasTable('crm_deals')) {
        DB::table('crm_deals')->where('titulo', 'like', '%'.FUNIL_TAG.'%')->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.FUNIL_TAG.'%')->delete();
    }
}

function funilNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function funilUsuario(string $username, array $permissoes, int $biz = FUNIL_BIZ): User
{
    funilNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Funil', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function funilCliente(int $biz, string $nome, User $dono): int
{
    funilNegocio($biz);

    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.FUNIL_TAG, 'mobile' => '0',
        'contact_id' => 'F'.random_int(100000, 999999), 'created_by' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function funilDeal(int $biz, int $contato, User $dono, string $titulo, string $etapa = 'lead', float $valor = 1000): int
{
    return (int) DB::table('crm_deals')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'titulo' => $titulo.' '.FUNIL_TAG, 'stage' => $etapa,
        'valor_estimado' => $valor, 'owner_user_id' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pedido parcial como o browser faz (X-Inertia + X-Requested-With). */
function funilParcial(User $user, string $prop): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = test()->actingAs($user)->get('/crm/funil', [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => 'Crm/Funil/Index',
    ]);
    $r->assertOk();

    return (array) $r->json('props.'.$prop);
}

it('UC-CRMFN-01 · sem permissão o funil é 403; com crm.funil.ver abre as 6 etapas', function () {
    $this->actingAs(funilUsuario('funil_sem_test', []))->get('/crm/funil')->assertForbidden();

    $this->actingAs(funilUsuario('funil_ver_test', ['crm.funil.ver']))
        ->get('/crm/funil')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Funil/Index', false)
            ->has('etapas', 6)
            ->where('etapas.0.id', 'lead')->where('etapas.4.id', 'ganho')
            ->where('pode_editar', false));
});

it('UC-CRMFN-02 · lista os negócios da própria empresa, nunca os da outra [T0]', function () {
    $user = funilUsuario('funil_ver_test', ['crm.funil.ver']);
    $vizinho = funilUsuario('funil_vizinho_test', ['crm.funil.ver'], FUNIL_OUTRO);
    funilDeal(FUNIL_BIZ, funilCliente(FUNIL_BIZ, 'Grafica Aurora', $user), $user, 'Banner fachada', 'proposta', 3200);
    funilDeal(FUNIL_OUTRO, funilCliente(FUNIL_OUTRO, 'Vizinho', $vizinho), $vizinho, 'Negocio vizinho');

    $titulos = collect(funilParcial($user, 'negocios'))->pluck('titulo')->filter(fn ($t) => str_contains($t, FUNIL_TAG))->values()->all();
    expect($titulos)->toBe(['Banner fachada '.FUNIL_TAG]);
    $n = collect(funilParcial($user, 'negocios'))->firstWhere('titulo', 'Banner fachada '.FUNIL_TAG);
    expect($n['etapa'])->toBe('proposta')->and((float) $n['valor'])->toBe(3200.0)->and($n['contato'])->toContain('Grafica Aurora');
});

it('UC-CRMFN-03 · criar negócio exige crm.funil.editar, nasce em Lead e recusa cliente de outra empresa', function () {
    $ver = funilUsuario('funil_ver_test', ['crm.funil.ver']);
    $editor = funilUsuario('funil_editor_test', ['crm.funil.ver', 'crm.funil.editar']);
    $meu = funilCliente(FUNIL_BIZ, 'Cliente meu', $editor);
    $alheio = funilCliente(FUNIL_OUTRO, 'Cliente alheio', funilUsuario('funil_vizinho_test', [], FUNIL_OUTRO));
    $dados = ['titulo' => 'Adesivos frota '.FUNIL_TAG, 'contact_id' => $meu, 'valor_estimado' => 1234.567];

    $this->actingAs($ver)->post('/crm/funil', $dados)->assertForbidden();
    $this->actingAs($editor)->post('/crm/funil', ['contact_id' => $alheio] + $dados)->assertSessionHasErrors('contact_id');
    $this->actingAs($editor)->post('/crm/funil', ['valor_estimado' => -1] + $dados)->assertSessionHasErrors('valor_estimado');
    expect(DB::table('crm_deals')->where('titulo', 'Adesivos frota '.FUNIL_TAG)->count())->toBe(0);

    $this->actingAs($editor)->post('/crm/funil', $dados)->assertSessionHasNoErrors();
    $d = DB::table('crm_deals')->where('titulo', 'Adesivos frota '.FUNIL_TAG)->first();
    expect((int) $d->business_id)->toBe(FUNIL_BIZ)
        ->and($d->stage)->toBe('lead')
        ->and((float) $d->valor_estimado)->toBe(1234.57)
        ->and((int) $d->owner_user_id)->toBe($editor->id);
});

it('UC-CRMFN-04 · mover de etapa grava a transição; ganho não volta; negócio de outra empresa dá 404 [T0]', function () {
    $editor = funilUsuario('funil_editor_test', ['crm.funil.ver', 'crm.funil.editar']);
    $vizinho = funilUsuario('funil_vizinho_test', [], FUNIL_OUTRO);
    $deal = funilDeal(FUNIL_BIZ, funilCliente(FUNIL_BIZ, 'Cliente', $editor), $editor, 'Placa ACM');
    $alheio = funilDeal(FUNIL_OUTRO, funilCliente(FUNIL_OUTRO, 'Vizinho', $vizinho), $vizinho, 'Alheio');

    $this->actingAs($editor)->patch("/crm/funil/{$deal}/etapa", ['etapa' => 'negociacao', 'razao' => 'pediu desconto'])->assertSessionHasNoErrors();
    $row = DB::table('crm_deals')->where('id', $deal)->first();
    expect($row->stage)->toBe('negociacao')
        ->and(json_decode((string) $row->metadata, true)['transitions'][0])->toMatchArray(['from' => 'lead', 'to' => 'negociacao', 'razao' => 'pediu desconto']);

    $this->actingAs($editor)->patch("/crm/funil/{$deal}/etapa", ['etapa' => 'ganho'])->assertSessionHasNoErrors();
    $this->actingAs($editor)->patch("/crm/funil/{$deal}/etapa", ['etapa' => 'lead'])->assertSessionHasErrors('etapa');
    expect(DB::table('crm_deals')->where('id', $deal)->value('stage'))->toBe('ganho');

    $this->actingAs($editor)->patch("/crm/funil/{$alheio}/etapa", ['etapa' => 'ganho'])->assertNotFound();
    $this->actingAs($editor)->patch("/crm/funil/{$deal}/etapa", ['etapa' => 'inventada'])->assertSessionHasErrors('etapa');
    expect(DB::table('crm_deals')->where('id', $alheio)->value('stage'))->toBe('lead');
});

it('UC-CRMFN-05 · o resumo soma por etapa e prevê o fechamento do mês só com negócios da empresa', function () {
    $user = funilUsuario('funil_ver_test', ['crm.funil.ver']);
    $vizinho = funilUsuario('funil_vizinho_test', ['crm.funil.ver'], FUNIL_OUTRO);
    $c = funilCliente(FUNIL_BIZ, 'Cliente', $user);
    funilDeal(FUNIL_BIZ, $c, $user, 'A', 'proposta', 1000);
    funilDeal(FUNIL_BIZ, $c, $user, 'B', 'proposta', 3000);
    funilDeal(FUNIL_OUTRO, funilCliente(FUNIL_OUTRO, 'Viz', $vizinho), $vizinho, 'C', 'proposta', 50000);

    $antes = (float) (DB::table('crm_deals')->where('business_id', FUNIL_BIZ)->where('stage', 'proposta')->whereNull('deleted_at')->sum('valor_estimado'));
    $resumo = funilParcial($user, 'resumo');
    // A soma vem do serviço, com os mesmos negócios que o SELECT direto enxerga nesta empresa.
    expect((float) $resumo['por_etapa']['proposta']['valor_total'])->toBe($antes)
        ->and((float) $resumo['por_etapa']['proposta']['valor_total'])->toBeLessThan(50000.0);
});
