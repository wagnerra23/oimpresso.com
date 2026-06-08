---
date: "2026-06-02"
hour: "10:30 BRT"
topic: "Incidente: revert do PR2 endereço na venda (#2104) após regressão reportada por cliente"
authors: [C, W]
prs: [2104, 2105, 2107]
us: [US-SELL-044]
outcomes:
  - "Revert #2107 mergeado em main — aguardando redeploy prod pra restaurar cliente"
  - "Causa raiz pendente de confirmação (sintoma exato do cliente)"
---

# Incidente — regressão na tela de venda pós-merge do PR2 (#2104)

> **TL;DR:** Mergeei o PR2 (#2104 — endereço de entrega de 1ª classe na `/sells/create`) com **CI verde mas sem smoke visual** — eu avisei o risco e Wagner topou. Minutos depois, **cliente reportou regressão** na venda. Mitiguei com **revert (#2107)** do PR2 inteiro. **Pendente:** redeploy do prod + sintoma exato pra confirmar a causa raiz e refazer com smoke.

## Timeline (2026-06-02)
- Implementei o PR2 sobre a base crm-078 (catálogo `contact_addresses`, US-CRM-078 PR1, já em main). CI ficou verde após corrigir 4 falhas (Larastan relations `Contact`, frontmatter charter, `ui:lint` amber→`text-warning`, `check-scope` `ContactAddressController`).
- **Merge #2104 → main** (admin, bypass do review obrigatório; Wagner autorizou) — **sem smoke visual** (sem PHP/browser local pra rodar a tela).
- Merge #2105 junto (docs `Crm = Cliente` + consolidação `requisitos/Cliente`→`Crm`).
- ~30 min depois: **Wagner reporta cliente reclamando — "parece que regrediu"** na venda.
- Decisão **mitigate-first** → revert. Criado + admin-merge do **#2107** revertendo o #2104 inteiro (`b43162ce2`).

## Suspeitas de causa raiz (confirmar com o sintoma)
1. **Busca de cliente 500** — `ContactController@getCustomers` passou a fazer `->with(['addresses' => ...])`; se a migration `contact_addresses` (PR1) não rodou em prod, o endpoint `/contacts/customers` quebra. Endpoint **compartilhado** com o Blade → flag-off **não** mitigaria; só o revert+redeploy. *(suspeito #1)*
2. **`shipping_address` não salva** — frete migrou pra seção "Entrega" com toggle default **Retirada**; o transform só envia `shipping_*` quando `mode === 'entrega'`. Cliente que não liga "Entrega" perde o endereço de entrega.
3. **UX** — campo de frete saiu de "Mais opções" pra seção nova; cliente "não acha".

## Ações tomadas
- ✅ Revert **#2107** mergeado em main (`b43162ce2`) — `/sells/create` volta ao estado **pré-PR2**.
- ⏳ **Redeploy prod** (ação Wagner) — pull do main + clear cache. **Não** precisa rollback de migration: `contact_addresses` (PR1) fica, é inofensiva sozinha.
- ⏳ Sintoma exato do cliente → confirma qual das 3 hipóteses.
- 📌 US-SELL-044 (PR3 fiscal MDF-e) segue de pé, independente do revert.

## Lição
Não mergear o **#1 screen** (tela de venda) sem **smoke visual**, mesmo com CI verde e mesmo autorizado. O CI (lint/types/`visual-regression`/Pest unit) **não pegou** a regressão — o gate visual da PRE-MERGE-UI existe exatamente pra isso. Refazer o PR2 só após smoke real: staging logado + busca de cliente + toggle Entrega + console limpo.

## Refs
- Revertido: PR #2104 (`1e4bb33c4`) · Revert: PR #2107 (`b43162ce2`) · Docs junto: #2105
- `app/Http/Controllers/ContactController.php` (`getCustomers`) · `resources/js/Pages/Sells/Create.tsx` (sec-entrega) · `app/Contact.php` (relations)
- US-SELL-044 (PR3 fiscal) · ADR 0105 (cliente como sinal) · PRE-MERGE-UI · dossiê `memory/sessions/2026-06-01-contexto-venda-dossie-git.md` (revertido junto — reescrever no re-do)
