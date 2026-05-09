---
name: audit-constituicao
description: ATIVAR quando user pedir "audit pós-constituição", "/audit-constituicao", "consolidação geral", "revisão geral desde a constituição", OU trimestralmente como housekeeping. Roda 6-dimensional governance audit em paralelo (auto-mem ADR 0061 / MWART artifact coverage / ADR lifecycle index / CAPTERRA cobertura / cycle goals status / skills audit) e consolida em diagnóstico + plano 3-tiers (A safe agora / B precisa ADR / C backlog).
type: process-skill
status: active
version: 1.0.0
trust_level: L2
owner: wagner
created_at: 2026-05-09
updated_at: 2026-05-09
charter_adr: 0094
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."
triggers_on:
  - "/audit-constituicao"
  - "audit pós-constituição"
  - "audit pos-constituicao"
  - "consolidação geral"
  - "revisão geral desde a constituição"
  - "revisão geral pós-constituição"
  - "housekeeping trimestral"
  - "auditar governança"
does_not_trigger_on:
  - "auditar módulo X" (use /comparativo)
  - "auditar tela X contra Cockpit" (use cockpit-runbook modo B)
  - "auditar PR/diff" (use /ultrareview)
  - "auditar acessibilidade" (use design:accessibility-review)
roi_metric:
  type: time
  baseline: "Wagner audita governança manual em ~3h (lê 119 ADRs + cruza com MEMORY.md + abre cycles + revisa CAPTERRA + skills audit) ~1x/trimestre"
  target: "/audit-constituicao reduz pra ~30min — disparar 6 agents paralelos + revisar tabela consolidada + decidir Tier A em batch"
metrics:
  audits_executados: 0
  gaps_tier_a_aprovados: 0
  gaps_tier_b_promovidos_adr: 0
  ultima_execucao: null
artefatos_governados:
  - "memory/sessions/YYYY-MM-DD-audit-constituicao.md (output diagnóstico)"
  - "mcp_tasks via tasks-create (Tier A aprovados)"
  - "memory/decisions/NNNN-*.md (Tier B promovidos a ADR — quando aplicável)"
tier: C
parent_adr: 0095
---

# audit-constituicao — auditoria 6-dimensional pós-Constituição v2

## Por que existe

A Constituição v2 ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)) introduziu 7 camadas de governança e 8 princípios duros. Desde então o projeto acumula drift natural — auto-mem stale, MWART sem charter, ADRs sem lifecycle, CAPTERRA desatualizado, cycles sem rollover limpo, skills duplicadas. Esta skill orquestra a auditoria 6-dimensional canônica que o Wagner roda trimestralmente (ou sob demanda) pra consolidar drift num diagnóstico único + plano 3-tiers acionável. Substitui ~3h de trabalho manual por ~30min de orquestração paralela + revisão.

## Quando ativar

Triggers explícitos (slash command ou frase):

- `/audit-constituicao`
- "audit pós-constituição"
- "consolidação geral"
- "revisão geral desde a constituição"
- "housekeeping trimestral de governança"
- "auditar governança do projeto"

Triggers implícitos aceitáveis (perguntar antes de disparar):

- Após cycle close se Wagner pedir "fechamento amplo" + sem audit nos últimos 90d
- Se brief-fetch reportar drift score >X (futuro — depende de KPI)

**NÃO ativar pra:**
- Auditoria de módulo único (use `/comparativo {modulo}`)
- Auditoria de tela contra Cockpit (use `cockpit-runbook` modo B)
- Code review de PR (use `/ultrareview`)
- Audit de acessibilidade (use `design:accessibility-review`)

## Como executar

### Passo 1 — Pré-flight + aviso de custo

Antes de disparar, avisar Wagner em PT-BR curto:

```
Vou rodar audit 6-dimensional pós-Constituição. Custo estimado: 200-300k tokens
(ADR audit é o mais pesado, ~89k sozinho — varre 119 ADRs frontmatter).
Os 6 agents rodam em paralelo numa única tool call. Confirma? (s/n)
```

Se Wagner aprovar, prosseguir. Se "só X dimensões", rodar subset.

### Passo 2 — Carregar tools MCP necessárias

Audit #5 (cycle goals) precisa tools MCP que podem estar deferred. Pré-carregar via ToolSearch:

```
ToolSearch query: "select:mcp__Oimpresso_MCP___Wagner__cycles-active,mcp__Oimpresso_MCP___Wagner__cycle-goals-track,mcp__Oimpresso_MCP___Wagner__cycles-close"
```

(Substituir `__Wagner__` pelo dev atual — Felipe/Maiara/Luiz/Eliana — conforme ambiente.)

### Passo 3 — Disparar 6 agents em paralelo

**UMA única mensagem com 6 chamadas Task em paralelo.** Cada agent usa o prompt canônico de `prompts/0N-*.md` neste skill folder.

| # | Dimensão | Prompt source | Custo estimado |
|---|---|---|---|
| 1 | Auto-mem MEMORY.md vs ADR 0061 | `prompts/01-auto-mem.md` | ~25k |
| 2 | MWART artifact coverage | `prompts/02-mwart-coverage.md` | ~35k |
| 3 | ADR lifecycle index | `prompts/03-adr-lifecycle.md` | **~89k (mais pesado)** |
| 4 | CAPTERRA cobertura | `prompts/04-capterra-coverage.md` | ~52k |
| 5 | Cycle goals status | `prompts/05-cycle-goals.md` | ~20k |
| 6 | Skills audit pós-Constituição | `prompts/06-skills-audit.md` | ~30k |

**Cross-cuts esperados (não duplicação — convergência):**
- Audits #2 (MWART coverage) e #6 (skills audit) ambos tocam skills `mwart-*` — confirmar que diagnósticos batem
- Audits #1 (auto-mem) e #3 (ADR lifecycle) podem ambos sinalizar drift entre auto-mem e ADRs canônicas

### Passo 4 — Aguardar todos retornarem

Não consolidar parcial. Esperar os 6 outputs.

### Passo 5 — Consolidar em diagnóstico + plano 3-tiers

Apresentar pro Wagner em formato fixo (ver §Output format obrigatório). Skill **PARA** após apresentar — Wagner decide quais Tier A executar, quais Tier B viram ADR, quais Tier C ficam no backlog.

### Passo 6 — Salvar diagnóstico (ver §Pós-execução)

## 6 dimensões + heurística de saúde

Cada dimensão tem um prompt canônico em `prompts/0N-*.md` (lá está o que checa em detalhe + output format do sub-agent). Resumo + heurística aqui:

| # | Dimensão | O que checa (curto) | 🟢 verde | 🟡 amarelo | 🔴 vermelho |
|---|---|---|---|---|---|
| 1 | Auto-mem vs ADR 0061 | MEMORY.md órfão / stale / PII-leak | 0 órfãos críticos + ≤2 stale | 1-3 stale ou 1 órfão não-crítico | PII-leak OU órfão crítico OU >3 stale |
| 2 | MWART artifact coverage | Pages têm RUNBOOK + visual-comparison + charter (S4+) | ≥95% cobertas + zero core sem artefato | 80-94% cobertas (gaps em peripheral) | <80% OU core Page top sem artefato |
| 3 | ADR lifecycle index | frontmatter `lifecycle:` + supersedes íntegras + _INDEX sync | 100% válido | 1-5 sem lifecycle ou 1-2 supersedes broken | >5 sem lifecycle OU _INDEX dessync >10% OU ADR órfã |
| 4 | CAPTERRA cobertura | FICHA + INVENTARIO + SPEC sync nos módulos top | 100% top + inventários <90d | 1-2 top com inv 90-180d ou 1 sem ficha | >2 top sem ficha OU inv >180d OU SPEC dessync |
| 5 | Cycle goals status | cycle ativo tem goals trackados + cycles passados rollover + órfãs | 3+ goals + últimos 3 fechados rollover + <5 órfãs | goals sem track 14d+ OU 5-15 órfãs | sem goals OU cycle abandonado OU >15 órfãs |
| 6 | Skills audit | tier declarado + Tier A com parent_adr + zero duplicada | 100% tier + 0 duplicação | 1-3 sem tier OU 1 duplicação leve | >3 sem tier OU Tier A sem ADR OU duplicação clara |

**Regra de exclusão crítica pra Dimensão 2 (anti false-positive descoberto 2026-05-09):** sub-components em `_components/` ou `components/` e `.tsx` que não têm `Inertia::render('<Mod>/<Tela>')` no controller correspondente NÃO contam como Page MWART. Detalhe completo em `prompts/02-mwart-coverage.md`.

**Aviso de custo:** Dimensão 3 é a mais cara (~89k tokens — varre 119 ADRs frontmatter). Considere rodar isolado se short on credits.

## Output format obrigatório

Após receber os 6 outputs, consolidar em **uma única mensagem** pro Wagner, com 3 blocos:

### Bloco 1 — Tabela diagnóstico 6-rows

```markdown
| # | Dimensão | Saúde | Headline |
|---|---|---|---|
| 1 | Auto-mem vs ADR 0061 | 🟢/🟡/🔴 | <1-frase resumindo achado principal> |
| 2 | MWART artifact coverage | 🟢/🟡/🔴 | <1-frase> |
| 3 | ADR lifecycle index | 🟢/🟡/🔴 | <1-frase> |
| 4 | CAPTERRA cobertura | 🟢/🟡/🔴 | <1-frase> |
| 5 | Cycle goals status | 🟢/🟡/🔴 | <1-frase> |
| 6 | Skills audit | 🟢/🟡/🔴 | <1-frase> |
```

### Bloco 2 — Achados Tier 0 (se houver)

Tier 0 = violação de princípio duro (ADR 0094 §princípios duros) ou regra IRREVOGÁVEL (multi-tenant, runtime separation, auto-mem privada com PII). Listar **antes** do plano 3-tiers — exigem atenção imediata, não viram backlog.

```markdown
## Achados Tier 0 (atenção imediata)

- [Dimensão N] <descrição curta + path/ref> — viola <princípio>
- ...

(Se zero: "Nenhum achado Tier 0. Prossegue ao plano 3-tiers.")
```

### Bloco 3 — Plano 3-tiers acionável

```markdown
## Plano 3-tiers

### Tier A — safe agora (sem ADR, sem aprovação extra além desta)

- [ ] <ação concreta> — <evidência/path> — estimativa: ~<X>min
- [ ] ...

### Tier B — precisa ADR antes de executar

- [ ] <ação> — propor ADR `NNNN-<slug>` — razão: <muda contrato canônico / Tier 0 affected / muda Princípio duro>
- [ ] ...

### Tier C — backlog longo prazo

- [ ] <ação> — task MCP sugerida: `tasks-create module:<X> priority:P<N> title:"<>" tags:[from-audit-constituicao]`
- [ ] ...
```

### Pergunta final fixa

Após apresentar os 3 blocos, perguntar literalmente:

```
Quais Tier A executo agora? (todos / nenhum / "1,3,5" / lista numerada da minha escolha)
Tier B viro ADRs draft? (s/n — se s, listo as ADRs propostas)
Tier C salvo como tasks MCP? (s/n)
```

**Skill PARA aqui.** Não bulk-fix. Wagner decide.

## Anti-padrões

- ❌ **Bulk-fix sem aprovação Wagner** — skill apresenta diagnóstico, não executa correções automáticas. Mesmo Tier A "safe" exige aprovação explícita por item ou batch.
- ❌ **Editar ADR canônica existente** — append-only ([proibições.md](../../memory/proibicoes.md)). Tier B vira ADR nova com `supersedes: [N]`.
- ❌ **1 PR multi-intent** — se Wagner aprovar 3+ Tier A, ainda assim cada um vira PR separado (skill `commit-discipline` Tier A: 1 PR = 1 intent ≤300 linhas).
- ❌ **Auto-criar tasks MCP sem confirmação** — alinhado com `comparativo-do-modulo` e [publication-policy](../publication-policy/SKILL.md).
- ❌ **Pular dimensão silenciosamente** — se 1 dos 6 agents falhar, reportar explicitamente "Dimensão N falhou: <razão>" no diagnóstico, não suprimir.
- ❌ **Rodar em sequência em vez de paralelo** — desperdiça ~3x tempo de wall-clock. Sempre 6 Task em uma única tool call.
- ❌ **Contar sub-component como Page MWART** — regra de exclusão clara em §Dimensão 2.

## Pós-execução

### Salvar diagnóstico

Após Wagner decidir, salvar o diagnóstico consolidado (mesmo formato do Output) em:

```
memory/sessions/YYYY-MM-DD-audit-constituicao.md
```

Frontmatter:

```yaml
---
name: Audit Constituição v2 — YYYY-MM-DD
description: 6-dimensional governance audit run via skill audit-constituicao
type: audit
related_adr: 0094
created: YYYY-MM-DD
authors: [claude]
status: completed
saude_global: 🟢/🟡/🔴
tier_a_aprovados: <N>
tier_b_promovidos: <N>
tier_c_backlog: <N>
---
```

### Criar tasks MCP pros Tier A aprovados

Pra cada Tier A que Wagner aprovou:

```
tasks-create module:governanca priority:P1 title:"<ação>" tags:["from-audit-constituicao", "<dimensao>"]
```

### Criar ADRs draft pros Tier B (se Wagner pediu)

Pra cada Tier B promovido, criar `memory/decisions/NNNN-<slug>.md` com `lifecycle: draft` aguardando Wagner aceitar.

### Atualizar telemetria da skill

Bumpar no frontmatter da própria SKILL.md:
- `metrics.audits_executados += 1`
- `metrics.gaps_tier_a_aprovados += N`
- `metrics.gaps_tier_b_promovidos_adr += N`
- `metrics.ultima_execucao: YYYY-MM-DD`

### Commit + push

```bash
git add memory/sessions/YYYY-MM-DD-audit-constituicao.md .claude/skills/audit-constituicao/SKILL.md
git add memory/decisions/  # se houver ADRs draft
git commit -m "docs(audit): consolidação geral pós-constituição YYYY-MM-DD via /audit-constituicao"
git push origin <branch>
```

Webhook GitHub→MCP propaga em <60s.

## Refs

- [ADR 0094 — Constituição v2 (mãe)](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0061 — Zero auto-mem privada](../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0070 — Jira-style task management](../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0089 — Capterra-driven module evolution](../../memory/decisions/0089-capterra-driven-module-evolution.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0095 — Skills tiers convenção interna](../../memory/decisions/0095-skills-tiers-convencao-interna.md)
- [ADR 0104 — Processo MWART canônico](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [memory/proibicoes.md](../../memory/proibicoes.md) — Tier 0 catalogadas
- [memory/sprints/s3-constituicao/03-skills-audit.md](../../memory/sprints/s3-constituicao/03-skills-audit.md) — baseline skills audit S3

---

**Última atualização:** 2026-05-09 — versão inicial pós-sessão "consolidação geral" (Wagner pediu skill reutilizável).
