---
paths:
  - "**/Database/Migrations/*.php"
  - "database/migrations/*.php"
---

# Rule path-scoped — Database migrations

> Carrega quando Claude lê/edita arquivo de migration. Garante idempotência + multi-tenant Tier 0 + reversibilidade.

## Idempotência obrigatória

Migrations DEVEM sobreviver a re-run sem quebrar. Pattern canônico:

```php
public function up(): void
{
    if (!Schema::hasTable('xxx')) {
        Schema::create('xxx', function (Blueprint $table) { ... });
    }
    if (!Schema::hasColumn('xxx', 'new_col')) {
        Schema::table('xxx', fn ($t) => $t->string('new_col')->nullable());
    }
}
```

Pest test obrigatório: a migration sobrevive a `php artisan migrate:fresh && php artisan migrate` (idempotência verificada via `ProcedureDriftSnapshotTest` similar).

## Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

Toda tabela de negócio nova OBRIGATORIAMENTE:

- Coluna `business_id` (unsigned bigint) NOT NULL
- Index em `business_id` (queries com global scope passam por aqui)
- Foreign key `business_id` → `business.id` ON DELETE CASCADE (ou RESTRICT se preserva-vendas)
- Nome do índice ≤64 chars (MySQL hard limit) — passar explicito se composto

**Exceção repo-wide:** se tabela é compartilhada entre business (ex: `users`, `permissions`, `media_library`), documentar no PR body — sem business_id é decisão arquitetural que vira ADR.

## Reversibilidade

Sempre implementar `down()` reversível. Pattern:

```php
public function down(): void
{
    if (Schema::hasColumn('xxx', 'new_col')) {
        Schema::table('xxx', fn ($t) => $t->dropColumn('new_col'));
    }
}
```

Exceção: migrations que destruiriam dados sensíveis em rollback — explicitar `throw new \LogicException('irreversível por design')` E adicionar ADR.

## Proibições absolutas

- ⛔ **DDL direto em prod** sem migration (`ALTER TABLE`, `CREATE/REPLACE PROCEDURE`) — check `procedure_drift` em `jana:health-check` detecta + `ProcedureDriftSnapshotTest` quebra CI (US-COPI-092, [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5)
- ⛔ **Append-only:** triggers MySQL imutabilidade `ponto_marcacoes` (Portaria 671/2021) — não remover sem ADR
- ⛔ **Identificador >64 chars** — passar nome explícito em índices compostos

## Skills relacionadas

`multi-tenant-patterns` (Tier A) · `preflight-modulo` (Tier A) · `criar-modulo` (Tier B)
