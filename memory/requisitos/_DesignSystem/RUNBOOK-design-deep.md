---
title: "RUNBOOK · design-deep-analysis — análise contextualizada de tela por persona"
status: ativo
version: "1.0"
owner: "W"
related_adrs:
  - "0016-design-contextualizado-por-persona"
  - "0013-constituicao-ui-v2-camadas"
  - "0015-padrao-cowork-default-forms"
---

# RUNBOOK · design-deep-analysis

Passo-a-passo executável pra Wagner refinar tela com profundidade contextual por persona. Tempo total: 5-8 min.

## Quando usar

✅ Tela importante precisa refator visual (Wagner pediu "repensar")
✅ Cliente paga + reportou fricção concreta na tela
✅ Capterra senior detectou gap competitivo (Bling/Tiny faz melhor)
✅ Métrica detecta drift (tempo de tarefa subindo)

❌ Bug fix pontual / Edit cosmético 1-line / Componente baixo uso

## Pré-condições

- Persona alvo existe em `memory/clientes/<cliente>/personas/<nome>.yml`
- Se não existir: rodar `cliente-discovery` skill PRIMEIRO (entrevista cliente real)

## Passo-a-passo

### 1. Wagner solicita

Pattern canon do prompt:

```
/design-deep <persona-slug>

[print colado da tela aqui]

JOB: <verbo + objeto — o que persona PRECISA fazer aqui>
FRICÇÃO: <1-2 frases concretas sobre o que sofre hoje>
SUCESSO: <comportamento mensurável — ex "cadastra cliente em ≤30s">

RESTRIÇÕES OPCIONAIS: <tokens, dark mode, mobile, multi-tenant, etc>
```

Exemplo:
```
/design-deep daniela-martinho

[print Edit OS]

JOB: registrar entrada de caminhão Martinho com placa, km, fotos do dano
FRICÇÃO: precisa clicar 4 tabs pra chegar nas fotos
SUCESSO: cria OS completa em ≤45s sem trocar de tab
```

### 2. Claude carrega contexto automaticamente

- `memory/clientes/martinho-cacambas/perfil.yml` → empresa
- `memory/clientes/martinho-cacambas/personas/daniela.yml` → persona
- `memory/clientes/martinho-cacambas/discovery-*.md` → últimas entrevistas
- Arquivo `.tsx` da tela → código atual
- Tokens/design system canon (Cowork ADR UI-0015)

### 3. Análise paralela via skills design:* (Claude Design plugin Anthropic)

Em 1 turno Claude invoca:
- `design:design-critique` → fricções visuais hoje
- `design:design-system` → consistency vs Cowork canon
- `design:ux-copy` → review microcopy PT-BR
- `design:accessibility-review` → WCAG 2.5 audit
- `design:research-synthesis` → insights da persona + discovery raw

### 4. Score 15 dimensões + ponderação

Cada uma das 15 dimensões ([framework-15-dimensoes.md](framework-15-dimensoes.md)) recebe score 0-100.

Score ponderado total pela persona específica:
```
score_total = Σ (score_dim × peso_dim_persona) / total_max_persona × 100
```

### 5. Output canônico (markdown estruturado)

```markdown
# Análise tela <X> · persona <Y>

## Score atual: <N>/100 ponderado por <persona>

| Dimensão | Score | Peso persona | Contribuição |
|---|---|---|---|
| Speed-to-task | 45 | 3 | 135 |
| Cognitive load | 70 | 3 | 210 |
| ...

## Top 3 fricções pra <persona>

1. **[Dimensão N]** — <fricção concreta com exemplo da tela>
2. ...

## 3 alternativas

### A) Refator mínimo (~30 linhas, 30 min)
- Mudança X em arquivo Y
- Ganho estimado: dimensão N de 45 → 75

### B) Refator médio (~150 linhas, 2h)
- Mudança ABC
- Ganho: dimensão N de 45 → 85

### C) Repensar arquitetura (~500 linhas, 1 dia)
- Quebra em 2 telas, defaults inteligentes
- Ganho: dimensão N de 45 → 95

## Recomendação

<A | B | C> porque <razão concreta ligada a persona + ROI>

## Métrica antes/depois

- Antes: <N cliques pra completar JOB>
- Depois: <N-X cliques>
- Mensurar via: <Lighthouse / browser MCP / smoke prod>
```

### 6. Wagner decide

Wagner escolhe A/B/C. Claude aplica diff, abre PR, força admin merge se aprovado.

### 7. Smoke prod + gravar aprendizado

- Browser MCP screenshot pré + pós (`memory/sessions/YYYY-MM-DD-design-deep-<tela>.md`)
- Se persona reportou após uso real → append em `memory/clientes/<cliente>/discovery-YYYY-MM-DD.md`

## Anti-patterns

❌ Análise sem persona → "score" genérico sem ponderação = improviso disfarçado
❌ Persona hipotética sem cliente real → ficção. Use [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — só cliente paga + reportou
❌ "Refator visual" sem JOB declarado → mexe sem objetivo
❌ Mostrar persona ou score pro cliente final → persona é interna ao design system

## Skills relacionadas

- `cliente-discovery` (entrevista cliente → cria/atualiza persona YAML)
- `design-arte` (agent — meta-análise módulo inteiro vs estado-da-arte)
- `capterra-senior` (agent — compara com Bling/Tiny/Omie)
- `mwart-comparative` (skill Tier A — migração Blade→Inertia)
