# Mapa de construção — Drawer de OS (OficinaAuto · "Nova Ordem de Serviço")

> **Tipo:** mapa de construção (alvo `OficinaAuto/Os/Create.tsx` NÃO existe — só charter `draft`). NÃO é gap-vs-vivo.
> **Fonte do design (Wagner aprovou):** `_cowork-handoff-staging/.../oficina-os-page.jsx` (351ln) + `oficina-forms.jsx` (702ln) + `data-os.jsx` + `oficina-os-page.css`.
> **Read-only · Fase 1 da skill `aplicar-prototipo`.** PT-BR.

## ⚠️ ACHADO PRINCIPAL — quase tudo do mockup JÁ EXISTE LIVE

O drawer do mockup `oficina-os-page.jsx` é, parte por parte, **o que já está construído e em produção** em `resources/js/Pages/OficinaAuto/ServiceOrders/` (charter `Create.charter.md` `status: live` v3, + `Show.tsx`, + `ServiceOrderRichBody` no `ProducaoOficina/_components/ServiceOrderRichSheet.tsx`). O corpo rico (`ServiceOrderRichBody`) já tem **as 8 seções do mockup montadas e enfileiradas** (hero veículo, observação, DVI, fotos&laudo, peças&MO, checklist de etapa/StageGate, pipeline FSM, linha do tempo + footer cobrar/imprimir/conversa).

**Conclusão:** isto NÃO é "construir um drawer novo". É **decisão de reconciliação** (o conflito Tier 0 que o próprio charter draft registra): o `Os/Create.charter.md` é uma 2ª descrição da MESMA superfície que `ServiceOrders/` já entrega. Construir `Os/Create.tsx` do zero **duplicaria** a tela viva — violação direta da regra "comparar e não duplicar" (how-trabalhar §paralelização item 3) e do registro de regressões.

> Regra dura aplicável: o charter draft (`Os/Create.charter.md`) já diz, em vermelho, que **sobrepõe** `ServiceOrders/Create.charter.md` (live, Martinho biz=164) e que "o `Create.tsx` desta pasta **não existe** (a criar só na F3, após [W] decidir)". **Decisão de [W] continua pendente.** Sem ela, o caminho default é REUSAR `ServiceOrders/`, não criar `Os/`.

---

## Drawer dividido em PARTES — classificação REUSA / ADAPTA / CONSTRÓI-NOVO

Legenda esforço: P (pequeno) · M (médio) · G (grande). "Vivo em" = arquivo que já entrega a parte.

| # | Parte do mockup | Classe | Vivo em / como | Esforço | Risco | Dep. backend |
|---|---|---|---|---|---|---|
| 1 | **Cabeçalho veículo** (placa Mercosul, modelo/ano, KM, combustível gauge, mecânico, cliente+histórico) | **REUSA** | `ServiceOrderRichBody` hero (placa `MercosulPlate` + KV Cliente/KM/Box/Mecânico/Valor) + `Show.tsx` (gauge combustível + KM + entrada). `MercosulPlate` = `Components/shared`. | P | baixo | já servido por `show()` JSON |
| 2 | **Sintoma reportado** (relato cliente) | **REUSA** | `Show.tsx` campo `notes` ("Observações") + `RichBody` seção "Observação". No form de criação = `Create.tsx` Textarea "Defeito/Observações". | P | baixo | `notes` (existe) |
| 3 | **Check-in de entrada** (avarias + fotos entrada — mockup junta no hero/seção check-in) | **REUSA** | `ServiceOrders/_components/EntryCheckinFields.tsx` (combustível + avarias, usado no `Create.tsx`) + `Show.tsx` render check-in. UC-01 travado no charter live. | P | baixo | `fuel_level_at_entry`, `entry_damages` (existem) |
| 4 | **Vistoria Digital DVI** (semáforo ok/atenção/crítico, item a item, foto, "+orçamento") | **REUSA** | 2 implementações vivas: `ServiceOrders/_components/DviBudgetSection.tsx` (Show, read+toOrcamento) e `ProducaoOficina/_components/DviInlineEditor.tsx` (drawer, CRUD inline + semáforo `DviTraffic`). Backend `DviInspectionController::toOrcamento`. | P | baixo | `dvi_items`, endpoint `toOrcamento` (existem) |
| 5 | **Orçamento — peças & mão de obra** (lista, estoque/encomenda, total) ⚠️ **toca VALOR + ESTOQUE (regra mestre)** | **REUSA** | `ServiceOrders/_components/ServiceOrderItemRow.tsx` + `ServiceOrderItemFormSheet.tsx` (CRUD item peça/MO/terceiro) + total em `Show.tsx`/`RichBody` (`items_total`). Status estoque (`stat: ok/encomend./ag.aprov.`) já no editor do mockup → mapear pro DTO de item. | P–M | **médio (valor/estoque)** | `items[]`, `items_total`, item controller (existem) |
| 6 | **Fotos & laudo** | **REUSA** | `ProducaoOficina/_components/LaudoPhotoSection.tsx` (Modules/Arquivos) + grid fotos no `RichBody`. | P | baixo | `laudo_photos` (existe) |
| 7 | **Gate de checklist de etapa** ("Iniciar execução": cliente aprovou PIN/WhatsApp, peças confirmadas, mecânico disponível, box/elevador) | **REUSA** | `ServiceOrders/_components/ServiceOrderStageGate.tsx` — espelha o `STAGE_GATES` do `oficina-forms.jsx` **e** é ENFORÇADO no servidor (`ServiceOrderFsmActionController::execute`, GET `/fsm/gate`). Requisitos auto (bloqueiam) + manual (advisory). | P | baixo | endpoint `/fsm/gate` + `/fsm/execute` (existem) |
| 8 | **Gate de aprovação do cliente** (aguardando→enviado→aprovado, enviar WhatsApp, bloqueia avançar) | **REUSA** | `ServiceOrders/_components/ApprovalGateCard.tsx` (Show) + `DviInlineEditor` `DviGateFoot` (none→pending→approved/declined→reopen) + `RichBody.handlePedirAprovacao` POST `/enviar-aprovacao`. Tela pública `AprovacaoPublica.tsx` já existe. | P | baixo | `approval`, `/enviar-aprovacao` (existem) |
| 9 | **Linha do tempo FSM** | **REUSA** | `ServiceOrders/_components/ServiceOrderTimeline.tsx` (sale_stage_history real) + `TimelineSkeleton` fallback no `RichBody`. | P | baixo | timeline endpoint (existe) |
| 10 | **Ações** (cobrar, imprimir OS, conversa cliente) | **REUSA** | Footer sticky do `RichBody`: `wa.me` conversa + `printServiceOrder` (`Lib/printServiceOrder`) + Editar. "Cobrar" = `DviGateFoot` ação `cobrar`. | P | baixo | print endpoint (existe) |
| 11 | **Split fiscal** (NF-e 55 peças / NFS-e MO + garantia) — está no mockup (rail direito) | **REUSA** | `ServiceOrders/_components/FiscalSplitCard.tsx` (computa de `items`, presentacional; emissão via Observer ADR 0192). | P | baixo | `items` (existe) |
| 12 | **Stepper FSM no topo** (Recepção→…→Pronto) | **ADAPTA** | `ProducaoOficina/_components/ServiceOrderStagePipeline.tsx` existe; o mockup mostra o stepper como header horizontal — render diferente do drawer atual (drawer mostra etapa no eyebrow). Decisão de [W] se quer o stepper visível no topo. | P | baixo | `current_stage` (existe) |
| 13 | **Switch de vertical** (Oficina/Com.Visual/Vestuário no topo do mockup) | **CONSTRÓI-NOVO _ou_ DESCARTA** | É artefato do **protótipo demo** (3 verticais no mesmo documento). Não há equivalente vivo e provavelmente **não deve existir** na tela real — cada vertical tem sua própria superfície. Marcar como _fora de escopo_ salvo [W] pedir. | — | — | — |

---

## Repair/JobSheet — reusável vs OficinaAuto-específico

`Repair/JobSheet/{Create,Show,Edit,Index,AddParts}.tsx` é a **infraestrutura compartilhada de OS entre verticais** (CRUD genérico + `JobSheetFsmPanel` que espelha `Sells/_components/FsmActionPanel`). Porém:

- **OficinaAuto NÃO consome `JobSheet` diretamente.** Tem seu próprio par de telas (`ServiceOrders/`) + backend (`/oficina-auto/ordens-servico`, `service_orders` table) já live. O domínio de veículo (placa/chassi/KM/combustível) é **OficinaAuto-específico** — `JobSheet` é genérico de aparelho (brand/device/serial_no).
- **O que vale imitar de `JobSheet` (padrão, não copiar arquivo):** o wrapper `JobSheetFsmPanel` (fetch actions + confirm modal + side-effect badge) — mas `OficinaAuto` já tem o equivalente `ServiceOrderFsmActionPanel`. `Inertia::defer` + `<Deferred>` no Show (peças/anexos/timeline) é bom padrão a herdar se `Os/` vier a existir.
- **`ProducaoOficina/Index.tsx`** (kanban onde o drawer abriria) é `Modules/Repair` shared, mas **`OficinaAuto/ProducaoOficina/` + `ServiceOrders/Board.tsx`** são a versão vertical que já embute o `ServiceOrderRichSheet` (drawer). O drawer **já abre do kanban**.

**Veredito reuso:** o drawer de OS é **OficinaAuto-específico e já existe** (`ServiceOrderRichBody`). `Repair/JobSheet` fornece só o *padrão* FSM/defer a imitar — não código a copiar.

---

## Veredito — mapa de construção priorizado

**O que vem 1º (antes de QUALQUER código):**

1. **Resolver o conflito Tier 0 de [W]** que o `Os/Create.charter.md` registra: `Os/` (este charter draft) **vs** `ServiceOrders/` (live). Três saídas possíveis — (a) **descartar** `Os/Create.charter.md` e adotar `ServiceOrders/` como a tela canônica do mockup (recomendado: zero duplicação, já em prod); (b) evoluir `ServiceOrders/` pra absorver deltas do mockup; (c) tela nova distinta. **Não criar `Os/Create.tsx` sem essa decisão** — seria duplicar a superfície viva.

2. Se [W] escolher **(a)/(b)**: o trabalho restante é **mínimo** — não há parte CONSTRÓI-NOVO real além de decisões cosméticas. Deltas candidatos (todos P): stepper FSM horizontal no topo (#12), e mapear o status de estoque do item (`ok/encomend./ag.aprov.` do mockup) pro DTO/badge de `ServiceOrderItemRow` se ainda não exibido.

3. **Switch de vertical (#13)** = descartar (artefato de demo do protótipo).

**Repair/JobSheet reusável:** só como *padrão* (FSM wrapper, Inertia::defer) — não como código. **OficinaAuto-específico:** todo o drawer (veículo, DVI, gate, fiscal split) — e já está construído.

**Maior risco:** **duplicação**. Construir `Os/Create.tsx` do mockup ignorando que `ServiceOrders/` já entrega tudo recria uma tela viva em paralelo (incidente clássico do registro de regressões). Risco secundário: **VALOR/ESTOQUE** na parte #5 (orçamento peças&MO) — qualquer mexida em total/estoque cai na regra mestre (dupla confirmação + impacto antes→depois), mas como é REUSA do que já existe, só vigiar se algum cálculo for tocado.

> **Pendências honestas (_pendente_):** (1) decisão de [W] sobre o conflito Os/ vs ServiceOrders/; (2) se o status de estoque por item já renderiza no `ServiceOrderItemRow` (não inspecionado a fundo); (3) presença ou não do stepper FSM horizontal no topo do drawer/Board atual.
