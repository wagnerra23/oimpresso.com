---
page: Whatsapp/Templates/Index
file: resources/js/Pages/Whatsapp/Templates/Index.tsx
charter: AUSENTE
review_round: 1
review_type: static-analysis
review_at: 2026-05-17
reviewer: W31 Bulk Review R1 (agent)
status: draft (aguarda Wagner)
seed_pattern: resources/js/Pages/Admin/GovernanceV4.review.md (referência charter v4)
---

# Review estática R1 — `resources/js/Pages/Whatsapp/Templates/Index.tsx`

> Análise estática sem execução. Tela LIVE (Lote 2e implementada per `@memcofre` header). US-WA-013 + ADR 0096 (Meta Cloud direto).

## Resumo

Listagem de templates HSM Meta + locais Z-API/Baileys. Sync HSM Meta (botão). Criar template LOCAL inline (form). Filtros provider+status. Aviso `orphanCount` (LOCAL sem contraparte Meta = fallback ban falha). `<Deferred>` wrap em templates list — D-14 pattern aplicado corretamente.

## Aderência ao canon

| Item | Status | Nota |
|---|---|---|
| Charter ao lado | ❌ AUSENTE | Bloqueio MWART (ADR 0104). Tela LIVE sem charter (LGPD-relevante via WhatsApp opt-in) |
| RUNBOOK | ✅ provável (existe `memory/requisitos/Whatsapp/SPEC.md` per header) | Confirmar `RUNBOOK-templates-index.md` |
| Inertia::defer + `<Deferred>` | ✅ APLICADO (linha 219) | `data="templates"` com skeleton 5 cards + Loader2 — exemplar pattern D-14 |
| Multi-tenant Tier 0 | ⚠️ Dependente backend | `templates?: Template[]` esperado escopo `business_id` no `WhatsappTemplateController@index` — verificar |
| Localstorage | ✅ N/A | Sem persistência |
| Cor semântica ADR 0110 | ❌ VIOLAÇÃO sistêmica | Múltiplas cores cruas: `border-amber-300 bg-amber-50 text-amber-900` (linha 176-178), `border-emerald-500 text-emerald-700` (linha 275-276), `text-red-700 bg-red-50 border-red-200` (linha 285), `text-amber-700` (linha 290) |
| Permissão | ✅ declarada `whatsapp.templates.manage` (linha 6) | Spatie middleware precisa estar no controller |
| `route()` helper Ziggy | ✅ usado (linhas 57, 63, 72) | Routes nomeadas — bom |
| Emoji UI | ✅ mínimo | Apenas `lucide-react` icons — exemplar |

## Top 5 riscos identificados

1. **CHARTER AUSENTE em tela LIVE LGPD-relevante** — Whatsapp templates afetam opt-in cliente. Sem charter falta Non-Goal "NÃO permite criar template MARKETING sem consent flow LGPD".
2. **Cores cruas amber/emerald/red sistêmicas** — ≥6 ocorrências violando ADR 0110. Banner orphan (linha 176-184), badges ready/rejected (linha 275, 285), warnings (linha 290). Batch refactor recomendado.
3. **Sync Meta sem feedback de progresso** (linha 60-67) — `syncMeta` faz POST + `setSyncing(false)` no `onFinish`. Se sync demorar (Meta API lenta), user só vê "Sincronizando..." sem ETA / quantos templates / erros parciais. Idealmente: response com `synced: N, failed: M, errors: [...]`.
4. **Form inline criar template SEM validação client-side estrita** — `placeholder="repair_status_ready"` mas `setNewTpl({...newTpl, name: e.target.value.toLowerCase()})` (linha 132) só lowercase. Não valida snake_case (sem espaço/special chars). Backend rejeita → toast genérico (provavelmente). Adicionar regex `^[a-z][a-z0-9_]{2,63}$` client-side.
5. **`createSubmitting` flag não bloqueia re-submit via Enter no Textarea** — se user pressionar Enter no textarea body durante submit, sem race obvio mas potencial. Adicionar `onKeyDown preventDefault` se `createSubmitting`.

## Pest GUARD recomendados (pendente)

```php
it('renders /whatsapp/templates with Deferred wrapper (templates not eager)')
it('blocks user without whatsapp.templates.manage permission (403)')
it('isolates templates by business_id (biz=1 vs biz=4)')
it('sync_meta returns synced/failed counts (not just 200 OK)')
it('store rejects name not matching ^[a-z][a-z0-9_]{2,63}$')
it('store rejects MARKETING category without LGPD opt-in flow (charter Non-Goal)')
it('respects ADR 0110 — no raw bg-(amber|emerald|red)-N classes')
it('renders at 1280px (Larissa balcão) without horizontal scroll')
```

## Recomendações priorizadas

| # | Ação | Prioridade | Owner sugerido |
|---|---|---|---|
| 1 | Criar `Index.charter.md` (Mission + Non-Goal LGPD opt-in MARKETING) | P0 bloqueador | Wagner aprova |
| 2 | Verificar `RUNBOOK-templates-index.md` existe (MWART gate) | P0 | F |
| 3 | Refactor cores cruas amber/emerald/red → tokens Cockpit V2 | P1 | F3 followup |
| 4 | `sync_meta` response com counts detalhados + UI feedback | P1 UX | F |
| 5 | Validação client-side regex name + erro inline | P2 | F3 followup |
| 6 | Pest GUARD permission + isolation + LGPD opt-in | P2 | Agent C |

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W31 Bulk R1 | Review estática R1 criada. Aguarda Wagner. Charter AUSENTE flagged P0 (LGPD-relevante). |
