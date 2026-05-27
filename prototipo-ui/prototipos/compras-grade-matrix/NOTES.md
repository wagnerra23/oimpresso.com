---
name: GradeMatrixInput — F1 protótipo Cowork
description: Pino visual canônico pra entrada de compra matricial tam × cor (vestuário PME). Gate F1.5 ADR 0107 antes de F3.
type: prototype
status: F1-commit-only
created: 2026-05-21
persona: Larissa @ ROTA LIVRE · biz=4 · 1280px · não-técnica · vestuário
related:
  - memory/requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md
  - memory/requisitos/Compras/CAPTERRA-DESIGN-FICHA.md
  - memory/requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md
  - memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md
  - memory/decisions/0104-processo-mwart-canonico-unico-caminho.md
  - memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md
  - memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md
---

# F1 — GradeMatrixInput (entrada compra matricial tam × cor)

Pino visual de referência pra o componente de entrada matricial de variações em compras de vestuário. **Não é tela completa de Compras** — é só o BLOCO "Adicionar item à compra" que vive dentro do form de criar/editar compra.

## Por que existe

Onda 1 do roadmap Compras (ver [AUDITORIA-COMPRAS-2026-05-21.md](../../../memory/requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md)) tem como **P0 esse componente**. Larissa @ ROTA LIVRE compra e dá entrada por grade (Wagner confirmou 2026-05-21). Hoje o oimpresso obriga linha-a-linha ([purchase_entry_row.blade.php](../../../resources/views/purchase/partials/purchase_entry_row.blade.php) via `@foreach $variations`) — empatamos com Bling/Tiny BR mas ficamos abaixo de Cin7/Lightspeed global.

Pesquisa estado-da-arte completa em [memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md](../../../memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md).

## Como ver

Abrir [`Compras - Grade Matrix.html`](Compras%20-%20Grade%20Matrix.html) num navegador local. Não tem dep server-side — React 18 + Babel via CDN, mock data inline. Resoluções suportadas: ≥1280px (alvo Larissa).

## Decisões UX pinadas neste F1

### Ergonomia teclado canônica Cin7 + Lightspeed

| Tecla | Ação | Por quê |
|---|---|---|
| `Tab` | próxima cor (mesma linha) | padrão spreadsheet · esperado |
| `Shift+Tab` | cor anterior | reverter rápido |
| `Enter` | próximo tamanho (mesma cor) | navegação vertical típica vestuário |
| `↑ ↓ ← →` | 4 direções | accessibility · não-poweruser também usa |
| `Esc` | limpar grade inteira | escape value, "comece de novo" |
| `2× clique col-head` | preencher coluna toda com qty N | quick-fill V1.1 — Cin7 tem; Lightspeed via OCR |

**NÃO** implementado neste F1 (fica V2):
- Paste de Excel (Larissa não usa Excel no fluxo dela)
- Custo por célula override (default = 1 custo por modelo é suficiente)
- Mobile/touch (fora de escopo persona 1280px)

### Layout

- **Linhas = tamanhos** (P/M/G/GG ou 36/38/40/42/44). Cabeçalho lateral fixo.
- **Colunas = cores** (Preto/Branco/Azul). Swatch HEX visual antes do nome. Cabeçalho topo fixo.
- **Última coluna = Σ linha** (soma das cores por tamanho). Fundo neutro.
- **Última linha = Σ coluna** (soma dos tamanhos por cor). Fundo neutro.
- **Canto inferior-direito = grand total** (qty total + valor R$). Fundo accent (azul ERP).
- **Células com valor > 0**: fundo verde claro (`--gmi-ok-soft`), texto verde escuro, font-weight 600. Feedback visual de progresso sem precisar Σ.

### Empty state — modelo single

Se `product.type='single'` (insumo, etiqueta, etc), **NÃO renderiza grade**. Mostra um único input gigante centralizado com label "Quantidade". Mantém o mesmo componente unificado pra evitar branching no caller.

### Totais on-the-fly

`useMemo` recalcula Σ linha + Σ coluna + grand total + valor R$ a cada keystroke. Sem libs. Sem debounce — instantâneo.

### Save model

`onAdd` emite `{ model_id, lines: [{ variation_id, qty, unit_cost }] }`. Caller (`Pages/Compras/Create.tsx` futura) acumula em state local. Só faz POST `/compras/store` no submit do form inteiro — alinha com convenção UltimatePOS atual ([PurchaseController.php:645](../../../app/Http/Controllers/PurchaseController.php#L645) já espera array de `purchase_lines`).

## Mock data — vestuário Larissa

Persona alinhada (não gráfica):

- **MOD-101 Camiseta básica** — PMGG × 3 cores = 12 SKUs filhos (Preto/Branco/Azul mar.)
- **MOD-102 Calça jeans skinny** — 36/38/40/42/44 × 2 cores = 10 SKUs (Jeans/Preto)
- **MOD-103 Vestido midi viscose** — PMGG × 4 cores = 16 SKUs (Floral azul/Floral rosa/Preto/Verde)
- **MOD-999 Etiqueta adesiva** — single (testa empty state)

Total típico Larissa = ~12-16 SKUs por modelo. Cabe confortável em 1280px (grade tem `min-width: 72px` por célula × 4 cores = 288px + headers + total = ~500px coluna grade, sobra espaço pra drawer ou outras seções).

## Gates do loop ([PROTOCOL.md](../../PROTOCOL.md))

- [x] **F1 commit-only** — este arquivo
- [x] **F1.5 visual comparison gate** ([ADR 0107](../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — **APROVADO Wagner 2026-05-21 03:15** via preview panel · ergonomia/visual ok · F2 e F3 liberados
- [ ] **F2** — Wagner aprova ergonomia em call rápida (Bloco 4.5 do discovery [DISCOVERY-LARISSA-COMPRAS.md](../../../memory/requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md) pode ser feita junto)
- [ ] **F3** — Tradução pra `resources/js/Pages/Compras/Components/GradeMatrixInput.tsx` (componente reusável) + integração `Pages/Compras/Create.tsx`. Cuidado com 6 anti-patterns F3 catalogados em [LICOES_F3_FINANCEIRO_REJEITADO.md](../../LICOES_F3_FINANCEIRO_REJEITADO.md).
- [ ] **F4** — Pest tests: unit `GradeMatrixInput` (Tab/Enter/Esc/Σ correto) + feature `purchase.store` com payload grade multi-tenant biz=4 (Tier 0 [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- [ ] **F5** — Deploy + smoke prod biz=4 + Larissa testa 1 entrada real cronometrada.

## Risco declarado

C de Wagner ("vai direto pinar sem call Larissa primeiro") — **risco aceito**: se a call validar que Larissa não usa atalhos teclado (só mouse), F1.5 ainda salva — basta esconder a barra de atalhos no rodapé. **Risco NÃO aceito implicitamente**: se Larissa rejeitar paradigma matriz tam×cor (improvável dado o sinal Wagner), F1 vira pino histórico, redesenha-se.

## Estimate consolidado (ADR 0106)

| Fase | Esforço IA-pair | Cumulativo |
|---|---|---|
| F1 (este protótipo) | **2-3h** (entregue) | 2-3h |
| F1.5 screenshot + gate | 30-60 min Wagner | + |
| F2 call Larissa validação | 20-30 min Wagner | + |
| F3 GradeMatrixInput.tsx + Pages/Compras/Create.tsx | 6-8h | 8-11h |
| F4 Pest unit + feature | 2-3h | 10-14h |
| F5 deploy + smoke real | 30-60 min | 11-15h |
| Margem 2x | | **22-30h ≈ 3-4 dias úteis** |

## Próximo passo

Wagner abrir o `.html` num navegador, mexer 30 segundos no teclado, decidir:

- **A**: aprovado · seguir Bloco 4.5 da call Larissa pra quantificar grade típica, depois F3
- **B**: rejeitado · refator F1 com novo paradigma
- **C**: aprovado com refinos · listar refinos em `CODE_NOTES.md` raiz e re-pinar
