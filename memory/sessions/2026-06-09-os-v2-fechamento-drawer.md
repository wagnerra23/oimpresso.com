---
date: "2026-06-09"
topic: "Fechamento total do drawer de OS (ServiceOrderRichSheet) — OS-V2-3..6 (gate aprovação, timeline FSM, StageGate, item inline)"
authors: [C, W]
related_adrs: [0265-oficina-reparo-erradica-locacao, 0093-multi-tenant-isolation-tier-0, 0143-fsm-pipeline-live-prod-marco-2026-05-12, 0105-cliente-como-sinal-guiar-sem-mandar]
---

# Fechamento do drawer de OS — OS-V2-3..6 (F3)

Branch `feat/oficina-os-drawer-fechamento` (off `origin/main` @ #2482). 4 itens F1-verificados e F2-aprovados por [W] 2026-06-09. Continuação do batch que landou OS-V2-1 (Fotos & Laudo) + OS-V2-2 (DVI inline) em [#2482](https://github.com/wagnerra23/oimpresso.com/pull/2482).

## O que entrou

- **OS-V2-3 · Gate de aprovação com ciclo de estados.** `DviGateFoot` (4 estados: none → pending → approved | declined) no `DviInlineEditor`, derivados do backend — migration `approval_requested_at`/`approval_decided_at`/`approval_decision` em `service_orders` + accessor `ServiceOrder::approval_state`. `enviarAprovacao` carimba `requested_at` e re-dispara WhatsApp no "Cobrar" (limpa a chave de idempotência de 7d do Job). `AprovacaoOsController` carimba a decisão (`approved`/`declined`). **Sem botões de simulação** (eram demo-only no protótipo).
- **OS-V2-4 · Timeline FSM auditável.** Drawer passou a usar `ServiceOrderTimeline` real (endpoint **existente** `/service-orders/{id}/history`) com fallback pra `TimelineSkeleton` quando a OS antiga não tem histórico.
- **OS-V2-5 · StageGate (checklist de etapa).** Seção nova entre Peças e Pipeline FSM (`ServiceOrderStageGate`). Requisitos data-driven por transição (`StageGateEvaluator`, config `oficina_mecanica_os`). **Gate ENFORÇADO no servidor**: `ServiceOrderFsmActionController::execute` retorna 422 quando há bloqueante pendente; a UI é espelho (FsmActionPanel desabilita + tooltip; CTA "Avançar" do StageGate). Override de gerente/superadmin registrado em `sale_stage_history.payload_snapshot`. Novo endpoint `GET /service-orders/{id}/fsm/gate`.
- **OS-V2-6 · Lançar item inline.** "+ Adicionar item" abre o `ServiceOrderItemFormSheet` (nested, sem fechar o drawer) + Editar/Remover por item via `ServiceOrderItemRow`; refetch atualiza Total OS. Touch ≥44px.

Testes Pest: `ServiceOrderApprovalGateTest` (4), `ServiceOrderStageGateTest` (4). MySQL-only (ADR 0101) — skipam local (sqlite), rodam no CI.

## Deltas vs handoff (intenção preservada, infra real divergiu)

1. **Timeline route** é `/service-orders/{id}/history` (já existia + tem teste), não `/fsm/history` como o handoff supôs. Reusei o existente.
2. **Gate enforçado no controller do módulo** (`ServiceOrderFsmActionController`), não no `App\Domain\Fsm\ExecuteStageActionService` compartilhado — pra não arriscar o Sells (FSM LIVE prod biz=1). Continua server-authoritative.
3. **Config do gate** vive como const de módulo (`StageGateEvaluator::RULES`), data-driven server-side, não em tabela/seeder (ADR 0105 — sem tabela nova sem sinal). Migrar pra DB é trivial depois.
4. **"concluir_servico"** não tem flag "item concluído" no schema → regra manual/advisory + um bloqueante "≥1 item".
5. Itens **manuais** da checklist persistem em localStorage (advisory, não bloqueiam o servidor) — espelha o protótipo.
6. **"Revisar e reenviar"** (declined) re-envia direto (→pending); a DVI é editável a qualquer momento.
7. **Item kebab** = ações inline Pencil/Trash (reuso de `ServiceOrderItemRow`, consistente com Show/Edit), não um menu kebab literal.
8. **Eventos de aprovação na trilha**: aparecem como transições FSM quando feitas pelo pipeline (`enviar_orcamento`/`aprovar_*`/`recusar_orcamento`) + via estado do gate; a linha não-FSM do "Pedir aprovação" status-based não vira entry separada de `sale_stage_history` (deferido).
9. **Sem screenshots** nesta sessão — o ambiente não roda o app Inertia + browser; gate visual fica pra [W] no preview/F3.
10. O doc de sessão `2026-06-09-auditoria-lista-kanban-fechamento.md` do handoff retornou 404 (token expirado) — este log substitui.

## Validação pré-CI

`php -l` (8 arquivos) ✓ · `tsc --noEmit` sem erros nos arquivos tocados ✓ · ESLint 0 erros + 0 warnings novos no baseline (`path|rule` count) ✓ · Pest carrega os 2 arquivos sem colisão (8 skipped local sqlite) ✓.
