---
id: requisitos-superadmin-runbook-usuario360-index
title: "RUNBOOK — /superadmin/usuarios (Usuário 360 · lista de busca)"
module: Superadmin
tela: superadmin/Usuario360/Index
owner: W
status: ativo
last_validated: "2026-11-16"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/usuarios` (Usuário 360, lista de busca)

Escrito em 2026-11-16 (lote L6 da auditoria) para uma tela que já existia sem RUNBOOK. Descreve o código em `origin/main` 580be57; não é um F1 de migração.

- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Index.tsx` (até 2026-08-19 morava em `resources/js/Pages/superadmin/`; o namespace Inertia não mudou).
- **Charter + casos:** `Index.charter.md` (`draft`) e `Index.casos.md` (UC-SAUL-01..04), ao lado do `.tsx`.
- **US:** US-SUPER-010.
- **Rota:** `GET /superadmin/usuarios` → `Usuario360Controller::index` (`Modules/Superadmin/Routes/web.php:15`), no grupo com middleware `superadmin` + `throttle:superadmin`.

## 1. O que o backend faz

`Usuario360Controller::index`:
- recebe `?q=` e procura por `username`, `email`, `first_name` e `last_name` com `LIKE`;
- ordena por `first_name` e corta em **200** linhas (`->limit(200)`, sem paginação);
- devolve `users` (`id`, `username`, `email`, `nome`, `business_id`, `status`, `user_type`) e `filters.q`.

Listar **não** grava log de acesso. O log é só da abertura do raio-X (UC-SAUX-06).

## 2. Tier 0 — invariantes

- Cross-tenant intencional: a busca enxerga usuários de todos os business (UC-SAUL-04, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) §exceções). O `User` não tem escopo global de `business_id`, então não há `withoutGlobalScope` aqui.
- O admin de negócio é barrado pelo middleware `superadmin` (UC-SAUL-02).

## 3. Testes

`Modules/Superadmin/Tests/Feature/Usuario360ContratoTest.php` (UC-SAUL-01..04, `@covers-us US-SUPER-010`). Rodar no CT 100.

## 4. Smoke prod (R1)

Não registrado. O `screen:files` em 2026-11-16 diz: sem E2E Browser, sem visual-comparison e "NÃO pode ligar" (charter `draft`, zero sinal de prod). A medição de tela é o lote L7.

## 5. Limites conhecidos

- O corte de 200 linhas é silencioso: a tela não avisa que há mais resultados.
- `LIKE '%q%'` sem índice de texto. Com a base de usuários de hoje não foi medido como problema.
