---
doc: SCHEMA delta — Charter Governance no KB
module: KB
status: contrato (pré-implementação)
base: memory/requisitos/KB/SCHEMA-DB-V1.md
adr: 0243-charter-governance-kb
created: 2026-06-01
---

# SCHEMA delta — Charter Governance no KB

> **Princípio:** tudo **aditivo**. Zero quebra de Tier 0. `type`/`status`/`edge_type` são `VARCHAR` (validação no Model, sem `ALTER ENUM`). Cada migration: `Schema::hasColumn` guard (idempotente) + `down()` reverso. Base: [SCHEMA-DB-V1.md](SCHEMA-DB-V1.md).

## Mapa dos 10 deltas

| # | Delta | Camada | Migration? |
|---|---|---|---|
| D1 | `type=module-charter` | Model (validação) | ❌ (VARCHAR) |
| D2 | edges `governs-module`, `parent-charter` | Model + deriver | ❌ (VARCHAR) |
| D3 | `kb_comments` → `kind` + `status` (modo sugestão) | DB | ✅ ALTER aditivo |
| D4 | `kb_nodes.status` lifecycle estendido | Model (validação) | ❌ (VARCHAR) |
| D5 | permissions `kb.charter.suggest`/`approve` | Spatie | ❌ (registry) |
| D6 | "publicar = PR" | Service | ❌ |
| D7 | tela Module Charter | Inertia | ❌ |
| D8 | `kb_nodes.verify_interval` (cadência) | DB | ✅ ALTER aditivo |
| D9 | "Spectral para charters" (linter CI) | CI script | ❌ |
| D10 | RACI no frontmatter `.charter.md` | convenção git | ❌ |

---

## D3 — Migration: modo sugestão em `kb_comments`

```php
// Modules/KB/Database/Migrations/2026_06_XX_000001_add_suggestion_mode_to_kb_comments.php
public function up(): void
{
    Schema::table('kb_comments', function (Blueprint $t) {
        if (! Schema::hasColumn('kb_comments', 'kind')) {
            $t->string('kind', 20)->default('comment')->after('block_idx')
              ->comment('comment|suggestion|question|erratum');
        }
        if (! Schema::hasColumn('kb_comments', 'status')) {
            $t->string('status', 20)->default('proposed')->after('kind')
              ->comment('proposed|under_review|accepted|rejected|merged');
        }
        if (! Schema::hasColumn('kb_comments', 'resolved_by_user_id')) {
            $t->unsignedInteger('resolved_by_user_id')->nullable()->after('author_user_id');
        }
        if (! Schema::hasColumn('kb_comments', 'resolution_note')) {
            $t->string('resolution_note', 500)->nullable()
              ->comment('comentário obrigatório no approve/reject (Document360-style)');
        }
        $t->index(['business_id', 'node_id', 'status'], 'idx_kb_comments_biz_node_status');
    });
}
public function down(): void
{
    Schema::table('kb_comments', function (Blueprint $t) {
        $t->dropIndex('idx_kb_comments_biz_node_status');
        $t->dropColumn(['kind', 'status', 'resolved_by_user_id', 'resolution_note']);
    });
}
```

> Compat: linhas existentes herdam `kind='comment'`, `status='proposed'` (default) — comentário cru vira "comentário" no novo modelo, sem migração de dados.

## D8 — Migration: cadência de re-verificação

```php
// Modules/KB/Database/Migrations/2026_06_XX_000002_add_verify_interval_to_kb_nodes.php
public function up(): void
{
    Schema::table('kb_nodes', function (Blueprint $t) {
        if (! Schema::hasColumn('kb_nodes', 'verify_interval')) {
            $t->string('verify_interval', 20)->nullable()->after('last_verified_at')
              ->comment('sprint|monthly|quarterly|yearly — NULL = sem cadência');
        }
    });
    // verify_due_at é DERIVADO (last_verified_at + interval) — calculado no Model, não coluna.
}
public function down(): void
{
    Schema::table('kb_nodes', function (Blueprint $t) {
        $t->dropColumn('verify_interval');
    });
}
```

## D1 + D4 — Validação no Model (sem migration)

```php
// Modules/KB/Entities/KbNode.php — constantes + validação
public const TYPES = ['article','adr','session','charter','module-charter','runbook',
    'briefing','spec','comparativo','reference','os','customer','product','nfe','equipment','external_file'];

public const CHARTER_STATUS = ['draft','in_review','ratified','outdated','superseded','deprecated','ok','deleted'];
//   draft → in_review → ratified(=live/committed) → outdated → superseded   (Oxide RFD-style)

public function scopeCharters(Builder $q): Builder
{
    return $q->whereIn("{$this->getTable()}.type", ['charter', 'module-charter']);
}
public function isCharter(): bool { return in_array($this->type, ['charter','module-charter'], true); }

// verify_due_at derivado
public function getVerifyDueAtAttribute(): ?Carbon
{
    if (! $this->last_verified_at || ! $this->verify_interval) return null;
    return match ($this->verify_interval) {
        'sprint'    => $this->last_verified_at->copy()->addDays(14),
        'monthly'   => $this->last_verified_at->copy()->addMonth(),
        'quarterly' => $this->last_verified_at->copy()->addMonths(3),
        'yearly'    => $this->last_verified_at->copy()->addYear(),
        default     => null,
    };
}
public function isStale(): bool
{
    $due = $this->verify_due_at;
    return $due !== null && $due->isPast();
}
```

> **Invariante preservada:** `module-charter` é bridge (`is_editable=false` ⇒ `body_blocks NULL`). O `KbNodeObserver::saving()` já enforça — só estender o teste pra cobrir o novo type (R-CHTR-001).

## D2 — Edge types novos (validação + deriver)

```php
// Modules/KB/Entities/KbEdge.php
public const EDGE_TYPES = ['next-in-path','fix-of-decision','supersedes','charter-of',
    'governs-module','parent-charter','references-data','ai-related','cross-link','related-by-tag'];

// Modules/KB/Services/KbEdgeAutoDeriver.php
//   deriveGovernsModule(KbNode $moduleCharter)  → liga module-charter → (nós do módulo X)
//   deriveParentCharter(KbNode $pageCharter)    → liga page-charter de tela de X → module-charter de X
//      (deriva do módulo inferido pelo path/slug do .charter.md)
```

## D5 — Permissions (Spatie / PermissionRegistry)

```php
// Modules/KB/Resources/permissions.php — adicionar:
['key' => 'kb.charter.view',    'label' => 'KB: Ver charters governados',        'risk' => 'low',    'requires' => ['kb.view']],
['key' => 'kb.charter.suggest', 'label' => 'KB: Propor sugestão a charter',      'risk' => 'low',    'requires' => ['kb.charter.view']],
['key' => 'kb.charter.approve', 'label' => 'KB: Aprovar/ratificar charter (owner)','risk' => 'high',  'requires' => ['kb.charter.view']],
```

> `kb.charter.approve` é `risk:high` (autoriza entrada no contrato). Owner do charter (frontmatter) + Wagner. Time MCP recebe `kb.charter.suggest`.

## D6 — Service "publicar = PR" (sem schema)

```
Modules/KB/Services/CharterPublishService.php
  publishCoreChange(KbComment $suggestion):
    1. valida suggestion.status === 'accepted' && kind === 'suggestion' && node.isCharter()
    2. monta patch do .charter.md (git_path do source_doc)
    3. abre PR (gh) OU cria task MCP "aplicar sugestão #N no charter X"  ← Wagner decide o canal
    4. on-merge (webhook) → KbBridgeFromMcpJob re-sincroniza → status volta 'ratified'
  publishAttachment(KbComment $suggestion):  // anexo não-núcleo
    → grava bloco aprovado anexo ao nó (NÃO no body_blocks do bridge; em tabela/campo anexo)
```

> **Tier 0:** núcleo (`body_blocks` de bridge) **nunca** sofre UPDATE direto — só via git→bridge (R-CHTR-003).

## D9 — Linter de charter no CI (sem schema)

```
scripts/charter-lint.mjs  (espelha scripts/stylelint-baseline.mjs)
  valida cada *.charter.md:
    ✓ frontmatter: page|module, owner, status, last_validated
    ✓ Mission presente (1 seção)
    ✓ Non-Goals NÃO-vazio
    ✓ Anti-hooks declarados
    ✓ UX targets mensuráveis (regex p95/ms/cliques)
    ⚠ charter-mínimo (Gloaguen): alerta se repete check já garantido por module:grade/stylelint
  .github/workflows/charter-lint-gate.yml  → falha PR fora do schema (ratchet baseline)
```

## D10 — RACI no frontmatter (convenção git)

```yaml
# *.charter.md frontmatter — adicionar (MADR 4.0-style):
owner: wagner            # quem decide/ratifica (R)
consulted: [felipe]      # opina antes (C)
informed: [maiara]       # avisado (I)
verify_interval: monthly # cadência (D8)
```

---

## Resumo: 2 migrations, 0 quebra

- **2 migrations aditivas** (`kb_comments` +4 col / `kb_nodes` +1 col), ambas idempotentes com `down()`.
- **0 tabela nova** (anti-SRS). **0 `ALTER ENUM`** (VARCHAR + validação Model).
- Tudo cross-tenant (`business_id` já nas tabelas). Pest cross-tenant antes/depois (R-CHTR-004).
- Observers/deriver/service/linter = código, não schema.
