---
slug: NEXT-constituicao-v2
number: NEXT
title: "Constituição v2 Oimpresso — 7 camadas + 8 princípios duros"
type: adr
status: proposed
authority: [Wagner]
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-06
decided_by: [Wagner, Claude]
tier: CANON
trust_level: tier-0-irrevogavel
related_adrs: [0035, 0040, 0053, 0061, 0062, 0070, 0079, 0091, 0093]
parent_charter: mission.constituicao-v2
supersedes: [0078, 0079]
referenced_by: []
authors: [wagner, sonnet]
---

# ADR — Constituição v2 Oimpresso (mãe das 7 camadas)

> **Status:** 📝 PROPOSTO — Wagner revisa cada seção e marca aprovação no PR.
> Substitui ADR 0079 (Constituição 10 artigos) consolidando aprendizado do roadmap S1–S7
> e estado-da-arte 2026 (deep-dives `memory/sprints/research/sN-deep-dive.md`).

---

## Contexto

A Constituição v1 (ADR 0079, 10 artigos) foi escrita em 05/05/2026 quando o projeto tinha 79 ADRs e 10 skills. Em 30 dias o ecossistema cresceu (90+ ADRs, 19 skills, 23 módulos) e os achados de 2026 sobre **agentic AI governance** ([CIO 2026 agentic constitution](https://www.cio.com/article/4118138/why-your-2026-it-strategy-needs-an-agentic-constitution.html), [arXiv 2604.27691 runtime constitutions](https://arxiv.org/html/2604.27691)) mostram convergência forte com práticas que o Oimpresso está adotando organicamente.

Esta v2 não invalida v1 — promove os artigos a uma **arquitetura formal de 7 camadas** com contratos explícitos entre elas, e adiciona 3 princípios novos (multi-tenant Tier 0, transparência, confiabilidade) que decisões dos últimos 30 dias revelaram como invariantes não-negociáveis.

**Estatística marcante:** apenas 36% das enterprises têm governance centralizada de agentes hoje, 12% usam plataforma centralizada ([Google AI Governance 2026](https://www.artificialintelligence-news.com/news/agentic-ai-governance-enterprise-readiness-google/)). Estamos na vanguarda — só falta formalizar.

## Decisão

Adotamos a **Constituição v2** como contrato canônico do Oimpresso, organizada em **7 camadas** (L1–L7) com **8 princípios duros** que não podem ser violados sem ADR mãe nova que supersede esta.

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

#### 1. Context as a product
Contexto é UI: tem hierarquia, cache, versão, owner. Não é "tudo que cabe no prompt" — é **interface curada**. CLAUDE.md ≤100 linhas com `@imports`. Brief diário gera estado consolidado. Charter por page com 8 seções canônicas.

> **Why:** sessões de Claude consumiam 30-60k tokens de orientação antes de fazer qualquer coisa útil. Custo desperdiçado + degradação de qualidade. Anthropic 2026 trend report confirma: "the best CLAUDE.md files are iterated over weeks" — contexto é produto, não documento.

#### 2. Tiered cost — Brain A default, Brain B excepcional, humano inevitável
Toda decisão custosa passa por triagem: Brain A (gpt-4o-mini, $0.15/1M) tenta primeiro; Brain B (Sonnet/Opus) só com risk score ≥ MED; humano só em CRIT ou Tier 0. Budget cap com circuit breaker 3 níveis (baseline / warning 2.5× / halt 5×).

> **Why:** sem triagem, agents tendem a usar Brain B sempre. 10 agents × 30 sessões/dia × Sonnet = $40-80/dia desnecessários. Estado-da-arte 2026 (TRiSM, GraphPlanner) confirma: routing tieradas é padrão.

#### 3. Charter > Spec
SPECs são documentos estáticos que apodrecem. Charters são **contratos vivos** lidos pela IA na hora do diff. Bloqueiam edição de `.tsx` que viola invariante.

> **Why:** spec escrito em 2026-04 sobre tela X já está desatualizado em 2026-05 quando o time refatora. Charter ao lado do `.tsx` (`Index.charter.md`) sincroniza naturalmente.

#### 4. Loop fechado por métrica
Toda regra tem dashboard provando ROI. Sem métrica = regra não existe. Brief diário expõe: brief uptime, custo Brain B/dia, % auto-aprovado, drift visual, charters apodrecendo, queries sem business_id detectadas.

> **Why:** governança que não se mede vira teatro. Wagner perdeu tempo investigando "se está OK" várias vezes — sentinela operacional (`jana:health-check`) resolve.

#### 5. Separation of concerns brutal
Uma coisa, um lugar, um dono. Tasks → tabelas `mcp_*` (não markdown). ADRs → `memory/decisions/` (não inline em código). Charters → `*.charter.md` ao lado do `.tsx`. Sessions → `memory/sessions/`. Auto-mem privada → ZERO (ADR 0061).

> **Why:** duplicação de fonte de verdade gerou bugs reais (CURRENT.md vs mcp_tasks, auto-mem vs git, copiloto_* vs jana_*). Append-only + single source per type elimina classe de erros.

#### 6. Multi-tenant by default — Tier 0 IRREVOGÁVEL ⚠️
Toda query, log, brief, audit trail respeita `business_id` global scope. Vazar dado entre tenants é o pior bug possível do projeto.

7 garantias em camadas (defense in depth) detalhadas em **[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)**:
schema obrigatório → Model `HasBusinessScope` → Job passa `$businessId` → Pest cross-tenant → CI lint → SQL audit mensal → Brief reporta health.

> **Why:** LGPD Art. 7º + Art. 46. Auto-mem confirma: "vazar dados entre tenants é o pior bug possível neste projeto". Reforçado por skill `multi-tenant-patterns` Tier A always-on (S3 §3).

#### 7. Transparência (Explainability) — adicionado v2
Toda decisão de Brain B tem trilha auditável: input completo + reasoning + output + custo. Cockpit `/governance/oimpresso` (S7) expõe drill-down. `mcp_audit_log` registra tudo.

> **Why:** TRiSM framework 2026 ([ScienceDirect](https://www.sciencedirect.com/science/article/pii/S2666651026000069)) define explicabilidade como pilar #1. Sem isso, decisões automáticas viram caixa preta — Wagner perde controle.

#### 8. Confiabilidade com fallback — adicionado v2
Toda chamada Brain B tem fallback Brain A; toda chamada Brain A tem fallback humano. Falha de provider externo (OpenAI down, Anthropic timeout) **não derruba** o oimpresso — degrada graciosamente.

> **Why:** dependência única de provedor externo é risco operacional alto. ADR 0058 (Centrifugo > Reverb) aprendeu isso na prática. Estende pra LLM: `ProfileDistiller` cai em silêncio se LLM falhar; `responderChat` cai em fixture.

## Como propor mudança

| Tipo de mudança | Caminho |
|---|---|
| **ADR canon nova** (alteração de camada/princípio) | PR + ADR Nygard + aprovação Wagner explícita |
| **ADR HISTORICAL** (registra decisão já tomada) | PR opcional, status `historical`, sem aprovação obrigatória |
| **Skill nova** (Tier B/C) | PR + SKILL.md com description triggerada por "Use ao/quando" |
| **Skill Tier A** (always-on) | PR + ADR + Wagner aprova promoção |
| **Charter novo** | PR + `*.charter.md` com 8 seções canônicas |
| **Mudança em ADR canon existente** | ❌ NÃO permitido (append-only). Criar nova com `supersedes: [N]` |

## Onde NÃO inventar (Tier 0 — sem ADR mãe nova é proibido)

- Tokens MCP (geração, rotação, schema `mcp_tokens`)
- `mcp_audit_log` schema (append-only via trigger MySQL)
- ADRs CANON existentes (regra append-only)
- `business_id` global scope (princípio #6)
- Centrifugo + FrankenPHP runtime CT 100 (ADR 0058)
- Hostinger ≠ CT 100 separação (ADR 0062)
- ZERO auto-mem privada (ADR 0061)
- `laravel/octane` no Hostinger (CLAUDE.md §4)

## Métricas de saúde da Constituição

Geradas pelo `jana:health-check` daily 06:00 BRT:

| Métrica | Alvo | Vermelho se |
|---|---|---|
| ADRs canon ativas | ≤30 | >40 (poda S7 obrigatória) |
| Skills Tier A em uso | ≥80% sessões | <60% (skill não está ativando) |
| Brief uptime | ≥99% | <97% |
| Cobertura charters páginas críticas | ≥80% | <60% |
| Custo Brain B/dia | ≤$25 médio | >$40 |
| % PRs auto-aprovados (S5) | ≥40% | <20% |
| Drift visual em PRs | <5% | >10% |
| Linhas órfãs `business_id IS NULL` | 0 | >0 (incidente Tier 0) |
| Tokens médios/sessão | 25-40k | >50k |
| Onboarding Claude novo | ≤2min via brief | >10min |

## Consequências

### Positivas
- Single source of truth pra arquitetura — IA e dev consultam mesma referência
- Defense in depth (7 camadas + 8 princípios) — falha de 1 não vira incidente
- Auditável — toda ADR nova referencia esta como mãe
- Alinha com state-of-the-art 2026 (agentic constitution + TRiSM)
- Reduz custo cognitivo do Wagner (tem onde apontar quando algo desviar)

### Negativas
- Atrito inicial até time interiorizar 8 princípios
- Algumas regras antigas (ex: ADR 0079) viram superseded — re-leitura
- ADR poda (S7) requer aprovação bloco a bloco do Wagner

### Mitigações
- Skills Tier A always-on enforce princípios automaticamente (brief-first, mcp-first, charter-first, ads-route, multi-tenant-patterns)
- Health-check sentinela (jana:health-check) detecta drift em <24h
- Sonnet pré-classifica 92 ADRs pra Wagner aprovar em ~10×10min em vez de 8h

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-05 | Wagner + Claude | ADR 0079 (Constituição v1, 10 artigos) — predecessora |
| 2026-05-06 | Sonnet + Wagner | Esta v2 — 7 camadas formais + 8 princípios duros (Tier 0 multi-tenant + Transparência + Confiabilidade adicionados) |
