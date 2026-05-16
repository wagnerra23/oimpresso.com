---
name: avaliar-modulo
mission: "Substituir avaliação subjetiva de maturidade de Modules/<X>/ por nota objetiva 0-100 ponderada pela rubrica oficial module-grade-v3 (9 dimensões, ADR 0155)."
description: ATIVAR quando user pedir "nota do módulo X", "avaliar Modules/X", "/avaliar-modulo X", "qual a nota de Y", "module grade de Z", "qual o bucket de W", "qual modulo está crítico?", "média do projeto", "ranking dos módulos", OU mencionar rubrica `module-grade-v3` (ou ADR 0155, 0154, 0153). Roda `php artisan module:grade {nome} --detail --evolve` (ou `--all` quando agregado) e formata output. Mostra nota total /100 normalizada (raw /118) + 9 dimensões (D1-D9 incl Performance, LGPD, Security, Observability) + top gaps + batch tasks sugeridas. NÃO cria tasks sem aprovação humana — apenas formata batch markdown pra Wagner aprovar/editar/colab via `tasks-create` MCP.
type: process-skill
status: active
version: 2.0.0
trust_level: L1
owner: wagner
created_at: 2026-05-16
updated_at: 2026-05-16
charter_adr: 0155
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."
triggers_on:
  - "/avaliar-modulo"
  - "/avaliar-modulo {modulo}"
  - "nota do módulo {X}"
  - "nota do {X}"
  - "qual a nota de {X}"
  - "avaliar Modules/{X}"
  - "module grade de {X}"
  - "qual bucket de {X}"
  - "ranking dos módulos"
  - "média do projeto"
  - "rubrica module-grade-v3"
  - "rubrica module-grade-v2"
  - "rubrica module-grade-v1"
related_adrs: [0155, 0154, 0153, 0093, 0101, 0094, 0105]
related_skills: [comparativo-do-modulo, module-completeness-audit, module-grades-gate, brief-update]
tier: B
---

# avaliar-modulo — rubrica `module-grade-v3` (ADR 0155)

> **Lineage:** v3 (ADR 0155) supersedes parcial v2 (ADR 0154 — N/A justificado) + v1 (ADR 0153 — 5 dimensões base). Atual: **9 dimensões D1-D9**, peso raw total 118, **score final normalizado /100** (`raw × 100 / 118`).

## Quando ativar

ATIVAR quando user pedir avaliação objetiva de maturidade de um módulo do oimpresso. Padrões:

- **Nome direto**: "nota do Crm", "avaliar Modules/Manufacturing", "module grade de NFSe"
- **Slash**: `/avaliar-modulo Repair`
- **Pergunta**: "qual o bucket de ADS?", "o módulo X está crítico?"
- **Agregado**: "ranking dos módulos", "média do projeto", "quais módulos estão no embrião?"
- **Citação direta**: usuário mencionar "rubrica module-grade-v3" (ou v2, v1) ou ADR 0155/0154/0153

## Como executar

### Caso 1 — módulo específico

```bash
php artisan module:grade <Nome> --detail --evolve
```

Output:
- Nota 0-100 normalizada (`score_v3_normalized`) + bucket colorido
- Raw acessível pra audit (`score_v3_raw` /118) em `--detail`
- Tabela **9 dimensões** (D1 multi-tenant, D2 Pest, D3 doc, D4 arquitetura, D5 cliente, D6 Performance, D7 LGPD, D8 Security, D9 Observability) com score/max e peso v3
- Breakdown completo: cada sub-item D1-D9 com score + evidência (`Inertia::defer` aplicado, `PiiRedactor` referenciado, `throttle` em rotas, OTel spans, etc)
- N/A justificado: sub-dims declarados `na_justified` no frontmatter SPEC são excluídos do cálculo + denominador re-normalizado (ADR 0154 backward-compat estendido pra D6-D9)
- Top 5 gaps ordenados por perda de pontos
- Batch tasks-create sugeridas (botão Evoluir CLI equivalent)

### Caso 2 — todos os módulos

```bash
php artisan module:grade --all
```

Output: tabela ranqueada por nota descendente + média projeto + distribuição buckets.

### Caso 3 — JSON pra dashboard ou CI

```bash
php artisan module:grade --all --json
```

## Como formatar resposta pro user

1. **Mostrar nota grande + bucket** logo no início (ex: "**Crm: 55/100 — Médio**")
2. **Tabela 9 dimensões D1-D9** com score/max e peso v3 (raw /118 → normalizado /100)
3. **Top 3 gaps** ordenados (perda > 5 pts) — frequentemente cai em D6 (Inertia::defer ausente) / D7 (PiiRedactor) / D8 (throttle) / D9 (OTel) porque são as novas
4. **Batch tasks suggested** SE user pediu `/avaliar-modulo` com flag implícita de querer ação
5. **Link** pra `/governance/module-grades/{Modulo}` (drill-down UI)
6. **Citação ADR** `module-grade-v3 (ADR 0155)` no rodapé. Mencionar parents (0153 v1, 0154 v2) quando user pergunta histórico

## Restrições Tier 0 IRREVOGÁVEIS

- ⛔ **NUNCA criar tasks-create automaticamente** sem aprovação humana — apenas mostrar batch markdown
- ⛔ **NUNCA editar pesos da rubrica** na conversa — rubrica é ADR canônica. Mudança = nova ADR v4 append-only com `supersedes_partially: [0155]`
- ⛔ **NUNCA inflar nota** ou justificar nota baixa com narrativa subjetiva — output é determinístico via Service
- ⛔ **NUNCA usar biz=4** em qualquer avaliação D5 (ADR 0101 — cliente ROTA LIVRE prod)
- ⛔ **NUNCA confundir raw /118 com normalizado /100** — comunicação default é /100. Raw só em `--detail` pra audit
- ⛔ **PT-BR** em toda comunicação

## Gate CI anti-regressão (ADR 0155)

Workflow [.github/workflows/module-grades-gate.yml](../../.github/workflows/module-grades-gate.yml) compara nota de cada módulo no PR vs [governance/module-grades-baseline.json](../../governance/module-grades-baseline.json). **Bloqueia merge se qualquer módulo regrediu.**

Override consciente: aplicar label `module-grades-allowed-regression` no PR (vira comentário automático justificando). Atualização do baseline é manual via PR — exige aprovação Wagner.

Skill complementar: `module-grades-gate` (rodar local antes de push + diagnosticar falha CI + atualizar baseline conscientemente).

## Antes de avaliar

Confirmar que módulo existe:

```bash
ls Modules/<Nome>/module.json
```

Se ausente → mostrar erro educativo (não inventar nota).

## Como propor melhoria via skill

Botão "Evoluir" da UI gera markdown com batch tasks. CLI equivalent: `php artisan module:grade X --evolve`. User cola no Claude Code → Claude pega o batch e chama `tasks-create` via MCP **com aprovação Wagner** (publication-policy).

## Skills relacionadas

- **`comparativo-do-modulo`** — capterra cruzamento com mercado (complementar — esta foca interno, comparativo foca mercado)
- **`module-completeness-audit`** — checklist binário de governança (gate "está pronto pra `done`?") — complementar à rubrica quantitativa
- **`module-grades-gate`** — operar o gate CI (rodar local, override, atualizar baseline)
- **`inertia-defer-default`** — força D6.a durante Edit em Controllers
- **`brief-update`** — atualiza BRIEFING.md (impacta D3.b score)

## Referências

- [ADR 0155](../../memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) — **rubrica oficial v3 (atual)** — 9 dims, gate CI anti-regressão
- [ADR 0154](../../memory/decisions/0154-module-grade-v2-na-justificado.md) — N/A justificado (parent, backward-compat estendido pra D6-D9)
- [ADR 0153](../../memory/decisions/0153-module-grade-rubrica-v1.md) — rubrica v1 5 dims (parent histórico)
- [Service](../../Modules/Governance/Services/ModuleGradeService.php) — implementação canônica (`WEIGHTS_V3` + sub-dim methods D6-D9)
- [Command](../../Modules/Governance/Console/Commands/ModuleGradeCommand.php) — CLI (`--all`/`--json`/`--detail`/`--evolve`)
- [Workflow gate CI](../../.github/workflows/module-grades-gate.yml) — anti-regressão
- [Baseline JSON](../../governance/module-grades-baseline.json) — snapshot referência (atualização via PR)
- [RUNBOOK gate CI](../../memory/requisitos/Infra/RUNBOOK-governance-gate-ci.md) — operar gate + override + baseline update
- [RUNBOOK module-grades](../../memory/requisitos/Governance/RUNBOOK-module-grades.md) — tela Inertia
- [Index page](../../resources/js/Pages/governance/ModuleGrades/Index.tsx) — UI
- [config/governance/module_clients.yaml](../../config/governance/module_clients.yaml) — D5 (Wagner edita manual)
