---
id: dominios-patterns-06-pest-test-multi-tenant
---

# Pattern 06 — Pest test multi-tenant Tier 0

**Status**: canônico desde 2026-05-09 (validado em [PR #353](https://github.com/wagnerra23/oimpresso.com/pull/353))
**Relacionado**: [skill `multi-tenant-patterns`](../../../.claude/skills/multi-tenant-patterns/), [feedback Tier 0](../../claude/feedback_tenancy_changes_require_pest_local.md)

## Contexto

Toda migration de Migration Factory que toca `business_id` (FK + UNIQUE composto) é **Tier 0 IRREVOGÁVEL** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Vazar dados entre tenants é o pior bug possível.

## Problema

- Análise estática diz "tá certo" mas só Pest **executando** valida isolamento real
- Wagner explicitamente reforçou (auto-mem 2026-05-09): "não autorizo mudanças baseadas em análise estática, mesmo defensivas; exige Pest verde rodado localmente"
- Pra cada bridge nova (`<core>_legacy_map`) e cada coluna `legacy_*` adicionada em módulo próprio, mesmo gate aplica

## Solução — 5 tests canônicos por entidade

Template: [`AccountsLegacyMapMultiTenantTest`](../../../Modules/Financeiro/Tests/Feature/AccountsLegacyMapMultiTenantTest.php).

### Test 1 — Isolamento BusinessScope cross-tenant

```php
public function test_business_scope_isola_<entity>_cross_tenant(): void
{
    $this->actAsAdmin();
    $primary = $this->business;
    $other = Business::where('id', '!=', $primary->id)->first();
    if (! $other) $this->markTestSkipped('Sem 2º business');

    // Cria registro no primary com bypass
    Model::query()->withoutGlobalScope(BusinessScopeImpl::class)->create([...]);

    // User normal: scope encontra
    if (! auth()->user()->can('superadmin')) {
        $this->assertEquals(1, Model::where(...)->count());
    }

    // Sessão other business: scope ATIVO
    auth()->logout();
    session(['user.business_id' => $other->id]);
    $this->assertEquals(0, Model::where(...)->count(),
        'NÃO deveria vazar cross-business');
}
```

### Test 2 — UNIQUE composto permite mesmo legacy_id em tenants diferentes

```php
public function test_unique_permite_mesmo_legacy_id_em_tenants_diferentes(): void
{
    // biz=1 e biz=2 ambos com (wr-comercial-delphi, '1') — válido
    $row1 = create(business_id=1, legacy_id='1');
    $row2 = create(business_id=2, legacy_id='1');
    $this->assertNotEquals($row1->id, $row2->id);
}
```

### Test 3 — UNIQUE bloqueia duplicidade no mesmo tenant

```php
public function test_unique_bloqueia_duplicidade_no_mesmo_tenant(): void
{
    create(business_id=1, legacy_id='1');
    $this->expectException(QueryException::class);
    create(business_id=1, legacy_id='1');  // SQLSTATE 23000
}
```

### Test 4 — Eloquent fillable/cast funciona

```php
public function test_<table>_aceita_legacy_columns(): void
{
    $row = Model::create([..., 'legacy_source' => 'X', 'legacy_id' => 'Y']);
    $this->assertEquals('X', $row->legacy_source);
    $this->assertInstanceOf(Carbon::class, $row->fresh()->legacy_imported_at);
}
```

### Test 5 — Superadmin atravessa scope

```php
public function test_superadmin_pode_ler_cross_tenant(): void
{
    create(business_id=1, ...);
    create(business_id=2, ...);

    // Bypass explícito (caller superadmin OU CLI/Job sem sessão)
    $allRows = Model::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where(...)->count();

    $this->assertEquals(2, $allRows, 'superadmin/importer atravessa tenants');
}
```

## Setup do test class

Estende [`FinanceiroTestCase`](../../../Modules/Financeiro/Tests/Feature/FinanceiroTestCase.php) (ou equivalente do módulo). NÃO usa `RefreshDatabase` (UltimatePOS tem 100+ migrations + triggers que não rodam em sqlite).

```php
protected function tearDown(): void
{
    // Cleanup ordem reversa de FKs
    cleanup_module_records();      // accounts_legacy_map etc
    cleanup_test_accounts();        // Account dedicada criada no test
    parent::tearDown();
}
```

## Pegadinhas

1. **`fin_contas_bancarias.account_id` UNIQUE 1:1** — não pode reusar Account existente que já tem ContaBancaria. Solução: helper `createTestAccount($businessId)` cria Account dedicada + cleanup tearDown.

2. **`legacy_imported_at` default null** — se test esperar valor automático, falha. Solução: setar manualmente e validar **cast Carbon**, não preenchimento automático.

3. **DB local com 1 só business** — tests cross-tenant pulam graciously com `markTestSkipped`. **Não falha** — sinaliza que cobertura plena rodaria em DB com 2+ businesses.

4. **Pest crash com `Tests\TestCase` duplicado** — quando phpunit.xml tem testsuite sobrepostos. Solução pragmática: `./vendor/bin/phpunit <path> --no-configuration` direto.

## Resultado esperado

```
PHPUnit 12.5.23 by Sebastian Bergmann and contributors.
.S..S                                                               5 / 5 (100%)
Tests: 5, Assertions: 7, Skipped: 2.
```

3 verdes + 2 skipped (esperado em DB dev) = pronto pra abrir PR.

## Quando NÃO precisa todos os 5

- **Tabela cross-tenant intencional** (ex: tabelas de catálogo FEBRABAN compartilhadas) — sem `business_id`, scope não aplica. Skip tests 1, 2, 3, 5; mantém 4.
- **Adição de coluna** em tabela já testada — pode focar no test 4.
- **Coluna read-only por importer** (sem Eloquent fillable) — pode skip test 4.

Mas **tests 1 e 5 são mandatórios** pra qualquer Tier 0.
