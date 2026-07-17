---
slug: 0084-triggers-mysql-imutabilidade-mcp-audit-log
number: 84
title: "Triggers MySQL append-only em mcp_audit_log + correção audit P0.1"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: governance
quarter: 2026-Q2
tags: [governance, audit, immutability, mysql-trigger, p0]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
pii: false
review_triggers:
  - "Auditoria externa demandar retention >5 anos no audit log"
  - "Particionamento mensal de mcp_audit_log virar bottleneck"
---

# ADR 0084 — Triggers MySQL append-only em mcp_audit_log + correção audit

## Contexto

Audit cascata Constitution v1.1.0 (`memory/governance/audit-2026-05-05-v1.1.md`) marcou 2 itens como P0:

- **P0.1** — `ponto_marcacoes` sem trigger MySQL append-only (Art. 3 + Portaria 671)
- **P0.2** — `mcp_audit_log` sem trigger MySQL append-only (Art. 9)

**Verificação em prod (Hostinger):**

```sql
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION
FROM information_schema.TRIGGERS
WHERE EVENT_OBJECT_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE IN ('ponto_marcacoes', 'mcp_audit_log');
```

Resultado:
- `ponto_marcacoes` → 2 triggers (BEFORE UPDATE + BEFORE DELETE) **já existem** desde migration original `2026_04_18_000004_create_ponto_marcacoes_table.php`
- `mcp_audit_log` → 0 triggers

**Conclusão:** P0.1 era falso positivo (audit Explore agent estava equivocado). P0.2 confirmado.

## Decisão

### 1. P0.1 corrigido no audit — falso positivo documentado

Atualizado `audit-2026-05-05-v1.1.md` marcando P0.1 como ✅ JÁ COMPLIANT desde 2026-04-18. Sem ação necessária.

### 2. P0.2 resolvido com migration

Migration `Modules/Copiloto/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php` criada e aplicada em prod 2026-05-05.

**Triggers criados:**

```sql
CREATE TRIGGER trg_mcp_audit_log_no_update
BEFORE UPDATE ON mcp_audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'mcp_audit_log is append-only (Constitution v1.1.0 Article 9). UPDATE forbidden.';
END;

CREATE TRIGGER trg_mcp_audit_log_no_delete
BEFORE DELETE ON mcp_audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'mcp_audit_log is append-only (Constitution v1.1.0 Article 9). DELETE forbidden.';
END;
```

Pattern idêntico ao usado em `ponto_marcacoes` (Portaria 671/2021).

### 3. Padrão pra futuras tabelas append-only

Toda tabela declarada append-only no schema design **deve** ter triggers MySQL na mesma migration que cria a tabela. Documentado como **boa prática mandatória** — não basta comentário "// append-only" no migration.

Tabelas afetadas no oimpresso (catalogadas pra futuro audit):

- `ponto_marcacoes` — ✅ trigger desde origem
- `mcp_audit_log` — ✅ trigger 2026-05-05 (este ADR)
- `mcp_skill_versions` — ⚠️ append-only por convenção, sem trigger (avaliar Fase 5)
- `memory/governance/srs/*` — git-based, imutabilidade via ADR + pre-commit hook (Fase 3.6)
- `memory/decisions/*` — git-based, ADR template enforça supersede

## Justificativa

**Por que trigger MySQL e não app-layer apenas.** App layer pode ser bypassado por SQL direto (mysql CLI, ferramentas DB, queries crus em jobs). Trigger MySQL é a defesa de último recurso — força do banco. Pattern já usado em `ponto_marcacoes` (Portaria 671) — coerência arquitetural.

**Por que SIGNAL SQLSTATE '45000'.** SQL standard para erro genérico definido por usuário. Compatível com MySQL 5.5+ (Hostinger usa 5.7+/MariaDB) sem extensions.

**Por que IDEMPOTENTE (`DROP IF EXISTS` antes de CREATE).** Migrations devem ser re-runnables em ambientes diferentes (staging, prod, worktrees). DROP IF EXISTS evita erro em re-run.

**Reabrir esta decisão se:** auditor externo demandar retention >5 anos em mcp_audit_log (precisa particionamento mensal); ou se MySQL versão suportada mudar.

## Cascade Review (cumprindo §10.4)

**Mudança em L7 Audit (trigger mcp_audit_log) — sem cascada abaixo (L7 é nível folha).**

Cascade lateral:
- **Skills cross-cutting:** skill `multi-tenant-patterns` referencia compliance Art. 9. Sem update necessário (regra geral mantida).
- **ADRs cross-cutting:** ADR 0079 Art. 9 implementação parcial → agora 100% compliant em mcp_audit_log.

## Consequências

**Positivas:**

- Audit log forense real. Auditor externo (LGPD, fiscal) confia no log.
- Defesa em profundidade: app layer + DB trigger.
- P0.2 resolvido em <1h (cumpre ETA do audit).

**Negativas / Trade-offs:**

- Trigger MySQL adiciona ~0.1ms latency em cada INSERT em mcp_audit_log. Aceitável (audit é background).
- Disaster recovery via `DELETE` (raro mas possível em emergência) requer DBA dropar trigger temporariamente. Documentado como procedimento controlado.

**Riscos mitigados:**

- Bypass via SQL direto bloqueado.
- Compliance audit-friendly garantido.

## Implementação

✅ **FEITO nesta ADR:**

1. Migration criada e commitada (`f6b26a64`)
2. Push pra main
3. SSH Hostinger + `php artisan migrate --path=...` aplicada
4. Verificação: triggers ATIVOS em prod
5. Audit `audit-2026-05-05-v1.1.md` atualizado corrigindo P0.1

## Referências

- [Constituição v1.1.0 — Artigo 9](../governance/CONSTITUTION.md)
- [Audit cascata v1.1.0](../governance/audit-2026-05-05-v1.1.md)
- [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md)
- [ADR 0080](0080-trust-tiers-operacional-audit-findings.md)
- [Migration aplicada](../../Modules/Copiloto/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php)
- Pattern de origem: [Modules/PontoWr2/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php](../../Modules/PontoWr2/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php)
