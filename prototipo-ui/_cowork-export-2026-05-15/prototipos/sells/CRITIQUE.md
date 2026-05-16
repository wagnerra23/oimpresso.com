# F1.5 — Critique Vendas A+ (geral + Oficina Mecânica)

> **Tela:** `Sells/Index + Sells/Show`
> **Fase:** F1.5 critique
> **Score:** **88 / 100** · Gate ≥80 ✅
> **Avaliador:** [CC] auto-crítica · não substitui [CD] formal
> **Data:** 2026-05-14

## TL;DR

Template **passou no gate** (88 pra um mínimo de 80). Forte em CV gráfica (9.5 vertical), médio em Oficina Mecânica (7.5 vertical). Recomendo **1 rodada de iteração** antes do F2 pra implementar 2 pendências high-severity que destravam multi-vertical real.

## First impression

Hero verde-floresta + sparkline cria identidade memorável; mini-stepper FSM + badges duplas NF-e/NFS-e na mesma linha resolvem o gargalo de domínio que existia no v1. Vista segmented troca KPI lateral e coluna comissão sem reload — sente que é cockpit, não CRUD. Em CV gráfica fica 9.5; em Oficina Mecânica cai pra ~7.5 por gaps verticais identificados abaixo.

## Scores por dimensão (15-D)

### A. Estrutura

| # | Dimensão | Nota | Comentário |
|---|---|---:|---|
| 1 | Layout shell | 9.5 | Cockpit V2 íntegro. Zero quebra. |
| 2 | Hierarquia visual | 9.0 | 1 primária + 2 secundárias respeitada. |
| 3 | Densidade | 9.5 | Larissa 1280px → 12–14 vendas visíveis. |
| 4 | Iconografia | 8.0 | 100% lucide. Mas 3 ícones faltavam no shell (I.up/I.cart/I.download → fallback). |
| 5 | Estados | 8.5 | Default/hover/selected ok. Falta skeleton loading. |
| 6 | Atalhos | 9.0 | ⌘K + N + Esc funcionam. Falta J/K row nav. |
| 7 | Persistência | 7.5 | vista persiste. savedView+selected NÃO. |
| 8 | Componentes shared | 9.0 | Reusa shell completo. Stepper/Fbadge/Avatar prontos pra extrair. |

### B. Estado da arte

| # | Dimensão | Nota | Comentário |
|---|---|---:|---|
| 9 | Tipografia numérica | 9.5 | tabular-nums em todos os valores. IBM Plex Mono. |
| 10 | Espaçamento | 9.0 | Tokens do shell respeitados. Drawer 720px. |
| 11 | Cores semânticas | 9.5 | Forest-green próprio + warm ok/warn/bad. |
| 12 | Microinterações | 8.5 | Hover/tooltip/slide-up ok. Falta fade Vista. |
| 13 | Wagner aprovou | — | F2 pendente. Não conta no gate F1.5. |
| 14 | Benchmark externo | 9.0 | Stripe + Linear + Conta Azul + Shopify direta. |
| 15 | Persona priorização | 9.0 | Larissa-first (atalhos, KPIs, densidade). Eliana e Wagner via Vistas. |

**Média:** 8.85 / 10 · **Score 100:** 88

## Pontos fortes (10)

1. **Identidade forest-green** diferencia de OS/Orçamentos sem brigar com tokens
2. **Hero KPI preto-verde + sparkline 30d** eleva 'cards Bootstrap' a 'cockpit Stripe'
3. **Badge dupla NF-e + NFS-e** + drawer com cards lado-a-lado resolve gap fiscal
4. **Mini-stepper FSM inline** (5 dots) — detalhe Linear-grade
5. **Vista toggle** muda KPI + coluna sem reload (Ramp/Mercury pattern)
6. **Drawer com 4 tabs** organiza melhor que "Resumo único" do v1
7. **⌘K** com últimas vendas + busca SEFAZ + saved views
8. **Bulk action bar** slide-up só com seleção — não polui UI
9. **Avatar vendedor + comissão calculada** cria ownership no balcão
10. **Saved views com contadores** preview do filtro antes de aplicar

## Fraquezas priorizadas

### 🔴 High severity

**1. FSM vertical-agnóstico** — funciona pra CV mas é errado pra Oficina (que tem `recepção→diagnóstico→peças→execução→pronto`, não `orç→ped→fat→ent→pag`). Stepper hoje renderiza labels CV em venda de oficina.

*Fix:* adicionar `vertical` no data shape. `VENDAS_FSM_STEPS` vira map por vertical. `<VdStepper>` lê `v.vertical` e escolhe set certo.

**2. Falta placa Mercosul inline pra Oficina.** Em oficina mecânica a IDENTIDADE da venda é a placa, não o cliente. Larissa busca por placa. Componente `Plate` existe em `oficina-page.jsx` mas não foi reutilizado.

*Fix:* extrair `Plate` pra Components shared. Coluna Cliente condicional: CV → "BB / banner", Oficina → "<Plate RBA-2H78/> Civic · Marcos Aleixo".

### 🟡 Med severity

**3. Coluna "Vendedor" não cobre "Mecânico responsável"** da oficina. Lá tem vendedor balcão + mecânico que executou — dois papéis.

*Fix:* renomear "Atendido por". Avatar duplo (vendedor pequeno + mecânico maior) em Oficina.

**4. Sem skeleton loading + sem error state** — qualquer falha de fetch vira tabela vazia indistinguível de "sem vendas".

*Fix:* skeleton 6 rows cinza. Error boundary com retry. Empty diferente ("Nenhuma venda hoje · Use N pra criar").

**5. savedView e selected não persistem** — F5 perde o filtro.

*Fix:* localStorage `oimpresso.sells.index.{vista,savedView,statusF,query}`.

**6. Auditoria de ícones** — 3 inexistentes só apareceram em runtime. `icons.jsx` precisa ser fonte canônica testada.

*Fix:* lint check antes do F3: grep `I\.` vs `Object.keys(window.I)`.

### 🟢 Low severity

**7. Faltam J/K** Linear-style nav de rows.
**8. Vista swap sem animação** — KPIs pulam.
**9. Wagner persona** sem CTA dedicado (tax burden, total comissões mês).

## Fitness por vertical

| Vertical | Nota | Status |
|---|---:|---|
| **CV gráfica** | 9.5 | ✅ uso pretendido. Template ideal. |
| **Oficina Mecânica** | 7.5 | ⚠️ funciona mas precisa P0 placa + P0 FSM mecânica. |
| **Vestuário** | 8.0 | OK. Falta matrix tamanho×cor (P2). |
| **Repair eletrônicos** | 7.0 | Falta IMEI + termo + FSM 7 etapas (P1). |

## Iteração recomendada

**Necessária:** sim, 1 rodada
**Foco:** vertical-aware. P0 placa Mercosul + P0 FSM por vertical
**Delta esperado:** score **88 → 92+**. Oficina **7.5 → 9.0**.

## Passa pro F2?

**Sim, condicional.**

- Se uso = **CV gráfica only** → pode ir direto pro F2 com a versão atual.
- Se uso = **template multi-vertical** (incluindo Oficina Mecânica) → 1 rodada de iteração antes do F2, implementando os 2 high-severity acima.

## Próximo passo

Você decide:

1. **"itera"** — eu rodo F1.1 implementando os 2 high-severity (placa + FSM por vertical) + os 3 med-severity (vendedor/mecânico, skeleton, persistência). Score projetado 92+.
2. **"vai pra F2"** — fica como está, vai pra screenshot approval. Riscos do multi-vertical ficam pra F3 (Claude Code resolve).
3. **"só placa + FSM"** — itera só os 2 P0, deixa o resto pro F3.
