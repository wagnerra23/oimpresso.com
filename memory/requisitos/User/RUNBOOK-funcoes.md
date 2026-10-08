---
slug: user-runbook-funcoes
title: "User — Runbook da tela Funções e permissões (Role)"
type: runbook
module: User
tela: Funcoes/Index
owner: W
status: ativo
last_validated: "2026-10-08"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Funções e permissões (`/roles`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/02`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/funcoes-page.jsx` (`FuncoesPage`) + `funcoes-perms.jsx` (catálogo agrupado).
> **Controller:** `App\Http\Controllers\RoleController` (`Route::resource('roles', …)`, `routes/web.php`).

## Estado final esperado

`GET /roles` responde Inertia `Funcoes/Index` **quando a flag `useV2SistemaFuncoes` está ligada para o negócio**;
desligada — o padrão — segue a Blade `role/index`. Cadastrar, editar e excluir gravam pelos endpoints de sempre
(`store`, `update`, `destroy`), com a mesma permissão.

## 1. Objetivo

Trocar a lista Blade (DataTable) pela Page React PT-01 sem mudar o que `store`, `update` e `destroy` gravam, nem as
permissões `roles.view|create|update|delete`.

## 2. Pré-condições

- Permissões: `roles.view` (lista), `roles.create`, `roles.update`, `roles.delete` — checadas em cada ação.
- Tabela `roles` do Spatie com `business_id` NOT NULL. **O Role não tem global scope** (`config/permission.php`):
  todo acesso filtra `business_id` à mão. Nome gravado como `<nome>#<business_id>`.
- A tabela `permissions` é **global** (sem `business_id`): só entra nome do catálogo (`App\Utils\PermissionCatalog`).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`funcoes-parity.md`](./funcoes-parity.md) + Pest baseline do comportamento da
   Blade (`tests/Feature/Roles/FuncoesBaselineTest.php`, lane `acessos-pest`, tenant 98 × 99). Controller intocado.
2. **F3-1:** `index()` ganha o ramo Inertia atrás da flag (padrão `PrinterController::FLAG_V2`); o ramo DataTable
   fica com `ajax() && ! inertia()`. Lista de funções com nº de usuários e marca de padrão; excluir confirma em
   diálogo. Cadastrar/editar levam à Blade (o formulário tem 153 controles do núcleo + os dos módulos).
3. **F3-2:** editor de permissões em React (catálogo agrupado vindo do servidor). Fica para depois da F3-1.
4. **F4:** smoke em biz=1 com a flag ligada só para biz=1 — decisão [W].
5. **F5 (cutover):** decisão [W] — não faz parte da thread.

## 4. Tokens CSS

Só tokens do DS. Sem cor crua (o `hue` por função do protótipo não tem fonte no banco; fica neutro).

## 5. Estados visuais

Lista · vazio · busca sem resultado · papel padrão (sem editar/excluir) · confirmação de exclusão · recusa
"em uso por N usuário(s)" (422) · aviso da última ação.

## 6. Responsividade

Tabela rola na horizontal abaixo de 768px.

## 7. Rollback

Desligar a flag `useV2SistemaFuncoes` (GrowthBook). Sem deploy: a Blade continua no repo até a F5.

## 8. Testes

- `FuncoesBaselineTest` (F2) — lista, cadastro, edição, exclusão e 403, todos com o par 98 × 99.
- `RoleTenantIsolationTest`, `RoleDeleteGuardTest`, `RolePermissionCatalogTest` — já existentes, mesma lane.
- Tudo na lane `acessos-pest` (MySQL real) e no CT 100. Nunca biz=4.

## 9. Pegadinhas

- `destroy()` só roda com `X-Requested-With` (o corpo inteiro está em `if (request()->ajax())`).
- O Inertia v3 manda `X-Requested-With` em toda visita: o ramo DataTable precisa de `! inertia()`.
- `update()` e `store()` engolem exceção num `catch` e devolvem redirect: o status não diz se gravou.
- `syncPermissions()` é destrutivo; o `update()` preserva o que o formulário não oferece (`__preservaNaoOfertadas`).
