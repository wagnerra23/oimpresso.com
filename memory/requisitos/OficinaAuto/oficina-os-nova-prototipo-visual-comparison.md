---
id: requisitos-oficina-auto-oficina-os-nova-prototipo-visual-comparison
slug: oficinaauto-oficina-os-nova-prototipo-visual-comparison
title: "OficinaAuto — Comparativo visual Nova OS (prototipo oficina-os-page.jsx)"
type: visual-comparison
module: OficinaAuto
status: draft
date: "2026-06-02"
canon_reference: design-bundle Cowork "Oimpresso ERP Conunicação Visual" / oficina-os-page.jsx (351 linhas)
blade_source: N/A (módulo novo, sem Blade legacy — já 100% Inertia)
inertia_target: resources/js/Pages/OficinaAuto/ServiceOrders/{Create,Edit,Show}.tsx + _components/*
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0137-oficina-auto-service-order
revisions:
  - 2026-06-02 V1 (draft) — delta entre o protótipo Cowork "Nova OS" (oficina-os-page.jsx, versão que a cliente Kamila/Martinho reagiu em chat37) e o módulo OficinaAuto JÁ EMBARCADO em main. Gerado por [CL] a partir do handoff bundle Claude Design.
---

# OficinaAuto — Nova OS (protótipo Cowork) — Visual comparison / delta

> **Achado-chave:** a tela "Oficina OS" do protótipo (`oficina-os-page.jsx`) **não é greenfield**. O módulo OficinaAuto já está 100% migrado em `main` (F1+F2+F3 do MWART completos): 3.512 linhas de TSX em `ServiceOrders/{Index,Create,Edit,Show}.tsx` + 11 componentes + backend FSM + backend DVI (`oa_inspection_items`) + aprovação pública por PIN (`AprovacaoOsController`).
>
> O protótipo propõe **consolidar o ciclo da OS num único workspace rico** (1 tela full-screen). Hoje o ciclo está distribuído: `Create.tsx` (form rápido em Sheet 720px) + `Show.tsx` (detalhe) + `ProducaoOficina/Index.tsx` (kanban). **Esta consolidação é decisão Tier 0 de produto (Wagner)** — vide regra do handoff "estender NÃO recriar, F1 = proposta".
>
> Este doc captura o protótipo como **spec acionável**: o que o protótipo agrega × o que já existe, priorizado P0–P3, com US propostas. Nenhum código foi alterado.

---

## Mapa de features: protótipo × embarcado

| # | Feature do protótipo | Onde aparece em `oficina-os-page.jsx` | Status no main | Componente/origem |
|---|---|---|---|---|
| 1 | **Stepper FSM** (Recepção→Diagnóstico→Orçamento→Aprovação→Execução→Pronto) | `OfxStepper`, l.49-63, `active:2` | ✅ embarcado | `_components/ServiceOrderStagePipeline.tsx` + `ServiceOrderFsmActionPanel.tsx` |
| 2 | **Placa Mercosul** no hero do veículo | `.ofx-plate`, l.166-169 | ✅ embarcado | `ProducaoOficina/_components/MercosulPlate.tsx` (usado em todas as telas) |
| 3 | **Itens Serviços / Peças** com abas + busca `/` | `OfxSection "Itens da OS"`, l.248-283 | ✅ embarcado | `_components/ServiceOrderItemRow.tsx` + `ServiceOrderItemFormSheet.tsx` |
| 4 | **Inspeção DVI** semáforo (g/y/r) + **"+ orçamento"** (reprovado vira linha de serviço) | `OfxDviRow` + `addReprov`, l.65-83 / 127-132 | 🟡 **parcial** — backend `oa_inspection_items` + `client_decision` + `DviInspectionController` existem; `DviPhotoGrid.tsx` existe em ProducaoOficina; **NÃO está cabeado no fluxo Create/Edit/Show da OS** | gap de wiring |
| 5 | **Gate de aprovação do cliente** in-screen (aguardando→enviado→aprovado, WhatsApp, **bloqueia execução**) | `.ofx-gate` + `sendWhats`/`markApproved`, l.296-317 | 🟡 **parcial** — `Public/AprovacaoOsController` (PIN 4 díg, "a oficina foi avisada") + status `aprovada` existem; **card de gate 3-estados dentro da tela da oficina não existe** | gap de superfície |
| 6 | **Painel fiscal dual-doc** (NF-e 55 peças / NFS-e mão de obra, lado a lado) | `.ofx-fiscal`, l.319-326 | 🟡 **parcial** — Show tem só botão "Imprimir OS A4 nota-fiscal-like" (US-OFICINA-037); **split NF-e/NFS-e por natureza não existe** | gap (cf. COWORK_NOTES item (c) "FiscalStatusBadge unificado") |
| 7 | **Barra de combustível** no hero do veículo | `.ofx-fuel`, l.179-183 | ❌ **ausente** (grep `fuel/combustível/tanque` = 0 no módulo) | novo |
| 8 | **Check-in de entrada** (relato + toggle de avarias + fotos de entrada) | `OfxSection "Check-in do veículo"`, l.204-230 | ❌ **ausente** (grep `avaria/check-in/relato/damage` = 0) | novo |
| 9 | **Recap no rodapé** + "Iniciar execução" travado até aprovar | `.ofx-foot`, l.332-344 | 🟡 lógica de gate existe no FsmActionPanel; layout footer recap não | gap de layout |
| 10 | **Switch de vertical** (Oficina / Com. Visual / Vestuário) no mesmo documento | `.ofx-vsw`, l.153-158 | ❌ ausente (decisão de arquitetura multi-vertical — fora de escopo OficinaAuto) | descartar p/ OficinaAuto |

Mapeamento de status FSM: protótipo tem **6 passos** (Recepção, Diagnóstico, Orçamento, Aprovação, Execução, Pronto); backend tem **5 status** (`aberta`, `orcamento`, `aprovada`, `entregue`, `cancelada`). Delta de naming/granularidade a alinhar (não bloqueante).

---

## Deltas priorizados (apenas o que OficinaAuto ainda não tem)

> Cada delta = 1 US = 1 PR MWART F3 (≤300 LOC, audit ≥70), **numa branch limpa a partir de `main`** — NÃO na `feat/staging-ct100` (divergida 368 commits).

### P1 — alto valor, baixo risco (aditivo, sem mexer no FSM)
- **US-OFICINA-038 · Check-in de entrada** (delta #8): seção em `Create.tsx`/`Edit.tsx` com relato do cliente (já existe `notes`), avarias na entrada (chips toggle) e fotos de entrada. Persistência: campos novos em `service_orders` ou reuso de `oa_inspection_items` tipo `entrada`. Backend baseline antes (F2).
- **US-OFICINA-039 · Barra de combustível no hero** (delta #7): indicador `fuel_level` (0–100%) no `MercosulPlate`/hero do veículo em Show + Producao. Campo `fuel_level_at_entry` em `service_orders`.

### P2 — valor alto, toca fluxo existente (precisa cuidado FSM/backend)
- **US-OFICINA-040 · Cabear DVI → "+ orçamento" no fluxo da OS** (delta #4): superar `oa_inspection_items` (com `client_decision`) dentro de Show/Edit; item reprovado vira `ServiceOrderItem` de serviço com 1 clique. Reusa `DviPhotoGrid` + backend existente; é wiring de UI.
- **US-OFICINA-041 · Card de gate de aprovação in-screen** (delta #5 + #9): card 3-estados (aguardando→enviado→aprovado) no Show da oficina, integrando `AprovacaoOsController` (gera link/PIN, dispara WhatsApp via Modules/Whatsapp) + recap footer com "Iniciar execução" travado. Estende, não recria, o `FsmActionPanel`.

### P3 — convergente com outro item já na fila
- **US-OFICINA-042 · Painel fiscal NF-e/NFS-e** (delta #6): split por natureza fiscal. **Convergir com COWORK_NOTES item (c)** "FiscalStatusBadge unificado" (NfceStatusBadge → NF-e 55 + NFS-e) — não duplicar. Provavelmente sai como reuso do componente fiscal compartilhado, não código novo no OficinaAuto.

### Descartar p/ OficinaAuto
- Switch de vertical (delta #10): é arquitetura multi-vertical do shell, não da OS. Fora de escopo.

---

## Decisão pendente de Wagner (Tier 0)

**Consolidar ou estender?**

- **Opção A — Estender (recomendado):** manter `Create` (form rápido) + `Show` (detalhe rico) e plugar os deltas P1/P2 no `Show`. Baixo risco, respeita "estender não recriar", cada PR pequeno. A cliente já validou o módulo atual.
- **Opção B — Consolidar:** transformar a OS num único workspace full-screen espelhando o protótipo (Create+Show fundidos). Maior valor de UX (o que brilhou os olhos da Kamila em chat37) mas é refactor grande, Tier 0 → exige **ADR próprio** + sign-off antes de codar.

Sem decisão, o caminho seguro é **Opção A, P1 primeiro** (check-in + combustível), que é aditivo e não conflita com nenhuma das duas opções futuras.

---

## Procedência (handoff bundle)

- Fonte: Claude Design bundle `Oimpresso ERP Conunicação Visual` (claude.ai/design), arquivo `project/oficina-os-page.jsx` + `oficina-os-page.css` + `data-os.jsx`.
- Intenção (chats): chat37 (2026-06-02) — foco na homologação Martinho; *"o fluxo real da Oficina… Recepção → Diagnóstico (DVI semáforo) → Orçamento → Aprovação (gate) → Execução… é o diferencial que brilhou os olhos dela."*
- Regras aplicadas: README do bundle ("recriar o output visual, perguntar se ambíguo, não copiar estrutura do protótipo"), ADR 0104 (MWART), commit-discipline (1 PR = 1 US), handoff §10.4 ("F1 = proposta, estender não recriar").
