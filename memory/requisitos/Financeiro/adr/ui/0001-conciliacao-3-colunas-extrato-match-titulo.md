# ADR UI-0001 (Financeiro) · Conciliação OFX em 3 colunas (extrato · match · título)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui
- **Relacionado**: SPEC §US-FIN-009, `memory/requisitos/_DesignSystem/adr/ui/0006-padrao-tela-operacional.md`

## Contexto

Conciliação bancária é **a tela mais densa do módulo**: precisa mostrar lado-a-lado:
- O que veio do extrato OFX (esquerda)
- O match sugerido pelo algoritmo (centro com score)
- O título oimpresso correspondente (direita)

Em concorrentes (Conta Azul, Tiny), a UI mais comum é tabela com linha por transação — mas isso obriga o usuário a "imaginar" o título no oimpresso, e o erro de match passa batido.

Larissa-ROTA-LIVRE: monitor 1280px (`auto-memória: cliente_rotalivre.md`). UI tem que caber sem scroll horizontal e ser legível à distância.

## Decisão

**Layout 3 colunas + ações em lote no header:**

```
┌─────────────────────────────────────────────────────────────────┐
│  [Importar OFX] [Confirmar 23 matches] [Rejeitar selecionados] │
├─────────────────┬───────────────────┬───────────────────────────┤
│ EXTRATO OFX     │  MATCH SUGERIDO   │  TÍTULO OIMPRESSO         │
│ (53 transações) │  (score)          │  (vínculo)                │
├─────────────────┼───────────────────┼───────────────────────────┤
│ ☑ 24/04 R$1.500 │  ✓ 95%  exato     │  #1234 ROTA LIVRE        │
│   "DEP TED"     │  data + valor +   │  R$ 1.500 venc 24/04     │
│                 │  fuzzy desc       │  status: aberto           │
├─────────────────┼───────────────────┼───────────────────────────┤
│ ☑ 24/04 R$87,50 │  ⚠ 72%  fraco     │  #1240 SUPER-FORN        │
│   "PIX FORN"    │  só valor + dia   │  R$ 87,50 venc 23/04     │
├─────────────────┼───────────────────┼───────────────────────────┤
│ ☐ 24/04 R$50    │  ✗ sem match      │  [Criar título manual]   │
│   "TARIFA"      │                   │  [Lançar como tarifa]    │
└─────────────────┴───────────────────┴───────────────────────────┘
```

Score visual:
- ✓ verde 90%+ (exato): valor + data ± 1d + descrição similar
- ⚠ âmbar 70-89% (fraco): valor exato mas data ou descrição diverge
- ✗ vermelho <70% ou nada: sem match, ação manual

Linhas selecionadas em lote no header. Scroll vertical em cada coluna independente (sticky head).

Mobile (< 1024px): empilha em accordion (extrato em cima, match no meio, título embaixo) — uso raro mas precisa funcionar.

## Consequências

**Positivas:**
- Larissa vê os 3 lados ao mesmo tempo → erro de match óbvio (valor R$ 1.500 ↔ R$ 1.499 escapa em tabela linear; em 3 colunas, salta)
- Score visual reduz cognição (sem precisar ler número)
- Ações em lote: "selecione todos exatos → confirmar" resolve 80% dos cases em 2 clicks
- Sem-match vira fila clara de exceções (workflow diferente do happy path)

**Negativas:**
- Densidade de informação alta — UI fica "intimidante" no primeiro contato (mitigar: tour de onboarding na primeira importação)
- Mobile fica inferior (mas conciliação não é tarefa mobile-first; gestor abre no notebook)
- Algoritmo de score precisa ser bom (90%+ matches) senão Larissa não confia → tópico de teste prioritário

## Pattern obrigatório

- shadcn/ui `<Card>` por linha (não `<Table>` — checkbox + ações por linha precisam de altura)
- TanStack Query mutation por confirmação em lote (otimista UI)
- `<Toggle>` pra "Mostrar só sem-match" (filtro rápido)
- Atalho de teclado: `Space` confirma, `R` rejeita, `M` marca pra criar manual
- Loading state: `<Skeleton>` enquanto algoritmo de match processa
- Success state: snackbar `sonner` "23 matches confirmados, R$ 12.450,00 baixados"

## Métricas a observar (post-launch)

- Tempo médio de uma conciliação (do upload OFX ao "Fechar mês") — meta < 5 min para 100 transações
- % de matches confirmados em 1 clique (sem editar) — meta > 80%
- Taxa de sem-match após 5 importações — meta < 10% (algoritmo aprende com aceites? futuro ML)

## Alternativas consideradas

- **Tabela linear estilo Excel** — rejeitado: erro de match escapa, contexto fica espalhado
- **Wizard step-by-step** (1 transação por vez) — rejeitado: pra 50+ transações vira tortura
- **Árvore expansível** (transação → match) — rejeitado: precisa clicar pra ver título; perde overview
- **Drag-and-drop** (arrasta extrato pro título) — rejeitado: clicks > drag em volume; mantém como upgrade futuro

## Referências

- `_DesignSystem/adr/ui/0006-padrao-tela-operacional.md` — KpiGrid + DataTable + EmptyState
- `auto-memória: cliente_rotalivre.md` — Larissa monitor 1280px
- Conta Azul / Tiny / Bling — todas usam tabela linear (oportunidade de diferenciar)
