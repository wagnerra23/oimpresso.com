---
id: requisitos-sells-briefing
module: Sells
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Sells (verdade destilada)

## Estado atual
Sells é feature CORE do UltimatePOS (não há `Modules/Sells`): telas em `resources/js/Pages/Sells/` (Index, Create, Drafts, Quotations, Subscriptions, entre outras — inventário vivo é o diretório), lógica em `SellController`, `SellPosController` e `SellsV3Controller`. A tela de venda V2 (Inertia/React) está LIVE para biz=4 (ROTA LIVRE, maior parte do volume) desde 2026-05-27 (evidência tripla: guard biz=4 removido, flag default `true`, bugs da Larissa corrigidos em 27/05); o relógio humano segue aberto (canary 7d / monitor 30d, `SPEC.md` §US-SELL-001), com a remoção da Blade em US-SELL-009 `_pendente_`. Model `app/Transaction.php` (`type='sell'`), pipeline `app/Domain/Fsm/`; SDD `SDD-tela-venda-v1.0.md` (ADR 0351, #4868). A restrição de negócio de [L] é literal: a tela do Guilherme e da Larissa não pode ser alterada — como a flag é default `true` no fallback offline-safe do `FeatureFlagService` (o valor vivo está no GrowthBook self-hosted, não consultado), editar `Create.tsx` e deployar atinge a ROTA LIVRE direto — por isso existe a preview `/sells/create-v3` (desde 2026-08-07), paralela à ativa `/pos/create`, sem cutover previsto (contrato em `CreateV3.charter.md` + `CreateV3.casos.md` + `RUNBOOK-create-v3.md`). Capacidade e design são eixos não somáveis; Sells não tem module-grade canônico (`governance/module-grades-baseline.json` marca `deprecated_pending_decision` — decisão [W] pendente: criar wrapper ou deprecar a entrada). Contagem viva: `node scripts/governance/requisitos-status.mjs Sells`.

## Capacidades
- Venda V2 em produção; a preview V3 não escreve (sem `store()`, sem POST) mas calcula no front o lançamento de item — área, quantidade faturada, unitário líquido, total, parcelas e tributação/DIFAL (conflito registrado no charter, decisão de [L]) — enquanto os totais de topo chegam prontos do controller (cena estática, `CreateV3.charter.md`); a preview tem drawer de item com abas (#6360).
- Drawer `SaleSheet` com FSM: ações por estágio, RBAC e timeline auditável (ADR 0143).
- Integração veículo/Oficina, ligada só quando `OficinaAuto` está instalado.
- Emissão fiscal NFe/NFS-e individual e em lote a partir da lista de vendas.
- Cobrança e pagamento: chip/drawer de cobrança, linha de pagamento e diálogo rápido.
- Caixa do dia por origem (`Caixa/Index`), painel de IA da venda (`SaleAiPanel`) e impressão (orçamento A4, recibo 80mm, PDF, modo apresentação).
- Guards Tier 0 de valor após o incidente `num_uf` (2026-06-05, `final_total` inflado em biz=4; fix #2279): `IncidentValorInfladoNumUfTest`, `NumUfHeuristicPtBRTest`, `NumericInputPtBR`, `SellsFinalTotalAuditCommand` e a rule `calculo-valor-estoque`.

## Gaps
- US-SELL-040 (P0): os guards do `num_uf` pegam a classe do incidente, mas não há teste HTTP provando que `final_total` grava certo de ponta a ponta.
- US-SELL-009: remover a Blade legacy da venda — só após cutover na ROTA LIVRE + 30 dias de monitor (relógio humano).
- Reverter/estornar um cancelamento: plausível, sem rota nem US achadas — não medido.
- Erratas que não voltam como gap: o dashboard `/relatorios/vendas-origem` é fantasma; ADR 0192 não é gap.

## Última mudança
Contrato de comportamento do editor (#6455, 2026-08-31) e as abas do drawer de item (#6360, 2026-08-27); depois, a Fronteira da preview (#6484, 2026-09-03) e o design-sync (#6892, 2026-09-06).

## Proveniência (destilado de)

- audit `requisitos/Sells/AUDIT-cockpit-runbook-Create-2026-05-15.md` — AUDIT-cockpit-runbook-Create-2026-05-15.md
- audit `requisitos/Sells/CAPTERRA-DESIGN-FICHA.md` — CAPTERRA-DESIGN-FICHA.md
- audit `requisitos/Sells/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- handoff `handoffs/2026-08-11-1514-venda-v3-densidade-e-a-utilitaria-que-nao-mordia.md` (2026-08-11) — 2026-08-11-1514-venda-v3-densidade-e-a-utilitaria-que-nao-mordia.md
