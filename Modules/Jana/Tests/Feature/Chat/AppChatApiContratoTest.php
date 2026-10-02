<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Support\ContextoNegocio;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * API de Chat com a Jana do app das lojas (tela 25) — POST /api/app/chat e GET /api/app/chat/{id}.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.4 + decisão [W] relatada pela
 * sessão do app (canal = Jana; reaproveitar o fluxo de conversa da Jana). NÃO derivado do controller.
 *
 * O único dublê é o AiAdapter (não há LLM em CI). A rota, o controller e o turno (ChatTurnoService,
 * o mesmo do chat web) são reais, e as mensagens vão para jana_conversas/jana_mensagens.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2. Transação revertida.
 */

const APP_CHAT_BIZ = 98;
const APP_CHAT_OUTRO = 2;

final class FakeAiAdapterAppChat implements AiAdapter
{
    /** @var list<string> */
    public array $recebidas = [];

    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        return '';
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        return [];
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        $this->recebidas[] = $mensagem;

        return 'Resposta da Jana para: ' . $mensagem;
    }

    public function responderChatStream(Conversa $conv, string $mensagem): \Generator
    {
        yield $this->responderChat($conv, $mensagem);
    }

    public function ultimoResultadoStream(): array
    {
        return [];
    }

    public function ultimoUsoTokens(): array
    {
        return ['tokens_in' => 11, 'tokens_out' => 22];
    }
}

function appChatUsuario(array $permissoes, int $biz = APP_CHAT_BIZ): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    $papel = Role::create(['name' => 'AppChat' . uniqid() . '#' . $biz, 'business_id' => $biz, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_CHAT_BIZ, APP_CHAT_OUTRO])->count() !== 2
        || ! Schema::hasTable('jana_conversas') || ! Schema::hasTable('jana_mensagens')) {
        $this->markTestSkipped('Tenants 98/2 ou tabelas do chat da Jana ausentes nesta lane.');
    }
    $this->ia = new FakeAiAdapterAppChat();
    app()->instance(AiAdapter::class, $this->ia);
    $this->comModulo = true;
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(
        fn ($biz, $chave) => $chave === 'jana_module' ? $this->comModulo : false
    );
    app()->instance(ModuleUtil::class, $mu);
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-APP25-01: sem token 401; sem jana.chat, sem jana.access ou sem o módulo no plano é 403 e a área some', function () {
    $this->postJson('/api/app/chat', ['mensagem' => 'oi'])->assertStatus(401);

    foreach ([['jana.access'], ['jana.chat']] as $permissoes) {
        Passport::actingAs(appChatUsuario($permissoes), [], 'api');
        $this->postJson('/api/app/chat', ['mensagem' => 'oi'])->assertForbidden()->assertJsonPath('erro', 'sem_permissao');
        expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('assistente');
    }

    $u = appChatUsuario(['jana.access', 'jana.chat']);
    Passport::actingAs($u, [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('assistente');

    $this->comModulo = false;
    $this->postJson('/api/app/chat', ['mensagem' => 'oi'])->assertForbidden();
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('assistente');
    expect($this->ia->recebidas)->toBe([]);
    expect(DB::table('jana_conversas')->where('user_id', $u->id)->count())->toBe(0);
});

it('UC-APP25-02: a 1ª mensagem abre conversa da Jana no meu business; a 2ª continua nela; o histórico vem em ordem', function () {
    $u = appChatUsuario(['jana.access', 'jana.chat']);
    Passport::actingAs($u, [], 'api');

    $r1 = $this->postJson('/api/app/chat', ['mensagem' => 'Quanto vendi hoje?', 'conversa_id' => null])->assertOk();
    $id = $r1->json('conversa_id');
    expect($id)->toBeString();
    expect($r1->json('resposta.de'))->toBe('jana');
    expect($r1->json('resposta.texto'))->toBe('Resposta da Jana para: Quanto vendi hoje?');
    expect($r1->json('resposta.criada_em'))->toBeString();

    $conversa = DB::table('jana_conversas')->where('id', (int) $id)->first();
    expect((int) $conversa->business_id)->toBe(APP_CHAT_BIZ);
    expect((int) $conversa->user_id)->toBe((int) $u->id);
    $resposta = DB::table('jana_mensagens')->where('conversa_id', (int) $id)->where('role', 'assistant')->first();
    expect((int) $resposta->tokens_in)->toBe(11); // o mesmo turno do chat web grava os tokens

    $r2 = $this->postJson('/api/app/chat', ['mensagem' => 'E ontem?', 'conversa_id' => $id])->assertOk();
    expect($r2->json('conversa_id'))->toBe($id);
    expect(DB::table('jana_conversas')->where('user_id', $u->id)->count())->toBe(1);

    $h = $this->getJson("/api/app/chat/{$id}")->assertOk();
    expect($h->json('conversa_id'))->toBe($id);
    expect(collect($h->json('mensagens'))->map(fn ($m) => [$m['de'], $m['texto']])->all())->toBe([
        ['eu', 'Quanto vendi hoje?'],
        ['jana', 'Resposta da Jana para: Quanto vendi hoje?'],
        ['eu', 'E ontem?'],
        ['jana', 'Resposta da Jana para: E ontem?'],
    ]);
    expect(array_keys($h->json('mensagens.0')))->toBe(['de', 'texto', 'criada_em']);
});

it('UC-APP25-03: conversa de OUTRO usuário ou de OUTRO business é 404, nada é lido nem gravado (Tier 0)', function () {
    $eu = appChatUsuario(['jana.access', 'jana.chat']);
    $colega = appChatUsuario(['jana.access', 'jana.chat']);
    $alheio = appChatUsuario(['jana.access', 'jana.chat'], APP_CHAT_OUTRO);
    $doColega = (int) DB::table('jana_conversas')->insertGetId([
        'business_id' => APP_CHAT_BIZ, 'user_id' => $colega->id, 'titulo' => 'Do colega', 'status' => 'ativa',
        'iniciada_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $doOutroBiz = (int) DB::table('jana_conversas')->insertGetId([
        'business_id' => APP_CHAT_OUTRO, 'user_id' => $alheio->id, 'titulo' => 'Alheia', 'status' => 'ativa',
        'iniciada_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Mesmo id de usuário em outro business: a conversa gravada sob o business 2 não é minha.
    $minhaSobOutroBiz = (int) DB::table('jana_conversas')->insertGetId([
        'business_id' => APP_CHAT_OUTRO, 'user_id' => $eu->id, 'titulo' => 'Sob outro business', 'status' => 'ativa',
        'iniciada_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    Passport::actingAs($eu, [], 'api');

    foreach ([$doColega, $doOutroBiz, $minhaSobOutroBiz] as $id) {
        $this->getJson("/api/app/chat/{$id}")->assertNotFound()->assertJsonPath('erro', 'nao_encontrado');
        $this->postJson('/api/app/chat', ['mensagem' => 'oi', 'conversa_id' => (string) $id])->assertNotFound();
    }
    expect(DB::table('jana_mensagens')->whereIn('conversa_id', [$doColega, $doOutroBiz, $minhaSobOutroBiz])->count())->toBe(0);
    expect($this->ia->recebidas)->toBe([]);

    // Controle positivo: a minha conversa no meu business abre.
    $minha = $this->postJson('/api/app/chat', ['mensagem' => 'oi'])->assertOk()->json('conversa_id');
    $this->getJson("/api/app/chat/{$minha}")->assertOk()->assertJsonCount(2, 'mensagens');
});

it('UC-APP25-04: mensagem vazia ou acima de 1000 caracteres é 422 e não chama a Jana', function () {
    Passport::actingAs(appChatUsuario(['jana.access', 'jana.chat']), [], 'api');

    $this->postJson('/api/app/chat', ['mensagem' => '   '])->assertStatus(422)->assertJsonPath('erro', 'validacao')
        ->assertJsonStructure(['campos' => ['mensagem']]);
    $this->postJson('/api/app/chat', ['mensagem' => str_repeat('a', 1001)])->assertStatus(422);
    $this->postJson('/api/app/chat', [])->assertStatus(422);
    expect($this->ia->recebidas)->toBe([]);
});
