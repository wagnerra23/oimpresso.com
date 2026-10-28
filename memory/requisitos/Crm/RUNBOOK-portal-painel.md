---
slug: crm-runbook-portal-painel
title: "Crm — Runbook da tela Início do portal do contato"
type: runbook
module: Crm
tela: Crm/Portal/Painel/Index
owner: W
status: ativo
last_validated: "2026-10-27"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Início do portal do contato (`/contact/contact-dashboard`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `crm/playbook/18`.
> **Fonte de design:** alvo `governance/design/targets/crm--portal-painel--index.alvo.json` (thread A3, #9734), medido sobre a rota `crm-portal-painel` do espelho (`prototipo-ui/cowork/Wagner/crm-portal.jsx`, view `painel`, e `crm-portal.css`).
> **Casos:** `prototipo-ui/cowork/Wagner/handoff-crm/Portal.casos.md` (UC-CRMPO-01, 02, 03, 17, 18), copiados para `Modules/Crm/Resources/js/Pages/Crm/Portal/Painel/Index.casos.md`.

## Qual tela é esta

A primeira tela que o **contato** vê depois do login (`users.user_type = 'user_customer'`, com `crm_contact_id`). Não é tela de operador: entra pelo grupo `prefix('contact')` de `Modules/Crm/Routes/web.php`, com `ContactSidebarMenu` + `CheckContactLogin`. O `HomeController@index` já redireciona o contato para cá.

## Estado final esperado

Com a flag `useV2CrmPortalPainel` ligada para o negócio, `GET /contact/contact-dashboard` abre `Crm/Portal/Painel/Index` na **casca do contato** (D6, [W] 2026-10-15): cabeçalho com a loja e o contato, abas no topo, barra inferior no celular, sem a sidebar do ERP. Sem a flag, ou com `?classico=1`, segue a Blade `crm::dashboard.index`.

## 1. Objetivo

Mostrar ao contato quanto ele deve e quanto já pagou — e só dele. Os blocos seguem o tipo do contato: cliente vê vendas, fornecedor vê compras, `both` vê os dois; saldo de abertura só quando existe.

## 2. Pré-condições

- A3 mergeada (#9734): alvos `crm--portal-painel--index` e `-390`.
- D3 (o portal entra agora) e D6 (casca própria, mobile-first) respondidas.
- Teste de contrato roda na lane MySQL (`verticais-pest.yml`); em SQLite pula — leia assertions, não "0 failed" (LC-13).

## 3. Paridade — Blade de hoje × protótipo × Page

| Bloco | Blade (`dashboard/index.blade.php`) | Protótipo (`painel`) | Page |
|---|---|---|---|
| Saudação | "Bem-vindo, {first_name}" | "Olá, {pessoa}." + nome do contato e da loja | protótipo |
| Vendas | 3 info-box: total, pago, a receber | bloco `portal-saldo`: "Você tem a pagar" + total e pago | protótipo |
| Compras | 3 info-box: total, pago, a pagar | KPIs "Total em compras" e "Compras a pagar" | protótipo |
| Abertura | 2 info-box: abertura e devido | KPI "Saldo de abertura" | protótipo; o devido entra no mesmo KPI como descrição |
| Atalhos | — | Pedir de novo · Agendar retirada · Acompanhar pedidos | só os que têm rota para este contato |
| Aviso de OS | — | "Pronto para retirada há N dias" · "Orçamento aguardando" | **fora desta thread** (§4) |

## 4. Divergências declaradas

- **Aviso de OS (UC-CRMPO-17) não entra.** O `main` não tem regra que diga, para o portal, quando uma OS está "pronta para retirada" ou com "orçamento aguardando aprovação" — é a mesma derivação que a aba Assistência (thread 25, UC-COS-24) precisa criar. Escrever a regra aqui seria inventá-la antes da fonte.
- **Assistência não aparece na navegação** até a thread 25 criar a rota.
- Atalho "Agendar retirada" só aparece com o módulo `booking` ligado; "Pedir de novo" e "Acompanhar pedidos", só com `crm_settings.enable_order_request` — as mesmas condições do menu da Blade.

## 5. Valor — regra mestre (CÁLCULO DE VALOR)

A tela só **mostra** valor. Os números vêm de `ContactUtil::getContactInfo` (mesma consulta da Blade), sem mudança no cálculo. "A pagar" = `total_invoice − invoice_received`, como a Blade. O teste prova os dois números contra valores semeados e conhecidos.

## 6. Regras Tier 0 da tela

- Dois escopos sempre juntos: `business_id` da sessão **e** `crm_contact_id` do usuário. Contato B do mesmo negócio e contato A' do negócio 99 não entram na conta.
- A resposta Inertia **não** leva o `shell` do ERP: o controller substitui a prop `shell` inteira pelo dado da casca do contato.
- Operador (sem `user_customer`) é recusado pelo `CheckContactLogin`; contato não entra em `/crm/*` (`CheckUserLogin`).

## 7. Passo-a-passo

1. Ligar `useV2CrmPortalPainel` para um negócio de teste (`flag:set --biz=<id>`), nunca biz=4.
2. Entrar como contato e abrir `/contact/contact-dashboard`.
3. Conferir a forma com `design-diff --compare --check` contra `crm--portal-painel--index` (1280) e `-390`, tema claro e escuro.
4. `?classico=1` deve abrir a Blade.

## 8. Pegadinhas

- O `ContactSidebarMenu` volta cedo em requisição ajax — e toda visita Inertia manda `X-Requested-With`. Por isso o menu da casca não sai do `Menu::create`: sai do `PortalContatoMenu`, que o middleware também usa.
- Os tokens de cor só existem sob `.cockpit`, e o `cockpit.css` só é importado pelo `AppShellV2`. A casca importa os dois e embrulha a tela em `.cockpit`.

## 9. DoD checklist

- [ ] Controller com `Inertia::render('Crm/Portal/Painel/Index')` atrás da flag, Blade em `?classico=1`.
- [ ] `CrmPortalPainelContratoTest` com UC-CRMPO-01, 02, 03 e 18 verdes na lane MySQL.
- [ ] Recibo `_saida-18.md` com placar e o UC-CRMPO-17 declarado ausente.

## 10. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (MWART) · decisões D3 e D6 em `prototipo-ui/cowork/Wagner/cowork-inbox/crm/playbook/`.
