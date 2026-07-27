---
id: requisitos-oficina-auto-sdd-tela-ordem-servico-v1-0
slug: oficinaauto-sdd
title: "SDD — Ordem de Serviço da Oficina (domínio OficinaAuto / família OS + frota)"
type: sdd
module: OficinaAuto
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-07-27
related_docs:
  - SPEC.md
  - BRIEFING.md
  - ROADMAP.md
  - CAPTERRA-FICHA.md
  - SUPERFICIE.md
  - RUNBOOK-board.md
  - RUNBOOK-create.md
  - RUNBOOK-edit.md
  - RUNBOOK-show.md
  - RUNBOOK-index.md
  - RUNBOOK-fsm-pipeline.md
  - RUNBOOK-erradicacao-locacao.md
  - RUNBOOK-migracao-cliente-legacy.md
  - _telas/importer-frota-legada.casos.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0137-modules-oficinaauto-qualificada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0265-oficina-reparo-erradica-locacao
  - 0351-sdd-from-source
related_us:
  - US-OFICINA-001
  - US-OFICINA-002
  - US-OFICINA-003
  - US-OFICINA-004
  - US-OFICINA-012
  - US-OFICINA-014
  - US-OFICINA-035
  - US-OFICINA-038
  - US-OFICINA-039
  - US-OFICINA-040
  - US-OFICINA-041
  - US-OFICINA-042
---

# SDD — Software Design Document · Ordem de Serviço da Oficina (domínio OficinaAuto)

> **Derivado do fonte** pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)), chip da Onda 2 do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md). Formato imitado do
> [SDD do Produto](../Produto/SDD-tela-cadastro-produto-v1.0.md) — **não reaberto**.
>
> ⚠️ **Este módulo está LIVE em produção** (piloto Martinho, ~91 veículos reais). Tudo abaixo
> descreve **sistema vivo**: o documento **fotografa e contrata**, não propõe mudança de
> comportamento. Onde o código diverge do que um artefato dizia, a correção foi feita no
> **artefato** (regra de precedência de [proibicoes.md](../../proibicoes.md)) — nunca no código.

<!-- curado: foto que envelhece -->

## 0. Base empírica

### 0.1 Fontes de verdade cruzadas (Camada 1 do agent)

| # | Fonte | Estado neste módulo | O que rendeu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ `SPEC.md` (48 US) · `ROADMAP.md` · `BRIEFING.md` · `CAPTERRA-FICHA.md` · 9 charters · 6 RUNBOOKs | a âncora dos CU do §6 |
| 2 | **React/Laravel atual** | ✅ 9 telas · 9 Controllers · 14 Services · 4 Entities · 44 testes Pest | o §5 (fluxo vivo) |
| 3 | **Blade legada** | 🟡 **quase inexistente** — só `resources/views/oficina_auto/print/service_order.blade.php` (impressão da OS). O módulo **nasceu React/Inertia** ([ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md)), não migrou de Blade | não há paridade Blade→React a defender, **exceto** a impressão |
| 4 | **Delphi / Office Comercial** | ❌ **NÃO EXISTE** — `find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, **ambos do Produto** | **gap declarado**, não inventado (ver §0.2) |

### 0.2 O gap da fonte 4 — declarado, não preenchido

A oficina do Martinho vinha de **Firebird / WR Sistemas legado**, e o resíduo desse mundo entra
aqui por **importador** (`oficina:import-firebird-martinho`), não por tela migrada. Não existe
`ANTI-REGRESSAO-*.md` destilado do sistema legado da oficina — logo **não há contrato de paridade
Delphi**. A consequência honesta:

- Os CU do §6 derivam de **3 fontes** (canon + código + charter), não 4.
- **Nenhum** Non-Goal deste SDD pode ser justificado por "o legado não fazia" — não sabemos.
- A dívida de paridade que **existe e está nomeada** é a de **domínio**, não a de tela:
  o vocabulário de aluguel de equipamento herdado do enquadramento antigo, erradicado pela
  [ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md) (ver §3.1 e CU-OFI-19).

> Se um dia o manual do sistema antigo da oficina for destilado, ele entra como
> `ANTI-REGRESSAO-oficina-legacy.md` e este §0.2 vira histórico.

---

## 1. Visão geral

**A Oficina é reparo/mecânica, ponto.** O objeto central é a **Ordem de Serviço (OS)**: um
documento vivo que nasce quando um veículo de cliente entra no pátio, acumula vistoria, peças e
mão-de-obra, passa por aprovação do dono do veículo, é executado e — ao concluir — vira venda.

### 1.1 Família de telas (9, medidas por `screen-coverage`/`requisitos-status`)

| Tela | Rota | Papel |
|---|---|---|
| `ServiceOrders/Board` | `/oficina-auto/ordens-servico/board` | o pátio num relance (kanban FSM) |
| `ServiceOrders/Create` | `/oficina-auto/ordens-servico/create` | abrir a OS |
| `ServiceOrders/Show` | `/oficina-auto/ordens-servico/{id}` | **a tela-âncora** — a OS como documento vivo |
| `ServiceOrders/Edit` | `/oficina-auto/ordens-servico/{id}/edit` | manter a OS + itens |
| `Vehicles/Index` | `/oficina-auto/veiculos` | a frota dos clientes |
| `Vehicles/Create` | `/oficina-auto/veiculos/create` | cadastrar o veículo que chegou |
| `Vehicles/Edit` | `/oficina-auto/veiculos/{id}/edit` | manter o cadastro |
| `Vehicles/Show` | `/oficina-auto/veiculos/{id}` | a ficha + histórico de OS |
| `AprovacaoPublica` | `/aprovar-os/{token}` | **fora do ERP** — o cliente final aprova pelo celular |

Fluxos **sem tela React** (contrato mora em `memory/requisitos/OficinaAuto/_telas/`):
importador de frota legada (`_telas/importer-frota-legada.casos.md`).

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

### P1 · Martinho — mecânica pesada (piloto LIVE, ~91 veículos de clientes)
Recebe caminhão de terceiro para peça/serviço. Precisa: abrir OS rápido pela placa, lançar peça e
hora, mandar o orçamento pro dono do caminhão aprovar **pelo celular**, e fechar. Monitor de
balcão; clientes dele em Android simples (a tela pública tem que caber em 360px).

### P2 · Mecânico de box
Vê a OS que é dele, registra o que fez, sobe foto de laudo. Não decide preço.

### P3 · Cliente final (dono do veículo) — **não é usuário do ERP**
Recebe um link no WhatsApp + um PIN. Aprova ou rejeita em segundos, sem criar conta. É a única
persona que atravessa a fronteira de autenticação do sistema (§5.1).

### P4 · Wagner [W] — governança
Confere isolamento entre clientes, valor da OS, e se a venda derivada nasceu certa.

---

## 3. Governança aplicável

### 3.1 Tier 0 — IRREVOGÁVEL

| Invariante | Onde morde |
|---|---|
| **Multi-tenant `business_id`** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | global scope em `Vehicle`/`ServiceOrder`/`OaInspectionItem`; `business_id` **explícito** nas queries que furam scope (board/stages); token de aprovação carrega o business assinado |
| **Valor e estoque** (REGRA MESTRE, [proibicoes](../../proibicoes.md)) | total da OS · baixa de estoque na conclusão · venda derivada. Todo CU marcado `[V0]` |
| **Reparo é o único domínio** ([ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)) | `order_type ∈ {manutencao, mecanica}`; o vocabulário de aluguel de equipamento **não volta** como conceito de negócio. Gate `dominio:check` (**required**) contra `memory/dominio/oficina-auto.md` |
| **FSM é o único portão de estágio** ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) | avanço de etapa via `ExecuteStageActionService`; história append-only |

### 3.2 Fronteira com o núcleo

A OS **não reimplementa** venda nem estoque: ao concluir, ela **deriva** uma `Transaction` do
núcleo UltimatePOS ([ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)) e
baixa `variation_location_details` pelo caminho auditável do núcleo. Peça é produto do catálogo
core; mão-de-obra e serviço de terceiro **não** têm produto e **não** tocam estoque.

---

## 4. Design system aplicável

Herda a [Constituição UI v2](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md):
Shell `AppShellV2` + `PageHeader`; Board segue padrão kanban; Create/Edit em modo FOCO (sem
SubNav); Show é documento vivo com seções (resumo · itens · vistoria · aprovação · fiscal ·
linha do tempo). `AprovacaoPublica` é a **exceção**: página pública sem shell, mobile-first
360px, porque a persona P3 não tem conta.

Componente diferencial: **placa Mercosul** desenhada fiel ao padrão BR (feedback Martinho
2026-05-26, registrado no `Vehicles/Index.charter.md`).

---

## 5. Arquitetura

<!-- derivado: re-rodável do fonte -->

### 5.1 Visão em camadas

```
BALCÃO (autenticado, AppShellV2)                 PÚBLICO (sem conta)
  Pages/OficinaAuto/ServiceOrders/*                Pages/OficinaAuto/AprovacaoPublica
  Pages/OficinaAuto/Vehicles/*                             │
        │  Inertia                                          │  token HMAC + PIN
        ▼                                                   ▼
  ServiceOrderController · VehicleController          Public\AprovacaoOsController
  ServiceOrderItemController · DviInspectionController        │
  ServiceOrderPhotoController · ProducaoOficinaController     │
        │                                                    │
        ▼                                                    ▼
  ServiceOrderItemService · DviInspectionService · StageGateEvaluator
  ServiceOrderSummaryService · ServiceOrderPipelineStarter
  VehicleLookupService (PlacaProvider: stub | http) · VehicleQueryService
  CapacidadeService                                   AprovacaoOsService
        │
        ▼
  Entities: ServiceOrder · ServiceOrderItem · Vehicle · OaInspectionItem
  Observer: ServiceOrderObserver  ──► App\Transaction (núcleo) + estoque core
  FSM canônico (app/Domain/Fsm): ExecuteStageActionService · sale_stage_* (ADR 0143)
```

**Âncoras estáveis** (símbolo + `grep` que re-localiza — não `arquivo:linha`, que apodrece):

| Símbolo | Como re-achar |
|---|---|
| `ServiceOrderController@show` | `grep -n "function show" Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php` |
| `ServiceOrderController@board` | idem, `function board` |
| `ServiceOrderItemService::baixarEstoqueConclusao` | `grep -rn "baixarEstoqueConclusao" Modules/OficinaAuto` |
| `ServiceOrderObserver::updated` | `grep -n "function updated" Modules/OficinaAuto/Observers/ServiceOrderObserver.php` |
| `StageGateEvaluator::evaluate` | `grep -rn "function evaluate" Modules/OficinaAuto/Services/StageGateEvaluator.php` |
| `AprovacaoOsService::validarToken` / `::validarPin` | `grep -rn "validarToken\|validarPin" Modules/OficinaAuto/Services/AprovacaoOsService.php` |
| `VehicleLookupService::normalizePlate` | `grep -rn "normalizePlate" Modules/OficinaAuto` |

### 5.2 Modelo de dados (núcleo)

| Tabela | Papel | Nota Tier 0 |
|---|---|---|
| `vehicles` | frota **dos clientes** (não frota própria) — placa principal + secundária, chassi, ano, RENAVAM, km de entrada | `business_id` auto-populado no `creating`; soft delete preserva o histórico de OS |
| `service_orders` | a OS — veículo, contato, `order_type`, datas, `current_stage_id`, `transaction_id`, check-in de entrada | `order_type` governado pelo dicionário; global scope |
| `oficina_service_order_items` | itens: `tipo ∈ {peca, mao_obra, servico_terceiro}` + `product_id` opcional | só `peca` com `product_id` toca estoque |
| `oa_inspection_items` | vistoria digital (DVI): categoria · severidade · decisão do cliente | global scope + `creating` hook |
| `sale_process*` / `sale_stage_history` | pipeline e história append-only da OS | processo `oficina_mecanica_os`, per-business |

### 5.3 Fluxos críticos

> Cada `F<n>` foi mapeado lendo o Controller/Service real (Camada 1.2). Onde o fluxo tem
> ramo Tier 0, ele está marcado.

#### F1 · Abrir a OS
`Create.tsx → POST /oficina-auto/ordens-servico → ServiceOrderController@store` (via
`StoreServiceOrderRequest`) → persiste → **redirect** para `/oficina-auto/ordens-servico/{id}`.
O `vehicle_id`/`contact_id` são re-checados contra o `business_id` da sessão antes de gravar
(**T0**). `status` **não é campo do formulário** — quem move a OS é o FSM (F5).

#### F2 · Ver a OS (documento vivo)
`Show.tsx ← GET /oficina-auto/ordens-servico/{id} → ServiceOrderController@show`. Monta veículo,
contato, itens com subtotal, DVI, bloco de aprovação, card fiscal, venda derivada e a linha do
tempo. As stages do board vêm com `business_id` **explícito** (o processo FSM não tem global
scope) — é o ponto onde furar o scope seria vazamento.

#### F3 · Manter a OS e seus itens `[V0]`
`Edit.tsx → PUT /oficina-auto/ordens-servico/{id} → @update`; itens por rotas próprias
(`POST/PUT/DELETE .../items[/{item}] → ServiceOrderItemController → ServiceOrderItemService`).
O **total é servidor**: `ServiceOrderItemService::recalcularTotal` / accessor `total_items` somam
`valor_total` dos itens; o cliente nunca manda o total. `addItem` rejeita `product_id` de catálogo
de **outro** business (**T0**) e `tipo` fora de `{peca, mao_obra, servico_terceiro}`.

#### F4 · Baixa de estoque `[V0]` — **na conclusão, não na inclusão**
`ServiceOrderItemService::baixarEstoqueConclusao(businessId, osId)`: percorre os itens
`tipo=peca` **com** `product_id`, ignora produto sem controle de estoque (`enable_stock=0`) e
decrementa `variation_location_details.qty_available` pela quantidade, **salvando pelo modelo**
(o `save()` dispara o log de inventário do núcleo — caminho auditável). Idempotente: concluir a
mesma OS 2× não baixa em dobro. Itens de mão-de-obra / serviço de terceiro **não** tocam estoque.

> ⚠️ **Achado desta corrida:** o `Edit.casos.md` afirmava *"Quando adiciona um item peça → o
> estoque é baixado"*. **Falso** — o teste citado (`ServiceOrderItemStockBaixaTest`) prova a
> baixa **ao concluir**. Corrigido no artefato (precedência: teste > casos), código intocado.

#### F5 · Avançar de etapa (FSM) `[T0-processo]`
`Board.tsx` (drag) ou `ServiceOrderFsmActionPanel` → `POST .../service-orders/{order}/fsm/execute`
→ `ExecuteStageActionService` ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
→ move a stage **e** grava `sale_stage_history` (append-only). Transição não permitida pelo
processo lança exceção; sujeito de outro business não transiciona (**T0**).

#### F6 · Gate de etapa (opina antes de deixar avançar)
`GET .../fsm/gate → StageGateEvaluator::evaluate(order, processKey, actionKey)` devolve os
requisitos da transição. Requisitos **bloqueantes** derrubam o `execute` com 422 no servidor;
requisitos `manual` são advisory (o operador confere). Transição **sem regra cadastrada** não
bloqueia — é fail-open **por desenho**, para não travar o pátio com regra faltando.

#### F7 · Vistoria digital (DVI) e a conversão em orçamento
`POST/PUT/DELETE .../ordens-servico/{order}/dvi[/{item}] → DviInspectionController →
DviInspectionService`. Categoria e severidade são validadas contra listas fechadas; OS de outro
business é rejeitada (**T0**). `listarOrdenado` põe o crítico no topo; `totalRecomendado` soma
**só** `atencao + critico` (o que está `ok` não entra em orçamento). A conversão item-DVI →
item-da-OS é **idempotente** (reconverter devolve 409) e tem guarda cross-OS (404).

#### F8 · Aprovação do cliente pelo celular `[T0-fronteira]`
`ServiceOrderController@enviarAprovacao` → `AprovacaoOsService::gerarTokenAprovacao` (token HMAC
carregando o business assinado + PIN de 4 dígitos) → `EnviarLinkAprovacaoWhatsappJob`.
`GET /aprovar-os/{token} → Public\AprovacaoOsController@show`: token inválido/expirado devolve a
**mesma** tela de estado vazio, sem dizer qual condição quebrou (não vaza oráculo).
`POST /aprovar-os/{token}` valida PIN (`regex 4 dígitos`), com **lockout após 5 tentativas** e
PIN **one-shot**. Token de biz A não valida OS de biz B (**T0**). Aprovar move a OS pela FSM;
rejeitar **não** muda o status (idempotente).

#### F9 · Concluir a OS → venda derivada `[V0]`
`ServiceOrderObserver::updated`: ao entrar no terminal de **sucesso**, cria a `Transaction`
(`type=sell`, `source=oficina`, `os_ref=SO-{id}`) com `final_total` = soma dos itens, **duplo
guarda de idempotência** — pula se a OS já tem `transaction_id` **ou** se já existe Transaction
com aquele `os_ref` no mesmo business. OS sem itens gera venda com total 0 (comportamento legado
preservado: [W] edita depois). Falha ao criar a venda **não** bloqueia a transição de status —
é logada. Em seguida dispara F4 (baixa de estoque).

#### F10 · Cadastrar / manter o veículo
`Vehicles/Create|Edit → POST|PUT /oficina-auto/veiculos[/{id}] → VehicleController@store|update`.
`business_id` **não vem do formulário** — é populado pelo hook `creating` do modelo (**T0**).
`destroy` é **soft delete**: o veículo some da lista mas as OS antigas continuam íntegras.
`Vehicles/Show` carrega as OS do veículo (histórico).

#### F11 · Consulta de placa (auto-preenche dados técnicos)
`POST /oficina-auto/veiculos/consulta-placa → VehicleController@consultaPlaca → VehicleLookupService`
(adapter `PlacaProvider`: `stub` por padrão, `http` pluga fornecedor real por `.env`). A placa é
normalizada e validada antes de qualquer chamada externa; throttle na rota.
**Escopo travado ([W] 2026-06-09):** só dados técnicos — **nenhum dado do proprietário** é
consultado ou armazenado (PII de terceiro).

#### F12 · Importar a frota legada (sem tela)
`php artisan oficina:import-firebird-martinho` — exige `--business` explícito (**T0**), tem
`--dry-run` que não escreve, e preserva `legacy_id` para rastreabilidade. Contrato em
[`_telas/importer-frota-legada.casos.md`](_telas/importer-frota-legada.casos.md).

#### F13 · Imprimir a OS (**único resíduo Blade**)
`GET .../ordens-servico/{order}/print → ServiceOrderController@printInvoice` →
`resources/views/oficina_auto/print/service_order.blade.php`. Confere `business_id` do pedido
contra a sessão e **aborta 404** se divergir (**T0** explícito, sem depender de scope).

### 5.4 Onde o desenho ainda não fecha (dívida nomeada, não escondida)

| # | Dívida | Recibo |
|---|---|---|
| D-1 | `ServiceOrder` **não** usa a trait `GuardsFsmTransitions` — UPDATE direto em `current_stage_id` não é barrado no modelo (o portão é o Controller/Service) | `SPEC.md` US-OFICINA-006 `_parcial_`; `grep -rn "GuardsFsmTransitions" Modules/OficinaAuto` = 0 |
| D-2 | Painel fiscal da OS é **presentacional** — `FiscalSplitCard` mostra o split peça×serviço, mas **não emite** NF-e/NFS-e | `SPEC.md` US-OFICINA-018/042; **nenhum teste** cobre o card (varrido: 0 arquivos) |
| D-3 | Vocabulário vestigial de aluguel de equipamento ainda vive no **schema** (`vehicles.current_status`, `vehicles.vehicle_type`) e como **código morto** ramificando em `order_type` | `memory/dominio/oficina-auto.md` §Resíduo + `RUNBOOK-erradicacao-locacao.md`; débito absorvido no baseline do `dominio:check` |
| D-4 | O gate de etapa é **fail-open** para transição sem regra cadastrada | `StageGateEvaluator` — decisão de desenho, mas nunca ratificada por ADR |
| D-5 | `Board.casos.md` usa ids de UC **sem prefixo de tela** (`UC-01`…`UC-09`) — invisíveis para a porta `requisitos-status` e colidíveis entre módulos | ver §8 |

---

## 6. Casos de uso

<!-- derivado: re-rodável do fonte -->

> Marcadores: `[must]`/`[should]` · `[T0]` invariante multi-tenant · `[V0]` regra mestre
> valor/estoque. Status: ✅ coberto por teste que cita o UC · 🟡 parcial · ❌ ausente.
> **O veredito verde/vermelho é da lane**, nunca deste documento (G-7).

#### CU-OFI-01 — Cadastrar o veículo do cliente que chegou `[must]` ✅
Placa (Mercosul), chassi, tipo, ano, RENAVAM, km de entrada, cor. Suporta **duas placas**
(cavalo + reboque, mecânica pesada). `business_id` nunca vem do formulário.
→ `UC-OVC-01` · `UC-OVC-02` · `UC-OVC-03` · US-OFICINA-001 · US-AUTO-001

#### CU-OFI-02 — Consultar e manter a frota `[must]` ✅
Lista filtrável dos veículos **do business**, ficha com histórico de OS, edição, e remoção que
**preserva** o histórico (soft delete).
→ `UC-OVI-01` · `UC-OVI-03` · `UC-OVE-01` · `UC-OVE-02` · `UC-OVS-01` · US-OFICINA-001 · US-OFICINA-017

#### CU-OFI-03 — Consulta de placa auto-preenche dados técnicos `[should]` 🟡
Digitar a placa busca marca/ano/cor/combustível/chassi/RENAVAM por adapter pluggável.
**Non-goal explícito de produto:** dados do proprietário (PII de terceiro) **não** são
consultados nem armazenados.
→ `[BACKLOG]` neste chip — a prova existe (`tests/Feature/Modules/OficinaAuto/ConsultaPlacaEndpointTest.php`
e `PlacaLookupServiceTest.php`) mas **fora** da área de escrita do chip (§8). US-OFICINA-012 · US-AUTO-002

#### CU-OFI-04 — Abrir a OS ligada a veículo e cliente `[must]` ✅
A OS nasce no estado inicial da FSM; `status` não é campo do formulário; veículo/contato de outro
business são recusados.
→ `UC-OCR-01` · `UC-OCR-03` · `UC-OCR-04` · US-OFICINA-001 · US-AUTO-005

#### CU-OFI-05 — Itens da OS e o total `[V0]` ✅
Peça · mão-de-obra · serviço de terceiro. **O total é calculado no servidor** somando os itens —
o cliente nunca envia o total. Tipo fora da lista é recusado.
→ `UC-OED-02` · `UC-OED-08` · US-OFICINA-001

#### CU-OFI-06 — Baixa de estoque das peças **ao concluir** a OS `[V0]` ✅
Só item `tipo=peca` **com** `product_id` de produto com controle de estoque; caminho auditável;
idempotente; mão-de-obra não mexe em saldo.
→ `UC-OED-03` · `UC-OED-06` · `UC-OED-07` · US-OFICINA-001

#### CU-OFI-07 — Avançar a OS pelo pipeline `[must]` ✅
Toda mudança de etapa passa pelo serviço FSM e grava história append-only. Transição inválida é
recusada.
→ `UC-OSH-02` · `UC-OCR-03` · US-OFICINA-003 · US-OFICINA-006 · US-AUTO-005

#### CU-OFI-08 — O gate de etapa opina antes de deixar avançar `[should]` ✅
Requisito bloqueante derruba a ação no **servidor** (não só na tela); requisito manual é advisory.
→ `UC-OSH-10` · US-OFICINA-003

#### CU-OFI-09 — Vistoria digital item a item `[should]` ✅
Categoria e severidade fechadas; crítico no topo; total recomendado soma só o que precisa de ação.
→ `UC-OSH-06` · US-OFICINA-035

#### CU-OFI-10 — A vistoria vira orçamento na OS `[should]` ✅
Item da vistoria vira item da OS com o valor sugerido; reconverter é recusado; item de outra OS
é recusado.
→ `UC-OSH-07` · US-OFICINA-040

#### CU-OFI-11 — Check-in de entrada `[must]` ✅
Avarias observadas na entrada + nível de combustível, ambos opcionais e validados.
→ `UC-OSH-05` · US-OFICINA-038 · US-OFICINA-039

#### CU-OFI-12 — O cliente aprova pelo celular, sem conta `[must]` ✅
Link + PIN de 4 dígitos; lockout após 5 tentativas; PIN one-shot; token assinado por business;
estado vazio não revela qual condição quebrou; rejeitar não muda o status.
→ `UC-OAP-01` … `UC-OAP-07` · US-OFICINA-014 · US-AUTO-009

#### CU-OFI-13 — O balcão vê e cobra a aprovação `[should]` ✅
A OS mostra o estado de aprovação **derivado** das colunas de decisão; reenviar redispara o aviso.
→ `UC-OSH-08` · US-OFICINA-041

#### CU-OFI-14 — Ver o pátio num relance `[must]` 🟡
Kanban por etapa com contagem e KPIs; arrastar dispara a transição governada.
→ `Board.casos.md` (9 UC provados por Playwright) — **ids sem prefixo de tela**, ver §8 e D-5.
US-OFICINA-004 · US-AUTO-005

#### CU-OFI-15 — A oficina de um cliente não enxerga a do outro `[T0]` ✅
Veículo, OS, item, vistoria, token de aprovação e impressão — todos scopados.
→ `UC-OVI-02` · `UC-OVS-02` · `UC-OVC-03` · `UC-OSH-03` · `UC-OED-05` · `UC-OCR-04` · `UC-OAP-05`

#### CU-OFI-16 — Concluir a OS gera a venda `[V0]` ✅
Venda derivada com total = soma dos itens; **uma só vez** por OS; falha na venda não trava a OS.
→ `UC-OSH-09` · `UC-OSH-11` · US-OFICINA-001

#### CU-OFI-17 — Painel fiscal da OS `[should]` ❌ **presentacional**
A OS mostra o split peça (NF-e) × serviço (NFS-e), mas **não emite**.
→ `[BACKLOG]` — sem teste no repo (varrido: 0). US-OFICINA-042 · US-OFICINA-018

#### CU-OFI-18 — Importar a frota do cliente migrado `[should]` ✅
Comando exige business explícito, tem ensaio que não escreve, e preserva o identificador legado.
→ `UC-OIM-01` · `UC-OIM-02` · `UC-OIM-03` · US-OFICINA-002

#### CU-OFI-19 — **Não existe fluxo de aluguel de equipamento** `[T0]` ✅
Invariante de domínio ([ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)):
`order_type` só aceita os dois valores de reparo; o backend recusa o terceiro.
→ `UC-OCR-02`

### 6.1 Non-goals explícitos (por design, não regressão)

| Non-goal | Fonte |
|---|---|
| A tela pública **não** deixa o cliente editar valor/itens — só aprovar ou rejeitar | `AprovacaoPublica.charter.md` |
| A tela pública **não** substitui assinatura eletrônica formal | idem |
| A tela pública **não** mostra outras OS do cliente | idem |
| Consulta de placa **não** traz nem guarda dados do proprietário | `Vehicles/Create.charter.md` v2 ([W] 2026-06-09) |
| Remoção de veículo **não** é definitiva (preserva OS) | `Vehicles/Index.charter.md` |
| Importação de frota **não** é inline na tela (é comando) | idem |
| Aluguel de equipamento **não** é conceito de negócio | [ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md) |

> ⚠️ Non-goal é **intenção** — só [W] cria ou remove. Esta tabela **transcreve** os charters
> existentes; não inventa nenhum.

---

## 7. Requisitos não-funcionais

| NFR | Alvo | Origem |
|---|---|---|
| Tela pública renderiza em ≤ 800ms em 3G | p50 | `AprovacaoPublica.charter.md` |
| Tela pública usável em 360px sem zoom | — | idem (clientes do Martinho) |
| Board sem scroll horizontal em 1280px | — | `Board.casos.md` UC-09 (monitor de balcão) |
| Props caras da OS via `Inertia::defer` | — | RUNBOOK de defer do DS |
| Aprovação: anti-bruteforce (lockout 5) + anti-tampering (HMAC) | — | `AprovacaoOsService` |
| Toda transição de etapa auditável | append-only | [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) |

---

## 8. Rastreabilidade e enforcement

| Camada | Onde vive | Gate |
|---|---|---|
| US | `SPEC.md` (48) | `anchor-lint` (`**Implementado em:**`) |
| CU | **este §6** (19) | citado como âncora nos `casos.md` |
| UC | `Pages/OficinaAuto/*.casos.md` + `memory/requisitos/OficinaAuto/_telas/*.casos.md` | `casos-gate` G-2 (**required**) |
| Teste | `Modules/OficinaAuto/Tests/Feature/**` (44 arquivos) | ⚠️ ver abaixo |

### 8.1 Onde os testes deste módulo **rodam** — medido, não deduzido

Três portas diferentes respondem três perguntas diferentes; abaixo está **qual** foi medida:

| Pergunta | Porta medida | Resposta (2026-07-27) |
|---|---|---|
| roda em algum lugar? | `phpunit.xml` | ✅ **sim** — `<directory>./Modules/OficinaAuto/Tests/Feature</directory>` está registrado, logo entra na suíte completa noturna |
| roda no PR? | `grep OficinaAuto .github/workflows/*.yml` | ❌ **não rodava** — nenhuma lane executava esses 44 arquivos. **Este chip cria** `.github/workflows/oficinaauto-pest.yml` |
| bloqueia merge? | `governance/required-checks-baseline.json` | ❌ **não** — a lane nasce **advisory**; o baseline **não** foi tocado |

### 8.2 Dívida de id de UC no Board (D-5) — reportada, **não** consertada

`Board.casos.md` declara `UC-01`…`UC-09`, ids **sem prefixo de tela**. Consequências medidas:

- `casos-coverage-guard` (required) **aceita** — o regex dele tolera o formato curto, e os 9 UC
  **estão** provados por `e2e/oficina-uc06-gate-etapa.spec.ts`.
- `requisitos-status.mjs` **não aceita** (exige um segmento no meio) — por isso a porta imprime
  *"`Board.casos.md` existe mas não declara nenhum UC"*. É **falso-negativo da porta**, não
  ausência de contrato.
- Ids curtos **colidem entre módulos** — já causaram um bug conhecido (um UC de outra tela contou
  como coberto por um e2e da Oficina).

**Por que não foi consertado aqui:** renomear para `UC-OBD-NN` exige editar
`e2e/oficina-uc06-gate-etapa.spec.ts` — **fora** da área deste chip. Renomear só o `casos.md`
deixaria os 9 UC órfãos e **quebraria o `casos-gate` required**. Fica como achado para [W].

---

## 9. Riscos

| Risco | Mitigação atual |
|---|---|
| Piloto LIVE — qualquer regressão atinge cliente real | este chip **não muda comportamento**: só documento, contrato e nome de teste |
| Vocabulário erradicado voltar pela porta dos fundos | `dominio:check` **required** varre Pages/Seeders/Migrations do módulo |
| Venda derivada duplicada (dinheiro) | duplo guarda de idempotência (F9) + UC-OSH-11 |
| Estoque baixado em dobro | idempotência na conclusão (F4) + UC-OED-06 |
| Teste existir e nunca rodar no PR | lane criada (§8.1) — advisory, visível |

---

## 10. Roadmap do documento

| Versão | Data | O quê |
|---|---|---|
| 1.0.0 | 2026-07-27 | Nascimento. §0–§10 derivados de 3 fontes (canon + código + charter; fonte Delphi **inexistente**, declarada em §0.2). 19 CU. Achados: F4 (casos.md contradizia o código), D-2 (fiscal sem teste), D-5 (ids de UC do Board). Chip da Onda 2 do passo 5. |

### Próximos passos propostos (decisão [W])

1. **D-5** — renomear os UC do Board para `UC-OBD-NN` num PR que toque `e2e/` junto.
2. **D-2** — decidir se o painel fiscal ganha contrato (`CU-OFI-17`) ou vira Non-Goal explícito.
3. **D-1** — avaliar a trait de guarda de FSM em `ServiceOrder` (hoje o portão é só o Controller).
4. **D-4** — ratificar (ou reverter) o fail-open do gate de etapa sem regra cadastrada.
5. **CU-OFI-03** — anexar o UC da consulta de placa aos testes que já existem em `tests/Feature/Modules/OficinaAuto/`.
