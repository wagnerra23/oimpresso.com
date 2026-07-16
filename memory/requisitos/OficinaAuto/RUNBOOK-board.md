---
title: "RUNBOOK — Quadro (Kanban) de OS de Mecânica (OficinaAuto/ServiceOrders/Board)"
module: OficinaAuto
tela: OficinaAuto/ServiceOrders/Board
owner: W
status: ativo
last_validated: "2026-06-02"
related_adrs:
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0129-state-machine-canonica-fsm-rbac
  - 0093-multi-tenant-isolation-tier-0
preconditions:
  - "Seeder OficinaAutoFsmSeeder rodado pro business (cria processo oficina_mecanica_os)"
  - "Migration order_type enum estendido com 'mecanica'"
  - "Pelo menos 1 OS order_type='mecanica' com pipeline iniciado (current_stage_id em etapa do board)"
steps:
  - "Acessar /oficina-auto/ordens-servico/board (ou botão Quadro na Lista de OS)"
  - "Arrastar card entre colunas → confirmar no diálogo → FSM executa a transição"
  - "Clicar card → drawer ServiceOrderRichSheet (FsmActionPanel + DVI + fotos)"
  - "OS sem pipeline aparece na coluna Recepção (in_pipeline=false) — abrir e iniciar pipeline"
---

# RUNBOOK — Quadro (Kanban) de OS de Mecânica

> **Tipo:** RUNBOOK MWART/Cowork-port (ADR 0104 §F1 + ADR 0114). Port do protótipo
> Cowork "oficina-page.jsx" (o wow da homologação Kamila), confirmado [W] 2026-06-02.
> **NÃO confundir** com o Kanban de caçamba (`ProducaoOficina/Index`) — vertical legado
> (ADR 0194). Este é o **fluxo real do carro** (caminhão entra pra reparo/troca de peça).

> **Renomeado 2026-07-16** — era `RUNBOOK-serviceorders-board.md`. A convenção canônica
> (`proibicoes.md §MWART` + `block-mwart-violation.mjs`) é `RUNBOOK-<tela-kebab>.md`, e o
> hook resolve a Page pelo nome do arquivo (`Board.tsx` → `board`), ignorando a subpasta —
> então o nome qualificado com `serviceorders-` NUNCA era encontrado e este RUNBOOK
> (que existe e cobre a tela desde 2026-06-02) não contava: editar `Board.tsx` batia em
> bloqueio como se o F1 PLAN não existisse. O nome novo alinha aos 4 irmãos do módulo
> (`RUNBOOK-create/edit/index/show.md`, todos planos). Sem referências a atualizar
> (`grep serviceorders-board` = 0 fora deste arquivo). Rename via `git mv` (histórico
> preservado); conteúdo inalterado.

## 1. O que esta tela faz

Quadro Kanban das Ordens de Serviço de **mecânica** (`order_type='mecanica'`), com
colunas = etapas reais do processo FSM **`oficina_mecanica_os`**:

`Recepção → Diagnóstico → Aguardando aprovação → Aguardando peças → Em execução → Pronto p/ retirar`

(terminais fora do quadro: Entregue, Cancelado, Garantia acionada).

Arrastar um card entre colunas **executa a transição de etapa de verdade** via FSM —
nunca um UPDATE direto.

## 2. Arquitetura (peças e onde vivem)

| Peça | Arquivo |
|---|---|
| Página | `resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx` |
| Card (mods [W]) | `…/ServiceOrders/_components/board/ServiceOrderKanbanCard.tsx` |
| Coluna data-driven | `…/ServiceOrders/_components/board/ServiceOrderKanbanColumn.tsx` |
| Tons por etapa | `…/ServiceOrders/_components/board/boardTone.ts` |
| Controller | `Modules/OficinaAuto/Http/Controllers/ServiceOrderController@board` |
| Rota | `GET /oficina-auto/ordens-servico/board` (name `oficinaauto.orders.board`) |
| Processo FSM | `Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder@seedMecanicaOsProcess` |
| Endpoint transição | `POST /oficina-auto/service-orders/{order}/fsm/execute` (ServiceOrderFsmActionController) |
| Reuso canon | `KanbanDndProvider`, `DragConfirmDialog`, `ServiceOrderRichSheet`, `MercosulPlate` (em `ProducaoOficina/_components/`) |

## 3. Contrato de dados (props)

```ts
columns: { key, name, color, cards: ServiceOrderCardData[], count }[]
kpis:    { total, aguardando_aprovacao, aguardando_pecas, em_execucao, pronto_retirada, atrasadas }
process_seeded: boolean   // false → EmptyProcessState (rodar seeder)
filters: { q: string }
```

`ServiceOrderCardData`: `id, number, in_pipeline, plate, vehicle_type, cliente_nome,
thumb_url, dvi_done, dvi_total, dvi_critico, valor, mechanic_name/initials,
entered_at, expected_completion, is_overdue, notes, urls{show,edit}`.

## 4. Modificações [W]-aceitas implementadas

1. **Foto real no card** (`thumb_url` = 1ª foto de item DVI via Arquivos). Sem foto →
   thumb escondido (ícone câmera discreto sinaliza ação; **sem placeholder de texto**).
2. **Contador DVI x/y** com ícone de checklist (`ClipboardCheck`, não cadeado) +
   tooltip ("x de y itens decididos pelo cliente · N críticos"). `x` = itens com
   `client_decision` approved/rejected; `y` = total. Esconde se total=0.
3. **Densidade @1280** via `@container/board` (Tailwind v4 nativo) — KPIs compactos
   como base, expandem em telas largas. **Não usa @media** (lição Financeiro F3).
4. **"N OS"** (não "boxes") + colunas **Aguardando aprovação** (âmbar · OK do cliente)
   distinta de **Aguardando peças** (violeta · peça física).

## 5. Como rodar / validar (smoke)

```bash
# 1. Garantir processo FSM cadastrado pro business
php artisan db:seed --class="Modules\\OficinaAuto\\Database\\Seeders\\OficinaAutoFsmSeeder"

# 2. Migration enum order_type
php artisan migrate --path=Modules/OficinaAuto/Database/Migrations

# 3. Pest GUARD + smoke (MySQL — skip em SQLite por ADR 0101)
php artisan test Modules/OficinaAuto/Tests/Feature/ServiceOrderBoardTest.php
```

Smoke manual: criar OS tipo "Mecânica", abrir o card no quadro → "Iniciar pipeline" →
arrastar Recepção→Diagnóstico→…→Pronto p/ retirar, confirmando cada diálogo. Cada
transição grava `sale_stage_history` (auditável). data-testid: `so-card-{id}`,
`board-column-{stageKey}`, `board-count-{stageKey}`.

## 6. Guard rails (NÃO violar)

- **Multi-tenant Tier 0** (ADR 0093): `ServiceOrder` global scope + `SaleProcess`
  filtrado por `business_id`. Board nunca cruza tenant.
- **FSM canon** (ADR 0129/0143): transição SÓ via `ExecuteStageActionService`
  (flag-guarded). **Nunca** `update(['current_stage_id'=>…])`.
- **RBAC**: actions críticas (aprovação/conclusão/cancelamento) exigem role
  (mecanico/gerente). Fail-secure US-SELL-031.
- **Domínio**: vocabulário de **carro/reparo**, nunca caçamba/locação (m³, diária).

## 7. Pegadinhas conhecidas

- OS `order_type='mecanica'` **sem** `current_stage_id` cai na coluna Recepção com
  `in_pipeline=false` (drag desabilitado) — abrir o card e iniciar o pipeline.
- `STAGE_TRANSITIONS` no `Board.tsx` espelha o seeder; se mudar etapas no seeder,
  atualizar o mapa (ou migrar pra derivação dinâmica de `/fsm/actions` — follow-up).
- Deploy prod + emissão fiscal real → **espera [W]** (build é reversível/autônomo CI).
