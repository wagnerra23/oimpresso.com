---
name: avaliar-modulo
mission: "Substituir avaliação subjetiva de maturidade de Modules/<X>/ por nota objetiva 0-100 ponderada pela rubrica oficial module-grade-v1."
description: ATIVAR quando user pedir "nota do módulo X", "avaliar Modules/X", "/avaliar-modulo X", "qual a nota de Y", "module grade de Z", "qual o bucket de W", "qual modulo está crítico?", "média do projeto", "ranking dos módulos", OU mencionar rubrica `module-grade-v1` ou ADR 0153. Roda `php artisan module:grade {nome} --detail --evolve` (ou `--all` quando agregado) e formata output. Mostra nota total + 5 dimensões + top gaps + batch tasks sugeridas. NÃO cria tasks sem aprovação humana — apenas formata batch markdown pra Wagner aprovar/editar/colaba via `tasks-create` MCP.
type: process-skill
status: active
version: 1.0.0
trust_level: L1
owner: wagner
created_at: 2026-05-16
updated_at: 2026-05-16
charter_adr: 0153
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ 10M em 24 meses."
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
  - "rubrica module-grade-v1"
related_adrs: [0153, 0093, 0101, 0094, 0105]
related_skills: [comparativo-do-modulo, module-completeness-audit, brief-update]
tier: B
---

# avaliar-modulo — rubrica `module-grade-v1` (ADR 0153)

## Quando ativar

ATIVAR quando user pedir avaliação objetiva de maturidade de um módulo do oimpresso. Padrões:

- **Nome direto**: "nota do Crm", "avaliar Modules/Manufacturing", "module grade de NFSe"
- **Slash**: `/avaliar-modulo Repair`
- **Pergunta**: "qual o bucket de ADS?", "o módulo X está crítico?"
- **Agregado**: "ranking dos módulos", "média do projeto", "quais módulos estão no embrião?"
- **Citação direta**: usuário mencionar "rubrica module-grade-v1" ou "ADR 0153"

## Como executar

### Caso 1 — módulo específico

```bash
php artisan module:grade <Nome> --detail --evolve
```

Output:
- Nota 0-100 + bucket colorido
- Tabela 5 dimensões (D1-D5) score/max
- Breakdown completo: cada sub-item com score + evidência
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

1. **Mostrar nota grande + bucket** logo no início (ex: "**Crm: 28/100 — Crítico**")
2. **Tabela 5 dimensões** com score/max e peso
3. **Top 3 gaps** ordenados (perda > 5 pts)
4. **Batch tasks suggested** SE user pediu `/avaliar-modulo` com flag implícita de querer ação
5. **Link** pra `/governance/module-grades/{Modulo}` (drill-down UI)
6. **Citação ADR** `module-grade-v1 (ADR 0153)` no rodapé

## Restrições Tier 0 IRREVOGÁVEIS

- ⛔ **NUNCA criar tasks-create automaticamente** sem aprovação humana — apenas mostrar batch markdown
- ⛔ **NUNCA editar pesos da rubrica** na conversa — rubrica é ADR canônica. Mudança = ADR 0154 v2 append-only
- ⛔ **NUNCA inflar nota** ou justificar nota baixa com narrativa subjetiva — output é determinístico via Service
- ⛔ **NUNCA usar biz=4** em qualquer avaliação D5 (ADR 0101 — cliente ROTA LIVRE prod)
- ⛔ **PT-BR** em toda comunicação

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
- **`brief-update`** — atualiza BRIEFING.md (impacta D3.b score)

## Referências

- [ADR 0153](../../memory/decisions/0153-module-grade-rubrica-v1.md) — rubrica oficial
- [Service](../../Modules/Governance/Services/ModuleGradeService.php) — implementação canônica
- [Command](../../Modules/Governance/Console/Commands/ModuleGradeCommand.php) — CLI
- [RUNBOOK](../../memory/requisitos/Governance/RUNBOOK-module-grades.md) — tela Inertia
- [Index page](../../resources/js/Pages/governance/ModuleGrades/Index.tsx) — UI
- [config/governance/module_clients.yaml](../../config/governance/module_clients.yaml) — D5 (Wagner edita manual)
