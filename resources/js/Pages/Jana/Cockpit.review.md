---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Cockpit
file: resources/js/Pages/Jana/Cockpit.tsx
charter_present: true
charter_file: Cockpit.charter.md
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-cockpit.md
append_only: true
---

# Review estática — `Jana/Cockpit.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · charter+RUNBOOK ✓ (compliance MWART completo)
- Status: `live` (pivot Cowork aceito, supersedes MVP-piloto WhatsApp anti-pattern)
- US-COPI-COCKPIT-002 V2 Analista IA + ADRs 0035/0039/0094/0104/0107/0114
- Visual source: `prototipo-ui/_cowork-export-2026-05-15/chat-jana.{jsx,css}` + CRITIQUE
- **CRÍTICOS Tier 0 declarados no comment:**
  - business_id scope via Controller (mock fase F2)
  - PT-BR em todo label
  - PII detector regex CPF/CNPJ/cartão composer + PiiRedactor server real
  - `dangerouslySetInnerHTML` PROIBIDO (markdown parser custom limitado · F3 troca por react-markdown + rehype-sanitize)
- Tipos ricos: Brief, Kpi, AnaliseKind (buckets/sparkline/bars/list/donut/text), PillTone, AcaoTone
- Streaming: `StreamingKind = 'markdown'`

## Riscos Tier 0

1. **XSS/M1 — Parser markdown custom limitado**: anti-pattern explícito declarado. Risco XSS se input não-sanitizado escapar. **Prioridade alta** trocar por `react-markdown` + `rehype-sanitize`.
2. **CUSTO IA/M1 — Cockpit consome LLM**: tracking + mock mode env.
3. **PII/M2 — Composer regex client + server PiiRedactor**: ambos camadas (defense-in-depth) ✓ — validar Pest.
4. **F2 MOCK/L3 — `business_id scope mock fase F2`**: validar transição F3 pra query real Tier 0 (ADR 0093).
5. **STREAMING/L3 — `'markdown'` streaming**: SSE? Tokens sanitized stream-by-stream?

## Top 5 recomendações

1. P0 — Migrar parser markdown custom → `react-markdown` + `rehype-sanitize` (F3 prioridade).
2. P0 — Pest GUARD: PiiRedactor server-side bloqueia CPF/CNPJ/cartão pre-LLM.
3. P0 — Pest GUARD: Cockpit não escapa `dangerouslySetInnerHTML`.
4. P1 — F2→F3 transição mock→query real com Pest cross-tenant.
5. P2 — Custo IA tracking + mock env var.
