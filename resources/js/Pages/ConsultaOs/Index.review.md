---
page: ConsultaOs/Index
file: resources/js/Pages/ConsultaOs/Index.tsx
charter: AUSENTE
review_round: 1
review_type: static-analysis
review_at: 2026-05-17
reviewer: W31 Bulk Review R1 (agent)
status: draft (aguarda Wagner)
seed_pattern: resources/js/Pages/Admin/GovernanceV4.review.md (referência charter v4)
---

# Review estática R1 — `resources/js/Pages/ConsultaOs/Index.tsx`

> Análise estática sem execução. Tela PÚBLICA (sem AppShellV2/sidebar) — cliente final do oimpresso (Larissa+terceiros) consulta status de OS.

## Resumo

Página pública minimalista 154 linhas — 3 estados FSM client-side (`busca`/`resultado`/`nao-encontrado`), `fetch` GET `/consulta-os/buscar?numero+estagio`, layout centralizado max-w-md/2xl. Sem layout shell. Branding "Oimpresso ERP" gradient blue→violet. Decisão arquitetural correta (público = sem cookies sessão admin).

## Aderência ao canon

| Item | Status | Nota |
|---|---|---|
| Charter ao lado | ❌ AUSENTE | Página LIVE pública SEM charter — bloqueia MWART canônico (ADR 0104). Tela pública é Tier 0 sensível (rate-limit, anti-scraping) |
| RUNBOOK | ❌ AUSENTE | `memory/requisitos/ConsultaOs/RUNBOOK-index.md` provavelmente ausente |
| Inertia::defer | ✅ N/A | Página inicial só renderiza form; fetch é client-side via `fetch()` não Inertia |
| Multi-tenant Tier 0 (ADR 0093) | ⚠️ CRÍTICO dependente backend | Rota `/consulta-os/buscar` é pública SEM auth; backend MUST scope por `business_id` baseado em subdomain/slug OU rejeitar request sem contexto multi-tenant. Sem isso, qualquer cliente vê OS de outro business |
| Localstorage | ✅ N/A | Sem persistência |
| Cor semântica ADR 0110 | ❌ VIOLAÇÃO | `from-blue-500 to-violet-600` cru (linha 70) no logo gradient — esperado token brand. `text-white` ok em context contrast |
| Persona / monitor | ⚠️ não declarado | Provavelmente mobile-first (cliente final consulta via WhatsApp link), mas charter ausente impede confirmar |
| LGPD | ⚠️ implícito | OsResultCard mostra "número OS" — verificar se backend retorna PII (cliente nome/CPF) sem auth seria vazamento |

## Top 5 riscos identificados

1. **Tela pública SEM rate-limit declarado** — `/consulta-os/buscar` aceita numero+estagio sem captcha/throttle visível no frontend. Risco: scraping enumeration (incrementar `numero` 1..N+ vê todas OS biz=4). Backend MUST `throttle:public,30,1` ou similar + verificar logs Wagner ataque.
2. **Multi-tenant isolation invisível no frontend** — não há prop `business_id`/slug. Backend tem que inferir do host (`larissa.oimpresso.com`?) ou query string. Se rota for global `oimpresso.com/consulta-os`, qualquer scope vaza.
3. **CHARTER AUSENTE bloqueador MWART** — sem `Index.charter.md` próximo Edit é violação. Tela pública precisa charter com Non-Goals (NÃO mostrar valor OS / NÃO mostrar telefone cliente / NÃO permitir CRUD).
4. **`fetch` sem retry / sem timeout** (linha 30-32) — se rede lenta, user vê "loading" infinito. Idealmente `AbortController` + timeout 10s + retry 1x.
5. **`erro` genérico "Não foi possível conectar"** (linha 52) — não diferencia 500 (problema servidor) vs CORS vs offline. Logging frontend (Sentry/console) ausente — diagnóstico cego em campo.

## Pest GUARD recomendados (pendente)

```php
it('responds /consulta-os without auth (public)')
it('throttles /consulta-os/buscar to 30 req/min per IP')
it('isolates OS by business_id from subdomain/slug (biz=1 cannot see biz=4 OS)')
it('returns 404 (not 500) when OS not found — no enumeration leak')
it('does not return cliente PII (cpf/telefone) in public payload — only status + estagio')
it('renders at 375px mobile (cliente WhatsApp link)')
it('no console.* leaks in production build')
```

## Recomendações priorizadas

| # | Ação | Prioridade | Owner sugerido |
|---|---|---|---|
| 1 | Criar `Index.charter.md` (Mission=consulta pública / Non-Goals=zero PII) | P0 bloqueador | Wagner aprova |
| 2 | Criar `RUNBOOK-index.md` (anti-scraping + rate-limit + LGPD checklist) | P0 bloqueador | Wagner aprova |
| 3 | Auditar backend `/consulta-os/buscar` — throttle + multi-tenant scope + zero PII | P0 segurança | F+M code review |
| 4 | Refactor `from-blue-500 to-violet-600` → token brand | P2 | F3 followup |
| 5 | `fetch` com AbortController + timeout 10s | P2 | F3 followup |
| 6 | Pest GUARD smoke (public route + throttle + isolation) | P1 | Agent C |

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W31 Bulk R1 | Review estática R1 criada. Aguarda Wagner. Charter AUSENTE flagged P0. |
