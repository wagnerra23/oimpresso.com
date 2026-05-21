# Sells/Index — Visual Comparison R2 (Unificação Tabs Visão)

> **ADR 0107** — visual-comparison F1.5 obrigatório em PR MWART (Cowork → Inertia/React)
> **Origem:** [ADR 0178](../../decisions/0178-sells-unified-tabs-visao-supersede-0136.md) — unificação Lista + Grade Avançada em tabs `visao`
> **Implementação:** PRs 2-6 da Onda Unificação (a executar — branch `feat/sells-unified-*`)
> **Status:** F1.5 PLACEHOLDER — baseline visual ainda não existe (este é o **primeiro** PR DOCS-only da onda; baseline visual será criada no PR4 quando `SellsTabelaUnificada` consumida em prod)

## Notas pré-baseline

PR1 (DOCS) **não muda nenhum pixel** em prod — apenas registra ADR + charter + este placeholder. Baseline canônica será criada no `pest tests/Browser/Sells/UnifiedTabsTest.php --update-snapshots` pós-merge PR4 (biz=1 Wagner @ WR2 Sistemas como tenant de smoke).

O CI `visual-regression` é tolerante a este PR (sem mudança visual). PRs 2-6 honram o gate normalmente.

## 15 dimensões canônicas (preenchidas pós-PR4)

### Layout & arquitetura

| # | Dimensão | Estado atual (PRs 1311-1320) | Target (PR4 unificado) | Δ |
|---|---|---|---|---|
| 1 | Toggle de visualização | Botões `Lista` / `Grade Avançada` (top-right) | Tabs horizontais `Operacional` / `Financeira` / `Produção` (esquerda do filtro) | ⚪ TBD |
| 2 | localStorage key | `oimpresso.sells.b<id>.viewMode = 'lista' \| 'grade-avancada'` | `oimpresso.sells.b<id>.visao = 'operacional' \| 'financeira' \| 'produção'` + migração silenciosa | ⚪ TBD |
| 3 | Largura mínima (Larissa 1280px) | Lista: ~970px OK; Grade: ~1080px tight | ≤9 cols/tab — ~1080px max | ⚪ TBD |

### Colunas por visão

| # | Dimensão | Estado atual | Target unificado | Δ |
|---|---|---|---|---|
| 4 | Operacional (default ROTA LIVRE) | Venda+★ · Data · Cliente+items · Atendido por · Pipeline · Fiscal · Pagamento+SLA · Total · Status+actions · (Comissão?) | Idem Lista atual — preserva 100% pra Larissa | ⚪ TBD |
| 5 | Financeira (Eliana) | Grade Avançada: Data · Nº fatura · Cliente · Localização · Total · Pago · A receber · Pagamento · Status · Produção · (Ações) | Venda · Data · Cliente · Total · Pago · A receber · Pagamento · Status · Comissão (focus financeiro) | ⚪ TBD |
| 6 | Produção (OfficeImpresso) | Não existe (sub-conjunto de Grade Avançada) | Venda · Data · Cliente · Localização · Produção · Pipeline · Pagamento · Total · Status | ⚪ TBD |

### Interações

| # | Dimensão | Estado atual | Target unificado | Δ |
|---|---|---|---|---|
| 7 | Multiseleção (checkbox por linha) | Só Grade Avançada | Tabs Financeira + Produção | ⚪ TBD |
| 8 | Totalizador sticky bottom | Só Grade Avançada | Tab Financeira | ⚪ TBD |
| 9 | Agrupamento (TanStack) | Só Grade Avançada (3 opções: cliente/status/mês) | Tabs Financeira + Produção (3 opções idem; Produção +"venda agrupada") | ⚪ TBD |
| 10 | Row-actions (DANFE/XML/Imprimir/Pagar) | Só Lista (hover vd-row-actions) | Tab Operacional + Pagar disponível em todas tabs | ⚪ TBD |
| 11 | J/K nav + foco visual | Só Lista | Tab Operacional | ⚪ TBD |
| 12 | ★ favoritos (B atalho) | Só Lista | Tab Operacional | ⚪ TBD |
| 13 | Modal pagamento inline | PR #1320 Lista + Grade HTML | Todas tabs (consume `QuickPaymentDialog` central) | ⚪ TBD |

### Detalhes finos

| # | Dimensão | Estado atual | Target unificado | Δ |
|---|---|---|---|---|
| 14 | SLA pill (vence Xd / atrasado Xd) | Lista coluna PAGAMENTO (vd-pay-sla) | Tab Operacional só (Financeira/Produção mostram via badge na coluna Status) | ⚪ TBD |
| 15 | items_summary inline | Lista linha Cliente (vd-notes) | Tab Operacional só (Financeira/Produção compactam) | ⚪ TBD |

## Screenshots baseline (a capturar pós-PR4)

Caminho canônico `tests/Browser/Screenshots/Sells/`:

- `Index-tab-operacional-default.png` (1280×800, biz=1)
- `Index-tab-financeira.png` (1280×800, biz=1)
- `Index-tab-producao.png` (1280×800, biz=1)
- `Index-quick-payment-modal-aberto.png` (focus modal)
- `Index-totalizador-sticky.png` (Financeira com 20+ linhas)
- `Index-bulk-actions-bar.png` (Financeira com 3 selecionadas)

## Risco visual

- **Larissa decora fluxos** ([feedback canon](../../reference/feedback-cliente-rotalivre.md)): tab "Operacional" default DEVE ser pixel-equivalente à Lista atual. Smoke biz=4 explícito (não só biz=1).
- **OfficeImpresso piloto futuro**: tab "Produção" ainda não tem cliente em prod usando — F1.5 dela será aprovada com persona simulada (Wagner manualmente, ou pest browser test).
- **Re-render loop catalogado em [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) §Riscos**: `useMemo` defensivo no `visibleColumns` derivado de `visao`.

## Próximo passo

PR2 (`SellsTabsVisao.tsx` + feature-flag `?tabs=1`) — preenche a primeira coluna Δ desta tabela com evidência real (screenshots ou notas) quando merged.
