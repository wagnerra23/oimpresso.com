<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela /hrm/holiday (Feriados do business) — `EssentialsHolidayController`.
 *
 * UC-EHOL-01 `[T0]` — a lista traz os feriados do meu business e não traz os de outro.
 * UC-EHOL-02        — o filtro De/Até é aplicado no servidor.
 * UC-EHOL-03        — o admin cria um feriado e ele é gravado no meu business.
 * UC-EHOL-04 `[T0]` — o admin não edita nem apaga feriado de outro business.
 * UC-EHOL-05        — quem não é admin vê a lista sem gestão e recebe 403 ao criar/editar/apagar.
 *
 * Os UC derivam do `Index.charter.md` (§Goals, §Backend contract, §Métricas) e do controller
 * real, nunca do `.tsx` nem do protótipo. A tela não tem US no SPEC do Essentials.
 * Trio: resources/js/Pages/Essentials/Holidays/{Index.charter.md,Index.casos.md}
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 (fictício) × 99 (adversário). NUNCA biz=4.
 * Feriados sem localidade (`location_id` NULL): valem para o negócio inteiro e passam pelo
 * filtro de `permitted_locations`, então o teste não depende de semear localidades.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('essentials_holidays')) {
        $this->markTestSkipped('Tabela essentials_holidays ausente — rode migrate Modules/Essentials.');
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }

    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role->name);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($user);
});

function eholFeriado(int $bizId, string $nome, string $inicio, string $fim): int
{
    // Insert cru: o global scope de HasBusinessScope não filtra INSERT, e o business_id
    // explícito é o que permite plantar o feriado do adversário.
    return DB::table('essentials_holidays')->insertGetId([
        'business_id' => $bizId,
        'name' => $nome,
        'start_date' => $inicio,
        'end_date' => $fim,
        'location_id' => null,
        'note' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function eholInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Resolve o `Inertia::defer` de `holidays` com os headers que o navegador manda. */
function eholPayload($test, string $query = ''): array
{
    $resposta = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => eholInertiaVersion(),
        'X-Inertia-Partial-Data' => 'holidays',
        'X-Inertia-Partial-Component' => 'Essentials/Holidays/Index',
    ])->get('/hrm/holiday'.$query);

    $resposta->assertStatus(200);
    $resposta->assertJsonPath('component', 'Essentials/Holidays/Index');

    return array_column($resposta->json('props.holidays') ?? [], 'name');
}

it('UC-EHOL-01 · a lista traz os feriados do meu business e não traz os de outro', function () {
    $tag = 'EHOL01-'.uniqid();
    eholFeriado($this->tenant->id, "$tag-meu", '2031-04-21', '2031-04-21');
    eholFeriado($this->adversario->id, "$tag-alheio", '2031-04-21', '2031-04-21');

    $nomes = eholPayload($this);

    expect($nomes)->toContain("$tag-meu");        // controle positivo
    expect($nomes)->not->toContain("$tag-alheio"); // Tier 0
});

it('UC-EHOL-02 · o filtro De/Até é aplicado no servidor', function () {
    $tag = 'EHOL02-'.uniqid();
    eholFeriado($this->tenant->id, "$tag-dentro", '2032-09-07', '2032-09-07');
    eholFeriado($this->tenant->id, "$tag-fora", '2032-11-15', '2032-11-15');

    $nomes = eholPayload($this, '?start_date=2032-09-01&end_date=2032-09-30');

    expect($nomes)->toContain("$tag-dentro");
    expect($nomes)->not->toContain("$tag-fora");
});

it('UC-EHOL-03 · o admin cria um feriado e ele é gravado no meu business', function () {
    $nome = 'EHOL03-'.uniqid();

    $this->post('/hrm/holiday', [
        'name' => $nome,
        'start_date' => '2033-12-25',
        'end_date' => '2033-12-26',
        'note' => 'Natal',
    ])->assertRedirect();

    $linha = DB::table('essentials_holidays')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->tenant->id);
    expect((string) $linha->start_date)->toStartWith('2033-12-25');
    expect((string) $linha->end_date)->toStartWith('2033-12-26');
});

it('UC-EHOL-04 · o admin não edita nem apaga feriado de outro business', function () {
    $tag = 'EHOL04-'.uniqid();
    $alheio = eholFeriado($this->adversario->id, "$tag-alheio", '2034-01-01', '2034-01-01');
    $meu = eholFeriado($this->tenant->id, "$tag-meu", '2034-01-01', '2034-01-01');

    $edicao = ['name' => "$tag-renomeado", 'start_date' => '2034-02-02', 'end_date' => '2034-02-02'];

    // Controle positivo: a mesma edição no meu feriado é gravada.
    $this->put("/hrm/holiday/{$meu}", $edicao)->assertRedirect();
    expect(DB::table('essentials_holidays')->where('id', $meu)->value('name'))->toBe("$tag-renomeado");

    $this->put("/hrm/holiday/{$alheio}", $edicao);
    $this->delete("/hrm/holiday/{$alheio}");

    $linha = DB::table('essentials_holidays')->where('id', $alheio)->first();
    expect($linha)->not->toBeNull();             // não foi apagado
    expect($linha->name)->toBe("$tag-alheio");    // não foi editado
});

it('UC-EHOL-05 · quem não é admin vê a lista sem gestão e recebe 403 ao criar, editar e apagar', function () {
    $tag = 'EHOL05-'.uniqid();
    $feriado = eholFeriado($this->tenant->id, "$tag-existente", '2035-05-01', '2035-05-01');

    $comum = $this->usuarioComPermissoes([], $this->tenant);
    session()->flush();
    $this->actingAs($comum);

    $pagina = $this->get('/hrm/holiday');
    $pagina->assertOk();
    expect($pagina->viewData('page')['props']['can_manage'])->toBeFalse();

    $dados = ['name' => "$tag-novo", 'start_date' => '2035-06-01', 'end_date' => '2035-06-01'];
    $this->post('/hrm/holiday', $dados)->assertForbidden();
    $this->put("/hrm/holiday/{$feriado}", $dados)->assertForbidden();
    $this->delete("/hrm/holiday/{$feriado}")->assertForbidden();

    expect(DB::table('essentials_holidays')->where('name', "$tag-novo")->exists())->toBeFalse();
    expect(DB::table('essentials_holidays')->where('id', $feriado)->value('name'))->toBe("$tag-existente");
});
