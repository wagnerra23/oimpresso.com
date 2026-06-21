---
date: "2026-06-09"
topic: Avaliação OS git + sweep ADR 0265 front + fila V2 drawer (ServiceOrderRichSheet)
authors: [C, W]
prs: [2477]
related_adrs: [0265-oficina-reparo-erradica-locacao, 0251-veiculo-na-venda-direta-oficina, 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada]
---

# Sessão 2026-06-09 — Avaliação OS git + sweep ADR 0265 front + fila V2 drawer

**Papel:** [CC] · **Pedido [W]:** conferir print feio da Oficina → avaliar módulo OS do git → arrumar via protocolo → registrar fila V2.

## TL;DR
Print da OS consertado + front da Oficina varrido de resíduos de locação (ADR 0265) e mergeado como PR #2477 (43 checks verdes). Drawer `ServiceOrderRichSheet` no main confere com o protótipo canon. [W] aprovou registrar a fila V2 (OS-V2-1..4: fotos/laudo, DVI inline, gate de aprovação hero, timeline FSM) + residuais de dados (backfill `order_type`, labels FSM) no COWORK_NOTES e na fila de review.

## O que foi feito
1. **Diagnóstico print:** template Blade A4 OK; mecanismo iframe oculto 0×0 + window.print() no srcdoc falhava em Brave/Chromium. Repro em `_scrap/oficina-os-print-repro.html`.
2. **Avaliação F1.5 do módulo OS** (`prototipo-ui/AVALIACAO_OS_GIT_2026-06-09.md`): 65/100. Causa-raiz: ADR 0265 erradicou locação no backend, front nunca varrido — `mecanica` nem existia nos types, caía no ramo locação ("Caçamba", "Diárias").
3. **Sweep front + fix print** entregue via ponte zero-toque → Code landou como **PR #2477, squash a0680f474, MERGED no main** (43 checks CI verdes). Conferido pós-merge linha a linha: printServiceOrder novo, Create sem Locação + combobox Cliente, RichSheet 100% reparo.
4. **Conferência do drawer no main:** já espelha protótipo canon — MercosulPlate (shared, ADR 0251), KV hero, Peças & Mão de obra, VendaDerivadaCard, FSM panel, timeline reparo, footer 3 ações.
5. **Fila V2 registrada** ([W] aprovou): bloco F0 appendado em `COWORK_NOTES.md` com OS-V2-1 (Fotos & Laudo) · OS-V2-2 (DVI inline) · OS-V2-3 (gate aprovação hero) · OS-V2-4 (timeline FSM real) + residuais (backfill order_type, labels FSM).

## Decisões
- Keys FSM `cacamba_locacao` NÃO migram (trava Tier 0 charter v4) — só labels, e como chore separado.
- Show.tsx × RichSheet (duas verdades do detalhe): consolidação adiada, decisão [W] pendente.

## Erros + correção
- [CC] afirmou que drawer real não tinha MercosulPlate/visual do protótipo — estava DESATUALIZADO; conferência no git mostrou que main já tinha. Regra: conferir o arquivo no main ANTES de listar gaps.

## Residual
- F2 visual pendente: MySQL local parado ([W] precisa subir Herd + npm run build + hard refresh).
- Backfill de dados + rename labels FSM (no bloco F0, item residual).
- PR #2475 (dead-code) em rebase pelo Code — overlap com #2477 confirmado disjunto.

## Refs
PR #2477 (a0680f474) · ADR 0265 · ADR 0251 · ADR 0194 · `prototipo-ui/AVALIACAO_OS_GIT_2026-06-09.md`

## Próximo passo
[W] cola prompt da ponte no Code (append COWORK_NOTES + sessão) → depois F1 do OS-V2-1/V2-2 por [CC].
