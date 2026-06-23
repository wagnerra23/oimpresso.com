# BRIEFING — `Suporte` (Modo Suporte)

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva (porta do módulo).
> **Refs:** [ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) · [SPEC](SPEC.md) · [RUNBOOK Empresas](RUNBOOK-empresas.md)

---

## 1. O que é

**URL (futura):** `https://oimpresso.com/suporte/empresas`
**Backend:** `app/Services/Support/` + `app/Http/Middleware/EnsureSupportAccess.php` (capability cross-tenant em **core**, NÃO é módulo nWidart — espelha o padrão cross-tenant do `Modules/Superadmin`, porém restrito).
**Frontend:** `resources/js/Pages/Suporte/` (em construção).

Dá à **equipe de suporte do operador** acesso **somente leitura** às empresas-cliente (ver tudo do cliente, inclusive o financeiro **dele**) **EXCETO a empresa operadora (biz=1)** — auditado e **sem escalonamento**. Regra: `suporte ⊂ (todas as empresas \ operador)`, com o operador vindo de config (`OPERATOR_BUSINESS_ID`, default 1).

## 2. Estado consolidado

| Dimensão | Estado | Data |
|---|---|---|
| Backend fase B (read-only) | ✅ completo no `main`, **dormente** (0 rotas em runtime) | 2026-06-23 |
| Decisão arquitetural | ✅ [ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) ratificado | 2026-06-23 |
| UI (tela) | 🚧 F1 (RUNBOOK) feito · F3 (`.tsx`) em construção | 2026-06-23 |
| "Atuar" (escrever) cross-tenant | ⛔ fora de escopo (inseguro — ver §4) | 2026-06-23 |

## 3. Capacidades hoje (no `main`, dormentes)

- **Resolução de tenants acessíveis** (`SupportAccessService`) — único ponto que exclui o operador.
- **Capability por conta** (`support_agents`) — concedida/revogada, distinta de `superadmin`.
- **Auditoria append-only** de acesso/negação (`support_access_logs` + `SupportAuditService`).
- **Middleware-guarda** (`EnsureSupportAccess`) — **service-direct, NÃO via Gate** (o `Gate::before` daria `true` a qualquer `Admin#biz` e vazaria a operadora).
- **Montagem read-only do cliente** (`SupportClientViewService`) — `business_id` **explícito**, nunca sessão/auth-user.

## 4. Decisão-chave — desenho seguro (auditoria de scoping 2026-06-23)

**Switch de contexto de sessão = DESCARTADO.** O código não é uniformemente scopado por sessão: `CashRegisterUtil`/`payContact`/criação-de-usuário leem `auth()->user()->business_id` → trocar a sessão vazaria o operador / gravaria no tenant errado (split-brain). O caminho seguro é **read-only com `business_id` explícito** (padrão do `Modules/Superadmin`). Escrever cross-tenant fica fora de escopo até uma refatoração das vias de scoping.

## 5. Gaps / próximos

- Telas `Suporte/Empresas` (lista) + `Suporte/Visao` (read-only) via MWART F3 — aval visual do Wagner já dado.
- Entrada no menu (nav).
- Tela de conceder/revogar capability por conta.
- Fase A "atuar" (write) — só após auditoria das vias de scoping `auth-user` vs `session`.

## 6. Refs

[ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) (decisão-mãe) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0) · [SPEC](SPEC.md) · [RUNBOOK Empresas](RUNBOOK-empresas.md) · [SPEC Superadmin](../Superadmin/SPEC.md) (padrão cross-tenant espelhado).

---

**Última atualização:** 2026-06-23 — criação (porta do módulo + desenho seguro pós-auditoria de scoping).
