---
slug: 0094-constituicao-v2-7-camadas-8-principios
number: 94
title: "Constituição v2 Oimpresso — 7 camadas + 8 princípios duros"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-06
decided_by: [W]
module: governance
tier: CANON
trust_level: tier-0-irrevogavel
related_adrs: [0035, 0040, 0053, 0061, 0062, 0070, 0079, 0091, 0093, 0095]
parent_charter: mission.constituicao-v2
supersedes:
  - '0079-constituicao-oimpresso-7-camadas-governanca'
supersedes_partially:
  - '0078-constituicao-uma-frase-skill-unidade-evolucao'
referenced_by: []
authors: [wagner, sonnet]
accepted_at: 2026-05-06
accepted_by: wagner
---

# ADR 0094 — Constituição v2 Oimpresso (mãe das 7 camadas)

> **Status:** ✅ ACEITA em 2026-05-06 por Wagner ("esta aprovado pode fazer o merge").
> Vigente. Substitui [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md)
> consolidando aprendizado de 30 dias + estado-da-arte 2026.

---

## Contexto

A Constituição v1 (ADR 0079, 10 artigos) foi escrita em 05/05/2026 quando o projeto tinha 79 ADRs e 10 skills. Em 30 dias o ecossistema cresceu (90+ ADRs, 19 skills, 23 módulos) e os achados de 2026 sobre **agentic AI governance** ([CIO 2026](https://www.cio.com/article/4118138/why-your-2026-it-strategy-needs-an-agentic-constitution.html), [arXiv 2604.27691](https://arxiv.org/html/2604.27691)) mostram convergência forte com práticas que o Oimpresso adotou organicamente.

Esta v2 não invalida v1 — promove os artigos a **arquitetura formal de 7 camadas** com contratos explícitos entre elas, e adiciona 3 princípios novos (multi-tenant Tier 0, transparência, confiabilidade) que decisões dos últimos 30 dias revelaram como invariantes não-negociáveis.

## Decisão

Adotamos a **Constituição v2** organizada em **7 camadas** (L1–L7) com **8 princípios duros** que não podem ser violados sem ADR mãe nova que supersede esta.

### As 7 camadas (verticais → ascendentes)

```
┌────────────────────────────────────────────────────────────────────────┐
│ L7 — DAILY BRIEF        ✅ PROD  · contexto consolidado 6×/dia, ~3k    │
│        Dono: Wagner · Contrato: brief-fetch tool MCP                   │
│ L6 — CHARTERS           🔲 S4    · contratos vivos page/feature/mission│
│        Dono: design+code per page · Contrato: charter-fetch + pre-edit │
│ L5 — ADRs canon         ⚠️ 92→30 · decisões irrevogáveis append-only   │
│        Dono: Wagner aprova · Contrato: nygard format + lifecycle       │
│ L4 — PLAYBOOKS          🔲 S6    · runbooks tactical + playbooks strat │
│        Dono: ops + dev · Contrato: frontmatter c/ last_tested          │
│ L3 — SKILLS             ⚠️ 19    · auto-trigger por description match  │
│        Dono: skill author · Contrato: SKILL.md + tier interno A/B/C    │
│ L2 — ADS Universal      🔲 S5    · firewall code/design/produto/mem/run│
│        Dono: ADS module · Contrato: decide(domain,intent,payload)      │
│ L1 — MCP CORE           ✅ PROD  · tools + memória + audit + RBAC      │
│        Dono: Wagner · Contrato: laravel/mcp + Identity Mesh ADR 0081   │
└────────────────────────────────────────────────────────────────────────┘
```

Cada camada tem **um dono claro** e **um contrato técnico**. Mudança em camada superior só pode invocar primitivo de camada inferior, nunca pular.

### Os 8 princípios duros

1. **Context as a product** — contexto é UI: hierarquia, cache, versão, owner. CLAUDE.md ≤100 linhas com `@imports`. Brief diário gera estado consolidado.
2. **Tiered cost** — Brain A (gpt-4o-mini) default; Brain B (Sonnet/Opus) só com risk score ≥MED; humano só CRIT/Tier 0. Circuit breaker 3 níveis (baseline / warning 2.5× / halt 5×).
3. **Charter > Spec** — SPECs apodrecem. Charters são contratos vivos lidos pela IA na hora do diff. Bloqueiam edição de `.tsx` que viola invariante.
4. **Loop fechado por métrica** — toda regra tem dashboard provando ROI. Sem métrica = regra não existe. `jana:health-check` daily 06:00 BRT.
5. **Separation of concerns brutal** — uma coisa, um lugar, um dono. Tasks→`mcp_*` (não md). ADRs→`memory/decisions/`. Charters→`*.charter.md`. Auto-mem privada→ZERO (ADR 0061).
6. **Multi-tenant by default — Tier 0 IRREVOGÁVEL** ⚠️ — `business_id` global scope obrigatório. Vazar tenant = pior bug possível. Ver [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — 7 garantias defense in depth.
7. **Transparência (Explainability)** — toda decisão Brain B tem trilha auditável: input + reasoning + output + custo. TRiSM 2026 pilar #1.
8. **Confiabilidade com fallback** — toda chamada Brain B tem fallback Brain A; toda Brain A tem fallback humano. Falha provider externo NÃO derruba oimpresso.

## Como propor mudança

| Tipo | Caminho |
|---|---|
| **ADR canon nova** (camada/princípio) | PR + ADR Nygard + aprovação Wagner explícita |
| **ADR HISTORICAL** (decisão já tomada) | PR opcional, status `historical` |
| **Skill Tier B/C** | PR + SKILL.md description "Use ao/quando..." |
| **Skill Tier A** (always-on) | PR + ADR específica + Wagner aprova promoção |
| **Charter novo** | PR + `*.charter.md` 8 seções (S4+) |
| **Mudança ADR canon existente** | ❌ NÃO permitido. Append-only. Criar nova com `supersedes: [N]` |

## Onde NÃO inventar (Tier 0 — sem ADR mãe nova é proibido)

- Tokens MCP, schema `mcp_audit_log` (append-only via trigger)
- ADRs CANON existentes (regra append-only)
- `business_id` global scope (princípio #6)
- Centrifugo + FrankenPHP runtime CT 100 ([ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md))
- Hostinger ≠ CT 100 separação ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md))
- ZERO auto-mem privada ([ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md))
- `laravel/octane` no Hostinger

## Métricas de saúde

Geradas pelo `jana:health-check` daily 06:00 BRT (ver `Modules/Jana/Console/Commands/HealthCheckCommand.php`):

| Métrica | Alvo | Vermelho se |
|---|---|---|
| ADRs canon ativas | ≤30 | >40 (poda S7 obrigatória) |
| Skills Tier A em uso | ≥80% sessões | <60% |
| Brief uptime | ≥99% | <97% |
| Cobertura charters páginas críticas | ≥80% | <60% |
| Custo Brain B/dia | ≤$25 médio | >$40 |
| Linhas órfãs `business_id IS NULL` | 0 | >0 (incidente Tier 0) |
| Tokens médios/sessão | 25-40k | >50k |

## Consequências

### Positivas
- Single source of truth — IA e dev consultam mesma referência
- Defense in depth (7 camadas + 8 princípios)
- Auditável — toda ADR nova referencia esta
- Alinha com state-of-the-art 2026

### Negativas
- Atrito inicial até time interiorizar 8 princípios
- ADRs antigas (ex: 0078, 0079) viram superseded — re-leitura

### Mitigações
- 5 Skills Tier A always-on (ADR 0095) enforce princípios mecanicamente
- `jana:health-check` detecta drift em <24h
- ADR poda (S7) bloco a bloco

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-05 | Wagner + Claude | ADR 0079 (Constituição v1, 10 artigos) — predecessora |
| 2026-05-06 | Sonnet rascunho + Wagner aprovação | Esta v2 — 7 camadas + 8 princípios duros (3 NOVOS: Tier 0 multi-tenant + Transparência + Confiabilidade) |
