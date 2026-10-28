---
slug: crm-runbook-portal-perfil
title: "Crm — Runbook da tela Perfil do portal do contato"
type: runbook
module: Crm
tela: Crm/Portal/Perfil/Index
owner: W
status: ativo
last_validated: "2026-10-27"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Perfil do portal do contato (`/contact/contact-profile`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `crm/playbook/19`.
> **Fonte de design:** alvo `governance/design/targets/crm--portal-perfil--index.alvo.json` (A3, #9734) sobre a view `perfil` de `prototipo-ui/cowork/Wagner/crm-portal.jsx`.
> **Casos:** `prototipo-ui/cowork/Wagner/handoff-crm/Portal.casos.md` (UC-CRMPO-04, 05), copiados para `Modules/Crm/Resources/js/Pages/Crm/Portal/Perfil/Index.casos.md`.
> **Casca:** a do contato (D6), criada na thread 18 (`Crm/Portal/_layout/PortalContatoLayout.tsx`).

## Qual tela é esta

O contato logado (`user_type = 'user_customer'`) vê e corrige os próprios dados de contato e troca a senha. Rota no grupo `prefix('contact')`, com `ContactSidebarMenu` + `CheckContactLogin`.

## A mudança de comportamento (ler antes de tudo)

| | Blade de hoje (`crm::profile.edit`) | Tela React (esta thread) |
|---|---|---|
| O que edita | o **usuário** de login: `surname`, `first_name`, `last_name`, `email`, `language`, foto | o **cadastro do contato**: `contato` (pessoa de contato), `mobile`, `email`, `address_line_1` |
| Fonte | lei do portal (`handoff-crm/Portal.charter.md`, Goals: *"Perfil editável (pessoa de contato, e-mail, telefone, endereço) + troca de senha; nome e CNPJ são read-only"*) | — |

As duas convivem até o cutover: o controller reconhece a visita Inertia (`X-Inertia`) e só nela grava o cadastro do contato. A Blade segue gravando o usuário, sem mudança.

## 1. Objetivo

Deixar o contato corrigir telefone, e-mail, endereço e pessoa de contato sem ligar para o balcão, sem nunca alterar o cadastro fiscal (nome, CNPJ/CPF, tipo) nem as chaves (`contact_id`, `business_id`).

## 2. Pré-condições

- Thread 18 (casca do contato) no `main` ou neste branch empilhado.
- A3 mergeada (#9734).

## 3. Paridade — Blade × protótipo × Page

| Bloco | Blade | Protótipo (`perfil`) | Page |
|---|---|---|---|
| Dados | nome, sobrenome, e-mail, idioma, foto do usuário | Nome e CNPJ só leitura; pessoa de contato, telefone, e-mail, endereço editáveis | protótipo |
| Senha | atual, nova, confirmar | igual | igual, nas mesmas rotas POST |

## 4. Divergências declaradas

- **Idioma e foto do usuário** não entram na tela React (o protótipo não os tem). Seguem na Blade (`?classico=1`).
- **Endereço** é um campo só, como no protótipo: grava em `address_line_1`. Cidade, UF e CEP seguem pelo balcão.
- **Senha atual errada** devolve erro de validação no campo (redirect com erro na sessão), não `422`: é assim que o Inertia trata formulário. O caso UC-CRMPO-05 diz `422`; o teste prova o que importa — o erro no campo e o hash intacto.

## 5. Regras Tier 0 da tela

- Contato sempre por `business_id` da sessão **e** `crm_contact_id` do usuário (`contatoDoUsuario()`).
- Lista permitida no controller (`CAMPOS_DO_CONTATO`): o que vier fora dela no corpo é ignorado.
- Senha: só a do `auth()->user()`; `user_id` no corpo não é lido.

## 6. Passo-a-passo

1. Ligar `useV2CrmPortalPerfil` num negócio de teste, nunca biz=4.
2. Entrar como contato e abrir `/contact/contact-profile`.
3. Mudar telefone e salvar; conferir no cadastro do contato (drawer de Cliente).
4. Trocar a senha com a atual errada e depois certa.
5. `design-diff --compare --check` contra `crm--portal-perfil--index` (1280 e 390, claro e escuro).

## 7. Pegadinhas

- `contacts.mobile` é `NOT NULL`: a tela exige telefone.
- O `ContactSidebarMenu` volta cedo em requisição ajax; a casca não depende dele (lê o `PortalContatoMenu`).

## 8. DoD checklist

- [ ] `getProfile` com `Inertia::render('Crm/Portal/Perfil/Index')` atrás da flag, Blade em `?classico=1`.
- [ ] `CrmPortalPerfilContratoTest` com UC-CRMPO-04 e 05 verdes na lane MySQL.
- [ ] Recibo `_saida-19.md`.

## 9. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · D3 e D6 em `prototipo-ui/cowork/Wagner/cowork-inbox/crm/playbook/`.
