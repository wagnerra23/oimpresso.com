<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\Meta;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Aba Plataforma da Jana (`/ia/superadmin/metas`) — CONTRATO. Fecha os UC do `Plataforma.casos.md`.
 *
 * A asserção central é de GATE (Tier 0): a aba e a rota só existem para quem tem
 * `jana.superadmin` DE VERDADE no Spatie — `can()` não serve (Gate::before, P0 #6421).
 * Tenant: `seededTenant()`. ⚠️ skip sai exit 0: leia ASSERTIONS (LC-13).
 */
const PLAT_CONTRATO = 'prototipo-ui/contrato/jana-plataforma.contract.json';
const PLAT_ALVO     = 'resources/js/Pages/Jana/Plataforma.tsx';

function plataformaBootstrap(): array
{
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    // Usuário comum (não `user_type` superadmin): é o único em que o gate real morde.
    $user = User::where('business_id', $business->id)
        ->whereNotIn('user_type', ['superadmin', 'user_oimpresso'])
        ->first();

    if (! $user) {
        test()->markTestSkipped("Sem usuário não-superadmin em business_id={$business->id}.");
    }

    try {
        Permission::findOrCreate('jana.access', 'web');
        Permission::findOrCreate('jana.superadmin', 'web');
        $user->givePermissionTo('jana.access');
        $user->revokePermissionTo('jana.superadmin');
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível preparar as permissions: '.$e->getMessage());
    }

    test()->actingAs($user);
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business'         => ['id' => $business->id, 'name' => $business->name],
    ]);

    return [$business, $user];
}

/**
 * A entry da Jana no `shell.menu` é gated pela ASSINATURA (Camada 1 — `jana_module` no
 * `package_details`, `ModuleUtil::hasThePermissionInSubscription`), não só pela permission.
 * O tenant do seed pode não ter assinatura: garantimos uma (transação, rollback no fim) —
 * é o mesmo eixo que o `JanaPlanoTierTest` escreve. Sem isto o UC-00 media o pacote, não a aba.
 */
function plataformaGaranteAssinaturaJana(Business $business, User $user): void
{
    if (! class_exists(\Modules\Superadmin\Entities\Subscription::class)) {
        return; // sem Superadmin, `hasThePermissionInSubscription` devolve true sozinho
    }

    $sub = \Modules\Superadmin\Entities\Subscription::active_subscription($business->id);

    if (! $sub) {
        $pkg = \Modules\Superadmin\Entities\Package::query()->first()
            ?: \Modules\Superadmin\Entities\Package::query()->forceCreate([
                'name' => 'Pacote de teste (Jana)', 'description' => 'fixture', 'location_count' => 0,
                'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0, 'interval' => 'months',
                'interval_count' => 1, 'trial_days' => 0, 'price' => 0, 'custom_permissions' => [],
                'created_by' => $user->id, 'is_active' => 1,
            ]);
        $sub = \Modules\Superadmin\Entities\Subscription::query()->forceCreate([
            'business_id' => $business->id, 'package_id' => $pkg->id,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
            'package_price' => 0, 'package_details' => [], 'created_id' => $user->id, 'status' => 'approved',
        ]);
    }

    $detalhes = (array) ($sub->package_details ?? []);
    $detalhes['jana_module'] = 1;
    $sub->package_details = $detalhes;
    $sub->save();
}

function plataformaGhosts(array $props): array
{
    $jana = collect($props['shell']['menu'] ?? [])
        ->first(fn ($m) => ($m['group'] ?? null) === 'ia' || strtolower($m['label'] ?? '') === 'jana');

    return array_column($jana['ghosts'] ?? [], 'key');
}

it('UC-PLAT-00: sem jana.superadmin REAL a rota dá 403 e a aba não existe; com ela, 200 e 6ª aba', function () {
    [$business, $user] = plataformaBootstrap();
    plataformaGaranteAssinaturaJana($business, $user);

    // MORDE: sem a permissão atribuída, 403 — mesmo que `can()` dissesse true (Gate::before).
    $this->get('/ia/superadmin/metas')->assertStatus(403);
    $ghostsSem = plataformaGhosts($this->get('/ia')->inertiaPage()['props']);
    expect($ghostsSem)->not->toContain('plataforma');

    // PASSA: permissão real no Spatie.
    $user->givePermissionTo('jana.superadmin');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $resposta = $this->get('/ia/superadmin/metas')->assertStatus(200);
    $resposta->assertInertia(fn ($page) => $page->component('Jana/Plataforma'));

    $ghosts = plataformaGhosts($resposta->inertiaPage()['props']);
    expect($ghosts)->toBe(['dashboard', 'copiloto', 'alertas', 'acoes', 'memorias', 'plataforma']);
});

it('UC-PLAT-01: as duas listas vêm cruas e cross-business, e as contagens de instalação vêm do disco', function () {
    [$business, $user] = plataformaBootstrap();
    $user->givePermissionTo('jana.superadmin');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $outro = Business::where('id', '!=', $business->id)->first();
    if (! $outro) {
        test()->markTestSkipped('Só um business no seed — sem como provar o cross-business.');
    }

    $alheia = Meta::create(['business_id' => $outro->id, 'slug' => 'uc-plat-01-'.uniqid(), 'nome' => 'Alheia', 'unidade' => 'R$', 'tipo_agregacao' => 'soma', 'ativo' => true, 'origem' => 'manual']);
    $plat   = Meta::create(['business_id' => null, 'slug' => 'uc-plat-01-plat-'.uniqid(), 'nome' => 'Da plataforma', 'unidade' => '%', 'tipo_agregacao' => 'media', 'ativo' => true, 'origem' => 'manual']);

    $props = $this->get('/ia/superadmin/metas')->assertStatus(200)->inertiaPage()['props'];

    // Plataforma: só NULL, e a que criei está lá com o formato da tela.
    expect(collect($props['metasPlataforma'])->pluck('id')->all())->toContain($plat->id);
    $linhaPlat = collect($props['metasPlataforma'])->firstWhere('id', $plat->id);
    expect(array_keys($linhaPlat))->toBe(['id', 'nome', 'slug', 'unidade', 'origem']);

    // Clientes: a meta de OUTRO business aparece — é o caso legítimo do ADR 0093 nesta tela.
    $linhaCli = collect($props['metasDeClientes'])->firstWhere('id', $alheia->id);
    expect($linhaCli)->not->toBeNull()
        ->and($linhaCli['business_id'])->toBe($outro->id)
        ->and($linhaCli['empresa'])->toBe($outro->name)
        ->and($linhaCli['periodo'])->toBeNull()
        ->and($linhaCli['ultima'])->toBeNull();
    expect(collect($props['metasDeClientes'])->pluck('id')->all())->not->toContain($plat->id);

    // Instalação: contagens do DISCO/registry, não digitadas.
    $inst = $props['instalacao'];
    expect($inst['migrations'])->toBe(count(glob(module_path('Jana', 'Database/Migrations/*.php'))))
        ->and($inst['seeders'])->toBe(count(glob(module_path('Jana', 'Database/Seeders/*.php'))))
        ->and($inst['permissoes'])->toBe(count((include module_path('Jana', 'Resources/permissions.php'))['permissions']))
        ->and($inst['podeOperar'])->toBe((bool) $user->can('superadmin'));
});

// ── ARQUIVO ──────────────────────────────────────────────────────────────────

it('UC-PLAT-02: copy e ordem do contrato no alvo — e o alerta caducado da âncora NÃO está', function () {
    $c    = json_decode(file_get_contents(base_path(PLAT_CONTRATO)), true);
    $blob = implode("\n", array_map(fn ($f) => file_get_contents(base_path($f)), $c['alvo']));

    preg_match_all('/data-contract\s*=\s*["\']([^"\']+)["\']/u', $blob, $m);
    $seq = $m[1];

    foreach ($c['secoes'] as $s) {
        expect($seq)->toContain($s['id']);
        foreach ($s['copy'] as $copy) {
            expect($blob)->toContain($copy);
        }
    }
    $pos = array_map(fn ($id) => array_search($id, $seq, true), $c['ordem']);
    expect($pos)->not->toContain(false);
    $sorted = $pos; sort($sorted);
    expect($pos)->toBe($sorted);

    // Negativo: o P0 fechou em 28/08 (#6421) — copy que o afirma aberto não pode voltar.
    expect(file_get_contents(base_path(PLAT_ALVO)))->not->toContain('não separa dono de empresa de superadmin');
});
