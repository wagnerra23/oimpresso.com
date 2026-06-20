---
name: design-deep-analysis
description: ATIVAR quando Wagner pedir /design-deep <persona-slug>, "analisar visualmente tela X pra persona Y", "design profundo da tela <Z>", OU em refator visual de tela que afeta cliente paga + reportou fricção. Skill canônica de análise contextualizada por persona — carrega persona YAML de memory/clientes/<cliente>/personas/, invoca design:* skills Anthropic em paralelo (critique + system + ux-copy + accessibility-review + research-synthesis), score 15 dimensões com ponderação por persona, entrega 3 alternativas A/B/C com diff de código preparado + métrica antes/depois. Refs ADR UI-0016, framework-15-dimensoes.md, RUNBOOK-design-deep.md.
tier: B
---

# design-deep-analysis — Análise contextualizada de tela por persona

Quando ativar (auto-trigger description matches):
- `/design-deep <persona-slug>` no input do Wagner
- "analisar tela X pra <pessoa>"
- "refator visual <tela>"
- "tela <X> tá ruim pra <persona>"
- Após `capterra-senior` identificar gap competitivo

NÃO ativar pra:
- Bug fix pontual
- Edit cosmético 1-line
- Componente novo de baixo uso
- Quando persona não existe ainda (rodar `cliente-discovery` primeiro)

## Pré-condições

1. Persona YAML existe em `memory/clientes/<cliente>/personas/<slug>.yml`
2. Wagner forneceu JOB + FRICÇÃO + SUCESSO no prompt
3. Tela tem código em `resources/js/Pages/<Mod>/<Tela>.tsx` (Inertia)

## Workflow

### 1. Load context

- Persona YAML
- Perfil cliente real (`memory/clientes/<cliente>/perfil.yml`)
- Último discovery raw (`memory/clientes/<cliente>/discovery-*.md`)
- Código da tela (.tsx + .charter.md se existir)
- Tokens canon (cowork-fields.css + cockpit.css)

### 2. Invocar design:* skills em paralelo (1 turno)

- `design:design-critique` → fricções visuais
- `design:design-system` → consistency vs Cowork canon
- `design:ux-copy` → microcopy PT-BR review
- `design:accessibility-review` → WCAG 2.5
- `design:research-synthesis` → insights persona + discovery raw

### 3. Score 15 dimensões

Ver [framework-15-dimensoes.md](../../../memory/requisitos/_DesignSystem/framework-15-dimensoes.md).

Cada dimensão score 0-100 estimado dos outputs das skills. Ponderação aplicada por persona (campo `pesos_override` do YAML; fallback pra tabela default do papel).

```
score_total_persona = Σ(score_dim × peso_dim_persona) / total_max_persona × 100
```

### 4. Output canônico em markdown

```markdown
# Análise tela <X> · persona <Y>

## Score atual: <N>/100 ponderado por <persona>

| Dim | Score | Peso | Contrib |
|---|---|---|---|
| ... | ... | ... | ... |

## Top 3 fricções pra <persona>

1. **[Dim]** — descrição concreta
2. ...

## 3 alternativas com diff preparado

### A) Refator mínimo (~N linhas, M min)
- Diff: <link OU snippet>
- Ganho: dim X de 45 → 75

### B) Refator médio (~N linhas, M h)
...

### C) Repensar arquitetura (~N linhas, M dia)
...

## Recomendação

<A | B | C> porque <razão ligada a persona + ROI>

## Smoke prod

- Métrica antes (mensurar via): <Lighthouse / browser MCP / cronometrar>
- Métrica depois (espera): <delta esperado>
```

### 5. Wagner decide → aplica

Wagner escolhe A/B/C. Claude executa via worktree + commit + push + PR. Smoke prod via Chrome MCP (screenshot pré + pós). Append aprendizado em `memory/sessions/YYYY-MM-DD-design-deep-<tela>.md`.

## Princípios duros

- **Persona obrigatória** — sem persona declarada, skill recusa e sugere `cliente-discovery` primeiro
- **ADR 0105** — só persona com cliente paga + reportou. Persona `_proposta` não vira input
- **Persona é interna** — nunca exposta ao cliente final ("Seu perfil" é creepy)
- **Score ponderado** — não usar média simples de dimensões, sempre ponderar
- **Diff preparado** — não entregar análise abstrata, sempre código pronto pra aplicar
- **Métrica mensurável** — não estética. Cliques, segundos, taxa de erro

## Relacionadas

- `cliente-discovery` (cria/atualiza persona — pré-requisito)
- `design-arte` (agent — meta-análise módulo inteiro)
- `capterra-senior` (agent — compara concorrência)
- `mwart-comparative` (skill Tier A — migração Blade→Inertia)
- Plugin Anthropic `design:*` skills (chamadas pela skill)
