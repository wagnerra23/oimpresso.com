---
status: proposal
title: Eixo de recurso (box/elevador + mecânico) no kanban da Oficina — filtro e foco reparo
proposed_by: Wagner + Claude
proposed_at: 2026-06-08
relates_to:
  - 0137-modules-oficinaauto-qualificada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0093-multi-tenant-isolation-tier-0
---

# PROPOSAL — Eixo de recurso (box/elevador + mecânico) no kanban da Oficina

> **Status:** `proposal` — Wagner promove pra ADR aceita (próximo número canônico, ~0263). **Tier 0** rebaixado a baixo-risco pela revisão abaixo (sem schema novo).

## ⚠️ REVISÃO 2026-06-08 — o dado JÁ EXISTE (premissa original corrigida)

> A premissa do "Contexto" abaixo (escrita antes de auditar o schema) está **errada**: `service_orders` **já tem** `box_label` (texto livre) + `assigned_user_id` (mecânico) desde a **Wave 2.1 US-OFICINA-027** (migration `2026_05_26_120001_add_box_and_assigned_user_to_service_orders.php`), com relação `assignedUser()` no model e exibição no drawer. **A decisão de domínio já foi tomada lá:** box é **texto livre, sem tabela**, "até sinal qualificado" ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)).
>
> **Consequência:** a proposta da **tabela `oficina_recursos`** (seção 1 abaixo) é **descartada por ora** — contradiz a decisão Wave 2.1 + ADR 0105 (nenhum cliente de reparo LIVE ainda; Martinho é mecânica pesada sem boxes). O **filtro funcional** foi implementado **sobre os campos existentes** (zero migration, zero Tier 0 de DB): `ProducaoOficinaController` projeta `box_label`/mecânico no card, filtra por `box`/`mecanico`, e expõe as opções distintas; a UI ganhou pills de box/mecânico (só aparecem quando há dado). Entregue no **PR do filtro box/mecânico** (2026-06-08).
>
> **A tabela `oficina_recursos` volta à mesa SE** aparecer cliente de reparo pagante que precise de agenda/capacidade por box (aí o texto-livre não basta). Até lá, **texto livre vence** (ADR 0105). O resto desta proposta fica como **registro do trade-off** e do desenho de tabela pra quando o sinal chegar.
>
> **Foco=Box/Mecânico (re-pivot do quadro)** segue como onda seguinte (muda a estrutura de colunas + semântica do drag) — não entrou no PR do filtro.

## Contexto (premissa original — ver revisão acima)

A convergência F3 da camada visível caçamba→reparo landou ([PR #2417](https://github.com/wagnerra23/oimpresso.com/pull/2417)): colunas, KPIs e vocabulário da tela `OficinaAuto/ProducaoOficina/Index` já são **reparo**. Mas o **filtro** ficou pela metade.

O handoff de design (`PROMPT_PARA_CODE_OFICINA-CONVERGE-CACAMBA-REPARO.md`, item #3) pede trocar o filtro de **Capacidade (m³ de caçamba)** pelo eixo do reparo: **filtro por box/elevador (recurso)** e, idealmente, o **Foco** (Etapa · Box · Mecânico) que re-pivota o quadro (gabarito `oficina-page.jsx` → `pivot`/`foco`).

No PR #2417 as pills de m³ foram **removidas** (vocabulário de caçamba; `capacity_m3 = null` pro cliente live → filtro inócuo). Um filtro **funcional** por box/elevador não foi implementado porque **não existe o dado**:

- `Vehicle` / `ServiceOrder` não têm conceito de **box/elevador** nem **mecânico atribuído**.
- `ProducaoOficinaController::projectVehicleCard()` não projeta recurso/mecânico.
- O controller só sabe filtrar por `capacity_m3` (caçamba) e busca livre `q`.

A Trava dura do handoff é explícita: **não migrar DB/seeder de cliente live dentro de um PR de design → subir como Tier 0**. Esta proposta é esse Tier 0.

## Decisão proposta

Introduzir o **eixo de recurso** (box/elevador) e **mecânico** no domínio da Oficina, de forma **aditiva e multi-tenant**, pra alimentar o filtro e o foco do kanban reparo.

### 1. Modelo de recurso (box/elevador) — por business

Tabela nova `oficina_recursos` (scopada `business_id`, [ADR 0093](../0093-multi-tenant-isolation-tier-0.md)):

| coluna | tipo | nota |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | bigint | global scope Tier 0 |
| `nome` | string | "Box 1", "Elevador 2" |
| `tipo` | enum(`box`,`elevador`) | dot rosa (box) / índigo (elevador) no gabarito |
| `ativo` | bool | soft-disable sem apagar histórico |
| timestamps | | |

> Alternativa mais leve (ver Alternativas): config JSON por business em vez de tabela. Tabela vence por permitir FK + contagem + futura agenda de box.

### 2. Atribuição na OS (aditivo, nullable)

Migration aditiva em `service_orders` (FSM table):

- `resource_id` bigint **nullable** FK → `oficina_recursos.id`.
- `mechanic_id` bigint **nullable** FK → `users.id` (mecânico responsável).

**Backfill:** OS existentes (incl. Martinho) ficam com `resource_id = null` / `mechanic_id = null` → caem em "Sem alocação" / "Sem mecânico". **Zero regressão** — nada quebra, ninguém é forçado a preencher.

### 3. Projeção + filtro no controller

- `projectVehicleCard()` passa a expor `recurso` (`{id, nome, tipo}`) e `mecanico` (`{id, nome, iniciais}`).
- `ProducaoOficinaController::index()` aceita `recurso` (id ou `_none`) e filtra as OS ativas por ele; remove de vez o ramo `capacidade`/`capacity_m3` (já morto na UI).
- Multi-tenant: recursos e filtro sempre sob `business_id` (global scope).

### 4. Filtro (mínimo viável) + Foco (onda seguinte)

- **Mínimo viável (esta ADR):** barra de tabs "Todos os boxes · Box 1..N · Elevador 1..N" com contagem, filtrando o kanban — espelha `prod-equip-filters` do gabarito.
- **Foco completo (onda seguinte, mesma ADR ou filha):** seletor Etapa · Box · Mecânico que **re-pivota** as colunas (gabarito `pivot`). Pode ficar pra PR 2 sobre este schema.

### 5. Relação com a dívida ADR 0194 (domínio caçamba→reparo)

Esta ADR é **um passo** da convergência de domínio que a [ADR 0194](../0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) abriu (`cacamba_locacao` → reparo). Ela **não** renomeia keys FSM nem migra `cacamba_locacao` — só **adiciona** o eixo de recurso/mecânico que o modelo reparo exige. A migração das keys FSM continua em trilha própria (a ser detalhada quando [W] priorizar).

## Consequências

**Positivo**
- Fecha o item #3 do handoff de design com dado real (filtro que filtra de verdade).
- Habilita o Foco (Box/Mecânico) — diferencial vs. concorrentes (Shopmonkey/Tekmetric usam alocação de bay).
- Aditivo + nullable + backfill null → **Martinho LIVE não regride**.
- Recurso por business → cada oficina configura seus boxes (multi-tenant nativo).

**Negativo / custo**
- 2 migrations (1 tabela nova + 2 colunas nullable) + seeder de recursos default por business + UI de cadastro de recurso (ou config inline).
- `projectVehicleCard` cresce; precisa eager-load de `resource`/`mechanic` (cuidar N+1).
- Atribuir box/mecânico vira passo novo no fluxo da OS (drawer/Edit) — escopo de UI a dimensionar.

**Tier 0 / risco**
- Toca schema com cliente LIVE → migration tem que ser idempotente, com `down()`, e testada em staging antes de prod ([criar-staging]).
- FK + global scope `business_id` obrigatórios (vazamento cross-tenant = pior bug).

## Alternativas consideradas

1. **Config JSON por business** (em vez de tabela `oficina_recursos`): mais rápido, mas sem FK/contagem/histórico e atrapalha o Foco=Box. Descartada como destino final; aceitável como MVP se [W] quiser velocidade.
2. **Reusar `capacity_m3`** re-rotulado: não — semântica errada (m³ ≠ box) e não cobre elevador/mecânico.
3. **Só visual (box fake, não-funcional):** rejeitada — controle que não filtra é mentira de UI (viola transparência / honestidade).
4. **Não fazer (manter só busca livre):** perde o eixo do reparo; o gabarito e o charter (inventário E "Filtro por box/elevador") pedem o eixo.

## Plano de implementação (pós-aceite)

1. Migration `oficina_recursos` + seeder default (3 boxes + 2 elevadores por business novo; opt-in pros existentes).
2. Migration aditiva `service_orders.resource_id` + `mechanic_id` (nullable, FK, `down()`).
3. Entity/relations + `projectVehicleCard` expõe recurso/mecânico (eager-load).
4. Controller: filtro `recurso`; remove ramo `capacidade`.
5. UI: tabs de recurso no filter bar (primitivos ADR 0253) + atribuição no drawer/Edit.
6. Foco=Box/Mecânico (re-pivot) — PR seguinte.
7. Testes: `FsmTransitionTest`/`ServiceOrderCrudTest` verdes + novo teste de scope multi-tenant do recurso. Staging antes de prod.

## Refs

- Handoff design: `prototipo-ui-patch/PROMPT_PARA_CODE_OFICINA-CONVERGE-CACAMBA-REPARO.md` (item #3)
- Gabarito: `oficina-page.jsx` (`RECURSOS`, `MECANICOS`, `pivot`, `prod-equip-filters`)
- Charter: `resources/js/Pages/OficinaAuto/ProducaoOficina/Index.charter.md` (inventário E)
- [ADR 0194](../0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (dívida de domínio caçamba→reparo)
- [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) · [ADR 0143](../0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
