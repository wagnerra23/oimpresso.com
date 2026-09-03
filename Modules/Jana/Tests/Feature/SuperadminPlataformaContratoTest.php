<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Http\Controllers\SuperadminController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela `Jana/Plataforma` (`/ia/superadmin/metas`) — F3 do MWART (ADR 0104).
 *
 * ── ÂNCORA DE CONTRATO (externa ao código — teste tautológico é lápide §5 2026-06-05)
 *   · `memory/requisitos/Jana/RUNBOOK-plataforma.md` — a F1: §3 o contrato preservado da
 *     Blade (títulos, colunas, as 2 copies de vazio), §4 o gate das 2 portas, §6.1 a
 *     agregação que NÃO existe e não vai ser inventada.
 *   · `resources/js/Pages/Jana/Plataforma.casos.md` — os UC que cada caso aqui cita.
 *   · ADR 0093 — multi-tenant Tier 0. Esta tela sai do escopo POR DESENHO; o que se
 *     defende é o QUEM, não o escopo.
 *   · ADR 0358 — tenant canônico 98, adversário 99. NUNCA biz=4 (ROTA LIVRE, cliente
 *     real) e NUNCA biz=1 (no CT 100 a base é clone de prod).
 *
 * ── O QUE ESTE ARQUIVO **NÃO** COBRE (e onde está)
 *   O gate em si — 403 do dono de negócio, entrada por permissão real, entrada por
 *   `user_type` — é do `SuperadminMetasCrossTenantTest.php` (#6421), que já traz o
 *   controle positivo de que `can('jana.superadmin')` é `true` para o dono. Não
 *   reimplementado aqui: seria segundo dono do mesmo tema (LC-19).
 *   Este arquivo cobre o CONTRATO DA TELA — component, payload, estados — e o elo que
 *   ninguém cobria: **o ghost do menu perguntar o mesmo que a rota**.
 *
 * @see Modules/Jana/Http/Controllers/SuperadminController.php
 * @see Modules/Jana/Tests/Feature/SuperadminMetasCrossTenantTest.php
 */

const PLATAF_BIZ_CANONICO = 98;
const PLATAF_BIZ_ADVERSARIO = 99;

function platafInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Partial reload — é o que força o `Inertia::defer` a resolver e devolver o payload. */
function platafPartial(object $test, string $prop)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => platafInertiaVersion(),
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => 'Jana/Plataforma',
    ])->get('/ia/superadmin/metas');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    if (! Schema::hasTable('jana_metas')) {
        $this->markTestSkipped('Tabela jana_metas ausente — rode migrate Modules/Jana.');
    }

    $business = Business::find(PLATAF_BIZ_CANONICO);
    if (! $business) {
        $this->markTestSkipped('business_id=98 (tenant canônico ADR 0358) ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', PLATAF_BIZ_CANONICO)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98.');
    }
    $this->user = $user;

    if (! Business::find(PLATAF_BIZ_ADVERSARIO)) {
        Business::forceCreate([
            'id' => PLATAF_BIZ_ADVERSARIO,
            'name' => 'Test Biz Adversario#99 (Plataforma contrato)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => $user->id,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }

    Permission::findOrCreate('jana.access', 'web');
    Permission::findOrCreate('jana.superadmin', 'web');

    $attrs = ['name' => 'Admin#'.PLATAF_BIZ_CANONICO, 'guard_name' => 'web'];
    if (Schema::hasColumn('roles', 'business_id')) {
        $attrs['business_id'] = PLATAF_BIZ_CANONICO;
    }
    $this->user->assignRole(Role::firstOrCreate($attrs));
    $this->user->givePermissionTo('jana.access');
    $this->user->user_type = 'user';
    $this->user->save();
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user);
    session([
        'user.business_id' => PLATAF_BIZ_CANONICO,
        'business' => ['id' => PLATAF_BIZ_CANONICO, 'name' => $business->name],
    ]);
});

/** Dá ao usuário a permissão REAL (a porta que de fato está em uso em produção). */
function platafViraSuperadmin(object $test): void
{
    $test->user->givePermissionTo('jana.superadmin');
    $test->user->forgetCachedPermissions();

    // CONTROLE POSITIVO da porta: sem isto, um 200 poderia vir da porta `user_type`
    // (ou de trava nenhuma) e o teste mediria outra coisa.
    expect($test->user->fresh()->hasPermissionTo('jana.superadmin'))->toBeTrue();
}

it('UC-PLATAF-01 · a rota devolve a tela Inertia Jana/Plataforma, não mais a view Blade', function () {
    platafViraSuperadmin($this);

    $resp = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => platafInertiaVersion(),
    ])->get('/ia/superadmin/metas');

    $resp->assertStatus(200);
    expect($resp->json('component'))->toBe('Jana/Plataforma');

    // As duas props são DEFERIDAS: não vêm no primeiro paint, e é isso que dá à tela um
    // estado de carregando real. Se um dia alguém tirar o `Inertia::defer`, este assert
    // cai — e o `<Deferred fallback>` do .tsx teria virado enfeite sem ninguém notar.
    $deferred = $resp->json('deferredProps') ?? [];
    $achatado = collect($deferred)->flatten()->all();
    expect($achatado)->toContain('metasPlataforma');
    expect($achatado)->toContain('metasDeClientes');
})->group('tier0');

it('UC-PLATAF-02 · o payload traz meta da plataforma (business_id NULL) com Nome · Unidade · Origem', function () {
    platafViraSuperadmin($this);

    $meta = Meta::withoutGlobalScopes()->create([
        'business_id' => null,
        'slug' => 'plat_'.uniqid(),
        'nome' => 'CANARIO-PLATAFORMA',
        'unidade' => '%',
        'tipo_agregacao' => 'ultimo',
        'ativo' => true,
        // ENUM REAL do banco: ['chat_ia','manual','seed'] (migration). NAO usar 'sistema',
        // que e do PROTOTIPO: o MySQL nao-estrito grava '' em vez de falhar.
        'origem' => 'seed',
    ]);

    $linhas = platafPartial($this, 'metasPlataforma')->json('props.metasPlataforma') ?? [];
    $achada = collect($linhas)->firstWhere('id', $meta->id);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: sem ela, uma lista vazia passaria como "contrato ok".
    expect($achada)->not->toBeNull('a meta da plataforma recém-criada não apareceu no payload');
    expect($achada['nome'])->toBe('CANARIO-PLATAFORMA');
    expect($achada['unidade'])->toBe('%');
    expect($achada['origem'])->toBe('seed');
})->group('tier0');

it('UC-PLATAF-03 · a lista de clientes é cross-business e traz período atual + última apuração', function () {
    platafViraSuperadmin($this);

    $meta = Meta::withoutGlobalScopes()->create([
        'business_id' => PLATAF_BIZ_ADVERSARIO,
        'slug' => 'cli_'.uniqid(),
        'nome' => 'CANARIO-CLIENTE-99',
        'unidade' => 'qtd',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);
    MetaPeriodo::create([
        'meta_id' => $meta->id,
        'tipo_periodo' => 'mensal',
        'data_ini' => now()->startOfMonth()->toDateString(),
        'data_fim' => now()->endOfMonth()->toDateString(),
        'valor_alvo' => 10,
    ]);
    MetaApuracao::create([
        'meta_id' => $meta->id,
        'data_ref' => now()->toDateString(),
        'valor_realizado' => 3,
        'calculado_em' => now(),
    ]);

    $linhas = platafPartial($this, 'metasDeClientes')->json('props.metasDeClientes') ?? [];
    $achada = collect($linhas)->firstWhere('id', $meta->id);

    // É POR DESENHO que a meta do tenant 99 aparece pro superadmin logado no 98 — este é
    // o caso legítimo do `withoutGlobalScope` (ADR 0093). O que o gate defende é o QUEM.
    expect($achada)->not->toBeNull('a meta do tenant adversário não apareceu — a visão cross-business quebrou');
    expect($achada['business_id'])->toBe(PLATAF_BIZ_ADVERSARIO);

    // Datas em ISO (Y-m-d): formatar no backend traria o shift +3h do `format_date` (ADR 0066).
    expect($achada['ultima_apuracao'])->toBe(now()->toDateString());
    expect($achada['periodo_atual']['data_ini'])->toBe(now()->startOfMonth()->toDateString());
})->group('tier0');

it('UC-PLATAF-04 · meta nunca apurada chega com ultima_apuracao nula (é o que esmaece a linha)', function () {
    platafViraSuperadmin($this);

    $meta = Meta::withoutGlobalScopes()->create([
        'business_id' => PLATAF_BIZ_ADVERSARIO,
        'slug' => 'semapur_'.uniqid(),
        'nome' => 'CANARIO-SEM-APURACAO',
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);

    $linhas = platafPartial($this, 'metasDeClientes')->json('props.metasDeClientes') ?? [];
    $achada = collect($linhas)->firstWhere('id', $meta->id);

    expect($achada)->not->toBeNull('a meta sem apuração não apareceu no payload');
    expect($achada['ultima_apuracao'])->toBeNull();
    expect($achada['periodo_atual'])->toBeNull();
})->group('tier0');

it('UC-PLATAF-05 · o payload NÃO agrega: nenhuma chave de total/soma cross-business', function () {
    platafViraSuperadmin($this);

    // O charter declara como Non-Goal, com a fonte: o protótipo escreve na própria tela
    // que somar aqui inventaria um total de plataforma. Este caso é o que impede a
    // promessa de voltar por um `sum()` de conveniência num PR futuro.
    $props = platafPartial($this, 'metasDeClientes')->json('props') ?? [];

    foreach (['totais', 'total', 'agregado', 'resumo', 'kpis'] as $proibida) {
        expect(array_key_exists($proibida, $props))->toBeFalse(
            "o payload ganhou a chave agregada \"{$proibida}\" — ver Non-Goal do charter e RUNBOOK-plataforma.md §6.1"
        );
    }
})->group('tier0');

it('UC-PLATAF-07 · as duas listas chegam SEMPRE como lista — nunca null', function () {
    platafViraSuperadmin($this);

    // Por que este caso existe: em produção a tela abre VAZIA (jana_metas = 0 linhas,
    // medido em 31/08/2026), então o estado vazio não é caso de borda — é o caso comum.
    // A regressão que isto defende é o payload virar `null` (um `->values()` removido, um
    // early-return): o `.map` do .tsx quebraria e a tela mostraria ERRO no lugar do
    // EmptyState com a copy da Blade. Falha silenciosa, no caminho mais percorrido.
    //
    // ⚠️ LIMITE HONESTO: este caso prova a FORMA do payload. A copy literal do vazio
    // ("Nenhuma meta da plataforma cadastrada." / "Nenhum cliente configurou metas ainda.")
    // é defendida por outro dono — o gate `contrato-de-tela`, que a pina em
    // prototipo-ui/contrato/jana-plataforma.contract.json e cuja mordida foi provada em
    // 31/08 (mutar a copy → exit 1 nomeando a seção; restaurar → exit 0). Reimplementar
    // aquela verificação aqui seria segundo dono do mesmo tema.
    foreach (['metasPlataforma', 'metasDeClientes'] as $prop) {
        $valor = platafPartial($this, $prop)->json("props.{$prop}");

        expect($valor)->not->toBeNull("a prop {$prop} veio null — a tela quebraria em vez de mostrar o vazio");
        expect($valor)->toBeArray();
    }
})->group('tier0');

it('UC-PLATAF-06 · o ghost do menu pergunta o MESMO que a rota — dono de negócio não vê a aba', function () {
    // CONTROLE POSITIVO: o dono PASSA no `can()` por causa do `Gate::before`. Sem este
    // assert, o `false` do predicado abaixo poderia vir de o usuário não ter permissão
    // nenhuma, e o teste mediria a ausência do bypass em vez da presença da defesa.
    expect($this->user->can('jana.superadmin'))->toBeTrue();
    expect($this->user->hasPermissionTo('jana.superadmin'))->toBeFalse();

    // O predicado do ghost diz NÃO...
    expect(SuperadminController::podeVerPlataforma($this->user))->toBeFalse();
    // ...e a rota diz o mesmo. É o acoplamento que impede "aba visível que dá 403".
    expect($this->get('/ia/superadmin/metas')->status())->toBe(403);

    // E o inverso: com a permissão real, os DOIS viram sim, juntos.
    platafViraSuperadmin($this);
    expect(SuperadminController::podeVerPlataforma($this->user->fresh()))->toBeTrue();
    expect($this->get('/ia/superadmin/metas')->status())->not->toBe(403);
})->group('tier0');
