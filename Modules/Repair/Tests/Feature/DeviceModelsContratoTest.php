<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável do catálogo de modelos de aparelho — Index + Create + Edit.
 *
 * Os aceites derivam do charter de cada tela e do RUNBOOK-device-models.md (F4 QA),
 * nunca do `.tsx` (proibicoes.md §5 2026-06-05 — teste que deriva do código é
 * tautológico). Onde o código divergir do que o charter promete, o vermelho aqui é o
 * achado, e a correção é decisão [W] (how-trabalhar.md §precedência).
 *
 * Complementa — e NÃO duplica — o `DeviceModelsInertiaSmokeTest`, que é o vizinho:
 * lá se cobre o chaveamento das flags MWART (Blade x Inertia); aqui se cobre o que a
 * tela PROMETE ao operador — filtro server-side, KPI por tenant, isolamento na
 * escrita e forma das opções de select. O bootstrap não é copiado do vizinho de
 * propósito: ele resolve o tenant com `Business::first` (= biz=1, empresa REAL) e
 * pendura o cross-tenant em SQLite, que a lane MySQL nunca executa. Aqui o tenant é
 * `seededTenant()` = 98, fictício e canônico (ADR 0358 — biz=4 proibido, biz=1 real).
 *
 * @see resources/js/Pages/Repair/DeviceModels/Index.charter.md
 * @see resources/js/Pages/Repair/DeviceModels/Create.charter.md
 * @see resources/js/Pages/Repair/DeviceModels/Edit.charter.md
 * @see memory/requisitos/Repair/RUNBOOK-device-models.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

/** Marcador dos registros deste arquivo — o banco do CT 100 PERSISTE entre runs. */
const DM_TAG = '[dm-contrato]';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS de `business` exige MySQL (ADR 0358).');
    }
    foreach (['business', 'users', 'permissions', 'repair_device_models'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo.");
        }
    }
    dmLimpa();
});

afterEach(function () {
    dmLimpa();
    config([
        'mwart.repair_device_models_index.enabled' => false,
        'mwart.repair_device_models_index.business_ids' => [],
        'mwart.repair_device_models_create.enabled' => false,
        'mwart.repair_device_models_create.business_ids' => [],
        'mwart.repair_device_models_edit.enabled' => false,
        'mwart.repair_device_models_edit.business_ids' => [],
    ]);
});

/**
 * Apaga só o que este arquivo cria — sem global scope, em qualquer tenant.
 * Ordem obrigatória: o filho sai antes dos pais (`brand_id` e `device_id` são FK).
 */
function dmLimpa(): void
{
    DB::table('repair_device_models')->where('name', 'like', '%'.DM_TAG.'%')->delete();
    DB::table('brands')->where('name', 'like', '%'.DM_TAG.'%')->delete();
    DB::table('categories')->where('name', 'like', '%'.DM_TAG.'%')->delete();
}

/**
 * Marca real do tenant. `repair_device_models.brand_id` tem FK para `brands`, então
 * id inventado é rejeitado pelo banco antes de o teste chegar ao que quer provar.
 */
function dmMarca(int $businessId, string $nome): int
{
    return (int) DB::table('brands')->insertGetId([
        'business_id' => $businessId,
        'name' => $nome.' '.DM_TAG,
        'created_by' => dmUserIdQualquer(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Categoria de tipo `device` — é o que `Category::forDropdown($biz, 'device')` enxerga. */
function dmCategoria(int $businessId, string $nome): int
{
    return (int) DB::table('categories')->insertGetId([
        'business_id' => $businessId,
        'name' => $nome.' '.DM_TAG,
        'category_type' => 'device',
        'parent_id' => 0,
        'created_by' => dmUserIdQualquer(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Qualquer user existente — `created_by` é FK para `users` nas três tabelas. */
function dmUserIdQualquer(): int
{
    return (int) DB::table('users')->orderBy('id')->value('id');
}

/** Modelo cru no tenant pedido. Sem auth o global scope não filtra (ScopeByBusiness). */
function dmModelo(int $businessId, string $nome, ?int $brandId = null, ?int $deviceId = null): int
{
    return (int) DB::table('repair_device_models')->insertGetId([
        'business_id' => $businessId,
        'name' => $nome.' '.DM_TAG,
        'brand_id' => $brandId,
        'device_id' => $deviceId,
        'created_by' => dmUserIdQualquer(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** User do tenant, com `superadmin` — satisfaz o 1º ramo do gate do controller. */
function dmUser(int $businessId, bool $superadmin = true): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'dm_contrato_'.uniqid(),
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    }

    return $user;
}

/** Sessão mínima: o layout Blade legado lê `currency['code']` sem coalescência. */
function dmSessao(int $businessId, int $userId): void
{
    session([
        'user.business_id' => $businessId,
        'user.id' => $userId,
        'business.id' => $businessId,
        'business.currency_symbol_placement' => 'before',
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/**
 * Headers de um PARTIAL RELOAD — a única forma de resolver props `Inertia::defer`.
 *
 * A versão é CALCULADA, nunca a string literal `'test'`: com versão errada o Inertia
 * responde **409** pedindo recarga, e aí não há payload nenhum para ler.
 *
 * ⚠️ NÃO use isto com `assertInertia`. As duas coisas se excluem, e é essa combinação
 * que deixa o vizinho `DeviceModelsInertiaSmokeTest` mudo: `AssertableInertia::
 * fromTestResponse` começa por `$response->assertViewHas('page')`, ou seja, exige o
 * render HTML com a variável `page`; mandar `X-Inertia: true` faz o servidor devolver
 * JSON, e o assert falha com "Not a valid Inertia response". Lá os dois são usados
 * juntos e o `if (status !== 200) markTestSkipped()` logo abaixo engole o resultado.
 * Medido no CT 100 em 2026-09-05: aquele arquivo roda **5 skipped, 3 passed
 * (3 assertions)** — os 3 verdes são só os `flag OFF`. Skip é verde no relatório, por
 * isso o buraco não aparecia (LC-13: `0 failed` não prova execução; leia as assertions).
 *
 * Daí a divisão neste arquivo: quem afirma COMPONENTE e props visita SEM header
 * (`assertInertia` sobre o render), e quem afirma PAYLOAD DEFERIDO usa este helper e
 * lê por `->json('props.…')`.
 */
function dmParcial(string $componente, string $partial): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => $componente,
        'X-Inertia-Partial-Data' => $partial,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Index — /repair/device-models
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DMIDX-01: com a flag ligada a listagem é servida por Inertia e ecoa o filtro da URL', function () {
    $biz = $this->seededTenant();
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_index.enabled' => true, 'mwart.repair_device_models_index.business_ids' => []]);

    $r = $this->actingAs($user)->get('/repair/device-models?brand_id=7');

    expect($r->status())->toBe(200);
    $r->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Repair/DeviceModels/Index')
        ->where('filters.brand_id', 7)
        ->has('brands')
        ->has('devices')
    );
});

it('UC-DMIDX-02: com a flag desligada a rota não devolve Inertia — o hub Blade segue dono da lista', function () {
    $biz = $this->seededTenant();
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_index.enabled' => false]);

    $r = $this->actingAs($user)->get('/repair/device-models');

    expect($r->headers->get('X-Inertia'))->toBeNull();
});

it('UC-DMIDX-03: a whitelist de business_ids exclui quem está fora e a tela não vira Inertia', function () {
    $biz = $this->seededTenant();
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config([
        'mwart.repair_device_models_index.enabled' => true,
        'mwart.repair_device_models_index.business_ids' => [(int) $biz->id + 999],
    ]);

    $r = $this->actingAs($user)->get('/repair/device-models');

    expect($r->headers->get('X-Inertia'))->toBeNull();
});

it('UC-DMIDX-04: o filtro de marca corta o payload no servidor, não só na tela', function () {
    $biz = $this->seededTenant();
    $marcaA = dmMarca((int) $biz->id, 'MarcaA');
    $marcaB = dmMarca((int) $biz->id, 'MarcaB');
    dmModelo((int) $biz->id, 'Alpha', $marcaA);
    dmModelo((int) $biz->id, 'Beta', $marcaB);
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_index.enabled' => true, 'mwart.repair_device_models_index.business_ids' => []]);

    $r = $this->actingAs($user)
        ->withHeaders(dmParcial('Repair/DeviceModels/Index', 'models'))
        ->get('/repair/device-models?brand_id='.$marcaA);

    expect($r->status())->toBe(200);
    $nomes = collect($r->json('props.models'))->pluck('name');
    expect($nomes)->toHaveCount(1)
        ->and($nomes->first())->toContain('Alpha');
});

it('UC-DMIDX-05: os KPIs contam só o tenant da sessão, nunca o catálogo do vizinho', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    // DELTA, não valor absoluto: o catálogo do tenant não começa vazio. No CT 100 a base
    // persiste entre runs e é compartilhada por outras sessões — afirmar `total === 2`
    // mediria o que os vizinhos deixaram para trás. O contrato é: os 2 modelos que EU criei
    // entram na conta e o do outro tenant NÃO entra, seja qual for a base de partida.
    $totalDe = function (int $businessId) use ($biz, $vizinho) {
        $u = dmUser((int) $biz->id);
        dmSessao((int) $biz->id, (int) $u->id);
        config(['mwart.repair_device_models_index.enabled' => true, 'mwart.repair_device_models_index.business_ids' => []]);

        return (int) $this->actingAs($u)
            ->withHeaders(dmParcial('Repair/DeviceModels/Index', 'kpis'))
            ->get('/repair/device-models')
            ->json('props.kpis.total');
    };

    $antes = $totalDe((int) $biz->id);

    $minhaMarca = dmMarca((int) $biz->id, 'MarcaKpi');
    $marcaAlheia = dmMarca((int) $vizinho->id, 'MarcaVizinha');
    dmModelo((int) $biz->id, 'Meu-A', $minhaMarca);
    dmModelo((int) $biz->id, 'Meu-B', $minhaMarca);
    dmModelo((int) $vizinho->id, 'Alheio', $marcaAlheia);

    $depois = $totalDe((int) $biz->id);

    expect($depois - $antes)->toBe(2);
});

it('UC-DMIDX-06: modelo de outro tenant não aparece na listagem (Tier 0 · ADR 0093)', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    dmModelo((int) $biz->id, 'Meu-Unico');
    dmModelo((int) $vizinho->id, 'Alheio-Invisivel');
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_index.enabled' => true, 'mwart.repair_device_models_index.business_ids' => []]);

    $r = $this->actingAs($user)
        ->withHeaders(dmParcial('Repair/DeviceModels/Index', 'models'))
        ->get('/repair/device-models');

    $nomes = collect($r->json('props.models'))->pluck('name')->implode(' | ');
    expect($nomes)->toContain('Meu-Unico')
        ->and($nomes)->not->toContain('Alheio-Invisivel');
});

// ─────────────────────────────────────────────────────────────────────────────
// Create — /repair/device-models/create  +  POST /repair/device-models
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DMCRE-01: com a flag ligada o formulário é servido por Inertia com marcas e categorias', function () {
    $biz = $this->seededTenant();
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_create.enabled' => true, 'mwart.repair_device_models_create.business_ids' => []]);

    $r = $this->actingAs($user)->get('/repair/device-models/create');

    expect($r->status())->toBe(200);
    $r->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Repair/DeviceModels/Create')
        ->has('brands')
        ->has('devices')
    );
});

it('UC-DMCRE-02: as opções dos selects nunca chegam com chave vazia', function () {
    $biz = $this->seededTenant();
    // Sem dado o teste passaria por vacuidade — um array vazio não contém chave vazia.
    dmMarca((int) $biz->id, 'MarcaOpcao');
    dmCategoria((int) $biz->id, 'CategoriaOpcao');
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_create.enabled' => true, 'mwart.repair_device_models_create.business_ids' => []]);

    $r = $this->actingAs($user)->get('/repair/device-models/create');

    expect($r->status())->toBe(200);
    // Visita sem header Inertia devolve o render HTML: as props saem de `viewData('page')`,
    // não de `->json()` (que aqui estouraria "Invalid JSON was returned from the route").
    $props = $r->viewData('page')['props'] ?? [];
    foreach (['brands', 'devices'] as $prop) {
        $opcoes = (array) ($props[$prop] ?? []);
        expect($opcoes)->not->toBeEmpty("prop {$prop} veio vazia — o teste não provaria nada");
        foreach (array_keys($opcoes) as $chave) {
            expect((string) $chave)->not->toBe('');
        }
    }
});

it('UC-DMCRE-03: o novo modelo nasce no tenant da sessão mesmo quando o payload pede outro (Tier 0)', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    $nome = 'Forjado '.DM_TAG;

    $this->actingAs($user)->post('/repair/device-models', [
        'name' => $nome,
        'business_id' => (int) $vizinho->id, // tentativa explícita de gravar no vizinho
    ]);

    $gravado = DB::table('repair_device_models')->where('name', $nome)->first();
    expect($gravado)->not->toBeNull()
        ->and((int) $gravado->business_id)->toBe((int) $biz->id);
});

it('UC-DMCRE-04: salvar grava no banco mas responde JSON cru — a página não recebe redirect', function () {
    $biz = $this->seededTenant();
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_create.enabled' => true, 'mwart.repair_device_models_create.business_ids' => []]);
    $nome = 'Salvo pela pagina '.DM_TAG;

    $r = $this->actingAs($user)->post('/repair/device-models', ['name' => $nome]);

    // FATO MEDIDO no CT 100 em 2026-09-05, e ele CONTRADIZ o RUNBOOK-device-models.md,
    // cujo risco R1 afirma: "Inertia branch usa redirect padrão Laravel via
    // useForm.post()". Não usa — `store()` devolve `$output` (array) nos dois caminhos,
    // sem nenhum branch de flag: 200 + application/json + sem header X-Inertia.
    //
    // Consequência para a tela (classe LC-30 — correção verde no CI e inerte no runtime):
    // `Create.tsx` faz `post('/repair/device-models')` via `useForm`; o adapter Inertia
    // só sabe seguir 3xx ou consumir resposta Inertia, então o registro nasce no banco e
    // a página fica parada — sem navegar, sem flash, sem erro visível.
    //
    // Este teste fixa o comportamento VIGENTE para que a divergência não se perca, não
    // para abençoá-la. Se ele quebrar porque o `store()` passou a redirecionar, o defeito
    // foi corrigido: atualize UC-DMCRE-04 no casos.md e o R1 do RUNBOOK no mesmo PR.
    // Consertar o código aqui seria conserto silencioso de contrato — decisão de [W].
    expect(DB::table('repair_device_models')->where('name', $nome)->exists())->toBeTrue();
    expect($r->status())->toBe(200);
    expect($r->headers->get('X-Inertia'))->toBeNull();
    expect($r->headers->get('Location'))->toBeNull();
    expect($r->json('success'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Edit — /repair/device-models/{id}/edit  +  PUT /repair/device-models/{id}
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DMEDT-01: com a flag ligada o formulário abre preenchido com o modelo do tenant', function () {
    $biz = $this->seededTenant();
    $id = dmModelo((int) $biz->id, 'Editavel');
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_edit.enabled' => true, 'mwart.repair_device_models_edit.business_ids' => []]);

    $r = $this->actingAs($user)->get("/repair/device-models/{$id}/edit");

    expect($r->status())->toBe(200);
    $r->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Repair/DeviceModels/Edit')
        ->where('model.id', $id)
        ->has('brands')
        ->has('devices')
    );
});

it('UC-DMEDT-02: abrir modelo de outro tenant devolve 404, não o dado do vizinho (Tier 0)', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    $alheio = dmModelo((int) $vizinho->id, 'Alheio-Edit');
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);
    config(['mwart.repair_device_models_edit.enabled' => true, 'mwart.repair_device_models_edit.business_ids' => []]);

    $r = $this->actingAs($user)->get("/repair/device-models/{$alheio}/edit");

    expect($r->status())->toBe(404);
});

it('UC-DMEDT-03: salvar a edição não move o registro para outro tenant (Tier 0)', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    $id = dmModelo((int) $biz->id, 'Permanece');
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);

    $this->actingAs($user)->put("/repair/device-models/{$id}", [
        'name' => 'Renomeado '.DM_TAG,
        'business_id' => (int) $vizinho->id, // tentativa de mudar de dono
    ]);

    $depois = DB::table('repair_device_models')->where('id', $id)->first();
    expect((int) $depois->business_id)->toBe((int) $biz->id);
});

it('UC-DMEDT-04: atualizar modelo de outro tenant não altera o dado do vizinho (Tier 0)', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    $alheio = dmModelo((int) $vizinho->id, 'Intacto');
    $antes = DB::table('repair_device_models')->where('id', $alheio)->value('name');
    $user = dmUser((int) $biz->id);
    dmSessao((int) $biz->id, (int) $user->id);

    $this->actingAs($user)->put("/repair/device-models/{$alheio}", ['name' => 'Invadido '.DM_TAG]);

    $depois = DB::table('repair_device_models')->where('id', $alheio)->value('name');
    expect($depois)->toBe($antes);
});
