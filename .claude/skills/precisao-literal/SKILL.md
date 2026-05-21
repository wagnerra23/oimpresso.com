---
name: precisao-literal
description: ATIVAR quando user pedir "compare com o protótipo", "avalie precisão", "que % literal", "ficou idêntico?", "compare lado a lado", "nota da paridade", "/precisao-literal <componente>", OU quando já existe `prototipo-ui/prototipos/<X>/` mergeado e Wagner quer saber distância visual/funcional do código atual ao protótipo. Diferente de `mwart-comparative` (PRÉ-implementação, gera draft 15 dim pra aprovar SCREENSHOT) — `precisao-literal` é PÓS-implementação rigorosa: lê código do protótipo + atual em paralelo, classifica peça-a-peça em 5 níveis (EXATO/ALTO/MÉDIO/BAIXO/AUSENTE) com evidência file:line literal, calcula nota ponderada, lista gaps priorizados por esforço×impacto, propõe Wave de fixes opcional. Anti-pattern: NÃO usar pra avaliar gap PROTÓTIPO vs CONCORRENTES (use `capterra-senior`) nem MÓDULO vs ESTADO-DA-ARTE (use `comparativo`). Use SÓ pra atual-em-prod vs protótipo-aprovado.
tier: B
status: active
version: 1.0
authority: canonical
related_adrs: [0107, 0114, 0149, 0179]
parent_session: 2026-05-21-cliente-drawer-760-wave-a-g
---

# Skill: precisao-literal — Análise de paridade pós-implementação (Tier B)

> **Skills irmãs:**
> - [`mwart-comparative`](../mwart-comparative/SKILL.md) — PRÉ-implementação (15 dim + gate F1.5 + screenshot Wagner aprova)
> - [`comparativo-do-modulo`](../comparativo-do-modulo/SKILL.md) — módulo vs estado-da-arte concorrentes
> - [`avaliar-modulo`](../avaliar-modulo/SKILL.md) — Module Grade v3 nota agregada
> - [`design-arte`](../design-arte/SKILL.md) — design estado-da-arte 15 dim
>
> **ADRs relacionadas:**
> - [ADR 0114 Cowork loop formalizado](../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — origem do protótipo
> - [ADR 0107 visual gate F1.5](../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
> - [ADR 0149 pattern reuse blueprint Cowork](../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)

## Por que esta skill existe

Sessão **2026-05-21 Cliente drawer 760** (PRs #1339→#1355) entregou Wave A-G+Z em ~3h elapsed (~35h IA-pair). Wagner pediu 4 vezes nota de paridade ao protótipo Cowork em momentos diferentes da entrega:

| Momento | Pergunta Wagner | Método aplicado | Nota |
|---|---|---|---|
| Antes (13:20) | "compare e dê nota por peça" | Inferência screenshots + tabela 21 peças subjetiva | 28/100 |
| Pós-merge (16:50) | "olhe como ficou e compare" | Re-cálculo mesmo método | 88/100 |
| Após (17:00) | "avalie nível de precisão ao protótipo" | **Lê protótipo + meu código em paralelo, classifica literal** | 83% |
| Pós-Z-2.1 (17:30) | (verificação) | Re-classificação após 7 fixes | 95% |

**Insight:** as 2 primeiras notas (28, 88) usaram inferência subjetiva — útil pra calibrar direção mas frágil. A 3ª e 4ª (83, 95) usaram **comparação literal código fonte** com classificação em 5 níveis e evidência file:line — robusta, defensável, reprodutível. Diferença: −12 pontos entre subjetivo otimista (88) e literal rigoroso (83).

Esta skill formaliza o método rigoroso pra reuso em qualquer migração MWART pós-implementação.

## Quando ATIVAR (Tier B auto-trigger)

- User pergunta nota de paridade pós-merge: "compare com o protótipo", "ficou idêntico?", "que % literal", "avalie precisão", "tá perto do protótipo?"
- Slash: `/precisao-literal <componente>`
- Wave de implementação MWART acabou de mergear e existe `prototipo-ui/prototipos/<X>/` aprovado por Wagner

## Quando NÃO usar

- ❌ **Pré-implementação** — use `mwart-comparative` (gate F1.5)
- ❌ **Módulo vs concorrentes mercado** — use `comparativo-do-modulo` ou `capterra-senior`
- ❌ **Nota agregada Module Grade** — use `avaliar-modulo`
- ❌ **Quando NÃO existe protótipo aprovado** — não há referência literal; use `design-critique` Anthropic skill

## O método em 6 fases

### Fase 1 — Inventário paralelo (~5min)

Mapeia EM PARALELO os arquivos canônicos do protótipo e do código atual:

**Protótipo (referência canônica):**
```
prototipo-ui/prototipos/<X>/
  ├── *.html           (entry visual)
  ├── *-data.jsx       (mock dados schema)
  ├── *-icons.jsx      (helpers: masks BR, validators, avatarFor, relDate)
  ├── *-listagem.jsx   (componentes lista: Pills, FilterDropdown, ActiveChip)
  ├── *-drawer.jsx     (componentes drawer: header, tabs, form sections)
  ├── *-tabs.jsx       (tabs especiais: IA, Auditoria, OSs)
  └── HANDOFF_*.md     (spec completa schema BR + endpoints)
```

**Atual (mergeado em prod):**
```
resources/js/Pages/<Mod>/<Tela>.tsx          (Page Inertia)
resources/js/Pages/<Mod>/_drawer/*.tsx       (sub-componentes drawer)
resources/js/Components/<mod>/*.tsx           (componentes compartilhados)
Modules/<Mod>/Http/Controllers/*.php          (endpoints novos)
Modules/<Mod>/Services/*.php                  (services novos)
database/migrations/2026_*.php                (migrations aditivas)
tests/Feature/<Mod>/*Test.php                 (Pest GUARDs)
```

**Output da fase:** lista pareada `protótipo:linha ↔ atual:linha` por dimensão.

### Fase 2 — Categorização em 14-21 peças

Quebra o domínio em peças mensuráveis. Dimensões canônicas oimpresso:

1. **Listagem — estrutura geral** (header + busca + tabela)
2. **Avatar** (hash determinístico, cores, forma, tamanho)
3. **Pills coloridas** (TipoPill, TagChip, FrescorPill, SaldoCell, StatusPill)
4. **FrescorPill thresholds** (dias × labels × cores)
5. **Filtros** (dropdowns × busca × atalhos)
6. **Tabela colunas** (mapping protótipo vs atual)
7. **Star pessoal** (localStorage)
8. **Atalhos teclado** (KB-9.75 + tab switching 1-N)
9. **Drawer estrutura** (width, side, padding, footer)
10. **Tabs do drawer** (8 tabs cadastrais vs operacional)
11. **Form fields BR** (máscaras CPF/CNPJ/CEP/tel + mod 11)
12. **Lookups externos** (ViaCEP + BrasilAPI proxy)
13. **Tabs IA cards** (Resumo + Segmento + Próxima + Score)
14. **Tabs Auditoria LGPD** (timeline + CSV export + PII mask)
15. **Header drawer rico** (avatar + nome + subtitle + toggle + 2 botões)
16. **Microcopy literal** (labels, descriptions, placeholders, errors)
17. **Estados** (loading, error, empty, success)
18. **Performance** (defer, partial reload, cache hit)
19. **Acessibilidade** (aria-* + keyboard nav)
20. **Brand voice** (PT-BR + identidade)
21. **Multi-tenant + LGPD** (business_id + PII mask)

Use 14-21 peças dependendo da complexidade. Cada peça vira 1 linha na tabela.

### Fase 3 — Classificação literal em 5 níveis

**Cada peça** recebe 1 dos 5 níveis com **evidência file:line**:

| Nível | Critério | Peso |
|---|---|---|
| **EXATO** (100%) | Algoritmo + microcopy + visual idênticos. Evidência: 2-3 linhas código pareado. | 1.00 |
| **ALTO** (85%) | Mesma intenção + função. Diferença cosmética ≤ 20% (palavra trocada, cor próxima, ícone diferente). | 0.85 |
| **MÉDIO** (65%) | Feature entrega mas estética divergente OU funcionalidade parcial. Ex: gradients oklch protótipo → HSL chapado meu. | 0.65 |
| **BAIXO** (40%) | Divergência funcional/visual ALTA. Ex: labels trocados, thresholds errados, dimensões erradas. | 0.40 |
| **AUSENTE** (0%) | Feature do protótipo não implementada. | 0.00 |
| **EXTRA** (n/a) | Implementado MAS não existe no protótipo (não conta na nota mas registra). | — |

**Regra de evidência:** **TODA classificação BAIXO/AUSENTE PRECISA file:line** do protótipo OU do código atual (preferencialmente os dois). Sem evidência, é estimativa, não literal.

### Fase 4 — Catalogação gaps priorizados por esforço×impacto

Pra cada peça **BAIXO ou AUSENTE**, gerar entrada do tipo:

```markdown
### Gap N — <peça> [<nível>]

- **Protótipo:** <evidência file:line>
- **Atual:** <evidência file:line>
- **Divergência:** <1 frase exata>
- **Fix proposto:** <Edit em arquivo:linha — operação concreta>
- **Estimate:** ~Xmin IA-pair (fator 10x ADR 0106)
- **Impacto visual:** ALTO/MÉDIO/BAIXO
- **Wave:** Z-N.M (se Wagner quiser fix opcional)
```

### Fase 5 — Nota ponderada + apresentação Wagner

**Fórmula nota:**

```
nota = Σ (peso_nível × contagem_peças) / total_peças × 100
```

**Apresentação:** tabela TXT-art com distribuição:

```
EXATO       (100%):   N itens  █████████████████████████░░░░░░░░░░░░░░░  ~XX%
ALTO        ( 85%):   N itens  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
MÉDIO       ( 65%):   N itens  █████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
BAIXO       ( 40%):   N itens  ███░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
AUSENTE     (  0%):   N itens  █░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%

Precisão média ponderada: ~XX% literal ao protótipo
```

**Decisão Wagner:**
1. **Aceitar como está** (X% é suficiente pro objetivo)
2. **Fix N gaps específicos** (Wagner escolhe quais BAIXO/AUSENTE valem o esforço)
3. **Fix all** (cria Wave Z-N.M de precisão)

### Fase 6 — Re-avaliação pós-fix (se aplicável)

Após Wave de fixes mergeada, re-classifica somente as peças tocadas e atualiza nota. Append em `memory/sessions/YYYY-MM-DD-precisao-<componente>.md` (timeline append-only).

## Output canônico

Gera arquivo `memory/sessions/YYYY-MM-DD-precisao-<componente>.md` com:

```markdown
---
slug: YYYY-MM-DD-precisao-<componente>
title: "Precisão literal — <componente> vs protótipo Cowork"
type: precisao-literal
date: YYYY-MM-DD
related_prs: [N, N+1, N+2]
prototype: prototipo-ui/prototipos/<X>/
final_score_percent: NN
status: snapshot
---

# Comparação literal — <componente>

## Fase 1 — Inventário (~5min)
<tabela arquivos protótipo ↔ atual>

## Fase 2-3 — Tabela 14-21 peças
| Peça | Protótipo | Atual | Precisão |
| ... |

## Fase 4 — Gaps catalogados
<lista BAIXO + AUSENTE com fixes propostos>

## Fase 5 — Nota agregada
<TXT-art + fórmula>

## Fase 6 — Re-avaliação (se houver)
<timeline >
```

## Worked example — Cliente drawer 760 (2026-05-21)

| Momento | Método | Nota |
|---|---|---|
| 13:20 (pré-Wave A) | Inferência screenshots subjetiva | 28/100 |
| 16:50 (pós-Wave G merge) | Re-cálculo mesmo método subjetivo | 88/100 |
| **17:00** | **`precisao-literal` Fases 1-5 rigorosas** | **83% literal** |
| 17:30 (pós-Wave Z-2.1) | Re-classificação após 7 fixes BAIXO+AUSENTE | **95% literal** |

**Diferença subjetivo vs literal:** −12 pontos (88 otimista → 83 rigoroso). Mostra valor da skill: detectou que parecia mais idêntico do que era. Sem skill, Wagner ia pra prod com 83% achando que era 88%; com skill, escolheu fechar pra 95% antes do canary.

**Output da sessão:** `memory/sessions/2026-05-21-precisao-cliente-drawer-760.md` (não criado nesta sessão — exemplo retroativo).

## Regras Tier 0 (preservar)

- **Multi-tenant** (ADR 0093) — comparação NÃO testa segurança; rodar `multi-tenant-patterns` separado
- **PII** — comparação NÃO loga CPF/CNPJ plain mesmo do protótipo
- **Append-only** — re-classificação após fixes vira ENTRADA NOVA, não edita antiga
- **Evidência file:line obrigatória** pra BAIXO/AUSENTE (sem evidência = ALTO ou MÉDIO no máximo)

## Anti-patterns (não fazer)

- ❌ Avaliar por **screenshot** subjetivo sem ler código
- ❌ Estimar **% sem categorização em níveis** (vira opinião sem base)
- ❌ Comparar **partes incomparáveis** (ex: protótipo HTML SPA vs Inertia + multi-tenant — meu tem features que protótipo nunca terá)
- ❌ Marcar **EXATO** sem 2 linhas pareadas
- ❌ Não distinguir **EXTRA** de **EXATO** (extra é além-do-protótipo, não conta na nota)
- ❌ Calcular nota **antes da Fase 4** (gaps catalogados aumentam fidelidade da nota)

## Refs

- Sessão worked example: [memory/handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md](../../../memory/handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md)
- Skill irmã `mwart-comparative` V4 (PRÉ-impl): [.claude/skills/mwart-comparative/SKILL.md](../mwart-comparative/SKILL.md)
- Skill irmã `comparativo-do-modulo` (vs estado-da-arte): [.claude/skills/comparativo-do-modulo/SKILL.md](../comparativo-do-modulo/SKILL.md)
- ADR 0114 Cowork loop formalizado: [memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md](../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- ADR 0107 visual gate F1.5: [memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md](../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- ADR 0149 pattern reuse Cowork: [memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md](../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)
