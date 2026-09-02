<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * /ia/memoria — isolamento Tier 0 e o LIMITE da camada de permissão.
 *
 * ── O CONTRATO (derivado do charter + ADR 0093, NÃO do .tsx — §5 2026-06-05)
 * `Memoria.charter.md` Mission: *"Acesso `business_id` scoped strict — fato cross-tenant
 * é bug Tier 0"*; Non-Goals: *"⛔ Mostrar fato de outro business"*.
 *
 * ── POR QUE ESTE ARQUIVO NÃO PROVA "403 sem a permissão de escrita"
 * MEDIDO em 2026-09-02: a permissão que o charter mandava (`copiloto.memoria.manage`)
 * NÃO EXISTE — `Modules/Jana/Resources/permissions.php` tem 22 keys, todas `jana.*`, e os
 * únicos hits do repo estavam DENTRO do próprio charter. E `jana.mcp.memory.manage`, a
 * única com "memory" no nome, é de OUTRO acervo: o `McpScopesSeeder:170` a declara como
 * *"Gerenciar KB MCP (mcp_memory_documents)"*, `admin_only: true` / `business_required: false`,
 * enquanto esta tela governa `jana_memoria_facts` (Modules/Jana) e é business-scoped.
 * Qual key deve travar a escrita é decisão [W] — o UC-JPERM-07 da emenda do Cowork
 * (`prototipo-ui/design-docs/cowork-inbox/JANA-CASOS-EMENDA-PERMISSAO-2026-08-27.md`) segue
 * ⬜ até lá, DE PROPÓSITO: escrever o caso antes da trava quebraria o G-2 do casos-gate.
 *
 * O que este arquivo faz enquanto isso: prova o isolamento (que existe e é Tier 0) e
 * TRAVA o limite medido da permissão, pra que ele não volte a ser invisível.
 *
 * TENANT: 98 canônico × 2 adversário — o par que o seed do CI semeia
 * (`.github/actions/pest-mysql-setup/action.yml`, que proíbe 99 por ser o
 * SUPPORT_CLIENT_TENANT_ID). NUNCA biz=4, NUNCA biz=1 (ADR 0358).
 *
 * @see Modules/KB/Http/Controllers/MemoriaController.php
 * @see Modules/Jana/Http/routes.php (linha 50 — `can:jana.access` do grupo /ia)
 */
const MEMPERM_BIZ = 98;
const MEMPERM_BIZ_ALHEIO = 2;

/** Insere um fato cru — o global scope filtra SELECT, então o INSERT tem de ser direto. */
function memPermFato(int $businessId, int $userId, string $texto): int
{
    return (int) DB::table('jana_memoria_facts')->insertGetId([
        'business_id' => $businessId,
        'user_id' => $userId,
        'fato' => $texto,
        'valid_from' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function memPermApagado(int $id): bool
{
    return DB::table('jana_memoria_facts')->where('id', $id)->whereNotNull('deleted_at')->exists();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    foreach ([MEMPERM_BIZ, MEMPERM_BIZ_ALHEIO] as $bid) {
        if (! Business::find($bid)) {
            $this->markTestSkipped("business_id={$bid} ausente — rode o seed do pest-mysql-setup.");
        }
    }
    $user = User::where('business_id', MEMPERM_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id='.MEMPERM_BIZ.'.');
    }
    $this->user = $user;

    Permission::findOrCreate('jana.access', 'web');

    // NÃO-admin de propósito: o `Gate::before` (AuthServiceProvider:34-47) devolve true em
    // qualquer ability pra quem tem `Admin#{business_id}`. Com o papel, todo caso de
    // permissão deste arquivo mediria o Gate::before, não a permissão. Rollback pela
    // transação — nada disto sobrevive ao teste.
    $this->user->syncRoles([]);
    $this->user->syncPermissions([]);
    $this->user->givePermissionTo('jana.access');
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user);
    session([
        'user.business_id' => MEMPERM_BIZ,
        'business' => ['id' => MEMPERM_BIZ, 'name' => Business::find(MEMPERM_BIZ)->name],
    ]);
});

it('CONTROLE: o Gate::before NÃO está liberando este usuário — sem isso nada abaixo mede permissão', function () {
    // `can()` de uma ability que o user NÃO tem precisa dar FALSE. Se der true, o
    // `Gate::before` (AuthServiceProvider:34-47) está devolvendo true por `Admin#{biz}` e
    // TODOS os casos abaixo viram teatro — medem o Gate, não a permissão. É o controle que
    // impede este arquivo de ficar verde por vácuo.
    expect($this->user->can('jana.superadmin'))->toBeFalse();
    expect($this->user->hasRole('Admin#'.MEMPERM_BIZ))->toBeFalse();
    expect($this->user->hasPermissionTo('jana.access'))->toBeTrue();
})->group('tier0');

it('UC-MEM-07 · Tier 0 — a listagem não mostra fato de outro business', function () {
    $meu = 'Cheque so e depositado na terca (fato do tenant '.MEMPERM_BIZ.')';
    $alheio = 'SEGREDO DO TENANT '.MEMPERM_BIZ_ALHEIO.' QUE NUNCA PODE VAZAR';

    // MESMO user_id nos dois fatos, DE PROPÓSITO: se o isolamento dependesse do `user_id`,
    // o caso passaria sem provar tenancy. Com o user igual, só o `business_id` pode separá-los.
    memPermFato(MEMPERM_BIZ, (int) $this->user->id, $meu);
    memPermFato(MEMPERM_BIZ_ALHEIO, (int) $this->user->id, $alheio);

    $resp = $this->get(route('jana.memoria.index'));

    // Anti-vácuo: sem estas duas pernas, um 403/500 faria o assertDontSee passar por engano.
    $resp->assertStatus(200);
    $resp->assertSee($meu, false);

    $resp->assertDontSee($alheio, false);
})->group('tier0');

it('UC-MEM-07 · Tier 0 — apagar id de outro business não apaga nada', function () {
    // Esta é a perna FORTE do isolamento: `MemoriaContrato::esquecer()` faz
    // `MemoriaFato::find($id)` cru — não há filtro de tenant no Controller nem no driver,
    // só o global scope do `HasBusinessScope`. Se ele cair, este caso é o que grita.
    $alheio = memPermFato(MEMPERM_BIZ_ALHEIO, (int) $this->user->id, 'fato do vizinho');
    $meu = memPermFato(MEMPERM_BIZ, (int) $this->user->id, 'fato meu');

    $this->delete(route('jana.memoria.destroy', ['id' => $alheio]));
    expect(memPermApagado($alheio))->toBeFalse('VAZAMENTO Tier 0: DELETE cruzou o business_id.');

    // Anti-vácuo: prova que o verbo FUNCIONA no fato próprio — senão um DELETE quebrado
    // (rota morta, 500) satisfaria o assert acima sem provar isolamento nenhum.
    $this->delete(route('jana.memoria.destroy', ['id' => $meu]));
    expect(memPermApagado($meu))->toBeTrue();
})->group('tier0');

it('UC-MEM-08 · LIMITE MEDIDO: hoje `jana.access` sozinho JÁ APAGA — quando a trava existir, este caso DEVE quebrar', function () {
    // ⚠️ Este caso NÃO abençoa o estado atual: ele o MEDE, pra que pare de ser invisível.
    // O charter prometia "⛔ Permitir edit sem <permissão de escrita>" desde 2026-05-16 e a
    // trava nunca existiu — nem a key. Enquanto [W] não decide qual permissão trava a
    // escrita, este assert é o que garante que ninguém diga "a tela está protegida".
    //
    // QUANDO A TRAVA FOR ADICIONADA: este caso fica vermelho. Isso é o sinal, não o defeito —
    // troque-o pelo UC-JPERM-07 (403 sem a permissão / 302 com ela) e atualize o charter.
    $perms = $this->user->getAllPermissions()->pluck('name')->all();
    expect($perms)->toHaveCount(1);
    expect($perms)->toContain('jana.access');

    $id = memPermFato(MEMPERM_BIZ, (int) $this->user->id, 'fato que sera apagado sem permissao de escrita');

    $this->delete(route('jana.memoria.destroy', ['id' => $id]))->assertStatus(302);

    expect(memPermApagado($id))->toBeTrue(
        'Se este assert falhou, a trava de escrita PASSOU A EXISTIR — atualize o charter e troque este caso pelo UC-JPERM-07.'
    );
})->group('tier0');
