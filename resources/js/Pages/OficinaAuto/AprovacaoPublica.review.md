---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/AprovacaoPublica
file: resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx
charter_present: true
charter_file: AprovacaoPublica.charter.md
runbook_present: false
runbook_notes: sem RUNBOOK-aprovacaopublica.md em memory/requisitos/OficinaAuto/ (apenas index/create/edit/show)
append_only: true
---

# Review estática — `OficinaAuto/AprovacaoPublica.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- **Rota PÚBLICA sem auth** — tela crítica (cliente externo aprova OS via WhatsApp + PIN)
- `useForm` ✓ · `usePage<{flash?}>()` para flash messages
- Header comment cita "token HMAC + business_id assinado (ADR 0093)" — multi-tenant aware ✓
- `tentativasRestantes: number` — rate limit consciente
- Props: `erro: 'link_invalido' | null`, `os: OsPayload | null` — defensive

## Riscos Tier 0

1. **SEC/M1 — Rota pública**: validar TODA defesa: HMAC token verify + PIN brute-force lockout (`tentativasRestantes` reset window?) + CSRF Inertia + rate-limit IP. Single tela = atacante target #1 do módulo.
2. **MWART/M2 — SEM RUNBOOK**: hook bloquear ou `/mwart-override`. Charter existe mas RUNBOOK não.
3. **PII/M2 — Vehicle plate exposta sem auth**: se token vazar (WhatsApp screenshot/email), atacante vê `plate`. Aceitável? Confirmar threat model na charter.
4. **A11Y/L3 — `pin` Input sem `inputmode="numeric"` aparente**: mobile keyboard
5. **LGPD/L3 — Sem consent banner**: cliente externo sem usuário interno → LGPD Art. 7º base legal? Documentar.

## Top 5 recomendações

1. P0 — Criar `RUNBOOK-aprovacaopublica.md` cobrindo: rate-limit, brute-force defense, HMAC verify, log de tentativas (audit trail).
2. P0 — Pest GUARD: token expirado/inválido NÃO vaza `os` payload (apenas `erro: 'link_invalido'`).
3. P0 — Pest GUARD: `tentativasRestantes === 0` bloqueia POST (não só UI).
4. P1 — `inputmode="numeric"` + `autocomplete="one-time-code"` no PIN.
5. P2 — Charter: documentar threat model + base legal LGPD.
