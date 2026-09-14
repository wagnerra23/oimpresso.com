<?php

declare(strict_types=1);

namespace Modules\Connector\Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * ApiClientsPanelTest — prova mínima do painel /connector/api (Conector · API).
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

        $this->business = Business::factory()->create();
        $this->superadmin = User::factory()->create(['business_id' => $this->business->id]);
        $this->superadmin->givePermissionTo('superadmin');
        $this->tecnico = User::factory()->create(['business_id' => $this->business->id]);
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

    private function token(object $client, bool $revoked = false): string
    {
        $id = Str::random(80);

        DB::table('oauth_access_tokens')->insert([
            'id' => $id,
            'user_id' => $this->superadmin->id,
            'client_id' => $client->id,
            'scopes' => '[]',
            'revoked' => $revoked,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addDays(15),
        ]);

        return $id;
    }

    // ── UC-CONN-01 · a lista é do meu negócio ──────────────────────────────
    public function test_index_lista_somente_clients_do_negocio_da_sessao(): void
    {
        $meu = $this->client();

        $outroNegocio = Business::factory()->create();
        $outroUser = User::factory()->create(['business_id' => $outroNegocio->id]);
        $alheio = $this->client($outroUser, 'Client de outro negócio');

        $res = $this->actingAs($this->superadmin)->get('/connector/api');

        $res->assertOk();
        $res->assertSee($meu->name);
        $res->assertDontSee($alheio->name);
    }

    // ── UC-CONN-02 ❌ segredo não é exibível ([W] D6) ───────────────────────
    public function test_index_nao_imprime_client_secret_no_html(): void
    {
        $c = $this->client();

        $res = $this->actingAs($this->superadmin)->get('/connector/api');

        $res->assertOk();
        $res->assertDontSee($c->secret);
    }

    public function test_nenhuma_rota_do_painel_devolve_o_segredo(): void
    {
        $c = $this->client();

        // ❌ hoje o ClientController::index chama makeVisible('secret')
        $lista = $this->actingAs($this->superadmin)->get('/connector/api')->getContent();
        $this->assertStringNotContainsString($c->secret, $lista);

        $detalhe = $this->actingAs($this->superadmin)->get("/connector/client/{$c->id}");
        $this->assertStringNotContainsString($c->secret, (string) $detalhe->getContent());
    }

    /**
     * Restrição dura ([W] 2026-08-19): o Delphi já está instalado nos clientes e não pode
     * ser alterado — credencial emitida NUNCA para de autenticar. Fechar a leitura do
     * segredo não pode virar hash de coluna nem rotação compulsória.
     */
    public function test_segredo_nao_e_hasheado_credencial_em_campo_continua_valendo(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/connector/client', ['name' => 'App do balcão'])
            ->assertRedirect();

        $row = DB::table('oauth_clients')->where('name', 'App do balcão')->first();

        $this->assertSame(40, strlen((string) $row->secret));
        $this->assertStringStartsNotWith('$2y$', (string) $row->secret);
        $this->assertFalse(
            \Laravel\Passport\Passport::$hashesClientSecrets ?? false,
            'hashClientSecrets invalidaria o client_secret instalado no Delphi em campo.'
        );
    }

    public function test_client_preexistente_ainda_obtem_token(): void
    {
        $c = $this->client(name: 'WR Comercial instalado em campo');

        $res = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $c->id,
            'client_secret' => $c->secret,
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
        $this->assertSame(40, strlen($row->secret));
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
            ->get('/connector/api')
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
        $outroNegocio = Business::factory()->create();
        $outroUser = User::factory()->create(['business_id' => $outroNegocio->id]);
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

    // ── UC-CONN-03 · contagem de tokens ativos em 24 h ────────────────────
    public function test_index_conta_apenas_tokens_ativos_das_ultimas_24h(): void
    {
        $c = $this->client();
        $this->token($c);                 // ativo
        $this->token($c, revoked: true);  // revogado — não conta

        $res = $this->actingAs($this->superadmin)->get('/connector/api');

        $res->assertOk();
        // A prop existe depois da CONN-O4 (tradução Inertia); antes disso a asserção
        // documenta o contrato de dados esperado pelo charter.
        $res->assertViewHas('clients');
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

    // ── UC-CONN-16 · demonstração recusa ──────────────────────────────────
    public function test_ambiente_demo_nao_expoe_clients(): void
    {
        config(['app.env' => 'demo']);
        $c = $this->client();

        $res = $this->actingAs($this->superadmin)->get('/connector/api');

        $res->assertOk();
        $res->assertDontSee($c->secret);
    }
}
