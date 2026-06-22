# BRIEFING — Cliente (cadastro de clientes / contatos)

> **Última atualização:** 2026-06-22 · **Owner:** Wagner · **Status produção:** ✅ usado por biz=4 (ROTA LIVRE — Larissa) e demais tenants.
> 🪪 **Cliente ≠ CRM:** este é o **cadastro de Cliente/contatos** — coisa separada do *pipeline CRM* (leads/propostas/campanhas), que está em **depreciação** (ver [plano](../Crm/DEPRECATION-PLAN-pipeline.md)). Decisão Wagner 2026-06-22 ("contacts não é o crm").

## O que é

Cadastro de clientes **PF e PJ** com canon fiscal BR completo (CPF/CNPJ com validação mod-11, IE/RG, regime, endereço, contato) e tela de detalhe rica via **drawer 760px lateral** (8 abas cadastrais) aberto da listagem ([ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)). Inclui múltiplos endereços por contato, lookup CEP (ViaCEP) e CNPJ (BrasilAPI), tab IA (Copiloto) e auditoria LGPD.

## Estado atual (verificado @origin/main)

- **15 US declaradas** na [SPEC.md](SPEC.md) — 14 com código verificado (`anchor_coverage 100%`, ADR 0273), 1 parcial (US-078 PR3: seletor de endereço salvo na venda).
- **Telas Inertia:** `resources/js/Pages/Cliente/{Index,Create,Edit,Show,Import,Ledger,Map}.tsx`. Superfície de detalhe viva = drawer (Index); `Show.tsx` é legado dual-render.
- **Multi-tenant Tier 0:** `App\Contact` com global scope `business_id`; cross-tenant → 404 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **LGPD:** `cpf_cnpj`/`ie_rg`/`bank_account` mascarados antes dos props; activity log exclui PII; sem hook WhatsApp/email no cadastro.

## Onde está

- **Requisitos (canon):** aqui em `memory/requisitos/Cliente/` — [SPEC.md](SPEC.md) + [relatório de alinhamento](audits/ALINHAMENTO-cliente-2026-06-22.md). RUNBOOKs/visual-comparisons ainda em `../Crm/` (a mover).
- **Código:** `Modules/Crm/` (controllers `Cliente*Controller`, `ContactAddressController`) — rename de módulo não feito; **não há um módulo `Cliente` separado** (o código fica em `Modules/Crm`).
- **Dados:** core `App\Contact` + `App\ContactAddress` (tabelas `contacts`/`contact_addresses`).

## Falta / próximos

- US-078 PR3 — dropdown de endereço salvo na tela de venda (`Sells/Create`; hoje `shipping_address` é texto livre), ~3h.
- Migrar RUNBOOKs/UI-CATALOG/ARCHITECTURE de `Crm/` → `Cliente/` (execução do plano de separação).
- Backlog secundário em [SPEC.md §5](SPEC.md).
