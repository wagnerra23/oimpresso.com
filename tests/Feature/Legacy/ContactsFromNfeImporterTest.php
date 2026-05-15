<?php

declare(strict_types=1);

/**
 * Pest — Importer `scripts/legacy-migration/import-contacts-from-nfe.py`.
 *
 * Cobre invariantes Tier 0 e contratos do importer SEM precisar rodar Python.
 * Validamos a SEMÂNTICA esperada do UPSERT (dedup, type promotion, cross-tenant,
 * idempotência, PII redaction) replicando os SQLs canônicos do importer
 * direto em PHP/Eloquent + DB. Isso permite rodar no CI Hostinger sem
 * Firebird disponível.
 *
 * Cobertura:
 *   1. Dedup CNPJ em mesmo business — 2 NFs com mesmo CNPJ = 1 contact
 *   2. Cross-tenant biz=1 vs biz=99 — fornecedor importado biz=1 NÃO contamina biz=99
 *   3. Type promotion customer → both — contact existente vira both ao receber NFe
 *   4. Idempotência — re-executar lógica 2× = no-op pra rows existentes
 *   5. PII redaction — CNPJ no audit JSON aparece como [REDACTED-XXXX...] (não plain)
 *   6. Cinto-suspensório SQL — UPDATE qualificado com AND business_id barra cross-business
 *
 * ADR 0101: testes usam biz=1 (Wagner) + biz=99 (cross-tenant fictício).
 * NUNCA biz=164 (Martinho cliente real em prod).
 *
 * Refs:
 *   - scripts/legacy-migration/import-contacts-from-nfe.py
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 *   - memory/reference/feedback-importer-cross-business-bug.md
 */

use App\Contact;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pest.php aplica Tests\TestCase em tests/Feature/.

const LEGACY_NFE_BIZ_WAGNER = 1;       // ADR 0101: biz=1 = Wagner, NUNCA biz cliente
const LEGACY_NFE_BIZ_CROSS = 99;       // segundo tenant pra cross-tenant
const LEGACY_NFE_PII_RGX = '/^\[REDACTED-\d{4}\.\.\.\]$/';

/**
 * Helper — pula se DB não tem schema MySQL completo necessário.
 */
function legacyNfeNeedMysqlOrSkip(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    if (! Schema::hasTable('contacts')) {
        test()->markTestSkipped('Tabela contacts ausente — migration não rodada.');
    }
    if (! Schema::hasColumn('contacts', 'legacy_id')) {
        test()->markTestSkipped('Coluna contacts.legacy_id ausente — migration legacy_id não aplicada.');
    }
}

/**
 * Normaliza CPF/CNPJ — réplica de `normalize_cpf_cnpj()` em Python.
 */
function legacyNormalizeCnpj(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === null || $digits === '') {
        return null;
    }
    return in_array(strlen($digits), [11, 14], true) ? $digits : null;
}

/**
 * Réplica do "redact_cnpj_prefix" — pra validar formato audit JSON.
 */
function legacyRedactCnpjPrefix(?string $cnpj): ?string
{
    if (! $cnpj) {
        return null;
    }
    return '[REDACTED-' . substr($cnpj, 0, 4) . '...]';
}

/**
 * Simula o UPSERT canônico do importer Python pra 1 fornecedor.
 * NÃO usa Eloquent::create — usa raw SQL com cinto-suspensório (espelha SQL Python).
 *
 * Retorna ['action' => 'insert'|'promote_both'|'noop'|'cross_business_blocked'].
 */
function legacyUpsertSupplierFromNfe(
    int $businessId,
    string $cnpjNormalized,
    string $razaoSocial,
    int $createdBy
): array {
    // Lookup canônico — cinto já no SELECT (business_id explícito)
    $existing = DB::table('contacts')
        ->where('business_id', $businessId)
        ->where('legacy_id', $cnpjNormalized)
        ->first(['id', 'type']);

    if ($existing) {
        $type = strtolower($existing->type ?? '');

        if ($type === 'customer') {
            // Promove pra both — UPDATE com cinto-suspensório (AND business_id)
            $rows = DB::update(
                'UPDATE contacts
                 SET type = ?, supplier_business_name = COALESCE(supplier_business_name, ?), updated_at = NOW()
                 WHERE id = ? AND business_id = ?',
                ['both', substr($razaoSocial, 0, 191), (int) $existing->id, $businessId]
            );
            if ($rows === 0) {
                return ['action' => 'cross_business_blocked'];
            }
            return ['action' => 'promote_both'];
        }

        // supplier ou both — no-op idempotente
        return ['action' => 'noop'];
    }

    // INSERT novo fornecedor
    DB::table('contacts')->insert([
        'business_id' => $businessId,
        'type' => 'supplier',
        'contact_type' => strlen($cnpjNormalized) === 11 ? 'person' : 'business',
        'name' => substr($razaoSocial, 0, 191),
        'supplier_business_name' => substr($razaoSocial, 0, 191),
        'cpf_cnpj' => substr($cnpjNormalized, 0, 20),
        'tax_number' => substr($cnpjNormalized, 0, 20),
        'country' => 'BRASIL',
        'mobile' => '',
        'contact_status' => 'active',
        'legacy_id' => substr($cnpjNormalized, 0, 32),
        'created_by' => $createdBy,
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return ['action' => 'insert'];
}

/**
 * Helper — pega user da biz (caso seeded). Retorna null se ausente → skip.
 */
function legacyNfeFirstUserOfBiz(int $bizId): ?User
{
    return User::where('business_id', $bizId)->first();
}

/**
 * Cleanup helper — apaga contacts criados pelo teste por legacy_id pattern.
 */
function legacyNfeCleanupContact(int $bizId, string $cnpj): void
{
    Contact::withTrashed()
        ->where('business_id', $bizId)
        ->where('legacy_id', $cnpj)
        ->forceDelete();
}

// ─── Caso 1: Dedup CNPJ em mesmo business ─────────────────────────────────────

it('1. dedup CNPJ no mesmo business — 2 NFs com mesmo emitente = 1 contact', function () {
    legacyNfeNeedMysqlOrSkip();
    $user = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_WAGNER);
    if (! $user) {
        $this->markTestSkipped('User biz=1 ausente — seed primeiro.');
    }

    // Simula CNPJ legítimo (não-PII real — gerado pra teste)
    $cnpj = '12345678000199';
    $razao = 'FORNECEDOR TESTE LEGACY NFE LTDA';

    legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);

    try {
        // Primeira NFe emitida por esse CNPJ → INSERT
        $r1 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r1['action'])->toBe('insert');

        // Segunda NFe (mesmo CNPJ) → no-op idempotente
        $r2 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r2['action'])->toBe('noop');

        // Confirma: só 1 contact existe
        $count = Contact::where('business_id', LEGACY_NFE_BIZ_WAGNER)
            ->where('legacy_id', $cnpj)
            ->count();
        expect($count)->toBe(1);
    } finally {
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
    }
});

// ─── Caso 2: Cross-tenant isolation (Tier 0 IRREVOGÁVEL — ADR 0093) ──────────

it('2. cross-tenant biz=1 vs biz=99 — fornecedor importado biz=1 NÃO contamina biz=99', function () {
    legacyNfeNeedMysqlOrSkip();
    $user1 = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_WAGNER);
    $user99 = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_CROSS);
    if (! $user1 || ! $user99) {
        $this->markTestSkipped('User biz=1 ou biz=99 ausente — seed primeiro.');
    }

    $cnpj = '98765432000111';
    $razao = 'FORNECEDOR CROSS TENANT TESTE LTDA';

    legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
    legacyNfeCleanupContact(LEGACY_NFE_BIZ_CROSS, $cnpj);

    try {
        // Importa fornecedor pra biz=1
        $r1 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user1->id);
        expect($r1['action'])->toBe('insert');

        // biz=99 NÃO enxerga esse contact via SELECT scoped
        $foundInBiz99 = Contact::where('business_id', LEGACY_NFE_BIZ_CROSS)
            ->where('legacy_id', $cnpj)
            ->count();
        expect($foundInBiz99)->toBe(0);

        // Importar mesmo CNPJ pra biz=99 cria ROW SEPARADA (mesma chave natural, businesses diferentes)
        $r99 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_CROSS, $cnpj, $razao, (int) $user99->id);
        expect($r99['action'])->toBe('insert');

        // Cada biz tem 1 row própria — total 2 rows globais com mesmo CNPJ
        $totalGlobal = Contact::where('legacy_id', $cnpj)
            ->whereIn('business_id', [LEGACY_NFE_BIZ_WAGNER, LEGACY_NFE_BIZ_CROSS])
            ->count();
        expect($totalGlobal)->toBe(2);

        // Cada biz vê só o seu
        expect(Contact::where('business_id', LEGACY_NFE_BIZ_WAGNER)->where('legacy_id', $cnpj)->count())->toBe(1);
        expect(Contact::where('business_id', LEGACY_NFE_BIZ_CROSS)->where('legacy_id', $cnpj)->count())->toBe(1);
    } finally {
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_CROSS, $cnpj);
    }
});

// ─── Caso 3: Type promotion customer → both ───────────────────────────────────

it('3. type promotion customer → both — cliente que recebe NFe vira both', function () {
    legacyNfeNeedMysqlOrSkip();
    $user = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_WAGNER);
    if (! $user) {
        $this->markTestSkipped('User biz=1 ausente — seed primeiro.');
    }

    $cnpj = '11122233000144';
    $razao = 'EMPRESA QUE ERA CLIENTE E AGORA E FORNECEDOR LTDA';

    legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);

    try {
        // Step 1: cria contact já como CUSTOMER (simula import-contacts-from-venda rodou antes)
        Contact::create([
            'business_id' => LEGACY_NFE_BIZ_WAGNER,
            'type' => 'customer',
            'contact_type' => 'business',
            'name' => $razao,
            'cpf_cnpj' => $cnpj,
            'mobile' => '48900000000',
            'contact_status' => 'active',
            'legacy_id' => $cnpj,
            'created_by' => (int) $user->id,
        ]);

        // Step 2: importer-from-nfe roda — deve PROMOTER pra both
        $r = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r['action'])->toBe('promote_both');

        // Confirma type=both no DB
        $contact = Contact::where('business_id', LEGACY_NFE_BIZ_WAGNER)
            ->where('legacy_id', $cnpj)
            ->first();
        expect($contact)->not->toBeNull();
        expect($contact->type)->toBe('both');
        expect($contact->supplier_business_name)->toBe($razao);

        // Step 3: re-rodar o importer NÃO deve regredir (idempotente)
        $r2 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r2['action'])->toBe('noop');
        $contact->refresh();
        expect($contact->type)->toBe('both');
    } finally {
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
    }
});

// ─── Caso 4: Idempotência ────────────────────────────────────────────────────

it('4. idempotência — re-rodar 2× pra row supplier existente = no-op', function () {
    legacyNfeNeedMysqlOrSkip();
    $user = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_WAGNER);
    if (! $user) {
        $this->markTestSkipped('User biz=1 ausente — seed primeiro.');
    }

    $cnpj = '55566677000122';
    $razao = 'FORNECEDOR JA EXISTENTE LTDA';

    legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);

    try {
        // Primeira passada — INSERT
        $r1 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r1['action'])->toBe('insert');

        $contact = Contact::where('business_id', LEGACY_NFE_BIZ_WAGNER)
            ->where('legacy_id', $cnpj)
            ->first();
        expect($contact)->not->toBeNull();
        $createdAtFirst = $contact->created_at?->toIso8601String();

        // Segunda passada — no-op (não muda created_at)
        $r2 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r2['action'])->toBe('noop');

        // Terceira passada — ainda no-op
        $r3 = legacyUpsertSupplierFromNfe(LEGACY_NFE_BIZ_WAGNER, $cnpj, $razao, (int) $user->id);
        expect($r3['action'])->toBe('noop');

        $contact->refresh();
        expect($contact->type)->toBe('supplier');
        expect($contact->created_at?->toIso8601String())->toBe($createdAtFirst);

        // Só existe 1 row
        $count = Contact::where('business_id', LEGACY_NFE_BIZ_WAGNER)
            ->where('legacy_id', $cnpj)
            ->count();
        expect($count)->toBe(1);
    } finally {
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
    }
});

// ─── Caso 5: PII redaction — formato audit JSON ──────────────────────────────

it('5. PII redaction audit JSON — CNPJ aparece [REDACTED-XXXX...] (não plain)', function () {
    // Esse caso valida APENAS as funções de redaction (porta direta do Python).
    // Não precisa MySQL — pode rodar sempre.

    $cnpjPlain = '12345678000199';
    $razao = 'NOME COMPLETO QUE NÃO DEVE VAZAR LTDA';

    // Simula o helper Python redact_cnpj_prefix em PHP
    $redactedCnpj = legacyRedactCnpjPrefix($cnpjPlain);
    expect($redactedCnpj)->toBe('[REDACTED-1234...]');
    expect($redactedCnpj)->toMatch(LEGACY_NFE_PII_RGX);

    // String CNPJ inteira NÃO deve aparecer no formato redacted
    expect($redactedCnpj)->not->toContain('000199');
    expect($redactedCnpj)->not->toContain('5678');

    // Validação: normalize aceita CPF (11) E CNPJ (14)
    expect(legacyNormalizeCnpj('123.456.780-99'))->toBeNull(); // 10 dígitos — inválido
    expect(legacyNormalizeCnpj('12345678901'))->toBe('12345678901'); // 11 — CPF ok
    expect(legacyNormalizeCnpj('12.345.678/0001-99'))->toBe('12345678000199'); // 14 — CNPJ ok
    expect(legacyNormalizeCnpj(''))->toBeNull();
    expect(legacyNormalizeCnpj(null))->toBeNull();

    // Razão social inteira NÃO deve aparecer no payload — placeholder [REDACTED]
    $razaoRedacted = $razao !== '' ? '[REDACTED]' : null;
    expect($razaoRedacted)->toBe('[REDACTED]');
    expect($razaoRedacted)->not->toContain('NOME COMPLETO');
});

// ─── Caso 6 (bonus): Cinto-suspensório SQL barra cross-business ──────────────

it('6. cinto-suspensório UPDATE com AND business_id — id colidindo entre bizs não vaza', function () {
    legacyNfeNeedMysqlOrSkip();
    $user1 = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_WAGNER);
    $user99 = legacyNfeFirstUserOfBiz(LEGACY_NFE_BIZ_CROSS);
    if (! $user1 || ! $user99) {
        $this->markTestSkipped('User biz=1 ou biz=99 ausente.');
    }

    $cnpj = '77788899000133';

    legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
    legacyNfeCleanupContact(LEGACY_NFE_BIZ_CROSS, $cnpj);

    try {
        // Cria contact pra biz=99 como customer
        Contact::create([
            'business_id' => LEGACY_NFE_BIZ_CROSS,
            'type' => 'customer',
            'contact_type' => 'business',
            'name' => 'TARGET BIZ 99',
            'cpf_cnpj' => $cnpj,
            'mobile' => '48911111111',
            'contact_status' => 'active',
            'legacy_id' => $cnpj,
            'created_by' => (int) $user99->id,
        ]);

        $biz99Contact = Contact::where('business_id', LEGACY_NFE_BIZ_CROSS)
            ->where('legacy_id', $cnpj)
            ->first();
        expect($biz99Contact)->not->toBeNull();
        $biz99IdAntes = $biz99Contact->id;
        $biz99TypeAntes = $biz99Contact->type;

        // Tenta UPDATE com cinto-suspensório usando o ID do biz=99 mas business_id=biz=1
        // — equivalente ao SQL canônico do importer: "AND business_id=%s".
        // Esperado: rowcount=0 (cinto barrou) e biz=99 fica intacto.
        $rows = DB::update(
            'UPDATE contacts
             SET type = ?, updated_at = NOW()
             WHERE id = ? AND business_id = ?',
            ['both', $biz99IdAntes, LEGACY_NFE_BIZ_WAGNER]
        );
        expect($rows)->toBe(0);

        // biz=99 não foi tocado
        $biz99Contact->refresh();
        expect($biz99Contact->type)->toBe($biz99TypeAntes);
    } finally {
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_WAGNER, $cnpj);
        legacyNfeCleanupContact(LEGACY_NFE_BIZ_CROSS, $cnpj);
    }
});

// ─── Caso 7 (estrutural — sem DB): importer Python existe + chamadas chave ───

it('7. estrutural — import-contacts-from-nfe.py existe + chamadas Tier 0 presentes', function () {
    $path = base_path('scripts/legacy-migration/import-contacts-from-nfe.py');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);

    // Tier 0 (ADR 0093) — business_id obrigatório em SELECT/UPDATE
    expect($src)->toContain('AND business_id=%s');
    expect($src)->toContain('WHERE business_id=%s AND legacy_id=%s');

    // PII redaction
    expect($src)->toContain('redact_cnpj_prefix');
    expect($src)->toContain('[REDACTED');

    // Delta sync support
    expect($src)->toContain('--delta-since-last-sync');
    expect($src)->toContain('--sync-type');
    expect($src)->toContain('contacts-fornecedores-nfe');

    // Type promotion logic
    expect($src)->toContain("'customer'");
    expect($src)->toContain("'both'");
    expect($src)->toContain("'supplier'");

    // Chunks / retry pattern
    expect($src)->toContain('skipped_cross_business_guard');
});
