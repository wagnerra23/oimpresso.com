---
name: Auditoria 19 skills atuais — pré-classificação Sonnet
description: Decisão de tier por skill. Wagner aprova bloco a bloco. Pós-S3 entrega 5 Tier A + ~9 Tier B + ~5 Tier C.
type: audit
related_adr: NEXT-skills-tiers
created: 2026-05-06
authors: [sonnet]
status: pending_wagner_approval
---

# Auditoria 19 skills atuais — pré-classificação Sonnet

> **Aprovação:** Wagner revisa por bloco (A–E) e marca APROVADO/AJUSTAR/RECUSAR.
> Critério primário pra Tier A: regra que precisa ser SEMPRE lembrada (não pode depender de description match).

---

## Sumário da decisão proposta

| Categoria | Quantidade | Skills |
|---|---|---|
| **Tier A — always-on (ativas)** | 3 | brief-first, mcp-first (rename), multi-tenant-patterns |
| **Tier A — dormentes (esperando S4/S5)** | 2 | charter-first, ads-route |
| **Tier A — criar nova** | 1 | commit-discipline (não existe ainda) |
| **Tier B — auto-trigger contextual** | 9 | ads-decision-flow, comparativo-do-modulo, copiloto-arch, criar-modulo, memoria-recall-flow, memory-sync, migrar-modulo, publication-policy, runtime-rules-hostinger-ct100 |
| **Tier C — slash command/on-demand** | 6 | cockpit-runbook, meta-skill-roi-erp-autonomo, oimpresso-cc-watcher-setup, oimpresso-stack, oimpresso-team-onboarding, proxmox-docker-host |
| **Reclassificar/melhorar description** | 1 | sidebar-menu-arch (description fraca, virar Tier B) |
| **Arquivar** | 0 | (sem dados telemetria suficiente — esperar 30d pós-S3) |

**Total final pós-S3:** 19 atuais + 1 nova (commit-discipline) − 0 archived = **20 skills**, com **5 Tier A canônicas** (3 ativas + 2 dormentes + 1 nova).

---

## Bloco A — Tier A always-on (5 skills, ações por skill)

> ⚠️ **Wagner aprova individualmente cada uma. Cada Tier A precisa ADR justificando promoção.**

### A.1 — `brief-first` ✅ JÁ Tier A (PR #129)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A (manter) |
| **Mecanismo** | Hook `SessionStart` força `mcp__oimpresso__brief-fetch` |
| **Why Tier A** | Reduz onboarding 30k→3k tokens em toda sessão; ROI medido (Sprint 1 §1.1) |
| **Description atual** | "ATIVAR PRIMEIRO em toda sessão — força chamada brief-fetch" ✅ ok |
| **Action** | Adicionar `tier: A` no frontmatter (documental) |
| **Telemetria 7d** | a coletar pós-S3 |

### A.2 — `mcp-first` (renomear de `oimpresso-mcp-first`)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A + renomear pasta |
| **Mecanismo** | Description match em "ler/buscar/listar" + hint via SessionStart |
| **Why Tier A** | Garante uso de tools MCP antes de Read filesystem (governança + audit log) |
| **Action** | `git mv .claude/skills/oimpresso-mcp-first .claude/skills/mcp-first` + `tier: A` |
| **Riscos** | Forçar MCP em sessão sem token → erro confuso. Mitigação: hint, não bloqueio |

### A.3 — `multi-tenant-patterns` (PROMOVER de B → A)

| Campo | Valor |
|---|---|
| **Decisão** | PROMOVER Tier B → Tier A |
| **Mecanismo** | Hook `SessionStart` exibe regras se sessão toca `Modules/*/Entities/` ou Migration |
| **Why Tier A** | Princípio duro #6 da Constituição (Tier 0 IRREVOGÁVEL). ADR 0093 mãe. Vazar dado entre tenants = pior bug possível. |
| **Description atual** | "Use ao criar Eloquent Model, Controller, Service, Job ou Migration que toca dados de negócio..." ✅ ok |
| **Action** | Adicionar `tier: A`, referência ADR 0093 no frontmatter |
| **Riscos** | Dispara em mudanças cosméticas (renomear classe sem tocar query) — false positive aceitável |

### A.4 — `commit-discipline` (CRIAR — não existe ainda)

| Campo | Valor |
|---|---|
| **Decisão** | CRIAR nova Tier A |
| **Mecanismo** | Hook `PreToolUse` em git commit + alerta se diff >300 linhas ou >1 intent |
| **Why Tier A** | 1 PR = 1 intent reduz risk score (S5 ADS). Diff <300 linhas é critério Anthropic 2026 best-practice. |
| **Description proposta** | "Use ANTES de git commit ou git push. Garante 1 PR = 1 intent, ≤300 linhas, conventional commits format." |
| **Action** | Criar `.claude/skills/commit-discipline/SKILL.md` |
| **Riscos** | Commits grandes legítimos (refactor amplo) podem ser flagged — flag override `--allow-large` |

### A.5 — `charter-first` (CRIAR DORMENTE — esperando S4)

| Campo | Valor |
|---|---|
| **Decisão** | CRIAR Tier A com `enabled: false` até S4 entregar tool `charter-fetch` |
| **Mecanismo** | Hook `PreToolUse` em Edit/Write em `.tsx`. Se houver `*.charter.md` ao lado, força chamada `charter-fetch` antes |
| **Why Tier A** | Princípio duro #3 (Charter > Spec). Sem hook, charter vira doc morto. |
| **Action** | Criar `.claude/skills/charter-first/SKILL.md` com `enabled: false` |
| **Ativação** | S4 entrega `charter-fetch` tool + sync `php artisan charter:sync` → `enabled: true` |

### A.6 — `ads-route` (CRIAR DORMENTE — esperando S5)

| Campo | Valor |
|---|---|
| **Decisão** | CRIAR Tier A com `enabled: false` até S5 entregar `decide()` |
| **Mecanismo** | Toda decisão custosa passa por `decide(domain, intent, payload)` |
| **Why Tier A** | Princípio duro #2 (Tiered cost). Sem firewall, agents usam Brain B sempre. |
| **Action** | Criar `.claude/skills/ads-route/SKILL.md` com `enabled: false` |
| **Ativação** | S5 entrega ADS Universal → `enabled: true` |

---

## Bloco B — Tier B auto-trigger contextual (9)

> Aprovação Wagner: bloco inteiro APROVADO/AJUSTAR.

| Skill | Description (preview) | Decisão | Ajuste pendente? |
|---|---|---|---|
| `ads-decision-flow` | "Use ao trabalhar em Modules/ADS/ ou tocar fluxo de decisão automatizada..." | TIER B | ✅ ok |
| `comparativo-do-modulo` | "ATIVAR quando user pedir comparar módulo X com mercado..." | TIER B | ✅ ok |
| `copiloto-arch` | "Use ao trabalhar em Modules/Copiloto/ ou ao tocar memória/IA..." | TIER B | ⚠️ rename pra `jana-arch` (alinhamento Modules/Jana/) |
| `criar-modulo` | "Use ao criar novo módulo Laravel modular..." | TIER B | ✅ ok |
| `memoria-recall-flow` | "Use ao tocar Modules/Copiloto/Services/Memoria/..." | TIER B | ⚠️ rename `jana-recall-flow`, ajustar paths Modules/Jana/ |
| `memory-sync` | "ATIVAR após criar/editar arquivo em memory/..." | TIER B | ✅ ok |
| `migrar-modulo` | "Use ao mover, renomear, ou extrair controller/módulo..." | TIER B | ✅ ok |
| `publication-policy` | "Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção..." | TIER B | ✅ ok |
| `runtime-rules-hostinger-ct100` | "Use ANTES de SSH no Hostinger, composer install/update em servidor..." | TIER B | ✅ ok |

**Sub-decisões pendentes Wagner:**
- [ ] Renomear `copiloto-arch` → `jana-arch`?
- [ ] Renomear `memoria-recall-flow` → `jana-recall-flow`?

---

## Bloco C — Tier C slash command/on-demand (6)

| Skill | Decisão | Justificativa |
|---|---|---|
| `cockpit-runbook` | TIER C | Geração de runbook por demanda — não dispara contextual |
| `meta-skill-roi-erp-autonomo` | TIER C | Ativa quando usar `skill:scaffold` ou criar skill nova — uso pontual |
| `oimpresso-cc-watcher-setup` | TIER C | One-time setup do watcher cross-dev — não recorrente |
| `oimpresso-stack` | TIER C | One-time onboarding ao entrar no oimpresso — não cada sessão |
| `oimpresso-team-onboarding` | TIER C | One-time setup MCP per dev — não recorrente |
| `proxmox-docker-host` | TIER C | Receita Proxmox específica — só quando trabalhar infra CT 100 |

---

## Bloco D — Skills com description fraca (precisa ajuste)

| Skill | Problema | Proposta |
|---|---|---|
| `sidebar-menu-arch` | Description começa com "Reconhecer, auditar..." (não "Use ao") — não trigger Anthropic-pattern | Reescrever: "Use ao mexer no sidebar AppShellV2, criar/alterar menu lateral, debug rota não aparece no menu, ou auditar SIDEBAR_GROUPS/DataController.modifyAdminMenu" |

Após ajuste: TIER B.

---

## Bloco E — Skills NOVAS a criar (5 arquivos novos/movidos)

| Skill | Tier | Path | Estado |
|---|---|---|---|
| `commit-discipline` | A | `.claude/skills/commit-discipline/SKILL.md` | criar — body ≤200 linhas |
| `charter-first` | A dormente | `.claude/skills/charter-first/SKILL.md` | criar com `enabled: false` |
| `ads-route` | A dormente | `.claude/skills/ads-route/SKILL.md` | criar com `enabled: false` |
| `mcp-first` | A | `.claude/skills/mcp-first/SKILL.md` | renomear `oimpresso-mcp-first/` |
| `mwart-migrate` | C | `.claude/skills/mwart-migrate/SKILL.md` | spec já existe em `s2-os-listagem/05-skill-mwart-migrate.md`; finalizar |

---

## Telemetria — coletar 30 dias antes de qualquer archive

Skills NÃO arquivar nesta rodada (sem baseline). Em 30d pós-S3:
- Skill com 0 disparos → arquivar
- Skill com <10% sessões → considerar Tier C ou archive
- Skill com >80% sessões e Tier B → considerar promoção A (ADR específica)

---

## Plano de execução pós-aprovação (passo a passo)

```bash
# 1. Mover/renomear arquivos físicos (precisa Wagner aprovar primeiro)
git mv .claude/skills/oimpresso-mcp-first .claude/skills/mcp-first
git mv .claude/skills/copiloto-arch .claude/skills/jana-arch  # se aprovado
git mv .claude/skills/memoria-recall-flow .claude/skills/jana-recall-flow  # se aprovado

# 2. Adicionar tier: no frontmatter de cada skill (script bash sed em loop)

# 3. Reescrever sidebar-menu-arch description

# 4. Criar 3 SKILL.md novas: commit-discipline, charter-first, ads-route

# 5. Atualizar mwart-migrate (já tem spec)

# 6. Configurar hook SessionStart em .claude/settings.json
#    (force tier A: brief-fetch automaticamente)

# 7. Telemetria — verificar mcp_skill_telemetry está populando
```

---

## Decisões pendentes Wagner

> Marque ✅ APROVADO ou ❌ AJUSTAR pra cada bloco. PR só será mergeado após todos os blocos com ✅.

- [ ] **Bloco A** (Tier A — 3 ativas + 2 dormentes + 1 nova = 6 promoções/criações) — APROVAR?
- [ ] **Bloco B** (9 Tier B — incluindo 2 renames `copiloto-` → `jana-`) — APROVAR?
- [ ] **Bloco C** (6 Tier C) — APROVAR?
- [ ] **Bloco D** (sidebar-menu-arch reescrita) — APROVAR?
- [ ] **Bloco E** (5 skills novas/renomeadas) — APROVAR?

**Sub-decisões:**
- [ ] Renames `copiloto-` → `jana-` em arquivos de skills (alinhamento com módulo)?
- [ ] `commit-discipline` Tier A imediato OU dormente até S4?
