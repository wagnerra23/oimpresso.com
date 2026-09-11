---
id: requisitos-asset-management-runbook-bens
title: "RUNBOOK — Patrimônio · Bens (`/asset/assets`)"
module: AssetManagement
tela: Patrimonio/Bens
owner: W
status: rascunho
last_validated: "2026-09-08"
preconditions:
  - "Usuário autenticado com a permission `asset.view` (declarada em `DataController::user_permissions()`, default `false`)"
  - "`business_id` na sessão — `Asset` NÃO tem global scope; o isolamento é filtro manual (ADR 0093, Tier 0)"
  - "Módulo `assetmanagement_module` habilitado no pacote do business (Camada 1 — superadmin/packages)"
  - "Middleware `AdminSidebarMenu` na rota — é ele que dispara `DataController::modifyAdminMenu()`, dono dos ghosts que a sub-navegação lê"
preconditions_short: permission asset.view, business_id na sessão, módulo habilitado, AdminSidebarMenu na rota
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header, 0394-endereco-de-ui-do-patrimonio-pages-patrimonio]
---

# RUNBOOK — Patrimônio · Bens (`/asset/assets`)

> **F1 PLAN do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).**
> Escrito ANTES do `.tsx`, como o hook `block-mwart-violation` exige — ele não tem override
> (medido 2026-08-08: zero `process.env`, única saída é `process.exit(2)`).
>
> **Bens é a PRIMEIRA tela Inertia do módulo.** Ela funda o `_shared/` e o primeiro
> `Inertia::render` do `Modules/AssetManagement`. As outras 6 telas herdam o que está aqui.

## 1. Objetivo

Migrar a **listagem de bens** de Blade + DataTables (yajra, server-side via `$request->ajax()`)
para Inertia/React, no endereço decidido pela [ADR 0394](../../decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md):
`resources/js/Pages/Patrimonio/Bens.tsx`.

**A URL não muda:** `GET /asset/assets` (rota `assets.index`, já existente via
`Route::resource`). O que muda é o que o método devolve.

## 2. Persona principal

Quem administra o patrimônio da empresa — o mesmo perfil que hoje abre `/asset/assets`
no Blade. Monitor de 1280px é o piso de largura (mesma restrição registrada em
`memory/regras-time.md`), então a tabela **rola horizontalmente dentro do próprio
wrapper** em vez de espremer coluna.

## 3. Pré-requisitos

Ver `preconditions` no frontmatter. Os dois que mais derrubam em runtime:

- **`asset.view` pode não existir na tabela `permissions`.** O seeder do módulo
  (`AssetManagementDatabaseSeeder`) é vazio; as permissões nascem sob demanda em
  `RoleController::__createPermissionIfNotExists()`, só quando alguém salva um Role.
  Num banco limpo, `can('asset.view')` devolve `false` por AUSÊNCIA — e o 403 provaria a
  coisa errada. Todo teste desta tela cria a `Permission` nos dois cenários.
- **`AdminSidebarMenu` na stack da rota.** Sem ele, `shell.menu` não tem a entry
  "Gestão de ativos" e a sub-navegação não renderiza (degrada pra `null`, não quebra).

## 4. Fluxo principal (golden path)

1. Usuário abre `/asset/assets`.
2. `AssetController::index()` verifica `asset.view` → depois a assinatura do módulo.
3. Devolve `Inertia::render('Patrimonio/Bens', ...)` com:
   - `bens` — **`Inertia::defer`** (paginator; é a prop cara: 3 `leftJoin` + agregação + eager-load)
   - `filtros`, `opcoes`, `permissoes` — eager (estado de UI, dropdowns pequenos, booleanos)
4. React pinta header + sub-navegação na hora; a tabela entra com `<Deferred>` + skeleton.
5. Filtrar/ordenar/buscar/paginar = `router.get('/asset/assets', {...})` — o `DataTable`
   compartilhado já faz isso (paginação, sort e busca são **server-side**).

## 5. Onda desta entrega, e o que fica pra depois

**Nesta onda (1 PR):** listagem + os 4 filtros que o backend JÁ tinha
(`location_id`, `category_id`, `purchase_type`, `is_allocatable`) + busca + ordenação +
paginação + a sub-navegação compartilhada.

**Fica pra depois, e o motivo — cada um é escolha, não esquecimento:**

| Adiado | Por quê |
|---|---|
| Sub-recortes "Garantia crítica" e "Em manutenção" (protótipo `:355`) | exigem **predicado SQL novo** (janela de garantia; `status` de manutenção). Filtrar só a página corrente seria mentir sobre o conjunto — a contagem da pílula diria "3" olhando 25 de 400 linhas. Onda própria, com o predicado no servidor. |
| **Total somado** no rodapé (`Σ valor × qtd`, protótipo `:400`) | é número de **VALOR** ⇒ REGRA MESTRE Tier 0 (`proibicoes.md`): exige prova por dois caminhos independentes + antes→depois apresentado ao [W]. Somar só a página seria falso; somar o conjunto filtrado é número novo que ninguém auditou. O valor **por linha** entra (é o que o Blade já mostrava). |
| Seleção em lote + BulkBar (slot 4 do PT-01) | as duas ações do protótipo ("Exportar seleção", "Enviar pra manutenção" em lote) não têm endpoint hoje. Anunciar botão sem destino é afordância falsa. |
| Colunas configuráveis, densidade, export CSV | fora do que o backend serve hoje; nenhuma delas é regressão vs. o Blade. |
| Drawer de criar/editar (slot 6) | `create`/`edit` seguem Blade nesta onda. O botão "Novo ativo" aponta pra rota Blade existente — link real, não `#`. |

**Regressão vs. o Blade: nenhuma pretendida.** O Blade mostrava ação por linha
(alocar / manutenção / editar / excluir), imagem, garantia e "n em manutenção" — tudo
isso entra. O que o Blade tinha e não entra: nada.

## 6. Estados (loading / empty / error / success)

| Estado | O que aparece |
|---|---|
| **loading** | skeleton de tabela via `<Deferred fallback>` — header e sub-navegação já pintados |
| **vazio (nenhum bem)** | empty state com CTA "Cadastrar o primeiro bem" (só se `permissoes.criar`) |
| **vazio por filtro/busca** | mensagem contextual do `DataTable` (`emptyMessage`), distinta do vazio real |
| **erro** | 403 do gate é página de erro do framework, não estado de tela; falha de rede é do router do Inertia |
| **sucesso** | tabela + paginação server-side |

## 7. Atalhos de teclado

Nenhum próprio nesta onda. `/` e `⌘K` continuam sendo do shell. Declarar atalho que a
tela não implementa é afordância falsa — quando a onda de seleção/densidade entrar, os
canônicos do PT-01 (`J`/`K`/`Enter`/`N`) entram com ela.

## 8. Dependências de API/backend

- `GET /asset/assets` → `AssetController::index()` — único endpoint da tela.
  Aceita `q`, `sort`, `dir`, `page`, `location_id`, `category_id`, `purchase_type`, `is_allocatable`.
- **O ramo `$request->ajax()` (DataTables/yajra) fica intacto e passa a ser inerte para
  esta tela.** Removê-lo é cutover (F5), não frontend — e o `.blade.php` não está no
  prefixo desta onda. Fica registrado aqui pra não virar "descoberta" depois.

## 9. Multi-tenant + LGPD

- `Asset` **não tem** global scope por business (o `SPEC.md` declara isso em `## Estado`).
  Todo filtro é manual: `->where('assets.business_id', $business_id)`.
- `permitted_locations()` continua restringindo por local — preservado do Blade.
- ⚠️ **RESÍDUO Tier 0 HERDADO, não introduzido por esta onda.** As agregações
  `allocated_qty` (join `AT`) e `revoked_qty` (subconsulta `AR`) do `index()` **não têm
  predicado de tenant**. É o gêmeo já catalogado em `_saida-01.md §9(a)`
  (*"o gêmeo `AssetController:97` é o mesmo defeito e tem maior alcance — é o índice"*),
  com thread dona. Esta onda **preserva a expressão byte-a-byte** e não a conserta, por
  duas leis que caem juntas: mexer em quantidade é REGRA MESTRE Tier 0 (prova dupla +
  antes→depois + [W]) e 1 PR = 1 intent. **Enquanto não fechar, `Alocado` não é número
  auditado.** A medição antes→depois está pronta no `_saida-06-bens.md` pra quem for fechar.
- Sem PII nova na tela: nome, código, modelo, série, categoria, local, quantidade, valor.

## 10. Smoke check pós-deploy

```bash
curl -sv https://oimpresso.com/asset/assets 2>&1 | grep '^< HTTP'
```

Depois, no HTML: o `data-page` tem de trazer o componente `Patrimonio/Bens` — é isso que
distingue "virou Inertia" de "continua Blade". Gate: usuário sem `asset.view` recebe 403.

Pest correspondente: `Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php`
(cenários `MORDE:` / `CN:` do gate), rodado no CT 100 — **nunca local**.

## 11. O que NÃO fazer

- ❌ **Não duplicar a lista de abas.** O dono é `DataController::modifyAdminMenu()`
  (`ghosts[]`), que chega ao React por `shell.menu`. Um array de abas escrito no
  `_shared/` criaria um segundo dono, que droga no primeiro rename. O `PatrimonioSubNav`
  **deriva**, não declara.
- ❌ **Não inventar aba.** O protótipo tem 7; o menu vivo tem 6 ghosts, com **Devoluções**
  (que o protótipo não tem) e sem **Garantias** / **Auditoria** — e essas duas são decisões
  de produto ABERTAS ([W], itens 4 e 5 do `00-INDICE.md §6`). Renderizar aba que não
  navega é afordância falsa.
- ❌ **Não somar valor na tela** sem cumprir a REGRA MESTRE (§5).
- ❌ **Não mexer na aritmética** de `allocated_qty` / `revoked_qty` (§9).
- ❌ **Não trocar a URL.** `assets.index` já existe; rota nova = segundo dono da mesma tela.
- ❌ **Não rodar Pest local** — CT 100, sempre.

## 12. Diagnóstico / Troubleshoot

| Sintoma | Causa provável |
|---|---|
| Sub-navegação não aparece | `shell.menu` sem a entry "Gestão de ativos" — módulo não assinado, usuário sem nenhuma permission `asset.*`, ou `AdminSidebarMenu` fora da rota |
| 403 na tela | falta `asset.view` **ou** a permission não existe no catálogo (ver §3) |
| Tabela vazia com dados no banco | `permitted_locations()` restringindo, ou `business_id` ausente na sessão |
| Tela em branco no build | glob do Inertia — `Pages/**/*.tsx` cobre este arquivo; conferir `pages-colisao --check` |

## 13. Refs

- [ADR 0394 — endereço de UI do Patrimônio](../../decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md)
- [ADR 0104 — MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0180 — sidebar v3, ghosts no header](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)
- [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md)
- Fonte visual: `prototipo-ui/cowork/Wagner/patrimonio-page.jsx` (aba `bens` em `:355`) — **alvo, não decisão de produto**
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/06-ui-bloqueada.md`
