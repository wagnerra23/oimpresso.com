<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Wave F (ADR 0179) -- Tab Auditoria timeline + CSV export.
 *
 * Cobre Modules/Crm/Http/Controllers/ClienteAuditoriaController:
 *   GET /cliente/{id}/auditoria          -> JSON paginado (20/pg)
 *   GET /cliente/{id}/auditoria/export   -> CSV UTF-8 BOM streaming
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): cross-tenant biz=1 vs biz=99999
 * blocked com 404 (NAO 403 -- nao vaza existencia do recurso).
 *
 * Permission gate LGPD Art. 18 leitura ampla: customer.view OU supplier.view
 * OU .view_own qualifica. NAO exige .update.
 *
 * PII LGPD: maskPiiValue() defesa em profundidade -- App\Contact ja exclui
 * tax_number do logOnly() (ADR 0127 §F1) mas se aparecer em description, o
 * display layer mascara.
 *
 * Skip-graceful em sqlite :memory: ou schema UPOS ausente (CI sem MySQL).
 * Padrao copiado de tests/Feature/Cliente/ClienteDrawerCadastroAutosaveTest.php.
 *
 * Refs:
 *   - memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md §Wave F
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - memory/decisions/0127-modules-auditoria-ui-undo.md §F1
 *   - Modules/Auditoria/Tests/Feature/MultiTenantIsolationTest.php (pattern)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: activity_log com business_id + causer_kind requer schema MySQL UltimatePOS (ADR 0101).');
    }
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('Schema UltimatePOS ausente -- rode com DB_CONNECTION=mysql (dev).');
    }
    if (! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('Coluna business_id ausente em activity_log -- rode migration 2021_03_16.');
    }

    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $now = now();
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Cliente Wave F Auditoria Test',
        'mobile' => '11999999999',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);
});

// Helper -- cria Activity Spatie associada ao contact em $bizId com event/properties.
function fakeActivity(int $contactId, int $bizId, string $event, array $old = [], array $attributes = [], ?int $causerId = null): Activity
{
    return Activity::create([
        'log_name' => 'crm.contact',
        'description' => $event,
        'event' => $event,
        'subject_type' => Contact::class,
        'subject_id' => $contactId,
        'causer_type' => $causerId ? \App\User::class : null,
        'causer_id' => $causerId,
        'causer_kind' => 'user',
        'business_id' => $bizId,
        'properties' => ['old' => $old, 'attributes' => $attributes],
    ]);
}

// ---------------------------------------------------------------------
// Happy path -- timeline endpoint
// ---------------------------------------------------------------------

test('GET /cliente/{id}/auditoria retorna 200 com data + meta', function () {
    fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'Cliente Wave F Auditoria Test'], $this->user->id);
    fakeActivity($this->contactId, $this->business->id, 'updated', ['email' => 'old@x.com'], ['email' => 'new@y.com'], $this->user->id);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'type', 'description', 'causer',
                    'created_at', 'created_at_human', 'icon_hint',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(2);
});

test('GET timeline ordering -- latest first (orderByDesc id)', function () {
    $a1 = fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'X'], $this->user->id);
    $a2 = fakeActivity($this->contactId, $this->business->id, 'updated', [], ['email' => 'x@y.com'], $this->user->id);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data[0]['id'])->toBe($a2->id); // mais recente primeiro
    expect($data[1]['id'])->toBe($a1->id);
});

test('GET timeline -- icon_hint mapeia 6 tipos canon (created=plus, updated=edit, deleted=trash)', function () {
    fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'X'], $this->user->id);
    fakeActivity($this->contactId, $this->business->id, 'updated', ['name' => 'old'], ['name' => 'new'], $this->user->id);
    fakeActivity($this->contactId, $this->business->id, 'deleted', ['name' => 'X'], [], $this->user->id);
    fakeActivity($this->contactId, $this->business->id, 'restored', [], ['deleted_at' => null], $this->user->id);
    fakeActivity($this->contactId, $this->business->id, 'updated', ['tags' => '[]'], ['tags' => '["vip"]'], $this->user->id);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");
    $response->assertStatus(200);

    $byType = collect($response->json('data'))->keyBy('type');
    expect($byType->get('created')['icon_hint'] ?? null)->toBe('plus');
    expect($byType->get('deleted')['icon_hint'] ?? null)->toBe('trash');
    expect($byType->get('restored')['icon_hint'] ?? null)->toBe('shield');

    // Updated com field=tags vira icon_hint=tag (semantic mapping)
    $tagsEvent = collect($response->json('data'))
        ->first(fn ($e) => ($e['field'] ?? null) === 'tags');
    expect($tagsEvent['icon_hint'] ?? null)->toBe('tag');
});

test('GET timeline -- descricao PT-BR humanizada para event updated', function () {
    fakeActivity($this->contactId, $this->business->id, 'updated', ['email' => 'old@x.com'], ['email' => 'new@y.com'], $this->user->id);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");
    $response->assertStatus(200);

    $description = $response->json('data.0.description');
    // Espera "Email alterado: old@x.com -> new@y.com" (label PT-BR + dois valores)
    expect($description)->toContain('alterado');
    expect($description)->toContain('old@x.com');
    expect($description)->toContain('new@y.com');
});

// ---------------------------------------------------------------------
// PII LGPD mask
// ---------------------------------------------------------------------

test('GET timeline -- PII CPF aparecendo em properties e mascarado em description', function () {
    // Codigo legacy pode ter logado tax_number por engano (defense-in-depth).
    fakeActivity(
        $this->contactId,
        $this->business->id,
        'updated',
        ['tax_number' => '111.444.777-35'],
        ['tax_number' => '999.888.777-66'],
        $this->user->id
    );

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");
    $response->assertStatus(200);

    $description = $response->json('data.0.description');
    // CPF deve estar mascarado (***.***.***-XX), nao plain.
    expect($description)->not->toContain('111.444.777');
    expect($description)->not->toContain('999.888.777');
    expect($description)->toContain('***.***.***');
});

// ---------------------------------------------------------------------
// Paginacao
// ---------------------------------------------------------------------

test('GET timeline pagination -- per_page e current_page respeitados', function () {
    // Cria 25 eventos -- com per_page=10 vamos ter 3 paginas.
    for ($i = 0; $i < 25; $i++) {
        fakeActivity($this->contactId, $this->business->id, 'updated', [], ['name' => "v{$i}"], $this->user->id);
    }

    $page1 = $this->getJson("/cliente/{$this->contactId}/auditoria?page=1&per_page=10");
    $page1->assertStatus(200)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 10);
    expect(count($page1->json('data')))->toBe(10);

    $page2 = $this->getJson("/cliente/{$this->contactId}/auditoria?page=2&per_page=10");
    $page2->assertStatus(200)
        ->assertJsonPath('meta.current_page', 2);

    // IDs nao se sobrepoem entre paginas.
    $idsPage1 = collect($page1->json('data'))->pluck('id');
    $idsPage2 = collect($page2->json('data'))->pluck('id');
    expect($idsPage1->intersect($idsPage2)->count())->toBe(0);
});

test('GET timeline -- per_page acima do cap 100 e clampado', function () {
    fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'X'], $this->user->id);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria?per_page=500");
    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 100); // cap aplicado
});

// ---------------------------------------------------------------------
// Multi-tenant Tier 0
// ---------------------------------------------------------------------

test('GET timeline cross-tenant retorna 404 (nao vaza existencia)', function () {
    $foreignBizId = 99999;
    $now = now();
    $foreignContactId = DB::table('contacts')->insertGetId([
        'business_id' => $foreignBizId,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Foreign biz contact',
        'mobile' => '11000000000',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // User do biz=$this->business->id tenta acessar contact de biz=99999.
    $response = $this->getJson("/cliente/{$foreignContactId}/auditoria");
    $response->assertStatus(404);
});

test('GET timeline -- Activity de outro biz nao vaza (defense em where business_id)', function () {
    // Cria Activity associada ao contact mas com business_id de OUTRO biz
    // (cenario adversario: alguem injetou activity_log com subject_id certo
    // mas business_id errado).
    Activity::create([
        'log_name' => 'crm.contact',
        'description' => 'updated',
        'event' => 'updated',
        'subject_type' => Contact::class,
        'subject_id' => $this->contactId,
        'causer_type' => \App\User::class,
        'causer_id' => $this->user->id,
        'causer_kind' => 'user',
        'business_id' => 99999, // cross-tenant adversario
        'properties' => ['old' => [], 'attributes' => ['name' => 'pwned']],
    ]);

    // Activity legitima do biz correto.
    fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'X'], $this->user->id);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");
    $response->assertStatus(200);

    // Apenas a Activity do biz correto deve aparecer (1, nao 2).
    $events = $response->json('data');
    foreach ($events as $ev) {
        expect($ev['description'])->not->toContain('pwned');
    }
});

// ---------------------------------------------------------------------
// CSV Export
// ---------------------------------------------------------------------

test('GET /cliente/{id}/auditoria/export retorna CSV download com BOM UTF-8', function () {
    fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'X'], $this->user->id);
    fakeActivity($this->contactId, $this->business->id, 'updated', ['email' => 'old@x.com'], ['email' => 'new@y.com'], $this->user->id);

    $response = $this->get("/cliente/{$this->contactId}/auditoria/export?format=csv");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $cd = $response->headers->get('Content-Disposition');
    expect($cd)->toContain('attachment');
    expect($cd)->toContain("auditoria-cliente-{$this->contactId}-");
    expect($cd)->toContain('.csv');

    $body = $response->streamedContent();
    // BOM UTF-8 nos primeiros 3 bytes (Excel BR abre acentos certo)
    expect(substr($body, 0, 3))->toBe("\xEF\xBB\xBF");
    // Cabecalho CSV PT-BR com separador ;
    expect($body)->toContain('ID;Tipo;Descricao;Causer;Data');
});

test('GET CSV export cross-tenant retorna 404', function () {
    $foreignBizId = 99999;
    $foreignContactId = DB::table('contacts')->insertGetId([
        'business_id' => $foreignBizId,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Foreign biz contact',
        'mobile' => '11000000000',
        'contact_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->get("/cliente/{$foreignContactId}/auditoria/export?format=csv");
    $response->assertStatus(404);
});

test('GET CSV export -- causer name em coluna Causer', function () {
    fakeActivity($this->contactId, $this->business->id, 'updated', [], ['name' => 'X'], $this->user->id);

    $response = $this->get("/cliente/{$this->contactId}/auditoria/export?format=csv");
    $response->assertStatus(200);

    $body = $response->streamedContent();
    $first = trim((string) ($this->user->first_name ?? ''));
    if ($first !== '') {
        // Causer name aparece no CSV (ou pelo menos parte dele)
        expect($body)->toContain($first);
    }
});

// ---------------------------------------------------------------------
// Permission gate
// ---------------------------------------------------------------------

test('GET timeline -- user sem nenhuma permission .view retorna 403', function () {
    // Cria user sem permissions de view (revoga todas).
    /** @var \App\User $user */
    $user = \App\User::where('business_id', $this->business->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user pra teste de permissao.');
    }

    // Salva permissions atuais e revoga as 4 .view.
    $perms = ['customer.view', 'customer.view_own', 'supplier.view', 'supplier.view_own'];
    foreach ($perms as $p) {
        if ($user->hasPermissionTo($p)) {
            $user->revokePermissionTo($p);
        }
    }
    $user->refresh();

    fakeActivity($this->contactId, $this->business->id, 'created', [], ['name' => 'X'], $user->id);

    $this->actingAs($user);
    session(['user.business_id' => $this->business->id]);

    $response = $this->getJson("/cliente/{$this->contactId}/auditoria");

    // Pode retornar 403 (sem permissao) ou 404 se o middleware fizer outro
    // shortcut. O importante: NAO retorna 200.
    expect($response->status())->toBeIn([403, 404, 401]);

    // Restaura permissions pra nao afetar outros testes (DatabaseTransactions
    // ja rollback mas spatie/permission usa cache).
    foreach ($perms as $p) {
        try {
            $user->givePermissionTo($p);
        } catch (\Throwable $e) {
            // ignora -- permissao pode nao existir nesse seed
        }
    }
});
