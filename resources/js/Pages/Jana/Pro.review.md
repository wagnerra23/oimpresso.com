---
review_round: 1
review_type: static-analysis
reviewer: design:review (Fase 1 mecanizada)
review_at: 2026-06-01
page: Jana/Pro
file: resources/js/Pages/Jana/Pro.tsx
measured_against_sha: 720ffdce1
nota: 88
nivel: Leader
charter_present: true
charter_file: Pro.charter.md
runbook_present: false
append_only: true
---
# Review — `Jana/Pro.tsx` (Round 1)

> Append-only. Próximos rounds entram ABAIXO (NUNCA editar/remover blocos anteriores).
> Gerado por `prototipo-ui/audit/review-gen.mjs` (Fase 1 mecanizada). Fase 2 (juiz LLM) refina R5/R8/R10 + nota holística.

**Nota mecanizada:** 88/100 (Leader) · **medido vs SHA** `720ffdce1` · **ds/\*:** 0

## Sinais técnicos

- Shell `AppShellV2` ✓ (Cockpit V2)
- Ícones `lucide-react` ✓ (R4)
- Hooks React: useEffect, useState
- Inertia `@inertiajs/react` ✓
- Charter ✓ (`status: live` · ADRs 0140, 0110, 0190, 0093)
- RUNBOOK da tela — ausente

## Riscos / Guardrails Tier 0

- Guardrail (NÃO faz): Billing real (assinatura Asaas, gateway, cobrança) — é **Sprint JANA-B**
- Guardrail (NÃO faz): Downgrade/gestão de assinatura (cancelar, trocar cartão) — backlog Sprint B.
- Guardrail (NÃO faz): WhatsApp como canal de contato ("Falar com a Jana" → `/ia`, nunca WA — proibição).
- Guardrail (NÃO faz): Modal full-screen, emoji, cor crua de status (`text-emerald/rose`) — usa tokens.
- Guardrail (NÃO faz): Comparar mais de 2 planos (Enterprise R$ 499 entra só em GA — Sprint JANA-C).
- Guardrail (NÃO faz): Escrever no banco no render (tela de leitura + 1 ação; sem efeito colateral).
- Guardrail (NÃO faz): Mostrar dados de outro `business_id` (Tier 0 — businessId sempre da sessão).
- Anti-hook: Não dispara email/SMS/WhatsApp ao abrir nem ao clicar (CTA é mock até Sprint B).
- Anti-hook: Não chama LLM/Brain B no render.
- Anti-hook: Não persiste nada no client além do estado efêmero da CTA (sem localStorage).
- Anti-hook: Não cobra nem cria assinatura (billing é Sprint JANA-B, gated por Wagner).

## Top recomendações (backlog de tarefas — worst-first)

1. **P0 — R1** cor crua → tokens DS (roxo 295) — evidência: `11× — oklch(`. (GOLDEN-REFERENCE)
2. **P2 — Fase 2 LLM** pendente (R5 gradient · R8 PT-BR · R10 overflow-chain = `R5/R8/R10`) + nota holística + `best_of_class` — gate `$` [W] (cadência real-mode, ADR ratchet 0236).

> **Benchmark (best-of-class):** Linear/Stripe (zero cor crua, componente DS, sem nativo).

