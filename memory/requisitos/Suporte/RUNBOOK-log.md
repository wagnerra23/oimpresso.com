---
id: requisitos-suporte-runbook-log
title: "Suporte — Runbook da tela Log de acessos (trilha append-only)"
module: Suporte
tela: Suporte/Log
owner: W
status: rascunho
last_validated: "2026-10-06"
preconditions:
  - "Backend do Modo Suporte no main (SupportAccessService + EnsureSupportAccess + SupportAuditService)"
  - "Tabela support_access_logs com target_user_id (migrations 2026_06_23_130000 e 2026_06_24_120000)"
  - "Usuário agente de suporte ativo (operadora ou concessão em support_agents)"
steps:
  - "Controller SupportController@log lê support_access_logs escopado a accessibleBusinessIds"
  - "Rota GET /suporte/log (suporte.log) no grupo support.access"
  - "Page Suporte/Log.tsx (PT-01 Lista read-only) com a lista deferida"
related_adrs:
  - 0305-modo-suporte-cross-tenant-exceto-operador
  - 0308-modo-suporte-fase-a-acessar-como-login-as-guardado
  - 0309-modo-suporte-operadora-e-o-time-de-suporte
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Suporte / Log de acessos

> **Tipo:** runbook reproduzível (F1 do MWART — ADR 0104). Tela **nova** (não há Blade legada a migrar).
> **Refs:** [ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) (RF3 auditoria append-only), [ADR 0308](../../decisions/0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md), [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md).
> **Fonte visual:** `LogAcessos` em `prototipo-ui/cowork/Wagner/suporte-page.jsx`.

Mostra ao time de suporte a trilha `support_access_logs`: quem entrou em qual empresa-cliente, quando, e em quem virou ("Acessar como"). Somente leitura — a tabela é append-only e a tela não oferece escrita nem correção.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/suporte/log` | Agente de suporte abre a URL → cabeçalho + lista |
| Operadora ausente | Nenhuma linha cuja empresa-alvo é a operadora (biz=1), nem as negações contra ela |
| Não-agente bloqueado | Usuário de cliente sem concessão → 403 (middleware `support.access`) |
| Só leitura | Não existe rota POST/PUT/DELETE em `/suporte/log` |

## 1. Objetivo

RF3 exige que todo acesso do suporte a um cliente seja auditado. A trilha já é gravada (middleware + `acessarComo`), mas não havia onde lê-la sem SQL. Esta tela é a leitura.

## 2. Pré-condições

- [ ] Ver frontmatter. Fixture de teste: `seededTenant()` (biz=1 operadora) + `seededSupportClientTenant()` (biz=99). NUNCA biz=4.

## 3. Passo-a-passo

1. `SupportController@log` — `accessibleBusinessIds()` (fonte única da exclusão da operadora) → `Inertia::defer` de um paginate(50) com join em `users` (agente e alvo) e `business` (nome).
2. Rota `GET suporte/log` → `suporte.log`, dentro do grupo que já tem `support.access` + `AdminSidebarMenu`.
3. `Pages/Suporte/Log.tsx` — `PageHeader` + `Deferred` + `DataTable` (Quando · Agente · Acessou como · Empresa).

## 4. Tokens CSS

Tokens semânticos do DS (`text-muted-foreground`, `Badge` warning/danger). Sem cor crua.

## 5. Estados visuais

Carregando (skeleton do `Deferred`) · cheia · vazia (`EmptyState`) · 403 para não-agente.

## 6. Responsividade

Cabe em 1280px; `max-w-6xl`, coluna Empresa elástica.

## 7. Atalhos

Nenhum.

## 8. Component contract

`PageHeader`, `shared/DataTable` (paginação servidor), `shared/EmptyState`, `ui/badge`, `ui/skeleton`.

## 9. DoD checklist

- [ ] Teste `SuporteLogContratoTest` verde na lane de PR (UC-SUP-08..10).
- [ ] Smoke em produção como agente (biz=1) — tela abre e lista.

## 10. Pegadinhas

- `logs` é prop deferida: teste lê por partial reload com header (`X-Inertia-Partial-Data`), nunca `?only=`.
- O GET `/suporte/log` não tem `{business}` → o middleware não grava linha de auditoria pela própria leitura.

## 11. ADR de origem

ADR 0305 (RF3) · ADR 0308 (ação `acessou_como`).
