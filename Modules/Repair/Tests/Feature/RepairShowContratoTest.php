<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável do detalhe da venda-de-reparo — `/repair/repair/{id}`.
 *
 * Cada `it()` cita no TÍTULO o UC que defende — é o título que o manifesto G-7 lê.
 *
 * O que este arquivo NÃO refaz: flag MWART on/off, 404 cross-tenant e a flag própria
 * do painel FSM já são defendidos por `Wave3B6RepairShowTest.php`; estão no casos.md
 * como `[BACKLOG]` apontando para lá, em vez de duplicados aqui.
 *
 * ⚠️ Nomes de função com prefixo `rshw` de propósito: função de topo em PHP é GLOBAL,
 * e duas declarações homônimas em arquivos diferentes derrubam a suíte inteira com
 * "Cannot redeclare". Os vizinhos usam `ridx`/`repairSettings`/`repairBootstrap`.
 *
 * Tenant: `seededTenant()` = 98 (fictício, canônico — ADR 0358).
 *
 * @see resources/js/Pages/Repair/Show.casos.md
 * @see resources/js/Pages/Repair/Show.charter.md
 * @see memory/requisitos/Repair/RUNBOOK-repair-show.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

const RSHW_MARCA = 'TEST-CONTRATO-RSHW-';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS exige MySQL (ADR 0358)');
    }
    foreach (['business', 'users', 'transactions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
        }
    }
});

afterEach(function () {
    try {
        Transaction::where('invoice_no', 'like', RSHW_MARCA.'%')->forceDelete();
    } catch (\Throwable $e) {
        // ambiente sem a coluna/tabela — nada a limpar
    }
    config(['mwart.repair_show.enabled' => false, 'mwart.repair_show.business_ids' => []]);
});

/**
 * A versão do Inertia perguntada AO PRÓPRIO middleware, não reimplementada.
 *
 * Medido no CT 100 em 2026-09-05: `RepairIndexMwartTest` manda o literal `'test'` e
 * falha 6/6 com **409** (conflito de versão) — mede o handshake, não a tela. Ninguém
 * viu porque a única lane que roda aquele arquivo (`modules-pest`) é SQLite `:memory:`
 * sem migrations, onde tudo pula. Reimplementar a regra (`md5_file` do manifesto) tem
 * o mesmo defeito com passos extras: `HandleInertiaRequests::version()` cai no
 * `parent::version()` quando `build-inertia/manifest.json` não existe, e aí qualquer
 * literal nosso diverge. Perguntar à fonte vale nos dois ambientes.
 */
function rshwInertiaVersion(): string
{
    return (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
}

/** `access_all_locations` evita que `permitted_locations()` esvazie tudo e o teste passe no vácuo. */
function rshwUser(int $businessId, array $perms): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'rshw_'.uniqid(),
    ]);

    foreach (array_merge(['access_all_locations'], $perms) as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }

    return $user;
}

function rshwSessao(int $businessId, int $userId): void
{
    session([
        'user.business_id' => $businessId,
        'user.id' => $userId,
        'business.id' => $businessId,
        'business.currency_symbol' => 'R$',
        'business' => ['id' => $businessId, 'name' => 'Tenant 98', 'currency_symbol' => 'R$'],
    ]);
}

/** Venda-de-reparo do tenant, marcada para limpeza. */
function rshwVenda(int $businessId, string $sufixo, array $over = []): Transaction
{
    $locId = DB::table('business_locations')->where('business_id', $businessId)->value('id');
    if (! $locId) {
        test()->markTestSkipped('Tenant 98 sem business_location — seed mínimo incompleto.');
    }

    return Transaction::create(array_merge([
        'business_id' => $businessId,
        'location_id' => $locId,
        'type' => 'sell',
        'sub_type' => 'repair',
        'status' => 'final',
        'payment_status' => 'due',
        'invoice_no' => RSHW_MARCA.$sufixo,
        'transaction_date' => now(),
        'final_total' => 1234.56,
        'created_by' => 1,
    ], $over));
}

/** GET do detalhe em modo Inertia com a flag ligada. `$partial` força resolver o defer. */
function rshwAbrirDetalhe($test, User $user, int $id, ?string $partial = null)
{
    config(['mwart.repair_show.enabled' => true, 'mwart.repair_show.business_ids' => []]);
    rshwSessao((int) $user->business_id, (int) $user->id);

    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => rshwInertiaVersion()];
    if ($partial !== null) {
        $headers['X-Inertia-Partial-Data'] = $partial;
        $headers['X-Inertia-Partial-Component'] = 'Repair/Show';
    }

    $resp = $test->actingAs($user)->withHeaders($headers)->get('/repair/repair/'.$id);

    if ($resp->status() === 403) {
        test()->markTestSkipped('Gate de assinatura/módulo barra repair_module neste ambiente.');
    }

    return $resp;
}

it('UC-RSHW-01 · o payload é o de uma VENDA, não o de uma Ordem-de-Serviço', function () {
    $biz = $this->seededTenant();
    $user = rshwUser((int) $biz->id, ['repair.view']);
    $venda = rshwVenda((int) $biz->id, 'PAYLOAD', ['repair_serial_no' => 'SN-CONTRATO-1']);

    $r = rshwAbrirDetalhe($this, $user, (int) $venda->id);
    $r->assertOk();

    expect($r->json('component'))->toBe('Repair/Show');

    // campos que só existem porque isto é uma VENDA
    expect($r->json('props.sell.id'))->toBe((int) $venda->id);
    expect($r->json('props.sell.invoice_no'))->toBe(RSHW_MARCA.'PAYLOAD');
    expect($r->json('props.sell.payment_status'))->toBe('due');

    // ...e as três chaves de faturamento existem, mesmo vazias
    $sell = $r->json('props.sell');
    foreach (['final_total', 'final_total_formatted', 'sell_lines', 'payments'] as $chave) {
        expect($sell)->toHaveKey($chave);
    }

    // campos do aparelho — o que distingue reparo de venda comum
    expect($r->json('props.sell.serial_no'))->toBe('SN-CONTRATO-1');
    foreach (['device_model_name', 'status', 'defects', 'warranty_name'] as $chave) {
        expect($sell)->toHaveKey($chave);
    }
});

it('UC-RSHW-02 · o histórico não segura o primeiro paint (activities vem deferida)', function () {
    $biz = $this->seededTenant();
    $user = rshwUser((int) $biz->id, ['repair.view']);
    $venda = rshwVenda((int) $biz->id, 'DEFER');

    // 1ª volta: a prop deferida NÃO pode estar no payload inicial
    $inicial = rshwAbrirDetalhe($this, $user, (int) $venda->id);
    $inicial->assertOk();
    expect($inicial->json('props'))->not->toHaveKey('activities');

    // 2ª volta (partial reload): agora resolve — controle positivo de que a prop EXISTE.
    // Sem esta metade, a ausência acima seria indistinguível de "a prop foi removida".
    $parcial = rshwAbrirDetalhe($this, $user, (int) $venda->id, 'activities');
    $parcial->assertOk();
    expect($parcial->json('props'))->toHaveKey('activities');
});

it('UC-RSHW-03 · sem repair.view, a tela não existe', function () {
    $biz = $this->seededTenant();
    $venda = rshwVenda((int) $biz->id, 'NEGADO');

    // usuário do tenant, sem repair.view e sem superadmin
    $semPerm = rshwUser((int) $biz->id, []);

    config(['mwart.repair_show.enabled' => true, 'mwart.repair_show.business_ids' => []]);
    rshwSessao((int) $biz->id, (int) $semPerm->id);

    $this->actingAs($semPerm)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => rshwInertiaVersion()])
        ->get('/repair/repair/'.$venda->id)
        ->assertStatus(403);

    // controle positivo: a MESMA venda abre para quem tem a permissão — prova que o 403
    // veio da permissão, e não de a venda não existir ou de o ambiente barrar tudo.
    rshwAbrirDetalhe($this, rshwUser((int) $biz->id, ['repair.view']), (int) $venda->id)
        ->assertOk();
});

it('UC-RSHW-04 · o valor sai formatado em pt-BR, e o número cru vai junto', function () {
    $biz = $this->seededTenant();
    $user = rshwUser((int) $biz->id, ['repair.view']);
    $venda = rshwVenda((int) $biz->id, 'VALOR', ['final_total' => 1234.56]);

    $r = rshwAbrirDetalhe($this, $user, (int) $venda->id);
    $r->assertOk();

    // ponto de milhar e vírgula decimal — a convenção pt-BR
    expect($r->json('props.sell.final_total_formatted'))->toBe('R$ 1.234,56');
    // e o número cru sobrevive: quem recalcular não reparseia a string
    expect((float) $r->json('props.sell.final_total'))->toBe(1234.56);
});
