---
owner: W
last_validated: "2026-10-01"
slug: site-runbook-privacidade
title: "Site — Runbook da política de privacidade pública do app oimpresso (ERP + ponto)"
type: runbook
module: Site
tela: Site/Privacidade
status: ativo
date: 2026-10-01
related_adrs:
  - 0383-ponto-interno-nao-coleta-biometria
---

# RUNBOOK — política de privacidade do app (`Site/Privacidade`)

> F1 PLAN de tela nova (não migra Blade: não havia política geral — medido em 2026-10-01,
> `https://oimpresso.com/privacidade` respondia 404; só existiam `/privacidade/ponto` e
> `/privacidade/ponto/exclusao`, do #8416, que cobrem apenas o ponto).

## 1. Por que existe

Decisão [W] 2026-10-01: o app das lojas (`com.oimpresso.app`, Capacitor) é o **ERP web inteiro +
o registro de ponto**. O Google Play e a App Store pedem **uma** URL de política de privacidade para
o app — e ela precisa cobrir também os dados do ERP (clientes cadastrados, anexos, financeiro),
não só o ponto.

| Rota | Nome | Tela |
|---|---|---|
| `GET /privacidade` | `publico.privacidade` | `Site/Privacidade` |

Pública (`web` + `throttle:60,1`, sem `auth`). O controller só renderiza: não lê banco, sessão nem tenant.

## 2. Fonte do texto

Inventário de [`docs/lojas-app/textos/privacidade-lojas.md`](../../../docs/lojas-app/textos/privacidade-lojas.md)
(o mesmo que alimenta o Data Safety e o App Privacy — a política e o formulário das lojas não podem
divergir). A parte do ponto **aponta** para `/privacidade/ponto` em vez de repetir.

## 3. Pendente antes de enviar às lojas

- **Revisão jurídica da Eliana [E]** — o texto é rascunho.
- Confirmar que o e-mail `lgpd@oimpresso.com.br` recebe mensagens (o mesmo já usado em `/privacidade/ponto`).
- Confirmar com a sessão do app Capacitor se há SDK de crash/analytics; se houver, acrescentar aqui e no Data Safety.

## 4. Como validar

- Pest: `tests/Feature/Site/PrivacidadeAppContratoTest.php` (lane sqlite per-PR, `.github/ci-sqlite-pest.list`).
- Smoke pós-deploy: `curl -sv https://oimpresso.com/privacidade 2>&1 | grep '^< HTTP'` → `200`, sem login.
