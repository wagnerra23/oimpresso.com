---
slug: crm-runbook-logins
title: "Crm — Runbook da tela Login de contatos"
type: runbook
module: Crm
tela: Crm/Logins/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Login de contatos (`/crm/all-contacts-login`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `crm/playbook/16` (portal do contato, D3).
> **Fonte de design:** alvo `governance/design/targets/crm--logins--index.alvo.json` (thread A2, #9255), medido sobre a rota `crm-logins` do espelho (`crm-blade-telas.jsx` → `Logins`).
> **Decisão de origem:** D3 [W] 2026-10-01 — *"sim, o portal do contato entra no escopo"* (`_DECISOES-W-2026-10-01.md`).

## Estado final esperado

`GET /crm/all-contacts-login` (`ContactLoginController::allContactsLoginList`) responde Inertia
`Crm/Logins/Index` **quando a flag `useV2CrmLogins` está ligada** para o negócio. Desligada (default), ou com
`?classico=1`, segue a Blade `crm::contact_login.all_contacts_login`, que hospeda os modais de adicionar, editar e
excluir. O `ajax()` da DataTable (`GET /crm/contact-login`, `index()`) continua respondendo JSON.

Esta fatia é **só leitura**. Criar, editar e excluir login seguem na Blade (os botões da Page levam a `?classico=1`).

## 1. Objetivo

Listar os logins do portal do contato (contato · usuário · nome · e-mail · departamento · designação) no desenho do
alvo da A2, sem mudar o que a Blade grava.

## 2. Pré-condições

- Assinatura com `crm_module` (ou superadmin) **e** permissão `crm.access_contact_login` — as duas que o controller
  exige em toda ação.
- Lane `verticais-pest.yml` (MySQL) roda `Modules/Crm/Tests/Feature/CrmLogins*`.

## 3. Paridade — Blade de hoje × protótipo × Page

| item | Blade (`all_contacts_login.blade.php` + `index()` ajax) | protótipo (`Logins`) | Page `Crm/Logins/Index` |
|---|---|---|---|
| título | "Contacts login" / widget "All contacts login" | "Login de todos os contatos" | o do protótipo |
| filtro | Contato (`contact_id`, `contactsDropdownForLogin`) | Contato | Contato, mesma lista |
| busca | busca global do DataTables (username, email, depto, cargo) | "Buscar por nome ou usuário" | nome **ou** usuário, no servidor |
| colunas | Ação · Contato · Usuário · Nome · E-mail · Departamento · Designação | as mesmas 7 | as mesmas 7 |
| ação por linha | Editar · Excluir (modal) | Editar login · Redefinir senha | Editar → `?classico=1` |
| adicionar | botão "Add" (modal) | "Adicionar" (modal com senha) | "Adicionar" → `?classico=1` |
| linhas | `users` do negócio com `crm_contact_id` cujo contato existe | mock | mesma consulta, paginada (25) |

## 4. Divergências declaradas (não são omissões)

- **"Redefinir senha"** do protótipo não existe no ERP (não há envio de link de senha para contato). Não entra.
- **Modal "Adicionar login" com senha**: a escrita não migra nesta fatia. Quando migrar, a senha é **só de escrita**:
  nunca volta nas props, nem mascarada.
- **Comissões** (`/crm/commissions`, `crm-comissoes` no protótipo) é outra tela, sem alvo medido na A2. Fica na Blade;
  pedir alvo antes de migrar. O percentual de comissão (`cmmsn_percent`) é **valor**: a lista não o mostra nem o altera.

## 5. Regras Tier 0 da tela

- **Senha nunca nas props.** A consulta não seleciona `password` nem `remember_token`; o teste de contrato assere a
  ausência das duas chaves em cada linha.
- **Negócio da sessão.** `users.business_id` da sessão; o filtro de contato só aceita contato do mesmo negócio.
- Tenant de teste **98**, vizinho **99** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)). Nunca biz=4.

## 6. Passo-a-passo

1. **F1** — este RUNBOOK.
2. **F2** — `CrmLoginsBaselineTest` trava a Blade de hoje: 403 sem permissão, ajax só com logins do negócio
   (98 × 99), JSON sem `password`, filtro por contato.
3. **F3** — `allContactsLoginList()` ganha o ramo Inertia atrás da flag; `Crm/Logins/Index.tsx` + charter + casos +
   contrato `crm-logins` + `CrmLoginsContratoTest`.
4. **F4** — smoke em biz=1 com a flag ligada (`flag:set useV2CrmLogins --biz=1`).
5. **F5** — cutover (flag default ligada) é decisão [W].

## 7. Pegadinhas

- **Inertia v3 manda `X-Requested-With`** em toda visita: o ramo do DataTables exige `ajax() && ! inertia()`
  (§5 2026-09-08). Aqui o ajax mora em outra rota (`index()`), mas a regra vale para o partial reload da Page.
- `catch (Exception $e)` sem `use Exception` no controller não captura nada (mesma raiz do #8771). Fora desta fatia.
- **`update()` grava em qualquer usuário do negócio pelo id**, sem exigir `crm_contact_id` — um login de contato
  editado pode apontar para usuário da equipe. Registrado; o conserto é PR próprio, com teste.

## 8. DoD checklist

- [ ] `CrmLoginsBaselineTest` e `CrmLoginsContratoTest` verdes na lane `verticais-pest.yml`, com assertions > 0.
- [ ] `contrato-de-tela --contract crm-logins.contract.json` limpo.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 9. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
