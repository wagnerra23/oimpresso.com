---
page: /oficina-auto/ordens-servico/board
component: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
owner: wagner
status: live
last_validated: "2026-06-02"
parent_module: OficinaAuto
related_adrs:
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0129-state-machine-canonica-fsm-rbac
  - 0093-multi-tenant-isolation-tier-0
tier: A
charter_version: 2
---

# Charter — Quadro (Kanban) de OS de Mecânica

## Mission
Dar ao operador da oficina (Martinho · mecânica pesada de caminhão) uma visão de
fluxo de trabalho das Ordens de Serviço — da recepção do veículo à retirada pelo
cliente — onde mover um card **executa a transição de etapa de verdade** (FSM),
não só reorganiza visualmente.

## Contexto de domínio (anti-confusão)
Martinho é **oficina de mecânica pesada** (caminhão entra pra manutenção/troca de
peça — quase concessionária/loja de peça). **NÃO é locação de caçamba.** O nome
"caçamba" nos artefatos legados (`cacamba_*`, `ProducaoOficina`) é equívoco já
corrigido pela [ADR 0194]. Este quadro roda no processo FSM **`oficina_mecanica_os`**
(nome correto), distinto do board de caçamba (ProducaoOficina/Index).

## Goals
- Colunas **data-driven** pelas etapas reais do FSM (Recepção → Diagnóstico →
  Aguardando aprovação → Aguardando peças → Em execução → Pronto p/ retirar).
- Drag entre colunas → confirmação → **ExecuteStageActionService** (audit em
  `sale_stage_history`). Nunca UPDATE direto em `current_stage_id`.
- Card legível na frente do cliente: **foto real** (sem placeholder de texto),
  contador **DVI x/y** (checklist), placa Mercosul, valor, mecânico, prazo.
- Densidade compacta @1280 (monitor do operador) via **@container** (não @media).
- Reusar o canon: KanbanDndProvider, DragConfirmDialog, ServiceOrderRichSheet,
  MercosulPlate (estender, não recriar — §10.4).

## Non-Goals
- NÃO cria OS inline pelo drag (usar "Nova OS").
- NÃO emite documento fiscal nem dispara cobrança (fiscal real espera [W]).
- NÃO substitui a Lista (Index) — é uma visão alternativa (toggle Quadro|Lista).
- NÃO mexe no board de caçamba (ProducaoOficina) nem no processo `cacamba_*` legado.
- NÃO faz drag pra etapas terminais (Entregue/Cancelado/Garantia saem pelo drawer).

## UX targets
- Mover card → etapa avançada + toast em < 1.5s (1 request FSM + reload parcial).
- Card sem foto **esconde** o thumb (ícone câmera discreto sinaliza ação; sem texto "inacabado").
- Distinção visual clara entre "Aguardando aprovação" (âmbar · OK do cliente) e
  "Aguardando peças" (violeta · peça física).
- Acessível: cards com `role=button` + teclado (Enter abre; dnd-kit Space/setas movem).

## Automation hooks
- `router.reload({ only: ['columns','kpis'] })` pós-transição (SPA parcial).
- Drawer `ServiceOrderRichSheet` reusa FsmActionPanel (StartPipeline pra OS sem etapa).

## Anti-hooks (o que NUNCA fazer aqui)
- Nunca `$order->update(['current_stage_id' => ...])` — só via FSM service.
- Nunca hardcodar colunas: ler do payload `columns` (etapas reais do processo).
- Nunca exibir vocabulário de caçamba/locação (m³, diária, recolhimento) — é carro.
- Nunca cor de status crua `text-rose/emerald-700` — usar `text-destructive`/`text-success`.
