# Avaliação F1.5 — OS da Oficina construída pela IA no git (`main`)
**Data:** 2026-06-09 · **Avaliador:** [CC] Claude Cowork · **Escopo:** `resources/js/Pages/OficinaAuto/ServiceOrders/*` + `ProducaoOficina/ServiceOrderRichSheet` + print Blade
**Veredito geral: 65/100 — REPROVADO no gate ≥80.** Estrutura é boa; o que mata é o **domínio morto de locação vazando em toda a UI** (ADR 0265 erradicou no backend HOJE, front nunca foi varrido).

---

## Scores por superfície

| Superfície | Score | Veredito |
|---|---|---|
| **Board.tsx** (Quadro Kanban) | **86** | ✅ Melhor tela. FSM data-driven, drag+confirmação, a11y correta, foto real, DVI x/y, densidade @container |
| **ServiceOrderKanbanCard** | **88** | ✅ Excelente — tempo relativo, tabular-nums, tooltips, aria-roledescription |
| **Print Blade** (service_order.blade.php) | **80** | ✅ Template A4 limpo. Problema é o MECANISMO (iframe oculto 0×0 + window.print — falha no Brave/Chromium) |
| **Index.tsx** (Lista) | **62** | ⚠️ Esqueleto Linear ok, mas o SCHEMA DE COLUNAS é da locação morta — pra mecânica vira tela de travessões |
| **ServiceOrderRichSheet** (drawer) | **60** | ❌ Branch polimórfico trata `mecanica` como LOCAÇÃO. Timeline fala "Caçamba entregue ao cliente" numa OS de motor |
| **Show.tsx** | **58** | ⚠️ Duplica o drawer com outro layout; tokens fora do DS (slate-*/emerald-* hardcoded); window.confirm |
| **Create.tsx** | **55** | ❌ Sem campo CLIENTE (charter exige); oferece "Locação" que o backend agora REJEITA; copy dev vazada |

---

## P0 — Bugs reais (usuário vê hoje, biz=164 Martinho LIVE)

1. **`OrderType = 'locacao' | 'manutencao'` — `mecanica` NÃO EXISTE nos types do front.**
   - `RichSheet`: `data.order_type === 'manutencao' ? (mecânica) : (LOCAÇÃO)` → toda OS `mecanica` renderiza o ramo locação: título "Caçamba —", "Diárias 0d × R$…", "Valor a receber". **É exatamente a OS-00004 'Cacamba' que [W] viu.**
   - `Index`: badge de tipo cai em "—" pra `mecanica`.
2. **Create oferece `<SelectItem value="locacao">Locação</SelectItem>`** — `StoreServiceOrderRequest` agora valida `in:manutencao,mecanica` (ADR 0265). Escolheu Locação → erro de validação. Caminho de criação QUEBRADO por opção fantasma.
3. **`formatBRL` devolve a string literal `"R$ [redacted Tier 0]"`** quando valor é null — vaza como texto na tabela e no drawer. (Index.tsx:~160, RichSheet:~150)
4. **Timeline do drawer é 100% vocabulário de locação:** "Locação iniciada" · "Caçamba entregue ao cliente" · "Prazo de devolução" · "Aguardando recolhimento…" — numa oficina de mecânica pesada.
5. **Erro do drawer:** "Não foi possível carregar **a caçamba**".
6. **Imprimir OS** — mecanismo iframe oculto + `window.print()` de dentro do srcdoc imprime a tela inteira em Chromium/Brave recente (template está certo; ver repro `_scrap/oficina-os-print-repro.html`).

## P1 — Violações de charter / DS

7. **Create sem autocomplete de cliente (contact)** — charter `ServiceOrders/Create.charter.md` lista como required ("Martinho atende caminhões de terceiros"). Sem cliente → gate de aprovação WhatsApp não tem destinatário.
8. **Campo "Status *" manual no Create** — status é do FSM; expor select livre convida estado inconsistente (canon GUARD: nunca mexer direto).
9. **Index: cabeçalho de coluna "Caçamba" + colunas Endereço/Diárias/A receber** — schema visual da locação; em mecânica viram colunas de "—". KPI "Locações ativas" idem (`Kpis.locacoes_ativas` — backend já removeu o KPI no W25/0265).
10. **Show.tsx × RichSheet = duas verdades** do detalhe de OS com layouts divergentes. Consolidar no drawer (padrão Cockpit V2) e Show vira rota que abre o drawer.
11. **Tokens fora do DS no Show:** `divide-slate-100`, `border-slate-200`, `text-emerald-700` (vs `text-success`), `window.confirm` pra excluir item (devia ser AlertDialog DS).
12. **Board define `KpiCard` local** ≠ `shared/KpiCard` usado no Index — dois estilos de KPI no mesmo módulo.
13. **Copy dev vazada pra UI:** SheetDescription "V0 — vínculo OS↔Vehicle obrigatório, status livre" (Create); EmptyProcessState manda usuário "rodar o seeder OficinaAutoFsmSeeder".
14. **Checkboxes `disabled` na tabela** (bulk-select fantasma) + grid de fotos placeholder com botão disabled "V2" — UI promissória.
15. **StatusBadge do RichSheet imprime `data.status` cru** (`em_execucao` com underscore) em vez de reusar `ServiceOrderStatusBadge`.

---

## Diagnóstico-raiz

O backend foi erradicado de locação (migration + validação + KPI + menu, ADR 0265 hoje), **mas nenhuma varredura tocou o front**. O design estrutural das telas (Board, cards, gates de aprovação, split fiscal) é estado-da-arte e segue o protótipo canon — o "porco" que [W] sente é: (a) OS de mecânica renderizada com roupa de locação, (b) strings internas vazando, (c) print imprimindo o shell.

## Correção proposta — 1 sweep front "locacao→reparo" (espelha ADR 0265 no client)

- Types: `OrderType = 'manutencao' | 'mecanica'`; RichSheet branch por `=== 'locacao'` morre — default = mecânica.
- Create: remover item Locação + remover select Status (status nasce `aberta`) + adicionar combobox Cliente (contact) required + copy do SheetDescription.
- Index: colunas → Nº OS · Veículo · Cliente · Defeito (notes) · Entrada · Previsão · Valor (items_total) · Status; KPIs → Em diagnóstico / Aguardando aprovação / Em execução / Atrasadas; pills sem "Locações".
- RichSheet: timeline neutra de reparo ("OS aberta" → "Em execução" → "Pronto p/ retirar"); erro "Não foi possível carregar a OS"; `formatBRL` null → "—".
- Show: consolidar com drawer (decisão [W]) ou no mínimo trocar tokens slate/emerald → DS + AlertDialog.
- printServiceOrder/printSaleReceipt: trocar iframe oculto por iframe visível fora da viewport com `contentWindow.print()` após `load` + fallback `window.open` (corrige Brave).

**Gate de regressão:** `dominio:check` já cobre enum; falta GUARD de front (grep `locacao|caçamba|Locação` em `Pages/OficinaAuto/**` permitido só em código morto marcado).
