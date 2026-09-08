---
id: requisitos-asset-management-runbook-manutencoes
title: "RUNBOOK — Patrimônio · Manutenções (`/asset/asset-maintenance`)"
module: AssetManagement
tela: Patrimonio/Manutencoes
owner: W
status: rascunho
last_validated: "2026-09-08"
preconditions:
  - "Usuário autenticado com `asset.view_all_maintenance` OU `asset.view_own_maintenance` — as duas são `is_radio` com o mesmo `radio_input_name`, logo MUTUAMENTE EXCLUSIVAS na UI de papéis (`DataController.php:51` e `:58`)"
  - "`business_id` na sessão — `AssetMaintenance` NÃO tem global scope; o isolamento é filtro manual (ADR 0093, Tier 0)"
  - "Módulo `assetmanagement_module` habilitado no pacote do business (Camada 1 — superadmin/packages)"
  - "Middleware `AdminSidebarMenu` na rota — é ele que dispara `DataController::modifyAdminMenu()`, dono dos ghosts que a sub-navegação lê"
preconditions_short: uma das 2 permissions de manutenção, business_id na sessão, módulo habilitado, AdminSidebarMenu na rota
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header, 0394-endereco-de-ui-do-patrimonio-pages-patrimonio, 0253-primitivos-de-layout]
---

# RUNBOOK — Patrimônio · Manutenções (`/asset/asset-maintenance`)

> **F1 PLAN do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).**
> Escrito ANTES do `.tsx`, como o hook `block-mwart-violation` exige — ele não tem override
> (medido 2026-08-08: zero `process.env`, única saída é `process.exit(2)`).
>
> **Segunda tela Inertia do módulo.** Não funda nada: herda o `_shared/PatrimonioSubNav.tsx`
> que [Bens](RUNBOOK-bens.md) criou em 2026-09-08 (#7035).

## 1. Objetivo

Listar as manutenções de bens do patrimônio — o que está fora de operação, com quem, em que
prioridade e há quanto tempo — preservando **exatamente** o que a tela Blade
(`asset_maintenance/index.blade.php`) já entregava, sem inventar dado que o banco não guarda.

## 2. Persona principal

Quem opera o patrimônio no dia a dia. **Dois perfis, e a tela sabe a diferença:**

- **vê todas** (`asset.view_all_maintenance`) — enxerga a fila inteira do business;
- **vê só as suas** (`asset.view_own_maintenance`) — enxerga onde é `created_by` ou `assigned_to`.

Os dois são **mutuamente exclusivos** na UI de papéis (`is_radio`, mesmo `radio_input_name`).
A tela **diz ao usuário restrito que ele está vendo um recorte** — o Blade não dizia, e silêncio
sobre escopo é a diferença entre "não há manutenção" e "não há manutenção *minha*".

## 3. Pré-requisitos

Estão no frontmatter. O que merece destaque:

- **A guarda da rota foi consertada em 2026-09-08** ([#7034](https://github.com/wagnerra23/oimpresso.com/pull/7034)):
  até ali o gate exigia as DUAS permissões com `&&` (insatisfazível por construção) e o
  `|| subscription` anulava tudo. Hoje são dois `if` sequenciais — permissão de tela primeiro,
  assinatura depois. **A tela não reimplementa isso**; ela herda.
- O dono do negócio passa pelo `Gate::before` (`AuthServiceProvider:34-46`, role `Admin#{business_id}`)
  em qualquer ability fora de `backup`/`superadmin`/`manage_modules`.

## 4. Fluxo principal (golden path)

1. Usuário abre `/asset/asset-maintenance` pelo ghost "Manutenções" da sub-navegação.
2. O `index()` responde `Inertia::render('Patrimonio/Manutencoes')` com filtros, opções e
   permissões **eager**, e a lista **deferida** (`Inertia::defer`).
3. Primeiro paint: cabeçalho + sub-nav + filtros + esqueleto da tabela.
4. A lista chega e a tabela renderiza. Quem tem só `view_own_maintenance` vê, acima dela, o
   aviso de que a lista está recortada.
5. Filtrar (status / prioridade / responsável) ou buscar **navega** — o estado mora na URL.
6. Clicar em editar abre `/asset/asset-maintenance/{id}/edit`; excluir pede confirmação e faz
   `DELETE` na rota `resource`.

## 5. Onda desta entrega, e o que fica pra depois

**Entra — paridade 1:1 com o Blade**, as 10 colunas que ele mostrava: código, bem, situação,
prioridade, garantia, detalhes, enviado em, atribuído a, criado por, ações. Mais os 3 filtros
que ele já tinha (status, prioridade, responsável), a busca, e o aviso de escopo restrito.

**Fica pra depois, e cada um tem motivo — não é esquecimento:**

- **Custo.** O protótipo mostra coluna `Custo` e dois KPIs de dinheiro ("Custo no ano", "Maior
  conserto"). **A tabela `asset_maintenances` não tem coluna de valor** — as colunas reais são
  `business_id · asset_id · maitenance_id · status · priority · created_by · assigned_to ·
  details · maintenance_note` + timestamps. O Blade também não mostra custo (medido: 0 menções
  a `cost|custo|amount|valor|price` nas 3 views, com controle positivo). **Decisão [W]
  2026-09-08: a tela migrada é igual ao Blade — sem custo.** Não é decisão nova: era pergunta
  que o comportamento vivo já respondia. Registrado no §6 item 3 do playbook.
- **Prestador e "Devolvido"** (colunas do protótipo): mesma razão — não existem no banco.
- **KPIs do topo.** Além dos dois de dinheiro, o "Em aberto" é contagem sobre o conjunto INTEIRO,
  não sobre a página. Fazê-lo na página corrente daria um número que mente (é o critério que
  [Bens §5](RUNBOOK-bens.md) usou para adiar os sub-recortes). Exige agregação no servidor.
- **"Concluir"** (ação primária do protótipo, que fecharia a OS e criaria título a pagar no
  Financeiro): não há endpoint, e o efeito colateral é **financeiro** — pede a REGRA MESTRE de
  VALOR (prova por dois caminhos + antes→depois pro [W]), não cabe numa migração de tela.

Nada disso é regressão: **tudo que o Blade mostrava está na tela nova.**

## 6. Estados (loading / empty / error / success)

| estado | o que a tela faz |
|---|---|
| **loading** | esqueleto da tabela (`Deferred fallback`) — cabeçalho, sub-nav e filtros já pintados |
| **empty (sem filtro)** | `EmptyState`: não há manutenção registrada. **Sem CTA de criar** — o fluxo nasce em Bens, e o Blade também não tinha botão aqui |
| **empty (com filtro)** | mensagem do `DataTable` pedindo para limpar o recorte — vazio de filtro ≠ vazio de dado |
| **empty (escopo restrito)** | o aviso de `view_own_maintenance` fica visível junto do vazio: "não há manutenção **sua**" é diferente de "não há manutenção" |
| **error** | erro de rede cai no tratamento padrão do Inertia; a guarda de permissão responde 403 antes de renderizar |

## 7. Atalhos de teclado

Nenhum próprio. Os do `DataTable` compartilhado valem (busca, paginação).

## 8. Dependências de API/backend

- `GET /asset/asset-maintenance` → `AssetMaitenanceController::index()` (Inertia + ramo `ajax`
  legado preservado)
- `GET /asset/asset-maintenance/{id}/edit` → modal de edição (**segue Blade nesta onda**)
- `DELETE /asset/asset-maintenance/{id}` → `destroy()`
- `GET /asset/asset-maintenance/create?asset_id=N` → criação, alcançada **a partir de Bens**

## 9. Multi-tenant + LGPD

- **Tier 0:** `AssetMaintenance` **não tem global scope**. O `index()` filtra
  `where('asset_maintenances.business_id', $business_id)` explicitamente — o payload novo usa a
  **mesma** query base, não uma paralela.
- **Escopo por dono:** quem tem só `view_own_maintenance` recebe
  `created_by = auth()->id OR assigned_to = auth()->id`. Esse filtro **já existia** (`:73`) e foi
  escrito para o perfil que o gate quebrado barrava.
- **Resíduo declarado, NÃO consertado aqui** (decisão de produto — [W]): `edit`, `update` e
  `destroy` filtram só por `business_id`, **não por dono**. Quem tem apenas `view_own_maintenance`
  pode editar/remover manutenção de outro se souber o id. **Não é regressão** — antes do #7034
  qualquer usuário do business já podia — e fechar exige permissão de escrita que o módulo não
  declara. A tela **preserva** o comportamento do Blade, que mostra editar/excluir em toda linha
  sem `can()` nenhum.
- **PII:** nomes de usuário (`assigned_to`, `created_by`) aparecem na lista, como no Blade.
  Nada de CPF/CNPJ; nada de valor monetário.

## 10. Smoke check pós-deploy

```bash
curl -sv https://oimpresso.com/asset/asset-maintenance 2>&1 | grep '^< HTTP'   # 302 sem sessão
```

⚠️ **O 302 NÃO prova a guarda** — vem do middleware `auth`, que roda antes do controller. Quem
prova o comportamento de permissão é `MaintenanceAuthGateTest` no CT 100 (MySQL + sessão reais).
Com sessão, a tela deve renderizar a tabela e, para papel restrito, o aviso de escopo.

## 11. O que NÃO fazer

- ⛔ **Não inventar coluna de custo, nem migration para ela.** O protótipo mostra; o banco não
  guarda; o Blade não mostra. Decisão [W] 2026-09-08: igual ao Blade.
- ⛔ **Não replicar os nomes de `getActivitylogOptions()`**: o Model audita `start_date`, `end_date`
  e `amount` — **as três não existem** na tabela (achado do `_saida-04.md §2a-bis`).
- ⛔ **Não reimplementar a guarda de permissão na tela.** Ela é do controller (#7034).
- ⛔ **Não derivar "garantia crítica" no cliente** — é recorte que o servidor não tem.
- ⛔ **Não usar `<div className="flex">` solto**: layout por primitivos `Stack`/`Inline`
  ([ADR 0253](../../decisions/0253-primitivos-de-layout.md)); o `layout-primitives-guard` é catraca.
- ⛔ **Não passar `<SelectItem value="">`** ao Radix — string vazia é o valor interno de "nada
  selecionado" e quebra o componente (§5 2026-06-29). Use a sentinela `TODAS`.

## 12. Diagnóstico / Troubleshoot

| sintoma | causa provável |
|---|---|
| sub-navegação some | `shell.menu` sem a entry "Gestão de ativos" — módulo não assinado, usuário sem nenhuma permission `asset.*`, ou rota sem `AdminSidebarMenu`. O `PatrimonioSubNav` **degrada para `null`**, nunca para erro |
| 403 na rota | usuário não tem nenhuma das duas permissions — comportamento correto desde o #7034 |
| lista vazia para quem deveria ver | conferir se o papel tem `view_own_maintenance` (recorte por dono) antes de suspeitar de dado |
| tabela não rola na horizontal | largura declarada faltando em coluna nova (`meta.width`) — sem ela a tabela sai de `table-layout: fixed` |

## 13. Refs

- Tela: [`resources/js/Pages/Patrimonio/Manutencoes.tsx`](../../../resources/js/Pages/Patrimonio/Manutencoes.tsx)
  · charter e casos ao lado
- Controller: `Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php`
- Blade de origem: `Modules/AssetManagement/Resources/views/asset_maintenance/index.blade.php`
- Fonte visual: `prototipo-ui/cowork/patrimonio-page.jsx`, `AbaManutencoes` (`:475`) — **ALVO,
  não decisão de produto** (`06-ui-bloqueada.md`)
- Playbook: [`_saida-06-manutencoes.md`](../../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-06-manutencoes.md)
