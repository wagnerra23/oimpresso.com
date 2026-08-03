---
module: Auditoria
purpose: "Quem mudou este registro, o quê e quando? — lê e investiga a trilha por-registro (activity_log) com isolamento multi-tenant e guarda o registry do que a lei não deixa desfazer. A reversão está codificada (RevertService) porém desligada do caminho HTTP: POST /{id}/revert responde 501."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "AuditoriaController (index · show · revert)"
  - "AuditEntryService — leitura paginada e detalhe de activity_log"
  - "RevertService — undo restaurando properties.old + registro do próprio undo"
  - "RevertCheck — veredito allow/deny com OTel span"
  - "unrevertibleRegistry() — whitelist do que NUNCA reverte (append-only legal/fiscal)"
  - "Telas Auditoria/Index + Auditoria/Detail"
  - "DataController"
  - "InstallController"
not_contains:
  - "EMITIR o log (trait LogsActivity nos Models de cada módulo) → o módulo dono do Model emite; Auditoria LÊ e REVERTE"
  - "Trilha de chamada de tool MCP (mcp_audit_log) → Modules/Jana"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: auditoria.*
charter_adr: 0094
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
url_prefixes:
  - /auditoria/*
drift_alerts: []
---

# Modules/Auditoria

## Missão

**Interface humana e guarda da trilha por-registro do ERP.** O `activity_log` (Spatie) grava *o que mudou em cada registro*; este módulo é quem **lê, investiga e reverte** — e quem **impede** a reversão do que a lei ou o fisco não deixam desfazer.

[W], 2026-07-30: *"ele registra as alterações em cada registro é super importante"* · *"não pode apagar"*.

## Fronteira — quem EMITE × quem LÊ

Esta é a distinção que a v1 errava, e o erro custou uma tentativa de deleção ([lápide §5](../../memory/proibicoes.md)):

| Papel | Onde vive | Exemplo |
|---|---|---|
| **EMITIR** o registro | trait `LogsActivity` no Model de **cada módulo** | `App\Transaction`, `App\Contact`, `App\Product` |
| **LER · INVESTIGAR · REVERTER** | **aqui** | `/auditoria` · `/auditoria/{activityId}` · `POST /{activityId}/revert` |

O módulo **não tem tabela própria com dado** — `auditoria_audit_notes` nunca chegou a produção e **não é o valor dele**. Ele vive do **`activity_log`**: em 2026-07-30, **117.510 linhas** com escrita no mesmo dia. Quem for medir a relevância deste módulo: **meça `activity_log`, não a tabela do módulo.**

## O que ele protege (por isso é Tier 0-adjacente)

`RevertService::canRevert()` nega em três eixos, nesta ordem:

1. **Multi-tenant Tier 0** — `activity.business_id ≠ user.business_id` → nega ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). Sem isso o undo vira vazamento cross-tenant com cara de feature.
2. **`unrevertibleRegistry()`** — whitelist do que **nunca** reverte: `Marcacao` (append-only por Portaria MTP 671/2021), `NfeTransaction` (nota autorizada na SEFAZ), `TituloBaixa` (baixa financeira), `OS` do Repair, e `Transaction` sob condição.
3. **Snapshot ausente** — Activity sem `properties.old` não tem o que restaurar.

O undo **também é auditado**: `revert()` cria uma nova `Activity` registrando quem reverteu e por quê.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Regra curta: **se o assunto é gravar o log, é do módulo dono do Model; se é ler, investigar ou desfazer, é aqui.**

---

- **v2.0.0** (2026-07-30) — Corrige o `not_contains` que expulsava o núcleo do módulo (declarava Activity Log per-Model como fora de escopo, quando `/{activityId}` e `/{activityId}/revert` sempre foram por-registro). Esse erro sustentou a conclusão "módulo vazio" numa tentativa de deprecação barrada por [W]. Adiciona a fronteira emitir×ler, o registry de irreversíveis e os 3 eixos de negação do revert.
- **v1.0.0** (2026-05-20) — SCOPE.md inicial gerado durante PR #1183 (Fiscal cockpit) pra desbloquear `check-scope --strict` no CI.
