---
slug: user-runbook-usuarios
title: "Usuários — Runbook da tela Usuários (ManageUser)"
type: runbook
module: User
tela: Usuarios/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Usuários (`/users`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/01`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/usuarios-page.jsx` (`UsuariosPage`) + `usuarios-page.css`. Mapa Blade ↔ protótipo: `sistema/playbook/_saida-00.md`.
> **Controller:** `App\Http\Controllers\ManageUserController` (`Route::resource('users', …)`).

## Estado final esperado

`GET /users` responde Inertia `Usuarios/Index` **quando a chave `mwart.sistema_usuarios_index` está ligada
para a empresa** (ou a visita já é Inertia). Com a chave desligada — o padrão — segue a Blade
`manage_user/index`. A chamada AJAX sem `X-Inertia` continua devolvendo o JSON da DataTable, então a
Blade funciona durante toda a migração. Ligar a chave por empresa é a F5, decisão do [W].

## 1. Objetivo

Lista de usuários no padrão PT-01 (busca, filtro de função e de situação, tabela) e um drawer de detalhe
(PT-02) como no protótipo. Cadastrar e editar continuam nas telas Blade `create`/`edit` nesta migração:
o formulário legado tem ~30 campos (RH, banco, módulos via `moduleViewPartials`) e o `update()` zera o
que não chega no corpo (login, contatos permitidos). Levar o formulário para drawer é PR próprio.

## 2. Pré-condições

- Permissões: `user.view`, `user.create`, `user.update`, `user.delete` — as de sempre. Nenhuma nova.
- Tabelas: `users` (escopo `business_id` + `->user()` + `is_cmmsn_agnt = 0`), `roles` (`Nome#<biz>`),
  `user_contact_access`, permissões `location.<id>` / `access_all_locations` (Spatie).
- Chave: `config/mwart.php` → `sistema_usuarios_index` (`MWART_SISTEMA_USUARIOS_INDEX` +
  `MWART_SISTEMA_USUARIOS_INDEX_BIZ`). Nasce desligada.

## 3. Passo-a-passo (fases)

1. **F1 PLAN** — este RUNBOOK.
2. **F2 BACKEND BASELINE** — `tests/Feature/Users/UsuariosContratoTest.php` caracteriza `store` (5
   fixtures), `update`, `destroy`, `edit` e a DataTable de `index` **antes** de qualquer mudança, no
   tenant 98 × cliente fictício 99 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
   Lane `acessos-pest` (ganha o controller e as views nos gatilhos).
3. **F2b Tier 0** — PR próprio, teste vermelho antes do conserto (ver §10).
4. **F3 FRONTEND** — `index()` ganha o ramo `Mwart::telaReact('sistema_usuarios_index')` antes do
   `ajax()`; props `usuarios` (deferido), `funcoes`, `pode`. Page `Usuarios/Index.tsx` + charter + casos
   no mesmo PR.
5. **F4 QA** — Pest de contrato verde na lane + smoke manual em biz=1 com a chave ligada só lá.
6. **F5 CUTOVER** — **não é desta thread.** [W] liga a chave por empresa.

## 4. Tokens CSS

Só tokens do DS (`text-muted-foreground`, `bg-muted`, `border`, `text-destructive`, variantes de `Badge`).
As cores por função (`ROLE_TONE`) e o avatar por hash do protótipo viram neutros: função é dado do
cliente, não há paleta fixa para ela.

## 5. Estados visuais

Carregando (deferido) · lista · vazio (só o próprio usuário) · sem resultado na busca/filtro · drawer de
detalhe · confirmação de excluir · aviso da última ação.

## 6. Responsividade

Tabela rola na horizontal abaixo de 768px; drawer ocupa a largura em tela estreita. Alvo 1280px.

## 7. Atalhos

`/` foca a busca.

## 8. Component contract

Seções `data-contract`: `page-header`, `kpis`, `toolbar`, `usuarios-table`, `vazio`, `usuario-drawer`,
`confirm-excluir`. Contrato de forma: depois do alvo medido.

## 9. DoD checklist

- [ ] Baseline Pest de `store/update/destroy/edit/index` verde na lane `acessos-pest`
- [ ] Tier 0 da §10 com teste vermelho → verde
- [ ] Ramo Inertia atrás da chave, Blade intacta como fallback
- [ ] Trio `Usuarios/Index` (`.tsx` + charter + casos) com UC citado por teste
- [ ] Smoke biz=1 com a chave ligada só lá (R1)
- [ ] F5 / remoção das Blades `manage_user/*` — decisão [W]

## 10. Pegadinhas e divergências do protótipo

- **Hipóteses Tier 0 (lidas, não provadas — viram teste vermelho na F2b):** `store()`/`update()` fazem
  `Role::findOrFail($request->input('role'))` sem `business_id` (aceitaria papel de outra empresa, e o
  `Admin#` que o `getRolesArray()` esconde de quem não é admin); `contactAccess()->sync()` aceita id de
  contato de qualquer empresa; `show()` usa `find()` e quebra com usuário de outra empresa em vez de 404.
- O Inertia manda `X-Requested-With`: o ramo `request()->ajax()` de `index()` engoliria a visita — o
  ramo React vem antes, via `Mwart::telaReact` (que devolve a Blade/JSON para AJAX sem `X-Inertia`).
- **Fora (sem fonte no legado):** "Convidar por e-mail", "Enviar link de redefinição", "Último acesso",
  "Verificação em 2 etapas", "Ativar/Desativar" direto da lista, atividade recente e o bloqueio de
  excluir por vendas/OS no nome do usuário. "Novo usuário" e "Editar usuário" abrem as telas Blade.
- O `destroy()` só responde a AJAX e devolve `{success, msg}`; a tela chama com `X-Requested-With`.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
