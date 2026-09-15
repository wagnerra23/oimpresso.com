---
page: /asset/dashboard
component: resources/js/Pages/Patrimonio/Index.tsx
owner: wagner
status: draft
parent_module: AssetManagement
related_prototype: prototipo-ui/cowork/Wagner/patrimonio-page.jsx
related_runbook: memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md
related_adrs:
  - 0394-endereco-de-ui-do-patrimonio-pages-patrimonio
  - 0104-processo-mwart-canonico-unico-caminho
  - 0182-pageheadertabs-canon-pattern-telas
  - 0093-multi-tenant-isolation-tier-0
alcance:
  rota: /asset/dashboard
  rota_nome: null                     # a rota é `Route::get('dashboard', …)` SEM `->name()` (Routes/web.php:21)
  permission: asset.view              # DataController::user_permissions:31 — NÃO `patrimonio.access`
  menu_hook: Modules/AssetManagement/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: assetmanagement_module      # superadmin_package
tier: B
charter_version: 1
last_validated: "2026-09-08"
---

# Page Charter — Patrimonio/Index (Painel do Patrimônio)

> **DRAFT.** Nascida do PT-04 Dashboard via `criar-tela.mjs` (UI-0013 — herança de padrão).
> Sobe para `live` com screenshot aprovado por [W] (F1.5 · ADR 0107).
>
> ⚠️ **Cinco campos do `alcance` foram CORRIGIDOS depois do gerador.** O `criar-tela.mjs`
> infere o módulo do path (`Pages/Patrimonio/` → `Patrimonio`), e o módulo real é
> `AssetManagement`. Ele carimbou `parent_module: Patrimonio`, `permission: patrimonio.access`,
> `pacote: patrimonio_module`, `menu_hook: Modules/Patrimonio/…` (pasta inexistente) e
> `rota_nome: patrimonio.index` (a rota não tem `->name()`). Nenhum dos cinco existia no repo.
> Endereço de pasta ≠ nome do módulo — é a mesma distinção que a ADR 0394 fez para a sidebar.

## Mission

Responder, sem depender de memória: **o que a empresa tem, quanto vale, quem está com o quê e
o que está parado ou sem cobertura de garantia.**

## Goals — Features (faz)

- 4 KPIs: patrimônio bruto · valor residual · alocados X de Y · garantia vencida ou vencendo
- 3 análises com origem declarada: patrimônio por categoria · situação da garantia (4 baldes) ·
  manutenção em aberto
- "Resumo de hoje" em prosa, derivado dos mesmos números — nada digitado à mão
- Consome a barra de abas da área (`_shared/PatrimonioSubNav.tsx`), fundada pela tela de Bens
- Ramo não-admin: os bens alocados ao próprio usuário (capacidade que o painel Blade já tinha)
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

> Os três abaixo **não são escolha de produto minha** — são bloqueios medidos, cada um com dono
> declarado. Os Non-Goals de produto (o que a tela deliberadamente não deve fazer) seguem
> pendentes de [W]: `charter-write` é proibida de inferi-los.

- ❌ **Não calcula depreciação nem valor residual.** A coluna `assets.depreciation` é gravada como
  texto livre e nunca lida para conta nenhuma; a regra (linear ou SAC, com que fonte contábil) é
  decisão [W] — RESÍDUO 6 do playbook, dono `SPEC.md:96 US-ASSET-W01`. O KPI mostra `—`.
- ❌ **Não mostra custo de manutenção.** `asset_maintenances` não tem coluna de valor. RESÍDUO 3
  do playbook. A lista e o total mostram `—`, não zero.
- ❌ **Não cria rota para Garantias nem para Auditoria.** As duas abas existem no protótipo e não
  no backend. A sub-navegação **deriva** do `shell.menu` e por isso simplesmente não as mostra —
  decisão da tela de Bens, que a fundou (*"renderizar aba que não navega é afordância falsa"*).
  Bloqueios `D-GARANTIAS` e `D-AUDITORIA`.

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)
- Primeiro paint sem esperar agregação: as 5 props caras são `Inertia::defer` com skeleton
- Todo painel tem estado vazio explícito (`EmptyState`), nenhum some quando o dado é 0

## Regras que a tela respeita

- **R3 (garantia é janela).** `asset_warranties.start_date/end_date` vs hoje → na garantia /
  vence em ≤30 dias / vencida. **Sem registro ≠ vencida** — é um quarto balde, "sem garantia".
- **Tier 0 multi-tenant (ADR 0093).** `asset_warranties` não tem `business_id`: toda leitura de
  garantia entra por `join` com `assets` filtrando `assets.business_id`.
- **Quantidade é decimal.** `assets.quantity` é `decimal(22,4)` e os cards somam **quantidade**,
  não contam registros — a tela não arredonda para inteiro.

## Refs

- Padrão de Tela: PT-04 Dashboard (KpiGrid + KpiCard) · Constituição UI v2: UI-0013
- RUNBOOK: `memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md`
- Casos: `Index.casos.md` (irmão)
