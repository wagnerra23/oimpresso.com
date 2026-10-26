---
id: requisitos-comissao-briefing
---

# BRIEFING — Comissao · 🌱 WISH (status: wish)

> **Feature-wish formal — aguarda sinal qualificado** (ADR 0105). O motor de comissão por regra (US-COMM-*) não existe; a comissão básica herdada do UltimatePOS, sim.
>
> **O que existe hoje (medido no `main` em 2026-10-26, commit `93d6a5d11c`):**
> - percentual fixo por vendedor (`users.cmmsn_percent`) e por comissionado externo (`SalesCommissionAgentController`, tela `Comissionados/Index`);
> - divisão da comissão de uma venda entre mecânico e balcão (`SellCommissionSplitController`, `PATCH /sells/{id}/commission-split`, editor `Sells/_components/CommissionSplitEditor.tsx`, testes `tests/Feature/Sells/CommissionSplit*Test.php`);
> - relatório de comissão por vendedor (`ReportController`, resumo em React e as abas de listagem na Blade).
> - **Falta** a comissão por regra: por produto, categoria, faixa ou meta, o fechamento do mês e o estorno no cancelamento (US-COMM-001 a 014). A comissão escalonada do Vestuário entra aqui.
> - Comissões de venda; dormente (ADR 0151).
> - Decisão E1 (frente KL · 2026-06-15): GENUÍNO — manter como wish; porta mínima.
> - Detalhe (quando houver) → `SPEC.md` desta pasta.

**Tipo:** porta de wish (KL-E2). Backlog só recebe item com sinal de cliente pagante (ADR 0105).
