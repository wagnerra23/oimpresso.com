---
page: /financeiro/unificado/novo
component: resources/js/Pages/Financeiro/Unificado/Novo.tsx
related_prototype: n/a (tela-stub picker/hub de 2 cards de link — não é formulário; não casa a assinatura de um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Financeiro
related_adrs: [114, 101, 93]
tier: C
charter_version: 1
---

# Page Charter — /financeiro/unificado/novo (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> **Stub (não é capacidade viva de formulário):** o Controller declara explicitamente "Stub — formulário unificado ainda não implementado; oferece picker receber/pagar e redireciona". A Page é só um hub de 2 cards que linkam pra `/financeiro/contas-receber/novo` e `/financeiro/contas-pagar/novo`. Por isso `tier: C` e PT silencioso — NÃO há `useForm`/`<form>` (declarar PT-02 seria count-pump).
>
> Backend: `Modules/Financeiro/Http/Controllers/UnificadoController@novo` (rota `financeiro.unificado.novo`, grupo `web/auth/language/timezone/AdminSidebarMenu`).

---

## Mission
Ponto de entrada pra registrar um novo lançamento no Financeiro: o usuário escolhe entre "Conta a receber" e "Conta a pagar" e é levado pro formulário específico. É um hub de navegação (picker), não o formulário em si — placeholder até um formulário unificado inline (sheet/modal) substituir.

---

## Goals — Features (faz)
- Mostra 2 cards de opção (receber/pagar) com ícone, título e descrição, cada um `<Link>` pra a rota de criação correspondente.
- Header canon `PageHeader` (shared) + `FinanceiroSubNav` (active `unificado`).

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera cálculo, valor, saldo, baixa ou estoque — é só navegação; nenhum dado é escrito nesta tela.
- ❌ NÃO tem formulário próprio nem submit — apenas encaminha pros formulários `/contas-receber/novo` e `/contas-pagar/novo`.
- ❌ NÃO usa a prop `tipos?` (chega opcional do Controller mas não é renderizada) — inferência pendente Wagner.
- ❌ NÃO cruza dados entre businesses — não faz query; a criação real (nas telas destino) escopa por `business_id` (session, nunca do payload — ADR 0093).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; layout centrado `max-w-3xl`.

---

## Automation hooks (faz)
- Nenhuma — tela puramente estática de navegação (2 links).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO cria título nem dispara qualquer mutação/side-effect.
- ❌ NÃO pré-seleciona nem redireciona sozinho — espera clique do usuário.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Decisão: manter como stub/picker (tier C) ou substituir pelo formulário unificado inline antes de virar live
- [ ] Smoke visual 1280/1440 (screenshot)
