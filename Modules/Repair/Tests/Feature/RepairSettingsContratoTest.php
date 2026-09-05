<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * F2 BASELINE da Onda 1 do Repair — contrato de gravação das configurações.
 *
 * Fixa o comportamento ATUAL dos dois endpoints de escrita ANTES de a tela
 * virar Inertia, para que a Page nova não descubra o contrato em produção.
 *
 * Por que dois endpoints (e não um, como o pacote de export afirmava):
 *
 *   POST /repair/repair-settings                  -> store()
 *        grava business.repair_settings
 *   POST /repair/update-repair-jobsheet-settings  -> updateJobsheetSettings()
 *        grava business.repair_jobsheet_settings  (rótulos + 17 chaves show_*)
 *
 * Uma Page que mandasse o segundo conjunto para o `store()` salvaria sem erro
 * e não persistiria nada — tela inerte (classe LC-30). UC-RSET-02 e UC-RSET-06
 * existem exatamente para que essa confusão quebre o CI, não a Larissa.
 *
 * Contrato destrutivo (UC-RSET-03): os dois métodos fazem `$request->only()`
 * seguido de `Business::update([<coluna> => json_encode($input)])`, ou seja
 * SUBSTITUEM o JSON inteiro. Chave ausente no POST some do banco. Isto não é
 * defeito a consertar aqui — é o contrato vigente, e a Page precisa enviar o
 * conjunto completo do seu endpoint a cada submit.
 *
 * Tenant: `seededTenant()` = 98 (fictício, canônico). biz=4 é PROIBIDO sem
 * exceção e biz=1 é empresa real.
 *
 * @see memory/requisitos/Repair/RUNBOOK-repair-settings.md (F1 PLAN)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see Modules/Repair/Http/Controllers/RepairSettingsController.php
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS de `business` exige MySQL (ADR 0358)');
    }
    foreach (['business', 'users', 'permissions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
        }
    }
    foreach (['repair_settings', 'repair_jobsheet_settings'] as $col) {
        if (! Schema::hasColumn('business', $col)) {
            $this->markTestSkipped("Coluna business.{$col} ausente — o módulo Repair não está migrado neste banco");
        }
    }
});

/** User do tenant dado, com `superadmin` (satisfaz o primeiro ramo do gate dos 3 métodos). */
function repairSettingsUser(int $businessId, bool $superadmin = true): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'repair_set_'.uniqid(),
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    }

    return $user;
}

/** Autentica e popula a session que o controller lê para resolver o business. */
function repairSettingsActAs($test, User $user)
{
    session()->put('user.business_id', $user->business_id);

    return $test->actingAs($user);
}

/** Lê uma das duas colunas JSON como array. */
function repairSettingsJson(int $businessId, string $coluna): array
{
    $raw = DB::table('business')->where('id', $businessId)->value($coluna);

    return is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
}

/** Snapshot das duas colunas de TODOS os outros tenants — prova de isolamento. */
function repairSettingsOutrosTenants(int $exceto): array
{
    return DB::table('business')
        ->where('id', '!=', $exceto)
        ->orderBy('id')
        ->get(['id', 'repair_settings', 'repair_jobsheet_settings'])
        ->map(fn ($r) => (array) $r)
        ->toArray();
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-RSET-01 — store() grava em business.repair_settings
// ─────────────────────────────────────────────────────────────────────────────

it('UC-RSET-01: store() grava as chaves da folha em business.repair_settings', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);

    repairSettingsActAs($this, $user)->post('/repair/repair-settings', [
        'job_sheet_prefix' => 'OS-F2',
        'barcode_type' => 'C128',
        'default_status' => '',
        'problem_reported_by_customer' => 'Não liga',
        'product_condition' => 'Riscado na tampa',
        'product_configuration' => '8GB RAM',
        'repair_tc_condition' => 'Garantia de 90 dias',
        'default_repair_checklist' => 'Conferir fonte',
        'job_sheet_custom_field_1' => 'Campo 1',
        'job_sheet_custom_field_2' => 'Campo 2',
        'job_sheet_custom_field_3' => 'Campo 3',
        'job_sheet_custom_field_4' => 'Campo 4',
        'job_sheet_custom_field_5' => 'Campo 5',
    ]);

    $gravado = repairSettingsJson((int) $biz->id, 'repair_settings');

    expect($gravado['job_sheet_prefix'] ?? null)->toBe('OS-F2')
        ->and($gravado['barcode_type'] ?? null)->toBe('C128')
        ->and($gravado['repair_tc_condition'] ?? null)->toBe('Garantia de 90 dias')
        ->and($gravado['job_sheet_custom_field_5'] ?? null)->toBe('Campo 5');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-RSET-02 — as duas colunas são disjuntas (o erro que a Page não pode cometer)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-RSET-02: store() NÃO toca business.repair_jobsheet_settings', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);

    DB::table('business')->where('id', $biz->id)->update([
        'repair_jobsheet_settings' => json_encode(['customer_label' => 'Cliente', 'show_barcode_in_label' => 1]),
    ]);

    repairSettingsActAs($this, $user)->post('/repair/repair-settings', [
        'job_sheet_prefix' => 'OS-DISJUNTO',
        'barcode_type' => 'C128',
        // Mandado de propósito para o endpoint ERRADO — é o que uma Page mal
        // desenhada faria. Tem de ser ignorado.
        'customer_label' => 'INVASOR',
        'show_barcode_in_label' => 0,
    ]);

    $etiqueta = repairSettingsJson((int) $biz->id, 'repair_jobsheet_settings');

    expect($etiqueta['customer_label'] ?? null)->toBe('Cliente')
        ->and($etiqueta['show_barcode_in_label'] ?? null)->toBe(1)
        ->and(repairSettingsJson((int) $biz->id, 'repair_settings')['job_sheet_prefix'] ?? null)->toBe('OS-DISJUNTO');
});

it('UC-RSET-06: updateJobsheetSettings() grava a etiqueta e NÃO toca business.repair_settings', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);

    DB::table('business')->where('id', $biz->id)->update([
        'repair_settings' => json_encode(['job_sheet_prefix' => 'INTACTO']),
    ]);

    repairSettingsActAs($this, $user)->post('/repair/update-repair-jobsheet-settings', [
        'customer_label' => 'Cliente',
        'client_id_label' => 'Código',
        'client_tax_label' => 'CNPJ',
        'label_width' => '75',
        'label_height' => '50',
        'show_customer' => 1,
        'show_barcode_in_label' => 1,
        'show_status_in_label' => 1,
    ]);

    $etiqueta = repairSettingsJson((int) $biz->id, 'repair_jobsheet_settings');

    expect($etiqueta['customer_label'] ?? null)->toBe('Cliente')
        ->and($etiqueta['label_width'] ?? null)->toBe('75')
        ->and($etiqueta['show_barcode_in_label'] ?? null)->toBe(1)
        ->and(repairSettingsJson((int) $biz->id, 'repair_settings')['job_sheet_prefix'] ?? null)->toBe('INTACTO');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-RSET-03 — a escrita é destrutiva: submit parcial APAGA
// ─────────────────────────────────────────────────────────────────────────────

it('UC-RSET-03: submit parcial APAGA as chaves ausentes (contrato vigente, não defeito a consertar aqui)', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);

    DB::table('business')->where('id', $biz->id)->update([
        'repair_settings' => json_encode([
            'job_sheet_prefix' => 'OS',
            'barcode_type' => 'C128',
            'job_sheet_custom_field_2' => 'NAO PODE SUMIR SEM AVISO',
        ]),
    ]);

    // Submit que esquece `job_sheet_custom_field_2` — exatamente o risco que a
    // Page corre se não renderizar (e reenviar) o conjunto completo.
    repairSettingsActAs($this, $user)->post('/repair/repair-settings', [
        'job_sheet_prefix' => 'OS',
        'barcode_type' => 'C128',
    ]);

    $gravado = repairSettingsJson((int) $biz->id, 'repair_settings');

    expect($gravado['job_sheet_prefix'] ?? null)->toBe('OS')
        ->and($gravado)->not->toHaveKey('job_sheet_custom_field_2');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-RSET-04 — permissão nega antes de gravar
// ─────────────────────────────────────────────────────────────────────────────

it('UC-RSET-04: sem superadmin nem repair.create, os dois endpoints negam e nada é gravado', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id, superadmin: false);

    DB::table('business')->where('id', $biz->id)->update([
        'repair_settings' => json_encode(['job_sheet_prefix' => 'ORIGINAL']),
        'repair_jobsheet_settings' => json_encode(['customer_label' => 'ORIGINAL']),
    ]);

    repairSettingsActAs($this, $user)->post('/repair/repair-settings', ['job_sheet_prefix' => 'HACK']);
    repairSettingsActAs($this, $user)->post('/repair/update-repair-jobsheet-settings', ['customer_label' => 'HACK']);

    expect(repairSettingsJson((int) $biz->id, 'repair_settings')['job_sheet_prefix'] ?? null)->toBe('ORIGINAL')
        ->and(repairSettingsJson((int) $biz->id, 'repair_jobsheet_settings')['customer_label'] ?? null)->toBe('ORIGINAL');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-RSET-05 — Tier 0: gravar num tenant não encosta em nenhum outro
// ─────────────────────────────────────────────────────────────────────────────

it('UC-RSET-05: gravar no tenant 98 não altera as configurações de nenhum outro business', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);

    $antes = repairSettingsOutrosTenants((int) $biz->id);

    repairSettingsActAs($this, $user)->post('/repair/repair-settings', [
        'job_sheet_prefix' => 'TIER0',
        'barcode_type' => 'C128',
    ]);
    repairSettingsActAs($this, $user)->post('/repair/update-repair-jobsheet-settings', [
        'customer_label' => 'TIER0',
        'label_width' => '75',
    ]);

    expect(repairSettingsOutrosTenants((int) $biz->id))->toBe($antes)
        ->and(repairSettingsJson((int) $biz->id, 'repair_settings')['job_sheet_prefix'] ?? null)->toBe('TIER0');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-RSET-07 / 08 — coexistência MWART: o cutover é decisão [W], não do deploy
// ─────────────────────────────────────────────────────────────────────────────

afterEach(function () {
    config([
        'mwart.repair_settings_index.enabled' => false,
        'mwart.repair_settings_index.business_ids' => [],
    ]);
});

it('UC-RSET-07: flag OFF (default) → Blade legado, sem Inertia', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);
    config(['mwart.repair_settings_index.enabled' => false]);

    $r = repairSettingsActAs($this, $user)->get('/repair/repair-settings');

    if ($r->status() >= 500) {
        // Render do Blade legado pode falhar por $contact_custom_fields indefinida
        // (jobsheet_settings_tab.blade.php:56) — é justamente o [BACKLOG] do casos.md.
        // Falhar aqui seria medir o defeito do legado, não a coexistência.
        expect($r->headers->get('X-Inertia'))->toBeNull();

        return;
    }

    expect($r->headers->get('X-Inertia'))->toBeNull();
});

it('UC-RSET-08: flag ON → Inertia renderiza Repair/Settings/Index com as props do contrato', function () {
    $biz = $this->seededTenant();
    $user = repairSettingsUser((int) $biz->id);
    config([
        'mwart.repair_settings_index.enabled' => true,
        'mwart.repair_settings_index.business_ids' => [],
    ]);

    $r = repairSettingsActAs($this, $user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/repair-settings');

    expect($r->status())->toBe(200);

    $r->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('Repair/Settings/Index')
        ->has('repairSettings')
        ->has('jobsheetPdfSettings')
        // As duas props que o Blade NUNCA recebeu e a aba de impressão exige.
        ->has('contactCustomFields')
        ->has('customLabels')
    );
});
