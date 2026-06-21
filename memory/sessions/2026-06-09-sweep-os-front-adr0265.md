---
date: "2026-06-09"
topic: "Avaliação F1.5 OS OficinaAuto + sweep ADR 0265 no front + fix Imprimir OS (Chromium)"
authors: ["W", "C"]
related_adrs:
  - 0265-oficina-reparo-erradica-locacao
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
---

# Sessão 2026-06-09 — Avaliação OS git + sweep ADR 0265 no front + fix print

## Pedido
[W]: (1) "confira porque não dá certo para exportar a oficina — não copia, fica feio"; (2) "pode avaliar o IA OS do git"; (3) "sim eu quero que arrume e use o protocolo".

## O que foi feito
1. **Diagnóstico do print:** template Blade `service_order.blade.php` está CORRETO (repro pixel-fiel em `_scrap/oficina-os-print-repro.html`). O defeito é o mecanismo `printServiceOrder.ts`: iframe 0×0 `visibility:hidden` + auto-`window.print()` injetado no srcdoc → Chromium/Brave imprime em branco ou imprime o shell.
2. **Avaliação F1.5 do módulo OS em `main`:** `AVALIACAO_OS_GIT_2026-06-09.md` — **65/100, reprovado**. Causa-raiz: ADR 0265 (erradicação de locação) nunca foi varrida no front. Board 86 / KanbanCard 88 / Print Blade 80 / Index 62 / RichSheet 60 / Show 58 / Create 55.
3. **Sweep aplicado (9 arquivos, espelho 1:1 do repo neste projeto):**
   - `Lib/printServiceOrder.ts` — REESCRITO: print disparado pelo PARENT via `contentWindow.print()` pós-load+fonts (sem visibility:hidden, sem script no srcdoc) + fallback `window.open`.
   - `ServiceOrders/Create.tsx` — sem "Locação" (backend rejeita `in:manutencao,mecanica`), sem select de Status (FSM manda; nasce `aberta`), combobox Cliente (renderiza quando controller enviar `contacts`), copy dev removida.
   - `ServiceOrders/Index.tsx` — `OrderType={manutencao,mecanica}`, colunas reparo (sem Endereço/Diárias/checkbox fantasma; "Caçamba"→"Veículo"), KPIs 3 (sem "Locações ativas"), pills sem locação, `formatBRL` null→"—" (matou `R$ [redacted Tier 0]` na UI).
   - `ServiceOrderRichSheet.tsx` — ramo locação ERRADICADO (mecanica caía nele: título "Caçamba", Diárias); KV grid reparo c/ Cliente; timeline vocabulário reparo; erro "a OS"; status via `ServiceOrderStatusBadge` shared (label PT).
   - `ServiceOrderSheet.tsx` — idem: título Mecânica/Manutenção, badge mecanica violet, MiniKpis sem Diárias, formatBRL.
   - `ServiceOrderStatusBadge.tsx` — types reparo; "Atrasada" pra qualquer tipo ativo (antes só locação).
   - `ServiceOrderFila.tsx` — types + typeLabel + sem bloco Diárias.
   - `ServiceOrderFsmActionPanel.tsx` — copy pipeline "Recepção → Diagnóstico → Execução → Pronto p/ retirar".
   - `ServiceOrders/Show.tsx` — tokens DS (slate/emerald hardcoded → border/success/card).

## Decisões
- Nenhuma ADR nova — o sweep EXECUTA a ADR 0265 no client (front estava em débito). Proposta de gate front anti-regressão registrada no prompt pro Code (decisão [W]/[CL]).
- Show.tsx × drawer (duas verdades) e KpiCard duplicado no Board = follow-ups, NÃO entraram no sweep (escopo cirúrgico).

## Erros + correção
- [CC] quase aplicou comentário com `old_string` parcial no RichSheet → texto duplicado; detectado e corrigido na hora (ler região antes de editar comentário multilinha).

## Residual
- `printSaleReceipt.ts` (Vendas) tem o MESMO bug de iframe — instrução de espelhamento incluída no prompt pro Code (não patchei: carrega CSS legacy, Code valida).
- Backend: controller `create()` precisa passar `contacts`; `StoreServiceOrderRequest` aceitar `contact_id` nullable.
- Charters: Create.charter.md ainda lista toggle locação (Goals) — Code appenda trilha do tempo.
- Keys FSM `cacamba_locacao`/`disponivel`/`locada` (ProducaoOficina) = dívida Tier 0 em ADR própria — NÃO tocadas (trava charter v4).

## Refs
- `AVALIACAO_OS_GIT_2026-06-09.md` · `_scrap/oficina-os-print-repro.html` · ADR 0265/0194/0143 · charter v4 PR #2417

## Próximo passo
Wagner cola o prompt zero-toque no Claude Code → [CL] valida (tsc/eslint/pest), commita e abre PR. F2 = Wagner confere screenshot das telas + testa "Imprimir OS" no Brave.
