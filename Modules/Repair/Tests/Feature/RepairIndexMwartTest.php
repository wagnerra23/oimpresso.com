<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Sprint 2 / MWART-0001 — cobertura do branch Inertia em `RepairController@index`.
 *
 * ── POR QUE ESTE ARQUIVO FOI REESCRITO (2026-09-05) ──────────────────────────
 * Ele falhava **6 de 6** contra MySQL e ninguém via. A única lane que o alcançava
 * (`modules-pest`) roda `DB_CONNECTION=sqlite :memory:` **sem migrations**: o
 * `Business::first()` estourava, o guard virava `markTestSkipped`, e skip sai
 * **exit 0** — o job dava success sem exercer uma linha. Gate mudo (LC-13).
 *
 * Medido no CT 100 (MySQL) em 2026-09-05, `6 failed (7 assertions)`, com TRÊS
 * causas distintas — não uma:
 *
 *   | Caso                        | Era        | Causa                          |
 *   |-----------------------------|------------|--------------------------------|
 *   | flag ligada / whitelist in  | **409** ×2 | `X-Inertia-Version: 'test'`    |
 *   | sort fora da whitelist      | **409**    | idem (esperava 302)            |
 *   | cross-tenant                | AssertionF | `assertInertia` + header        |
 *   | flag desligada / whitelist  | **500** ×2 | checkout do container defasado  |
 *
 * Os dois **500** NÃO eram defeito deste arquivo nem do produto vivo: o container
 * do CT 100 está em `c1abe9548` (2026-08-26), onde `isMobile()` lia
 * `$_SERVER['HTTP_USER_AGENT']` cru e o Blade estourava `Undefined array key`. O
 * `origin/main` já coalesce (`app/Http/helpers.php:78`) e o comentário de lá pede
 * explicitamente que não se crie um 3º contorno em teste — então aqui não há
 * contorno: o ramo Blade depende do conserto na origem, e é a lane de CI (que faz
 * checkout do HEAD do PR) quem dá o veredito desses dois.
 *
 * ── DECISÕES QUE ESTE ARQUIVO PASSA A HONRAR ────────────────────────────────
 *  1. **Versão do Inertia perguntada AO MIDDLEWARE**, nunca literal nem
 *     reimplementada — reimplementar herdaria o mesmo defeito, porque a regra tem
 *     um ramo (`parent::version()`) que só o próprio middleware conhece.
 *  2. **Header `X-Inertia` ⇒ ler `->json(...)`**. Com o header o servidor responde
 *     JSON, e `AssertableInertia::fromTestResponse` começa por `assertViewHas('page')`,
 *     que espera a casca HTML: header + `assertInertia` é combinação impossível e
 *     falha com "Not a valid Inertia response" mesmo com resposta 200 válida.
 *  3. **Tenant 98** (fictício, ADR 0358) via `seededTenant()`. O `Business::first()`
 *     anterior devolvia **biz=1 — a WR2, empresa REAL** (medido no log do CT 100:
 *     `business_id: 1`), e no CT 100 a base é clone de prod que não se limpa.
 *  4. **Guard de driver explícito**. Sem ele, `seededTenant()` em sqlite estoura
 *     `QueryException` (o trait não captura) e a `modules-pest` ficaria VERMELHA.
 *     Aqui o skip é visível e o veredito real vem da lane MySQL.
 *
 * Transactions de fixture são marcadas em `invoice_no` com `TEST-MWART-` e
 * limpas no `afterEach`.
 *
 * @see .github/workflows/verticais-pest.yml  (lane MySQL — allowlist verde)
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

uses(Tests\TestCase::class, WithSeededTenant::class);

const RMWART_MARCA = 'TEST-MWART-';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS exige MySQL (ADR 0358). Veredito real na lane verticais-pest.');
    }

    foreach (['business', 'users', 'transactions', 'business_locations'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo.");
        }
    }
});

/**
 * Versão do Inertia perguntada AO PRÓPRIO middleware — nunca um literal.
 *
 * `HandleInertiaRequests::version()` devolve `md5_file(public_path('build-inertia/manifest.json'))`
 * quando o manifesto existe e cai no `parent::version()` quando não existe. Onde há
 * build — o CT 100 tem o manifesto (1,4 MB) e a lane de CI o cria no step "Stub Vite
 * manifest" — qualquer literal diverge e o Inertia responde **409**, e aí o teste passa
 * a medir o handshake de versão em vez da tela.
 */
function repairInertiaVersion(): string
{
    return (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
}

/**
 * Tenant canônico 98 + user com as permissões mínimas + sessão UltimatePOS.
 *
 * `access_all_locations` NÃO é decoração: sem ele `permitted_locations()` devolve
 * lista de ids e o Controller aplica `whereIn('transactions.location_id', [...])`
 * (RepairController:549-552). A fila voltaria vazia e as asserções de ausência —
 * a do cross-tenant, em particular — passariam **no vácuo**, provando nada.
 */
function repairBootstrapUser(): array
{
    $business = test()->seededTenant();

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Tenant canônico 98 sem user — seed mínimo incompleto (.github/actions/pest-mysql-setup).');
    }

    foreach (['repair.view', 'repair.view_own', 'repair.create', 'repair.update', 'repair.delete', 'repair_status.update'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    repairGivePerm($user, 'access_all_locations');

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'business'                 => [
            'id'              => $business->id,
            'name'            => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin'                 => true,
    ]);

    return [$business, $user];
}

function repairGivePerm(User $user, string $perm): void
{
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    if (! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
}

/** Cria uma transaction de reparo no tenant, marcada para limpeza. */
function repairTransacao(int $businessId, int $locationId, string $sufixo, array $over = []): void
{
    Transaction::create(array_merge([
        'business_id'      => $businessId,
        'location_id'      => $locationId,
        'type'             => 'sell',
        'sub_type'         => 'repair',
        'status'           => 'final',
        'payment_status'   => 'due',
        'invoice_no'       => RMWART_MARCA.$sufixo,
        'transaction_date' => now(),
        'final_total'      => 1.00,
        'created_by'       => 1,
    ], $over));
}

/** GET em modo Inertia com a versão CERTA. Devolve a resposta JSON. */
function repairGetInertia($test, User $user, string $query = '')
{
    return $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => repairInertiaVersion()])
        ->get('/repair/repair'.$query);
}

/** `invoice_no` de todas as linhas devolvidas pela fila. */
function repairFaturas($response): array
{
    return collect($response->json('props.repairs.data') ?? [])
        ->pluck('invoice_no')->filter()->values()->all();
}

afterEach(function () {
    try {
        Transaction::where('invoice_no', 'like', RMWART_MARCA.'%')->forceDelete();
    } catch (\Throwable $e) {
        // ambiente sem a coluna/tabela — nada a limpar
    }
    config([
        'mwart.repair_index.enabled'      => false,
        'mwart.repair_index.business_ids' => [],
    ]);
});

it('respeita flag MWART desligada — retorna Blade', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config(['mwart.repair_index.enabled' => false]);

    $response = $this->actingAs($user)->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('respeita flag MWART ligada (todos businesses) — retorna Inertia Repair/Index', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [],
    ]);

    $response = repairGetInertia($this, $user);

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    $response->assertOk();

    // Com o header `X-Inertia` a resposta é JSON — o contrato se lê em `props.*`,
    // nunca por `assertInertia` (ver nota 2 do docblock deste arquivo).
    expect($response->json('component'))->toBe('Repair/Index');
    expect($response->json('props.repairs.data'))->toBeArray();
    expect($response->json('props.filters'))->toBeArray();
    expect($response->json('props.meta.repair_statuses'))->not->toBeNull();
    expect($response->json('props.meta.business_locations'))->not->toBeNull();
    expect($response->json('props.meta.totals'))->toBeArray();
    expect($response->json('props.permissions.view_all'))->toBeTrue();
});

it('respeita business_ids whitelist — biz fora da lista usa Blade', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    // Lista whitelist com id que NÃO é o business atual
    $foreignId = $business->id + 999;
    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [$foreignId],
    ]);

    $response = $this->actingAs($user)->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('respeita business_ids whitelist — biz dentro da lista usa Inertia', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [(int) $business->id],
    ]);

    $response = repairGetInertia($this, $user);

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    $response->assertOk();
    expect($response->json('component'))->toBe('Repair/Index');
});

it('força business_id scope — não vaza dados de outro tenant', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [],
    ]);

    $locId = DB::table('business_locations')->where('business_id', $business->id)->value('id');
    if (! $locId) {
        test()->markTestSkipped('Tenant 98 sem business_location — seed mínimo incompleto.');
    }

    // O "outro tenant" é o 99 FICTÍCIO, nunca `Business::where('id','!=',...)->first()`:
    // aquele devolvia **biz=1, a WR2 real**, e semear fixture ali escreve dentro do
    // espelho de uma empresa em operação (ADR 0358).
    $outroBiz = $this->seededSupportClientTenant();

    // `location_id` do tenant 98 de propósito: a FK exige uma location existente e o
    // que este caso prova é o filtro por `business_id`, não o vínculo da location.
    try {
        repairTransacao((int) $outroBiz->id, (int) $locId, 'CROSS-1');
        repairTransacao((int) $business->id, (int) $locId, 'PROPRIA-1');
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema de transactions não permite create simples: '.$e->getMessage());
    }

    $response = repairGetInertia($this, $user);

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    $faturas = repairFaturas($response);

    // CONTROLE POSITIVO — sem ele a asserção de ausência passaria no vácuo quando a
    // fila voltasse vazia, e um teste que não pode reprovar é carimbo, não defesa.
    expect($faturas)->toContain(RMWART_MARCA.'PROPRIA-1');
    expect($faturas)->not->toContain(RMWART_MARCA.'CROSS-1');
});

it('valida sort fora da whitelist é rejeitado', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [],
    ]);

    $response = repairGetInertia($this, $user, '?sort=hax;DROP--&dir=asc');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(302); // redirect with errors
});
