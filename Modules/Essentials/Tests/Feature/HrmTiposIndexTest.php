<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * PR-9 do pedido HRM (HRM-O7) — a lista de tipos de licença vira Inertia.
 *
 * Baseline F2 do MWART (ADR 0104): trava o `index()` ANTES de a Page mandar no
 * comportamento. O que este arquivo defende é o ISOLAMENTO do payload — a tela
 * mostra tipos e uma contagem de pedidos, e as duas coisas somam por tenant.
 *
 * A guarda de EXCLUSÃO não se repete aqui: ela já tem baseline próprio em
 * HrmExclusaoGuardaTest.php (PR #6789 — 422 com blocked_by, 404 cross-tenant,
 * DELETE que de fato apaga). Duplicar seria manter duas verdades sobre a mesma regra.
 *
 * Tenant: 98 (canônico, empresa FICTÍCIA) vs 99 (adversário cross-tenant) —
 * ADR 0358, que supersede a 0101 e tira o biz=1 do papel de default. NUNCA biz=4.
 * ADR 0093 Tier 0 IRREVOGÁVEL.
 *
 * `DatabaseTransactions`, nunca `RefreshDatabase`: a lane essentials-pest roda contra
 * MySQL semeado pela action, e um `migrate:fresh` dropa o schema das 16 lanes.
 */
beforeEach(function () {
    if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }

    foreach (['essentials_leave_types', 'essentials_leaves'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Essentials.");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->actor = $user;

    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role->name);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // NÃO setar session manualmente: SetSessionData roda DEPOIS do auth e reconstrói
    // user.business_id do usuário autenticado (padrão do HrmExclusaoGuardaTest).
    session()->flush();
    $this->actingAs($user);
});

function tiposInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function tiposCriarTipo(int $bizId, string $nome, ?int $max = null, ?string $intervalo = null): EssentialsLeaveType
{
    // INSERT não é filtrado pelo global scope — business_id explícito permite cross-tenant.
    return EssentialsLeaveType::create([
        'business_id' => $bizId,
        'leave_type' => $nome.'-'.uniqid(),
        'max_leave_count' => $max,
        'leave_count_interval' => $intervalo,
    ]);
}

/**
 * Headers que o NAVEGADOR manda numa visita Inertia (@inertiajs/core 3.x, getHeaders).
 * `X-Requested-With` vai em TODA visita — sem ele o teste passava verde enquanto a tela real
 * ficava no skeleton, porque o controller desviava para o JSON do DataTables (staging 2026-09-23).
 */
function tiposHeadersNavegador(array $extra = []): array
{
    return array_merge([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => tiposInertiaVersion(),
    ], $extra);
}

/** Resolve o `Inertia::defer` de `tipos` — no first render a prop nem existe. */
function tiposPayload($test): array
{
    $response = $test->withHeaders(tiposHeadersNavegador([
        'X-Inertia-Partial-Data' => 'tipos',
        'X-Inertia-Partial-Component' => 'Essentials/Tipos',
    ]))->get('/hrm/leave-type');

    $response->assertStatus(200);

    return $response->json('props.tipos') ?? [];
}

// ── Render ──────────────────────────────────────────────────────────────────────

it('UC-TIPOS-01: a lista responde 200 e renderiza o componente Inertia Essentials/Tipos', function () {
    $response = $this->withHeaders(tiposHeadersNavegador())->get('/hrm/leave-type');

    $response->assertStatus(200);
    expect($response->json('component'))->toBe('Essentials/Tipos');
    // `can_manage` é eager (booleano barato); `tipos` é defer e NÃO vem no first render.
    expect($response->json('props.can_manage'))->toBeTrue();
});

it('UC-TIPOS-02: o limite chega como max_leave_count + leave_count_interval, e nulo continua nulo', function () {
    $comLimite = tiposCriarTipo($this->tenant->id, 'UC02-ferias', 30, 'year');
    $semLimite = tiposCriarTipo($this->tenant->id, 'UC02-abonada');

    $porId = collect(tiposPayload($this))->keyBy('id');

    expect($porId[$comLimite->id]['max_leave_count'])->toBe(30);
    expect($porId[$comLimite->id]['leave_count_interval'])->toBe('year');

    // Ausência de limite é NULA no payload — a tela decide o rótulo ("sem limite"),
    // o servidor não inventa 0, que significaria "zero dias permitidos".
    expect($porId[$semLimite->id]['max_leave_count'])->toBeNull();
    expect($porId[$semLimite->id]['leave_count_interval'])->toBeNull();
});

// ── Tier 0 · isolamento ─────────────────────────────────────────────────────────

it('UC-TIPOS-03: tipo do tenant adversário NÃO aparece na lista (ADR 0093)', function () {
    $meu = tiposCriarTipo($this->tenant->id, 'UC03-meu');
    $alheio = tiposCriarTipo($this->adversario->id, 'UC03-alheio');

    $ids = array_column(tiposPayload($this), 'id');

    expect($ids)->toContain($meu->id);
    expect($ids)->not->toContain($alheio->id);
});

it('UC-TIPOS-04: "Pedidos no ano" conta só licenças DO TENANT, nunca as do vizinho', function () {
    $tipo = tiposCriarTipo($this->tenant->id, 'UC04-tipo');

    $adversarioUser = User::where('business_id', $this->adversario->id)->first();
    if (! $adversarioUser) {
        $this->markTestSkipped('Sem user no tenant adversário — seed mínimo não rodou.');
    }

    $ano = now()->year;

    // 2 licenças do MEU tenant nesse tipo.
    foreach ([1, 2] as $i) {
        EssentialsLeave::create([
            'business_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'essentials_leave_type_id' => $tipo->id,
            'start_date' => $ano.'-03-0'.$i,
            'end_date' => $ano.'-03-0'.$i,
            'status' => 'approved',
        ]);
    }

    // 3 licenças do ADVERSÁRIO apontando pro MESMO id de tipo. Não há FK nesta coluna
    // (migration 2019_05_17_175921 só cria índice), então o banco aceita — e é
    // exatamente por isso que o filtro por business_id na contagem precisa existir.
    foreach ([1, 2, 3] as $i) {
        EssentialsLeave::create([
            'business_id' => $this->adversario->id,
            'user_id' => $adversarioUser->id,
            'essentials_leave_type_id' => $tipo->id,
            'start_date' => $ano.'-04-0'.$i,
            'end_date' => $ano.'-04-0'.$i,
            'status' => 'approved',
        ]);
    }

    $porId = collect(tiposPayload($this))->keyBy('id');

    expect($porId[$tipo->id]['leaves_count'])->toBe(2);
});

it('UC-TIPOS-05: "Pedidos no ano" ignora licença de ano anterior', function () {
    $tipo = tiposCriarTipo($this->tenant->id, 'UC05-tipo');
    $anoPassado = now()->year - 1;

    EssentialsLeave::create([
        'business_id' => $this->tenant->id,
        'user_id' => $this->actor->id,
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => $anoPassado.'-05-01',
        'end_date' => $anoPassado.'-05-02',
        'status' => 'approved',
    ]);

    $porId = collect(tiposPayload($this))->keyBy('id');

    expect($porId[$tipo->id]['leaves_count'])->toBe(0);
});

// ── Férias CLT (US-PONTO-018) ───────────────────────────────────────────────────

it('UC-TIPOS-09: o payload diz se o tipo conta como férias da CLT, e o default é falso', function () {
    if (! Schema::hasColumn('essentials_leave_types', 'ferias_clt')) {
        $this->markTestSkipped('Coluna ferias_clt ausente — migration do Essentials não rodou.');
    }
    $comum  = tiposCriarTipo($this->tenant->id, 'UC09-abonada');
    $ferias = tiposCriarTipo($this->tenant->id, 'UC09-ferias');
    $ferias->forceFill(['ferias_clt' => true])->save();

    $porId = collect(tiposPayload($this))->keyBy('id');

    expect($porId[$comum->id]['ferias_clt'])->toBeFalse();
    expect($porId[$ferias->id]['ferias_clt'])->toBeTrue();
});

it('UC-TIPOS-10: editar marca e desmarca "conta como férias"; o tipo do vizinho não muda (ADR 0093)', function () {
    if (! Schema::hasColumn('essentials_leave_types', 'ferias_clt')) {
        $this->markTestSkipped('Coluna ferias_clt ausente — migration do Essentials não rodou.');
    }
    $meu    = tiposCriarTipo($this->tenant->id, 'UC10-meu');
    $alheio = tiposCriarTipo($this->adversario->id, 'UC10-alheio');

    $this->put("/hrm/leave-type/{$meu->id}", ['leave_type' => $meu->leave_type, 'ferias_clt' => '1'])
        ->assertStatus(200);
    $this->assertTrue((bool) EssentialsLeaveType::withoutGlobalScopes()->find($meu->id)->ferias_clt, 'marcar grava true'); // SUPERADMIN: leitura de fixture

    // Checkbox desmarcado não vem no POST do formulário.
    $this->put("/hrm/leave-type/{$meu->id}", ['leave_type' => $meu->leave_type])->assertStatus(200);
    $this->assertFalse((bool) EssentialsLeaveType::withoutGlobalScopes()->find($meu->id)->ferias_clt, 'desmarcar grava false'); // SUPERADMIN: leitura de fixture

    $this->put("/hrm/leave-type/{$alheio->id}", ['leave_type' => 'invadido', 'ferias_clt' => '1']);
    $depois = EssentialsLeaveType::withoutGlobalScopes()->find($alheio->id); // SUPERADMIN: confere o tenant adversário
    $this->assertFalse((bool) $depois->ferias_clt, 'tipo do vizinho não muda');
    $this->assertSame($alheio->leave_type, $depois->leave_type, 'nome do tipo do vizinho não muda');
});

/** Headers do `fetch` do painel lateral (Tipos.tsx, TipoPainel): JSON, sem X-Inertia. */
function tiposHeadersPainel(): array
{
    return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
}

it('UC-TIPOS-11: criar pelo painel grava o tipo no MEU negócio, e limite vazio vira "sem limite" (nunca 0)', function () {
    $nome = 'UC11-'.uniqid();

    $this->withHeaders(tiposHeadersPainel())
        ->postJson('/hrm/leave-type', [
            'leave_type' => $nome,
            'max_leave_count' => null,
            'leave_count_interval' => 'year',
        ])
        ->assertStatus(200)
        ->assertJson(['success' => true]);

    $criado = EssentialsLeaveType::withoutGlobalScopes()->where('leave_type', $nome)->first(); // SUPERADMIN: leitura de fixture
    expect($criado)->not->toBeNull();
    expect((int) $criado->business_id)->toBe((int) $this->tenant->id);
    expect($criado->max_leave_count)->toBeNull();
    // Sem limite, intervalo não tem sentido: o servidor zera mesmo que o cliente mande.
    expect($criado->leave_count_interval)->toBeNull();

    $comLimite = 'UC11-lim-'.uniqid();
    $this->withHeaders(tiposHeadersPainel())
        ->postJson('/hrm/leave-type', [
            'leave_type' => $comLimite,
            'max_leave_count' => 30,
            'leave_count_interval' => 'year',
            'ferias_clt' => 1,
        ])
        ->assertStatus(200);

    $outro = EssentialsLeaveType::withoutGlobalScopes()->where('leave_type', $comLimite)->first(); // SUPERADMIN: leitura de fixture
    expect((int) $outro->max_leave_count)->toBe(30);
    expect($outro->leave_count_interval)->toBe('year');
    expect((bool) $outro->ferias_clt)->toBeTrue();
});

it('UC-TIPOS-12: nome vazio, limite zero e intervalo inventado são recusados com 422 por campo, sem gravar', function () {
    $antes = EssentialsLeaveType::withoutGlobalScopes()->where('business_id', $this->tenant->id)->count(); // SUPERADMIN: contagem de fixture

    $this->withHeaders(tiposHeadersPainel())
        ->postJson('/hrm/leave-type', [
            'leave_type' => '',
            'max_leave_count' => 0,
            'leave_count_interval' => 'semana',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['leave_type', 'max_leave_count', 'leave_count_interval']);

    $depois = EssentialsLeaveType::withoutGlobalScopes()->where('business_id', $this->tenant->id)->count(); // SUPERADMIN: contagem de fixture
    expect($depois)->toBe($antes);

    // Editar também valida: um nome vazio não apaga o nome do tipo existente.
    $meu = tiposCriarTipo($this->tenant->id, 'UC12-meu');
    $this->withHeaders(tiposHeadersPainel())
        ->putJson("/hrm/leave-type/{$meu->id}", ['leave_type' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['leave_type']);
    expect(EssentialsLeaveType::withoutGlobalScopes()->find($meu->id)->leave_type)->toBe($meu->leave_type); // SUPERADMIN: leitura de fixture
});

it('UC-TIPOS-13: os links antigos de criar e editar levam à lista, não a uma página quebrada', function () {
    $meu = tiposCriarTipo($this->tenant->id, 'UC13-meu');

    // Antes: `create` devolvia view('essentials::create'), que não existe (500), e `edit`
    // devolvia um fragmento de modal Blade sem layout.
    $this->get('/hrm/leave-type/create')->assertRedirect('/hrm/leave-type');
    $this->get("/hrm/leave-type/{$meu->id}/edit")->assertRedirect('/hrm/leave-type');
});
