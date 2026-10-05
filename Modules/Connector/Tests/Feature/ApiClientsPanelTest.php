<?php

declare(strict_types=1);

namespace Modules\Connector\Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * ApiClientsPanelTest — prova mínima do painel /connector/client (Conector · API clients).
 * Thread 04 (2026-10-01): a lista é /connector/client (ClientController::index, Inertia
 * `Api/Index`); a cópia do Cowork usava /connector/api, que é outra tela (errata _saida-01).
 *
 * Escrito no F1 pelo [CC] a partir de Index.charter.md + Index.casos.md
 * (cowork-inbox/connector/). NASCE VERMELHO DE PROPÓSITO em quatro casos —
 * eles são os achados A2/A3/A6/A7 do charter, não regressão do protótipo:
 *
 *   UC-CONN-12  excluir revoga tokens em cadeia   ([W] D2 — ratificado)
 *   UC-CONN-14  rota /connector/regenerate removida ([W] D4 — ratificado)
 *   UC-CONN-15  /connector/client/create não dá 500 (view inexistente)
 *
 * Decisões [W] 2026-08-19 já refletidas: D1 fica em superadmin (UC-CONN-09 é caso
 * NEGATIVO: 403 é o correto e connector.access sai do catálogo) · D3 sem rotação
 * de segredo (nenhum teste de rotate) · D4 regenerar sai da tela e da rota.
 *
 * Rodar: php artisan test --filter=ApiClientsPanelTest
 */
class ApiClientsPanelTest extends TestCase
{
    use DatabaseTransactions;

    private User $superadmin;
    private User $tecnico;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Adaptação [CL] na cópia do Cowork (registrada no _saida-01): o repo não tem
        // Business::factory — tenant fictício 98 + adversário 99 (ADR 0358), e o mesmo
        // guard de driver/schema do ClientControllerBaselineTest (a lane Modules Pest
        // roda em SQLite sem migrate; a prova real é MySQL).
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358)');
        }
        foreach (['oauth_clients', 'oauth_access_tokens', 'business', 'users'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
            }
        }
        foreach (['superadmin', 'connector.access'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->business = $this->seededTenant();
        $this->superadmin = $this->user($this->business);
        $this->superadmin->givePermissionTo('superadmin');
        $this->tecnico = $this->user($this->business);
        session()->put('user.business_id', $this->business->id);
    }

    private function user(Business $business): User
    {
        return User::factory()->create([
            'business_id' => $business->id,
            'username' => 'conn_panel_'.uniqid(),
        ]);
    }

    private function client(?User $owner = null, string $name = 'WR Comercial — balcão'): object
    {
        $owner ??= $this->superadmin;

        $client = Passport::client()->forceFill([
            'user_id' => $owner->id,
            'name' => $name,
            'secret' => Str::random(40),
            'redirect' => 'http://localhost',
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => false,
        ]);
        $client->save();

        return $client;
    }

    private function token(object $client, bool $revoked = false, ?User $user = null, ?\DateTimeInterface $usadoEm = null, ?\DateTimeInterface $venceEm = null): string
    {
        $id = Str::random(80);

        DB::table('oauth_access_tokens')->insert([
            'id' => $id,
            'user_id' => ($user ?? $this->superadmin)->id,
            'client_id' => $client->id,
            'scopes' => '[]',
            'revoked' => $revoked,
            'created_at' => now(),
            'updated_at' => $usadoEm ?? now(),
            'expires_at' => $venceEm ?? now()->addDays(15),
        ]);

        return $id;
    }

    // ── UC-CONN-01 · a lista é do meu negócio ──────────────────────────────
    public function test_index_lista_somente_clients_do_negocio_da_sessao(): void
    {
        $meu = $this->client();

        $outroNegocio = $this->seededSupportClientTenant();
        $outroUser = $this->user($outroNegocio);
        $alheio = $this->client($outroUser, 'Client de outro negócio');

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $res->assertOk();
        // Lido na prop, não no HTML: o data-page é JSON e escapa "—"/"ã" como —/ã,
        // então assertSee do nome falhava com o client na lista e assertDontSee passaria
        // por vácuo (medido 2026-10-01, 1º run da lane connector-pest).
        $nomes = collect($res->viewData('page')['props']['clients'] ?? [])->pluck('name')->all();
        $this->assertContains($meu->name, $nomes);
        $this->assertNotContains($alheio->name, $nomes);
    }

    // ── UC-CONN-02 ❌ segredo não é exibível ([W] D6) ───────────────────────
    public function test_index_nao_imprime_client_secret_no_html(): void
    {
        $c = $this->client();

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $res->assertOk();
        $res->assertDontSee($c->secret);
    }

    public function test_nenhuma_rota_do_painel_devolve_o_segredo(): void
    {
        $c = $this->client();

        // até a thread 03 (2026-10-01) o ClientController::index chamava makeVisible('secret')
        $lista = $this->actingAs($this->superadmin)->get('/connector/client')->getContent();
        $this->assertStringNotContainsString($c->secret, $lista);

        // show/edit nao tem tela: devolvem o painel (antes era 500 com a pagina de erro)
        foreach (["/connector/client/{$c->id}", "/connector/client/{$c->id}/edit"] as $rota) {
            $detalhe = $this->actingAs($this->superadmin)->get($rota);
            $detalhe->assertRedirect('/connector/client');
            $this->assertStringNotContainsString($c->secret, (string) $detalhe->getContent());
        }
    }

    /**
     * Restrição dura ([W] 2026-08-19): o Delphi já está instalado nos clientes e não pode
     * ser alterado — credencial emitida NUNCA para de autenticar.
     *
     * Errata [W] 2026-10-01: até essa data este teste se chamava
     * `test_segredo_nao_e_hasheado_credencial_em_campo_continua_valendo` e exigia o segredo
     * em texto puro (40 caracteres, sem `$2y$`). O contrato estava desatualizado — decisão
     * [W] 2026-10-01: "sim contrato desatualizado. porque eu descriptografo e gravo a senha
     * nova no php". O Passport 13 grava o hash ao salvar e confere o texto puro no login
     * (`Hash::check`), então o hash não invalida o desktop. O que segue valendo: o texto
     * puro entregue UMA vez na criação é o que autentica.
     */
    public function test_segredo_gravado_com_hash_confere_com_o_entregue_na_criacao(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/connector/client', ['name' => 'App do balcão'])
            ->assertRedirect();

        $row = DB::table('oauth_clients')->where('name', 'App do balcão')->first();
        $entregue = (string) session('connector_credencial.secret');

        $this->assertNotNull($row);
        $this->assertSame(40, strlen($entregue), 'a criação entrega o segredo em texto puro, 40 caracteres');
        $this->assertNotSame($entregue, (string) $row->secret, 'o banco guarda o hash, não o texto puro');
        $this->assertTrue(Hash::check($entregue, (string) $row->secret), 'o texto puro entregue confere com o hash gravado');
    }

    public function test_client_preexistente_ainda_obtem_token(): void
    {
        $c = $this->client(name: 'WR Comercial instalado em campo');

        $res = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $c->id,
            // Errata [W] 2026-10-01: até essa data mandava `$c->secret` — que, com o
            // segredo gravado em hash, é o HASH; o Delphi manda o texto puro.
            'client_secret' => $c->plainSecret,
            'username' => $this->superadmin->email,
            'password' => 'password',
        ]);

        // O que não pode acontecer nunca: credencial já instalada deixar de autenticar.
        $this->assertNotSame(401, $res->getStatusCode());
    }

    // ── UC-CONN-04/05 · validação do nome ──────────────────────────────────
    public function test_store_recusa_nome_vazio(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/connector/client', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_store_recusa_nome_acima_de_191_caracteres(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/connector/client', ['name' => str_repeat('a', 192)])
            ->assertSessionHasErrors('name');
    }

    // ── UC-CONN-07 · o client nasce com o formato do controller ────────────
    public function test_store_cria_password_client_com_secret_de_40(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/connector/client', ['name' => 'App do técnico'])
            ->assertRedirect();

        $row = DB::table('oauth_clients')->where('name', 'App do técnico')->first();

        $this->assertNotNull($row);
        // Errata [W] 2026-10-01: até essa data media strlen($row->secret) === 40. O banco
        // guarda o hash (decisão [W] 2026-10-01); os 40 caracteres são do texto puro entregue.
        $entregue = (string) session('connector_credencial.secret');
        $this->assertSame(40, strlen($entregue));
        $this->assertTrue(Hash::check($entregue, (string) $row->secret));
        $this->assertSame('http://localhost', $row->redirect);
        $this->assertEquals(1, $row->password_client);
        $this->assertEquals(0, $row->personal_access_client);
        $this->assertEquals(0, $row->revoked);
        $this->assertEquals($this->superadmin->id, $row->user_id);
    }

    // ── UC-CONN-08 · criar é de superadmin (fail-secure no FormRequest) ────
    public function test_store_recusa_usuario_sem_superadmin(): void
    {
        $this->actingAs($this->tecnico)
            ->post('/connector/client', ['name' => 'Tentativa'])
            ->assertForbidden();
    }

    // ── UC-CONN-09 · não se delega por permissão ([W] D1) ─────────────────
    public function test_connector_access_nao_da_acesso_ao_painel(): void
    {
        // A permissão é removida do catálogo (DataController::user_permissions);
        // mesmo que alguém a conceda, o painel continua sendo de superadmin.
        $this->tecnico->givePermissionTo('connector.access');

        $this->actingAs($this->tecnico)
            ->get('/connector/client')
            ->assertForbidden();
    }

    public function test_catalogo_de_permissoes_do_modulo_nao_declara_connector_access(): void
    {
        $chaves = collect((new \Modules\Connector\Http\Controllers\DataController())->user_permissions())
            ->pluck('value');

        // ❌ hoje declara — a chave sai do catálogo na onda de limpeza ([W] D1)
        $this->assertNotContains('connector.access', $chaves);
    }

    // ── UC-CONN-11 · excluir é do meu negócio ──────────────────────────────
    public function test_destroy_nao_apaga_client_de_outro_negocio(): void
    {
        $outroNegocio = $this->seededSupportClientTenant();
        $outroUser = $this->user($outroNegocio);
        $alheio = $this->client($outroUser, 'Alheio');

        $this->actingAs($this->superadmin)->delete("/connector/client/{$alheio->id}");

        $this->assertDatabaseHas('oauth_clients', ['id' => $alheio->id]);
    }

    public function test_destroy_apaga_client_do_proprio_negocio(): void
    {
        $c = $this->client();

        $this->actingAs($this->superadmin)->delete("/connector/client/{$c->id}");

        $this->assertDatabaseMissing('oauth_clients', ['id' => $c->id]);
    }

    // ── UC-CONN-12 ❌ excluir revoga em cadeia (A3 · [W] D2) ───────────────
    public function test_destroy_revoga_tokens_do_client(): void
    {
        $c = $this->client();
        $tokenId = $this->token($c);

        $this->actingAs($this->superadmin)->delete("/connector/client/{$c->id}");

        // ❌ hoje o token sobrevive à exclusão do client e vale até expires_at
        $this->assertEquals(1, DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));
    }

    // ── UC-CONN-12 · a cadeia inclui o refresh token e a contagem volta ────
    // [CL] thread 02: o WR Comercial usa password grant (access + refresh); revogar só o
    // access deixaria o refresh renovar o acesso. Contagem = tokens ativos revogados.
    public function test_destroy_revoga_refresh_e_devolve_a_contagem(): void
    {
        $c = $this->client();
        $ativo = $this->token($c);
        $this->token($c, revoked: true);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => Str::random(80),
            'access_token_id' => $ativo,
            'revoked' => false,
            'expires_at' => now()->addDays(30),
        ]);

        $res = $this->actingAs($this->superadmin)->delete("/connector/client/{$c->id}");

        $this->assertEquals(1, $res->getSession()->get('status')['revoked_tokens'] ?? null);
        $this->assertEquals(1, DB::table('oauth_refresh_tokens')->where('access_token_id', $ativo)->value('revoked'));
        $this->assertDatabaseMissing('oauth_clients', ['id' => $c->id]);
    }

    public function test_destroy_de_client_alheio_nao_revoga_os_tokens_dele(): void
    {
        $outroNegocio = $this->seededSupportClientTenant();
        $alheio = $this->client($this->user($outroNegocio), 'Alheio');
        $tokenId = $this->token($alheio);

        $this->actingAs($this->superadmin)->delete("/connector/client/{$alheio->id}");

        $this->assertEquals(0, DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));
    }

    // ── CONN-O2 · instalar/desinstalar/atualizar fora de GET ───────────────
    public function test_get_de_uninstall_nao_desativa_o_modulo(): void
    {
        \App\System::addProperty('connector_version', '2.0');

        $this->actingAs($this->superadmin)->get('/connector/install/uninstall');

        $this->assertEquals('2.0', \App\System::getProperty('connector_version'));
    }

    public function test_acoes_de_instalacao_aceitam_post(): void
    {
        foreach (['connector/install', 'connector/install/uninstall', 'connector/install/update'] as $uri) {
            $post = collect(Route::getRoutes())->first(
                fn ($r) => $r->uri() === $uri && in_array('POST', $r->methods(), true)
            );
            $this->assertNotNull($post, "{$uri} precisa aceitar POST — a ação só roda no POST");
        }
    }

    // ── UC-CONN-03 · contagem de tokens ativos em 24 h ────────────────────
    public function test_index_conta_apenas_tokens_ativos_das_ultimas_24h(): void
    {
        $c = $this->client();
        $this->token($c);                 // ativo
        $this->token($c, revoked: true);  // revogado — não conta

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        // Thread 04 (2026-10-01): a lista é Inertia `Api/Index`; só o token ativo conta.
        $res->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Api/Index')->has('clients'));
        $linha = collect($res->viewData('page')['props']['clients'])->firstWhere('id', $c->id);
        $this->assertNotNull($linha, 'o client do próprio negócio precisa estar na lista');
        $this->assertSame(1, $linha['active_tokens_24h']);
        $this->assertArrayNotHasKey('secret', $linha);
    }

    // ── UC-CONN-13/14 ❌ regenerar sai da tela e da rota ([W] D4) ──────────
    public function test_rota_de_regenerate_nao_existe_em_nenhum_verbo(): void
    {
        // ❌ hoje Route::get responde 302 — a rota e o ClientController::regenerate são removidos
        $this->actingAs($this->superadmin)->get('/connector/regenerate')->assertNotFound();
        $this->actingAs($this->superadmin)->post('/connector/regenerate')->assertNotFound();
    }

    public function test_nenhuma_rota_de_ui_do_modulo_chama_passport_install(): void
    {
        $this->assertFalse(
            method_exists(\Modules\Connector\Http\Controllers\ClientController::class, 'regenerate'),
            'ClientController::regenerate deve ser removido — regenerar chaves é operação de servidor ([W] D4).'
        );
    }

    // ── UC-CONN-15 ❌ rota de criação sem view (A6) ────────────────────────
    public function test_rota_de_criacao_do_menu_nao_estoura(): void
    {
        $res = $this->actingAs($this->superadmin)->get('/connector/client/create');

        // ❌ hoje 500: create() devolve view('connector::create'), que não existe no módulo
        $this->assertNotEquals(500, $res->getStatusCode());
    }

    // ── UC-CONN-19 · catálogo bate com as rotas registradas ───────────────
    public function test_api_registra_pelo_menos_20_rotas_no_prefixo_connector_api(): void
    {
        $rotas = collect(Route::getRoutes())->filter(
            fn ($r) => str_starts_with($r->uri(), 'connector/api')
        );

        $this->assertGreaterThanOrEqual(20, $rotas->count());
    }

    // ── UC-CONN-19 · o catálogo da aba Documentação É o conjunto de rotas ────
    // Thread 04 PR-b: o catálogo vem do arquivo de rotas, não de lista escrita à mão.
    // Toda linha existe nas rotas e a contagem do KPI é a do catálogo.
    public function test_catalogo_da_documentacao_sao_as_rotas_registradas(): void
    {
        $uris = collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'connector/api/'))
            ->map(fn ($r) => $r->uri());

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $res->assertOk();
        $props = $res->viewData('page')['props'];
        $this->assertCount($uris->count(), $props['endpoints']);
        $this->assertSame($uris->count(), $props['endpoints_count']);
        foreach ($props['endpoints'] as $e) {
            $this->assertContains('connector/api/'.$e['rota'], $uris->all(), "rota do catálogo inexistente: {$e['rota']}");
            $this->assertNotSame('', $e['acao']);
        }
    }

    // ── UC-CONN-25 · a aba Módulo mostra o estado medido ──────────────────
    public function test_aba_modulo_mostra_versao_e_migracoes_medidas(): void
    {
        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $res->assertOk();
        $modulo = $res->viewData('page')['props']['modulo'];
        $this->assertSame((string) config('connector.module_version', '2.0'), $modulo['versao']);
        $this->assertSame(count(glob(module_path('Connector', 'Database/Migrations/*.php')) ?: []), $modulo['migracoes']);
        $this->assertIsBool($modulo['instalado']);
    }

    // ── UC-CONN-21 · quem usa a credencial — só deste negócio ─────────────
    public function test_quem_usa_lista_so_acessos_abertos_de_usuarios_do_negocio(): void
    {
        $c = $this->client();
        $this->tecnico->forceFill(['first_name' => 'Tecnico', 'last_name' => 'Campo'])->save();
        $this->superadmin->forceFill(['first_name' => 'Admin', 'last_name' => 'Balcao'])->save();
        $alheio = $this->user($this->seededSupportClientTenant());
        $alheio->forceFill(['first_name' => 'Alheio', 'last_name' => 'Outro'])->save();

        $this->token($c, user: $this->tecnico, usadoEm: now()->subMinutes(5));
        $this->token($c, user: $this->superadmin, usadoEm: now()->subHours(3));
        $this->token($c, user: $alheio, usadoEm: now());                       // outro negócio
        $this->token($c, revoked: true, user: $this->tecnico);                 // revogado
        $this->token($c, user: $this->tecnico, venceEm: now()->subDay());      // vencido

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $res->assertOk();
        $linha = collect($res->viewData('page')['props']['clients'])->firstWhere('id', $c->id);
        $this->assertNotNull($linha, 'o client do próprio negócio precisa estar na lista');
        $this->assertSame(['Tecnico Campo', 'Admin Balcao'], array_column($linha['tokens'], 'user_name'));
        $this->assertSame(0, $linha['tokens_resto']);
        $this->assertNotNull($linha['tokens'][0]['last_used_at']);
        $this->assertNotNull($linha['tokens'][0]['expires_at']);
        $this->assertStringNotContainsString('Alheio', json_encode($res->viewData('page')['props']['clients']));
    }

    // ── UC-CONN-21 · top 5 + contagem do resto ────────────────────────────
    public function test_quem_usa_mostra_cinco_e_conta_o_resto(): void
    {
        $c = $this->client();
        foreach (range(1, 7) as $i) {
            $this->token($c, user: $this->tecnico, usadoEm: now()->subMinutes($i));
        }
        $vazio = $this->client(name: 'Sem uso');

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $clients = collect($res->viewData('page')['props']['clients']);
        $linha = $clients->firstWhere('id', $c->id);
        $this->assertCount(5, $linha['tokens']);
        $this->assertSame(2, $linha['tokens_resto']);
        $this->assertSame([], $clients->firstWhere('id', $vazio->id)['tokens']);
        $this->assertSame(0, $clients->firstWhere('id', $vazio->id)['tokens_resto']);
    }

    // ── UC-CONN-16 · demonstração recusa ──────────────────────────────────
    public function test_ambiente_demo_nao_expoe_clients(): void
    {
        config(['app.env' => 'demo']);
        $c = $this->client();

        $res = $this->actingAs($this->superadmin)->get('/connector/client');

        $res->assertOk();
        $res->assertDontSee($c->secret);
    }
}
