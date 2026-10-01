---
id: requisitos-superadmin-runbook-comunicador
title: "RUNBOOK — /superadmin/communicator (Comunicador · Blade → Inertia)"
module: Superadmin
tela: superadmin/Comunicador/Index
owner: W
status: ativo
last_validated: "2026-09-30"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/communicator` (Comunicador, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) da
thread **Superadmin/05** (1º dos 2 PRs; o 2º é Configurações). A tela era
`superadmin::communicator.index` (Blade + DataTables + TinyMCE) e passa a `Inertia::render`.

- **Fonte de design:** [`prototipo-ui/cowork/Wagner/superadmin-page.jsx`](../../../prototipo-ui/cowork/Wagner/superadmin-page.jsx) → `ViewComunicador()` (L1224).
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Comunicador/Index.tsx`.
- **Rotas (não mudam):** `GET /superadmin/communicator` → `index()` · `POST /superadmin/communicator/send` → `send()`. O `GET …/get-history` (DataTables) fica sem consumidor na tela nova e **não** foi removido nesta onda.

## 1. O que mudou no backend

- `index()` manda duas props `Inertia::defer`: `negocios` (id + nome, todos) e `historico` (50 envios mais recentes, `resumo` com `strip_tags`).
- `send()` ganhou `validate()` (recipients ≥1, subject, message): antes aceitava qualquer coisa.
- `send()` passa a **escapar** o corpo (`nl2br(e(...))`). O e-mail usa `emails.plain_html`, que imprime o corpo cru; com o TinyMCE fora, texto puro sem escape abriria injeção de HTML no e-mail de todos os destinatários.

## 2. Tier 0 — invariantes

- Cross-tenant **de propósito** (ADR 0093 §exceções Superadmin). A trava é `can('superadmin')` + middleware, não `business_id`.
- O log `superadmin_communicator_logs` é trilha de auditoria (LGPD D7, 5 anos). A tela só lê.

## 3. Smoke prod (R1)

Após o deploy: abrir `https://oimpresso.com/superadmin/communicator` como superadmin, ver as duas colunas carregarem (compor + histórico) e um usuário comum ser barrado. **Não** disparar envio real em prod para testar: ele manda e-mail aos donos dos negócios marcados.

## 4. O que NÃO entrou

Grupos por status de assinatura/pacote, agendamento, "enviar teste para mim" e taxa de abertura (protótipo). Nenhum tem backend; ver charter §Divergências.
