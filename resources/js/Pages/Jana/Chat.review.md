---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Chat
file: resources/js/Pages/Jana/Chat.tsx
charter_present: true
charter_file: Chat.charter.md
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-chat.md
append_only: true
---

# Review estática — `Jana/Chat.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · `JanaAssistantUiChat` + `ThreadHeader` + cockpit shared ✓ · charter+RUNBOOK ✓
- Status: implementada Sprint 1 AppShellV2
- US-COPI-001/002/003/MEM-007 + ADRs 0026/0031/0032/0034/0035/0036/0039/UI-0008
- `Proposta` (metas) com payload_json — fluxo Jana propõe meta
- `Sugestao` + `MensagemBackend{role,content,propostas?}`
- `useMemo`/`useState`/`toast` (sonner)
- Imports cockpit shared (BusinessOpt, ConversaFoco, etc) — pattern canon
- `usuarioCargo`/`usuarioIniciais` shell props (multi-tenant cockpit)

## Riscos Tier 0

1. **CUSTO IA/M1 — Chat consome LLM**: validar custos tracking + mock mode pra Pest (`JANA_CHAT_FORCE_MOCK=true`?). ADR 0094 §4 obriga custo tracking.
2. **PII/M2 — User input livre**: `PiiRedactor` server-side ANTES de mandar pra LLM. Confirmar.
3. **PERF/M2 — `mensagens: MensagemBackend[]` EAGER**: thread longa = payload grande. Deferir ou paginar (load older).
4. **STREAMING/L3 — Charter cita streaming?**: validar SSE vs polling vs full-page.
5. **LGPD/L3 — Conteúdo conversas armazenadas indefinidamente?**: política retenção.

## Top 5 recomendações

1. P0 — Pest GUARD custo IA tracking (mcp_audit_log entries) + mock mode env var.
2. P0 — PiiRedactor enforce pre-LLM call (Pest GUARD).
3. P1 — Deferir `mensagens` + lazy "carregar histórico" botão.
4. P2 — Charter: validar streaming vs polling round 2.
5. P3 — LGPD: política retenção conversas (TTL configurable).
