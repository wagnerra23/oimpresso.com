# Session 2026-05-16 — CAPTERRA-FICHA Repair (Wave 22)

## Contexto

Wave 22 governance mega — agent dedicado CAPTERRA-FICHA do módulo Repair (Kanban OS shared infrastructure consumido por Vestuario/ComunicacaoVisual/OficinaAuto).

Branch: `claude/governance-wave-21-22-mega`. Worktree: `jolly-hypatia-b8741c`.

## O que foi feito

1. **WebSearch 4 buscas** sobre estado-da-arte 2026:
   - RepairShopr / Lokoz / ConsertaTudo / mHelpDesk comparison
   - RepairShopr features customer portal SMS
   - mHelpDesk RepairDesk field service kanban
   - Lokoz oficina BR + state machine workflow customer signature
2. **Leitura**: BRIEFING.md + SPEC.md + ARCHITECTURE.md + lista controllers Repair (11) + template ProjectMgmt/CAPTERRA-FICHA.md
3. **Criado**: `memory/requisitos/Repair/CAPTERRA-FICHA.md` (10 seções canônicas)
   - 21 capacidades catalogadas (6 P0, 8 P1, 3 P2, 4 P3)
   - Nota ponderada: **68.9/100** (31.0/45 pontos)
   - Top 5 gaps prioritários + roadmap 3 fases
4. **Concorrentes mapeados**: RepairShopr (global ticket+portal), mHelpDesk (global field service mobile), Orderry (workflow no-code 2026), Lokoz/Online OS/Oficina Integrada/LKOS (BR PMEs)

## Decisões tomadas

- **Nota 68.9/100** — Repair é forte em fundação (FSM canônica + multi-tenant + kanban drag-drop + shared infra) mas fraco em automation (SMS, NFSe gancho FSM), customer portal moderno, e mobile-first técnico (DVI foto/vídeo, signature)
- **Top 5 gaps priorizados**: #1 SMS/WhatsApp FSM listener, #2 customer portal moderno, #3 DVI foto/vídeo, #4 assinatura digital, #5 NFSe auto-emissão
- **Roadmap 3 fases** (~2sem + 1mês + 6sem)

## Diferencial competitivo identificado

- Único modular especializado por vertical (Vestuario/ComVis/OficinaAuto consomem mesma infra)
- FSM canônica com gateway service + audit append-only (forte vs RepairShopr status simples)
- Charter governance UI (9 charters Pages) — diferencial governança vs concorrentes

## Gaps Wave 23+ candidatos

| # | Gap | Esforço | ROI |
|---|---|---|---|
| 1 | FsmStageChanged listener → WhatsappNotificarJob | M | ALTO (-40% calls suporte) |
| 5 | FsmStageChanged listener → EmitirNFSeJob | S | ALTO (vertical BR) |

Wagner aprova antes Wave 23 disparar.

## Restrições respeitadas

- ✅ PT-BR
- ✅ Isolamento de áreas (só `memory/requisitos/Repair/CAPTERRA-FICHA.md` + `memory/sessions/2026-05-16-capterra-repair.md`)
- ✅ Sem git ops
- ✅ FSM canônica preservada (gateway service obrigatório)
- ✅ Multi-tenant Tier 0 mencionado em todas capacidades aplicáveis

## Arquivos criados

- `memory/requisitos/Repair/CAPTERRA-FICHA.md` (281 linhas)
- `memory/sessions/2026-05-16-capterra-repair.md` (este)
