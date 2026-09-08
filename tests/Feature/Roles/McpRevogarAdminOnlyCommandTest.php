<?php

declare(strict_types=1);

// Tests\TestCase ja e aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).

use App\Business;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Contrato do `jana:mcp-revogar-admin-only` — o ato SEPARADO que o #6962 deliberadamente nao fez.
 *
 * O #6962 fechou a CONCESSAO de scope `admin_only` (filtro no `mcpScopePermissions` +
 * `__preservaNaoOfertadas` no `RoleController`) e PRESERVOU o que ja estava concedido, porque
 * derrubar acesso de cliente LIVE num deploy e decisao do dono, nao efeito colateral de
 * correcao. Este comando e o ato; estes testes sao o contrato dele.
 *
 * ⚠️ ESCRITA EM PRODUCAO. O caso R1 e o que mais importa: **sem `--apply` nada e escrito**. Um
 * dry-run que escreve e pior que nenhum dry-run, porque o operador confia nele para decidir.
 *
 * ⚠️ DOIS CAMINHOS DE CONCESSAO. O Spatie concede por ROLE (`role_has_permissions`) e DIRETO no
 * usuario (`model_has_permissions`). R4 cobre o segundo — sem ele, uma revogacao so-por-role
 * deixaria o acesso vivo enquanto o relatorio dizia "revogado", que e a forma mais cara de
 * mentir num comando de seguranca.
 *
 * ⚠️ NAO RODADO LOCAL: Pest e CT 100/CI only (proibicoes.md §Ambiente).
 *
 * LANE: `PHP / Pest (Acessos · MySQL)` (advisory) roda `tests/Feature/Roles/` como DIRETORIO.
 *
 * @see Modules/Jana/Console/Commands/McpRevogarAdminOnlyCommand
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: exige schema MySQL UltimatePOS (FKs business/users/roles).');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('roles')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — esta suite roda em MySQL real semeado.');
    }

    // Tenant 98 (ADR 0358) — NUNCA biz=4 nem biz=164, que sao clientes reais.
    $this->biz = Business::find(98) ?: Business::forceCreate([
        'id' => 98,
        'name' => 'Tenant ficticio 98 (ADR 0358)',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);

    $this->adminOnly = array_values(array_map(
        static fn (array $s): string => $s['slug'],
        array_filter(
            McpScopesSeeder::catalogo(),
            static fn (array $s): bool => ($s['admin_only'] ?? false) === true
        )
    ));

    foreach (array_merge($this->adminOnly, ['jana.mcp.use', 'sell.view']) as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $this->papel = Role::create([
        'name' => 'RevogaTeste'.uniqid().'#'.$this->biz->id,
        'business_id' => $this->biz->id,
        'guard_name' => 'web',
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

/** Permissions do papel, relidas do banco. */
function revPerms(Role $r): array
{
    return Role::findOrFail($r->id)->permissions->pluck('name')->sort()->values()->all();
}

it('R0: o comando esta REGISTRADO no artisan (nao apenas a classe existe)', function () {
    // `class_exists`/`app(X::class)` medem o DISCO. Registro e pergunta do REGISTRY vivo —
    // §5 2026-07-28, onde 2 comandos ficaram mortos por 2,4 meses atras de um teste que
    // media a classe e afirmava "registrado" no nome.
    expect(array_keys(Artisan::all()))->toContain('jana:mcp-revogar-admin-only');
});

it('R1: SEM --apply nao escreve nada — dry-run que escreve e pior que nenhum', function () {
    $this->papel->syncPermissions(array_merge(['sell.view'], $this->adminOnly));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $antes = revPerms($this->papel);

    Artisan::call('jana:mcp-revogar-admin-only', ['business_id' => $this->biz->id]);

    expect(revPerms($this->papel))->toBe($antes);

    // Controle positivo: sem ele o caso passaria com o papel vazio, medindo nada.
    expect(array_intersect($antes, $this->adminOnly))->not->toBeEmpty();
});

it('R2: COM --apply revoga TODOS os admin_only e nao toca no resto', function () {
    $this->papel->syncPermissions(array_merge(['sell.view', 'jana.mcp.use'], $this->adminOnly));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Artisan::call('jana:mcp-revogar-admin-only', [
        'business_id' => $this->biz->id,
        '--apply' => true,
    ]);

    $depois = revPerms($this->papel);

    expect(array_values(array_intersect($depois, $this->adminOnly)))->toBe([]);

    // A outra metade do contrato: revogar o alvo NAO pode levar o resto junto. Sem isto,
    // um comando que apagasse todas as permissions passaria na assercao de cima.
    expect($depois)->toContain('sell.view');
    expect($depois)->toContain('jana.mcp.use');
});

it('R3: IDEMPOTENTE — a 2a passada nao encontra nada e sai 0', function () {
    $this->papel->syncPermissions(array_merge(['sell.view'], $this->adminOnly));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Artisan::call('jana:mcp-revogar-admin-only', ['business_id' => $this->biz->id, '--apply' => true]);
    $rc = Artisan::call('jana:mcp-revogar-admin-only', ['business_id' => $this->biz->id, '--apply' => true]);

    expect($rc)->toBe(0);
    expect(Artisan::output())->toContain('Nada a revogar');
    expect(revPerms($this->papel))->toContain('sell.view');
});

it('R4: revoga tambem a concessao DIRETA no usuario — o 2o caminho do Spatie', function () {
    // Sem este caso o comando revogaria a role, reportaria sucesso, e o acesso seguiria vivo
    // pela permission direta. E a forma mais cara de mentir num comando de seguranca.
    $user = User::factory()->create([
        'business_id' => $this->biz->id,
        'username' => 'rev_direto_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('jana.mcp.usage.all');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect(User::findOrFail($user->id)->permissions->pluck('name')->all())
        ->toContain('jana.mcp.usage.all'); // controle positivo do setup

    Artisan::call('jana:mcp-revogar-admin-only', ['business_id' => $this->biz->id, '--apply' => true]);

    expect(User::findOrFail($user->id)->permissions->pluck('name')->all())
        ->not->toContain('jana.mcp.usage.all');
});

it('R5 (Tier 0): nao alcanca papel de OUTRO business', function () {
    // `Role` e Spatie puro e NAO tem global scope de business_id (ADR 0093). Sem o filtro
    // explicito, revogar do biz A alcancaria papel do biz B.
    $outroBiz = Business::find(99) ?: Business::forceCreate([
        'id' => 99,
        'name' => 'Tenant ficticio 99 (cross-tenant)',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);

    $papelVizinho = Role::create([
        'name' => 'RevogaVizinho'.uniqid().'#'.$outroBiz->id,
        'business_id' => $outroBiz->id,
        'guard_name' => 'web',
    ]);
    $papelVizinho->syncPermissions($this->adminOnly);

    $this->papel->syncPermissions($this->adminOnly);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Artisan::call('jana:mcp-revogar-admin-only', ['business_id' => $this->biz->id, '--apply' => true]);

    // O alvo foi limpo...
    expect(array_values(array_intersect(revPerms($this->papel), $this->adminOnly)))->toBe([]);
    // ...e o vizinho ficou INTACTO.
    expect(count(array_intersect(revPerms($papelVizinho), $this->adminOnly)))->toBe(count($this->adminOnly));
});

it('R7: --todos ENCONTRA o business com concessao e diz o total', function () {
    $this->papel->syncPermissions(array_merge(['sell.view'], $this->adminOnly));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Artisan::call('jana:mcp-revogar-admin-only', ['--todos' => true]);
    $out = Artisan::output();

    expect($out)->toContain('VARREDURA GLOBAL');
    expect($out)->toContain('business_id='.$this->biz->id);
    expect($out)->toContain($this->papel->name);
    expect($out)->toContain('RESUMO:');
});

it('R8: --todos NAO escreve nada — e varredura, nao limpeza', function () {
    $this->papel->syncPermissions(array_merge(['sell.view'], $this->adminOnly));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $antes = revPerms($this->papel);

    Artisan::call('jana:mcp-revogar-admin-only', ['--todos' => true]);

    expect(revPerms($this->papel))->toBe($antes);
    expect(array_intersect($antes, $this->adminOnly))->not->toBeEmpty(); // controle positivo
});

it('R9: --todos --apply e RECUSADO (revogacao em massa nao cabe numa flag)', function () {
    $this->papel->syncPermissions(array_merge(['sell.view'], $this->adminOnly));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $antes = revPerms($this->papel);

    $rc = Artisan::call('jana:mcp-revogar-admin-only', ['--todos' => true, '--apply' => true]);

    expect($rc)->toBe(1);                       // recusa, nao no-op silencioso
    expect(revPerms($this->papel))->toBe($antes); // e de fato nao tocou
});

it('R10: --todos ACHA tambem a concessao direta no usuario', function () {
    // O mesmo ponto cego do R4, agora no eixo varredura: uma varredura so-por-role reportaria
    // "nenhum business com concessao" com o acesso vivo pelo caminho direto.
    $user = User::factory()->create([
        'business_id' => $this->biz->id,
        'username' => 'rev_varre_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('jana.mcp.usage.all');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Artisan::call('jana:mcp-revogar-admin-only', ['--todos' => true]);
    $out = Artisan::output();

    expect($out)->toContain('user #'.$user->id.' (direta)');
    expect($out)->toContain('jana.mcp.usage.all');
});

it('R11: sem business_id e sem --todos o comando RECUSA', function () {
    // O argumento virou opcional pra viabilizar o --todos; sem esta guarda, rodar sem nada
    // cairia em business_id=0 e varreria o vazio dizendo "nada a revogar".
    $rc = Artisan::call('jana:mcp-revogar-admin-only', []);

    expect($rc)->toBe(1);
});

it('R6: o catalogo de onde o comando deriva os slugs NAO esta vazio', function () {
    // Controle de sanidade dos casos acima, nao teste da aborcao. O nome diz exatamente
    // isso de proposito: o caminho `catalogo vazio -> FAILURE` existe no comando, mas
    // exercita-lo exigiria mockar o seeder estatico, e um teste cujo NOME promete mais do
    // que o assert exerce e documentacao errada com selo de CI verde (LC-15, registrada
    // hoje mesmo no #6965).
    //
    // O que este caso de fato defende: se o catalogo esvaziasse, R1/R2/R3/R5 passariam por
    // AUSENCIA DE DADO — a intersecao vazia e trivialmente vazia. Aqui isso vira vermelho.
    expect($this->adminOnly)->not->toBeEmpty();
    expect($this->adminOnly)->toContain('jana.mcp.usage.all');
});
