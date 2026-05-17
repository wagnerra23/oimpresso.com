---
review_round: W31-R1
tela: subcomponent /nfe-brasil/manifestacao
component: resources/js/Pages/NfeBrasil/Manifestacao/_components/LinkedHistorico.tsx
charter: N/A (subcomponent)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 74
---

# Review estático — Manifestacao/_components/LinkedHistorico

## Pontos fortes
- TIPO_LABEL mapping correto pros eventos SEFAZ (210210/200/220/240)
- Bullet color semântico (emerald=autorizado, amber=outros)
- `Date.toLocaleString('pt-BR', {...})` formato curto
- Mostra `cstat_evento` em mono pra debug
- Loading + empty states bem tratados
- Endpoint `/nfe-brasil/manifestacao/${dfeId}/eventos` retorna JSON

## Riscos / gaps
1. **fetch sem AbortController** — usuário troca foco J/K rapidinho, N requests pendentes voltam em ordem incerta. State `eventos` pode ficar do DFe errado. P1 RACE CONDITION
2. `r.ok ? r.json() : { eventos: [] }` swallow erros silencioso — sem toast de erro 401/403/500. P2
3. `.catch(() => setEventos([]))` mesmo problema — engole network errors. P2
4. `TIPO_LABEL` fallback `evento.tipo` (string raw) — pode aparecer "210110" cru se SEFAZ adicionar novo evento. P3
5. Sem refresh manual quando user dispara novo evento (Confirmar/Desconhecer) pelo pai — histórico fica stale até troca de foco. P1 STALE STATE
6. Sem paginação se eventos > 50 — overflow vertical sem max-height. P3 (vs LinkedItens tem `max-h-64 overflow-y-auto`)
7. `created_at` formatação sem timezone — assume UTC ou BRT? Lição ADR 0066 (`format_date` shift +3h) aplica? P2

## Multi-tenant
- Endpoint scoped por `dfeId` que deve estar scoped por business_id no backend Controller.

## Recomendação
1. AbortController + cleanup useEffect (P1)
2. Refresh trigger via prop `refreshKey` controlada pelo pai após Confirmar/Desconhecer (P1)
3. Toast de erro em fetch falho (P2)
4. max-h-64 overflow (P3)
