---
slug: metodo-precisao-literal
title: "Método precisão literal — comparação rigorosa pós-implementação vs protótipo"
type: reference
status: canon
authority: canonical
related_skills: [precisao-literal, mwart-comparative, comparativo-do-modulo]
related_adrs: [0107, 0114, 0149, 0179]
parent_session: 2026-05-21-cliente-drawer-760-wave-a-g
last_updated: 2026-05-21
---

# Método precisão literal — referência rápida + worked example

> Skill canônica: [`.claude/skills/precisao-literal/SKILL.md`](../../.claude/skills/precisao-literal/SKILL.md)
> Origem: sessão 2026-05-21 Cliente drawer 760 (PRs #1339→#1355). Wagner pediu nota de paridade 4× em momentos diferentes; método cristalizou na 3ª e 4ª chamada.

## TL;DR (1 minuto)

**Problema:** "Quanto % paridade com o protótipo Cowork?" — pergunta comum pós-merge MWART.

**Solução:** 6 fases rigorosas que classificam cada peça em 5 níveis (EXATO/ALTO/MÉDIO/BAIXO/AUSENTE) com **evidência file:line obrigatória** pra BAIXO+AUSENTE.

**Output:** nota ponderada defensável + lista de gaps priorizados com fixes propostos.

**Diferenciais vs métodos subjetivos:**
- ✅ Reprodutível (mesmo input → mesma nota)
- ✅ Defensável (cada item tem código pareado como evidência)
- ✅ Acionável (gaps viram Wave de fixes opcional)
- ✅ Append-only (re-avaliação pós-fix é entrada nova, não edita)

## Quando usar este método

| Cenário | Método aplicável | Resultado |
|---|---|---|
| Antes de codar tela MWART | `mwart-comparative` (PRÉ-impl) | Draft 15 dim + gate F1.5 + screenshot Wagner |
| Tela MWART acabou de mergear | **`precisao-literal`** (PÓS-impl) | Nota literal + gaps priorizados |
| Avaliar módulo vs concorrentes | `comparativo-do-modulo` | Inventário 3 buckets ✅/🟡/❌ |
| Nota agregada Module Grade | `avaliar-modulo` | Module Grade v3 9 dim |
| Design crítica pura (sem código) | Anthropic `design:design-critique` | 5 categorias UX |

## As 6 fases

### Fase 1 — Inventário paralelo (5min)

Mapeia EM PARALELO os arquivos canônicos do protótipo e código atual. Tabela pareada `protótipo:linha ↔ atual:linha`.

**Comando útil:**
```bash
# Inventário arquivos protótipo
ls prototipo-ui/prototipos/<X>/

# Inventário arquivos atuais
ls resources/js/Pages/<Mod>/ \
   resources/js/Pages/<Mod>/_drawer/ \
   resources/js/Components/<mod>/ \
   Modules/<Mod>/Http/Controllers/ \
   Modules/<Mod>/Services/ 2>/dev/null

# LOC comparativo
wc -l prototipo-ui/prototipos/<X>/*.jsx \
      resources/js/Pages/<Mod>/*.tsx \
      resources/js/Pages/<Mod>/_drawer/*.tsx
```

### Fase 2 — Categorização em 14-21 peças

Quebra o domínio em peças mensuráveis. **Dimensões canônicas oimpresso** (use 14-21 dependendo da complexidade):

| # | Peça | Quando aplica |
|---|---|---|
| 1 | Listagem — estrutura geral | sempre |
| 2 | Avatar (hash + cores + forma) | sempre |
| 3 | Pills coloridas (TipoPill, TagChip, StatusPill, SaldoCell) | sempre |
| 4 | FrescorPill (thresholds × labels × cores) | se aplicável |
| 5 | Filtros (dropdowns × busca × atalhos) | sempre |
| 6 | Tabela colunas (mapping protótipo vs atual) | sempre |
| 7 | Star pessoal localStorage | se aplicável |
| 8 | Atalhos teclado (KB-9.75 + tab switching) | sempre |
| 9 | Drawer estrutura (width, side, padding, footer) | se drawer |
| 10 | Tabs do drawer (cadastrais vs operacional) | se drawer |
| 11 | Form fields BR (máscaras + mod 11) | se cadastro |
| 12 | Lookups externos (ViaCEP/BrasilAPI proxy) | se cadastro |
| 13 | Tabs IA cards | se IA |
| 14 | Tabs Auditoria LGPD timeline | se auditoria |
| 15 | Header drawer rico (toggle + 2 botões) | se drawer |
| 16 | Microcopy literal (labels + descriptions + errors) | sempre |
| 17 | Estados (loading, error, empty, success) | sempre |
| 18 | Performance (defer + partial reload + cache) | sempre |
| 19 | Acessibilidade (aria-* + keyboard) | sempre |
| 20 | Brand voice (PT-BR + identidade) | sempre |
| 21 | Multi-tenant + LGPD (business_id + PII) | sempre |

### Fase 3 — Classificação em 5 níveis

**Critério canônico:**

| Nível | Critério rigoroso | Peso |
|---|---|---|
| **EXATO** (100%) | Algoritmo + microcopy + visual IDÊNTICOS. 2-3 linhas código pareado obrigatório. | 1.00 |
| **ALTO** (85%) | Mesma intenção. Diferença cosmética ≤ 20% (palavra, cor próxima, ícone). | 0.85 |
| **MÉDIO** (65%) | Feature entrega mas estética divergente OU parcialmente implementada. | 0.65 |
| **BAIXO** (40%) | Divergência funcional/visual ALTA (labels trocados, thresholds errados, dimensões erradas). | 0.40 |
| **AUSENTE** (0%) | Feature do protótipo NÃO implementada. | 0.00 |
| **EXTRA** (n/a) | Implementado mas não existe no protótipo (registra mas não conta nota). | — |

**REGRA DE EVIDÊNCIA (Tier 0 do método):**
- **BAIXO/AUSENTE** PRECISAM file:line do protótipo OU código atual
- Sem evidência → upgrade pra **ALTO** ou **MÉDIO** (estimativa, não literal)
- **EXATO** PRECISA 2 linhas pareadas (protótipo + atual)

### Fase 4 — Catalogação gaps priorizados

Pra cada **BAIXO ou AUSENTE**, entrada padrão:

```markdown
### Gap N — <peça> [<nível>]

- **Protótipo:** `prototipo-ui/prototipos/<X>/<arq>.jsx:linha` — quote curta
- **Atual:** `resources/js/.../arq.tsx:linha` — quote curta
- **Divergência:** <1 frase exata do que difere>
- **Fix proposto:** `Edit em <arq>:linha` <operação concreta>
- **Estimate:** ~Xmin IA-pair (fator 10x ADR 0106)
- **Impacto visual:** ALTO/MÉDIO/BAIXO
- **Wave fix (opcional):** Z-N.M
```

### Fase 5 — Nota ponderada + apresentação

**Fórmula:**
```
nota = (peso_EXATO × #EXATO + peso_ALTO × #ALTO + peso_MEDIO × #MEDIO
        + peso_BAIXO × #BAIXO + peso_AUSENTE × #AUSENTE)
       / (#EXATO + #ALTO + #MEDIO + #BAIXO + #AUSENTE)
       × 100
```

(EXTRA não entra na fórmula.)

**TXT-art apresentação:**
```
EXATO       (100%):   N itens  █████████████████████████░░░░░░░░░░░░░░░  ~XX%
ALTO        ( 85%):   N itens  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
MÉDIO       ( 65%):   N itens  █████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
BAIXO       ( 40%):   N itens  ███░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
AUSENTE     (  0%):   N itens  █░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%

Precisão média ponderada: ~XX% literal ao protótipo
```

**Decisão Wagner (3 opções):**
1. **Aceitar como está** — X% é suficiente; smoke prod com nota atual
2. **Fix N gaps específicos** — Wagner escolhe quais BAIXO/AUSENTE valem
3. **Fix all** — Wave Z-N.M completa de precisão

### Fase 6 — Re-avaliação pós-fix (se houver Wave de precisão)

Após Wave de fixes mergeada, re-classifica somente peças tocadas + atualiza nota.

**Append-only** em `memory/sessions/YYYY-MM-DD-precisao-<componente>.md`. NÃO edita classificação antiga (Tier 0 ADR 0130 append-only).

## Worked example — Cliente drawer 760 (2026-05-21)

### Linha do tempo das 4 perguntas Wagner

| Momento | Pergunta | Método | Nota | Diferença |
|---|---|---|---|---|
| **13:20** (pré-Wave A) | "compare e dê nota por peça" | Inferência screenshots + 21 peças subjetiva | 28/100 | baseline |
| **16:50** (pós-Wave G merge) | "olhe como ficou e compare" | Mesmo método subjetivo aplicado em código novo | 88/100 | +60 pontos |
| **17:00** | "avalie nível de precisão ao protótipo" | **`precisao-literal` 6 fases rigorosas** | **83% literal** | **−5 pontos vs subjetivo otimista** |
| **17:30** (pós-Wave Z-2.1) | (re-validação após 7 fixes) | Re-classificação peças tocadas | **95% literal** | +12 pontos |

### Insight chave da sessão

**Diferença subjetivo vs literal: −12 pontos** (88 otimista → 83 rigoroso). Mostra valor do método:

- Sem skill: Wagner iria pra prod biz=1 com 83% achando que era 88% (otimismo)
- Com skill: Wagner viu 83% real, escolheu Wave Z-2.1 pra fechar pra 95% antes do canary

### Tabela final 28 peças (compacta — output Fase 5)

```
EXATO       (100%):   29 itens  ████████████████████████████████████████  ~60%
ALTO        ( 85%):   14 itens  ██████████████░░░░░░░░░░░░░░░░░░░░░░░░░░  ~28%
MÉDIO       ( 65%):    4 itens  ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~8%
BAIXO       ( 40%):    0 itens  ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  0%
AUSENTE     (  0%):    0 itens  ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  0%

Precisão média ponderada: ~95% literal ao protótipo Cowork 9,4/10
```

### 7 gaps fechados na Wave Z-2.1 (exemplo concreto Fase 4)

| Gap | Antes Z-2.1 | Fix | Após Z-2.1 |
|---|---|---|---|
| FrescorPill thresholds | BAIXO (labels invertidos) | Edit Pills.tsx:120-141 | EXATO |
| Avatar drawer header | BAIXO (56px square HSL) | Edit Avatar.tsx + Index.tsx:1266 | EXATO |
| ActiveChip removível | AUSENTE | NEW Components/clientes/ActiveChip.tsx | EXATO |
| Atalho 1-8 tab | AUSENTE | Edit Index.tsx useEffect | EXATO |
| Score Card 4 título | MÉDIO ("risco") | Edit IATab.tsx:287-295 | EXATO ("relacionamento") |
| Card 2 label `+`→`&` | ALTO | Edit IATab.tsx replace_all | EXATO |
| Subtitle "cadastrado há —" | BAIXO (fallback) | Edit ContactController.php + Index.tsx | EXATO |

## Como Claude deve aplicar o método

### Trigger automático (Tier B description)

User diz: "compare com o protótipo", "ficou idêntico?", "avalie precisão", "que % literal", "tá perto do protótipo?", "/precisao-literal <componente>".

### Workflow Claude

1. **Confirma escopo** com Wagner (qual componente — listagem? drawer? módulo inteiro?)
2. **Fase 1:** lê em paralelo `prototipo-ui/prototipos/<X>/*.{jsx,html}` + `resources/js/Pages/<Mod>/**/*.tsx` + backend novo
3. **Fase 2:** decide quantas peças (14-21 dependendo do escopo)
4. **Fase 3:** classifica cada peça em 5 níveis com evidência file:line
5. **Fase 4:** cataloga BAIXO+AUSENTE com fixes propostos + estimates
6. **Fase 5:** TXT-art + tabela + nota ponderada + apresenta decisão Wagner (3 opções)
7. **Aguarda Wagner aprovar** opção
8. **Se opção 2 ou 3:** spawn agent OU faz fixes direto (depende do tamanho)
9. **Fase 6:** re-avalia após fixes mergeados

### Anti-patterns (NÃO fazer)

- ❌ Pular Fase 1 (sem inventário, classificação é chute)
- ❌ Calcular nota antes da Fase 4 (gaps catalogados aumentam fidelidade)
- ❌ Marcar EXATO sem 2 linhas pareadas
- ❌ Confundir EXTRA (além do protótipo) com EXATO (idêntico)
- ❌ Não distinguir BAIXO de AUSENTE (BAIXO existe mas errado; AUSENTE não existe)
- ❌ Comparar partes incomparáveis (SPA puro Cowork vs Inertia + multi-tenant — meu tem features que protótipo nunca terá)

## Templates prontos

### Template tabela 14-21 peças (Fase 2-3)

```markdown
| # | Peça | Protótipo (file:line) | Atual (file:line) | Precisão |
|---|---|---|---|---|
| 1 | Avatar HSL hash | `clientes-icons.jsx:141` `(h*31+charCode)>>>0` | `Avatar.tsx:22` `(h*31+charCode)\|0` | **EXATO** |
| 2 | Avatar visual | `clientes-icons.jsx:138` `linear-gradient oklch` | `Avatar.tsx:32` `hsl(...) 88%` | **MÉDIO** |
| ... | ... | ... | ... | ... |
```

### Template Gap entry (Fase 4)

```markdown
### Gap 3 — FrescorPill thresholds [BAIXO]

- **Protótipo:** `prototipo-ui/prototipos/clientes/clientes-975.jsx:33-39`
  ```jsx
  if (days < 30)  { kind = 'fresh';   label = 'fresco'; }
  if (days < 90)  { kind = 'recent';  label = 'recente'; }
  if (days < 180) { kind = 'cold';    label = 'frio'; }
  else            { kind = 'lost';    label = 'distante'; }
  ```
- **Atual:** `resources/js/Components/clientes/Pills.tsx:118-122`
  ```typescript
  if (days <= 14) return 'fresc';      // ✗ threshold 14 vs 30
  if (days <= 60) return 'recente';    // ✗ threshold 60 vs 90
  if (days <= 180) return 'distante';  // ✗ label trocado
  return 'frio';                       // ✗ label trocado
  ```
- **Divergência:** thresholds 14/60 vs 30/90 + labels `distante` e `frio` invertidos
- **Fix proposto:** `Edit Pills.tsx:114-122` substituir condicionais + ajustar FRESCOR_STYLE
- **Estimate:** ~10min IA-pair
- **Impacto visual:** MÉDIO (cliente Larissa vê pílula errada se compra entre 30-90d)
- **Wave fix:** Z-2.1
```

### Template TXT-art final (Fase 5)

```
EXATO       (100%):   N itens  ████████████████████████████████░░░░░░░░  ~XX%
ALTO        ( 85%):   N itens  █████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
MÉDIO       ( 65%):   N itens  █████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
BAIXO       ( 40%):   N itens  ███░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%
AUSENTE     (  0%):   N itens  █░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  ~XX%

Precisão média ponderada: ~XX% literal ao protótipo
```

## Refs

- Skill canônica: [`.claude/skills/precisao-literal/SKILL.md`](../../.claude/skills/precisao-literal/SKILL.md)
- Handoff sessão worked example: [memory/handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md](../handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md)
- ADR 0114 Cowork loop formalizado: [memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- ADR 0107 visual gate F1.5: [memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- ADR 0149 pattern reuse Cowork: [memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md](../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- ADR 0179 Cliente drawer 760 (worked example): [memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- Skill irmã `mwart-comparative` V4 (PRÉ-impl): [.claude/skills/mwart-comparative/SKILL.md](../../.claude/skills/mwart-comparative/SKILL.md)
- Skill irmã `comparativo-do-modulo`: [.claude/skills/comparativo-do-modulo/SKILL.md](../../.claude/skills/comparativo-do-modulo/SKILL.md)
