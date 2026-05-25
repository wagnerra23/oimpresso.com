---
slug: 0093-multi-tenant-isolation-tier-0
number: 93
title: Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-06"
decided_by: [W]
module: governance
tier: CANON
trust_level: tier-0-irrevogavel
last_reviewed: 2026-05-06
review_due: 2027-05-06
related_adrs: ["0006-multi-tenancy-logica", "0053-mcp-server-governanca-como-produto", "0057-tela-team-admin-regras-governanca-tokens-mcp", "0059-governanca-memoria-estilo-anthropic-team", "0066-format-date-shift-3h-preservado-legacy-clientes", "0070-jira-style-task-management-current-md-removed", "0072-maturacao-memoria-team-mcp-openclaw-soa-2026", "0073-team-mcp-skills-policies-entidades-governadas", "0074-temporal-validity-bi-temporal-time-travel", "0076-skills-db-primary-git-destino-drift-alert", "0078-constituicao-uma-frase-skill-unidade-evolucao", "0084-triggers-mysql-imutabilidade-mcp-audit-log", "0089-capterra-driven-module-evolution"]
parent_charter: mission.constituicao-v2  # criada em S3
supersedes: []
referenced_by: []
authors: [wagner, sonnet]
draft_origin: ROTEIRO-MESTRE.md §12 (rascunho 2026-05-06)
accepted_at: "2026-05-06"
decided_by: [W]
---

# ADR 0093 — Multi-tenant isolation by default (Tier 0, IRREVOGÁVEL)

> **Status:** ✅ ACEITO em 2026-05-06 por Wagner ("ok aprovado comece").
> Vigente. Toda nova ADR que toca dados de negócio deve referenciar esta.

---

## Contexto

UltimatePOS é multi-tenant por `business_id`. Cada negócio é um tenant isolado. **Vazar dados entre tenants é o pior bug possível do projeto** porque:

1. Viola **LGPD** (Art. 7º, Art. 46) — segurança de dados
2. Quebra contrato cliente (cliente vê dado de outro = perda de confiança permanente)
3. Bug é **silencioso** — só aparece quando cliente reporta vazamento
4. Recuperação custa caríssimo: audit forense + comunicação clientes + multas + churn

A regra "use `business_id` em todas as queries" está **disseminada** no projeto:
- CLAUDE.md §1 e §5 mencionam
- Skill `multi-tenant-patterns` (Tier B) cobre
- ~15 ADRs citam tangencialmente
- ~50% dos tests Pest validam isolation

Mas **falta uma fonte canônica única** que IA/dev consulta antes de tocar código sensível. Risco real = mesmo padrão das auto-mems privadas (ADR 0061): regra dispersa, alguém ignora, vira bug.

Esta ADR consolida o princípio em **Tier 0 IRREVOGÁVEL** — não pode ser quebrado sem nova ADR mãe que supersede esta.

## Decisão

**Multi-tenant isolation é princípio canônico Tier 0 do oimpresso.** Toda query, log, brief, audit trail, charter e decisão respeita `business_id` global scope.

Isto se desdobra em **7 garantias** (defense in depth):

### Garantia 1 — Schema obrigatório

Toda tabela de negócio (qualquer entidade que vive em escopo de empresa) tem:

```sql
$table->unsignedInteger('business_id')->index();   -- NOT NULL preferido (default)
$table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
```

Exceção: **superadmin tables** (administra a plataforma, não o tenant) podem ter `business_id NULL`. Devem documentar essa exceção em ADR específica do módulo.

### Garantia 2 — Model com global scope

Toda Eloquent Model que toca dados de negócio usa trait `HasBusinessScope`:

```php
class Repair extends Model {
  use HasBusinessScope;
}
```

`HasBusinessScope` aplica `where business_id = session('user.business_id')` automaticamente em **todas as queries**. Controllers nunca precisam filtrar manualmente — filtragem manual é **smell**, não solução.

### Garantia 3 — Job assíncrono passa `$businessId`

Jobs em fila perdem `session()`. Pattern obrigatório:

```php
class ProcessRepair implements ShouldQueue {
  public function __construct(public int $businessId, public int $repairId) {}

  public function handle(): void {
    $repair = Repair::withoutGlobalScopes()
      ->where('business_id', $this->businessId)
      ->where('id', $this->repairId)
      ->firstOrFail();
    // ...
  }
}
```

### Garantia 4 — Pest test cross-tenant obrigatório

Toda nova entidade tem teste Pest que cria 2 businesses, insere registro em A, e garante que B não vê:

```php
test('repair listagem isolada por business_id', function () {
  $bizA = Business::factory()->create();
  $bizB = Business::factory()->create();

  Repair::factory()->for($bizA)->create();

  actingAs(User::factory()->for($bizB)->create());
  expect(Repair::all())->toHaveCount(0);  // ⚠️ se quebrar, vazamento
});
```

### Garantia 5 — CI lint detecta uso suspeito

Pre-commit hook procura padrões suspeitos:

- `withoutGlobalScopes` em código novo sem comentário `// SUPERADMIN: <razão>`
- `Model::where('business_id', $x)` manual (deveria ser scope) em controller
- `DB::table('xxx')` sem cláusula business_id em tabelas de negócio

Hook bloqueia commit se padrão detectado.

### Garantia 6 — SQL audit mensal

Job mensal varre tabelas de negócio procurando linhas órfãs:

```sql
-- Linhas com business_id = NULL em tabela que não deveria
SELECT 'transactions' AS tabela, COUNT(*) FROM transactions WHERE business_id IS NULL
UNION ALL
SELECT 'repairs', COUNT(*) FROM repairs WHERE business_id IS NULL
-- ... pra cada tabela em config/multi-tenant-tables.php
```

Se qualquer tabela retornar > 0 → alerta HITL no `mcp_inbox` channel `hitl`.

### Garantia 7 — Brief diário (L7) reporta health

Daily brief inclui painel "Tenant isolation health":

```
Multi-tenant: ✅ verde
- Linhas órfãs últimos 30d: 0
- Queries sem global scope detectadas: 0
- Tests cross-tenant: 142 passing / 142 total
```

Vermelho se qualquer indicador piorar.

## Como aprovar exceção

Apenas Wagner aprova exceção. Procedimento:

1. Caso de uso concreto (com test cross-tenant SE possível)
2. PR com `// SUPERADMIN: <razão clara>` em todo lugar que pula scope
3. ADR módulo-específica documentando quando + por quê
4. Wagner aprova ADR + PR juntos
5. Lista de exceções vai pra `config/multi-tenant-exceptions.php` (auditável)

## Política de incidente

Se vazamento detectado (cliente reporta, audit retorna >0, ou test cross-tenant falha em prod):

1. **Stop the world**: pausa deploys imediatamente (CI block)
2. **Contenção**: identificar escopo (qual cliente, qual janela temporal)
3. **Comunicação**: Wagner notifica clientes afetados em <24h (LGPD obrigatório)
4. **Forense**: query SQL identifica todas as linhas órfãs + acessos
5. **Fix**: hotfix em <4h
6. **Postmortem**: ADR HISTORICAL com causa raiz + medidas preventivas
7. **DPO**: registro no LGPD se aplicável

Playbook completo: `memory/operational/playbooks/incident-multi-tenant-leak.md` (criado em S6).

## Métricas de health (mostradas no brief diário)

| Métrica | Alvo | Vermelho se |
|---|---|---|
| Linhas órfãs (`business_id IS NULL`) últimos 30d | 0 | > 0 |
| Tests cross-tenant passando | 100% | < 100% |
| Queries sem global scope detectadas | 0 | > 0 |
| Tempo desde último incidente leak | crescente | reset (incidente) |

## Consequências

### Positivas

- Single source of truth pra regra mais crítica do projeto
- Defense in depth (7 mecanismos) — falha de 1 camada não vira incidente
- Auditável (qualquer ADR nova que toca dados referencia esta)
- IA/Claude consulta antes de mexer em tabela de negócio (via skill `multi-tenant-patterns` Tier A)

### Negativas

- Custo cognitive maior pra criar nova tabela (mas template existe)
- CI mais lento (lint hook adiciona ~2s)
- Audit mensal precisa janela de manutenção curta
- Excepção pra superadmin precisa fluxo formal (atrito)

### Mitigações

- Skill `multi-tenant-patterns` promovida pra Tier A em S3 (always-on)
- Charter template (S4) tem campo obrigatório `multi_tenant_scope: required|superadmin_only|na`
- ADS firewall (S5) bloqueia mudança que toca tabela negócio sem trait

## Status do enforcement (rastreamento entre sprints)

| Garantia | Camada | Sprint | Status |
|---|---|---|---|
| #1 Schema obrigatório | (existente) | já | ✅ implementado |
| #2 Model HasBusinessScope | (existente) | já | ✅ implementado parcialmente |
| #3 Job passa businessId | (existente) | já | ✅ skill `multi-tenant-patterns` cobre |
| #4 Pest test cross-tenant | L4 Playbook | S6 | 🔴 padronizar |
| #5 CI lint hook | L4 Playbook | S6 | 🔴 criar |
| #6 SQL audit mensal | L7 Brief extension | S3+ | 🔴 criar job |
| #7 Brief reporta health | L7 Brief | S3 (extensão) | 🔴 adicionar painel |

Quando todas as 7 garantias estiverem ✅, esta ADR muda pra `status: enforced` (subset de `accepted`).

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-06 | Sonnet (rascunho) + Wagner (pedido) | Criação como rascunho. Wagner aprova dentro do S3. |
