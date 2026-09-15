---
id: requisitos-repair-briefing
module: Repair
status: shared-infra
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: "agente (re-leitura manual dos docs novos do módulo; `jana:distill-module-truth` não rodou — PHP indisponível no ambiente do agente e a execução dele segue gate [W]/CT100, ADR 0291 D-E)"
---

# BRIEFING — Repair (verdade destilada)

## Estado atual
Ordens de serviço como infraestrutura compartilhada entre verticais (`OficinaAuto`, `ComunicacaoVisual`, `Vestuario`). O Kanban `ProducaoOficina` e o FSM Pipeline de OS (13 estágios, ADR 0143) estão operacionais. O SPEC saiu do estado de placeholder: US-REPA-001 segue `_pendente_`, mas US-REPA-003/004/005 documentam telas vivas com âncora `**Testado em:**` (lane Verticais · Pest MySQL). O job `Pest Repair` vem da matriz `modules-pest.yml`; o enforcement dele é o que estiver em `governance/required-checks-baseline.json` — e foi por não bloquear merge que o vermelho do `Wave18RepairSaturationTest` (`Call to undefined method Container::basePath()`, run 31040822015 de 2026-08-05: 3 failed, 65 skipped, 80 passed) rodou sem segurar nada até o conserto em #6240 (2026-08-25); a matriz dispara por path de qualquer módulo dela, por isso o vermelho aparecia em PR alheio.

## Capacidades
- JobSheet (OS) com criação, edição e impressão.
- Kanban com colunas fixas (Recepção, Diagnóstico, Aguardando peças, Em execução, Pronto) e `repair_statuses` configuráveis por business; drag-and-drop (US-REPAIR-PROD-4).
- Venda derivada da OS: faturamento pelo POS a partir do JobSheet (`sub_type=repair&job_sheet_id=`), com card `VendaDerivadaCard` no Kanban.
- FSM Pipeline de estágios (`FsmProcessoOsReparoPadraoSeeder`).
- Vocabulário genérico multi-vertical com personalização por negócio.
- Contrato executável em Pest Feature nas telas de OS e de cadastro (#6882/#6883/#6884/#6887), com E2E + a11y no Kanban `ProducaoOficina` (#6878); `JobSheet/Index` segue sem `casos.md` — US-REPA-004 `_parcial_`.
- Dashboard (`/repair/dashboard`) com KPIs de **operação**, não de cadastro: `pending` (com `pending_unassigned` na descrição), `completed` e `overdue` (tom `danger` só quando > 0), numa agregada só com `leftJoin` no catálogo de status. Substituiu `total_repairs` (que contava status distintos, não OS) e `service_staff_count`. Mantém as barras de OS por status e por técnico e os 3 painéis de tendências — o de aparelhos deixou de receber `[]` literal, já que o Controller rodava `getTrendingDevices()` e descartava o resultado.

## Gaps
- Top-5 da FICHA (US-REP-005..009): app mobile, comissão, catálogo, retention-purge. O item **KPIs/dashboard** saiu do estado zero — os 3 KPIs de operação existem e têm teste; o que segue fora é o **ticket médio**, rejeitado de propósito nesta onda por ser média de valor monetário (REGRA MESTRE de VALOR: dupla prova por 2 caminhos + tabela antes→depois + aprovação [W]) e o **banner** de entrega vencida com CTA "Ver as atrasadas", cujo clique é drilldown e Non-Goal do charter.
- Configurações migradas para Inertia atrás da flag `repair_settings_index` (default OFF, #6779) e no PageHeader canon (#6814); o cutover da flag é decisão [W] — SPEC US-REPA-003 `_parcial_`.
- `repair:fsm:bulk-start` para iniciar OS legadas no FSM não existe (US-REP-FSM-006).
- O `base_path()` fora do bootstrap que quebrava o `Wave18RepairSaturationTest` foi corrigido em #6240 (2026-08-25); `SPEC.md` US-REPA-002 ainda diz `_pendente_` — SPEC atrás do código (precedência: teste > SPEC), correção pendente.

## Última mudança
2026-09-09..15 — onda de FORMA do `Dashboard/Index` sob [ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) (protótipo soberano no eixo forma). A F1 do MWART foi feita — [RUNBOOK-repair-dashboard.md](RUNBOOK-repair-dashboard.md) —, o que derrubou o bloqueio que o UC-RDSH-03 citava (*"mexer no `.tsx` exige RUNBOOK do Dashboard, que não existe"*). Charter, `casos.md` e teste mudaram no MESMO PR: UC-RDSH-02 virou controle negativo do KPI antigo e UC-RDSH-03 acompanhou o painel de aparelhos que parou de vir vazio. As duas linhas que o [gap-spec](repair-dashboard-gap.md) marcava como *"decidir — construir ou rejeitar por escrito"* têm resposta escrita lá. Antes: 2026-09-04..06 — onda MWART das Configurações (Inertia, #6779), `Settings/Index` no PageHeader canon (#6814), primeiro E2E + a11y do módulo (#6878), contrato executável das telas (#6882/#6883/#6884/#6887) e revogação de GUARDs fantasmas no charter `JobSheet/Index` (#6874). Antes: perf D-14 partial reload em `DeviceModels/Index` e `Repair/Index` (telas MWART/Inertia; #3901, 2026-07-06) e o draft de charter da OS (#4123, 2026-07-12).

## Proveniência (destilado de)

- runbook `requisitos/Repair/RUNBOOK-repair-dashboard.md` (2026-09-09) — F1 do MWART da tela do Dashboard
- gap-spec `requisitos/Repair/repair-dashboard-gap.md` (2026-09-15) — §"Resposta ESCRITA aos dois Decidir"
- audit `requisitos/Repair/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r3.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r3.md
- session `sessions/2026-09-05-raio-vazamento-variation-cross-tenant.md` (2026-09-05) — 2026-09-05-raio-vazamento-variation-cross-tenant.md
- handoff `handoffs/2026-08-08-1938-permissoes-classe-d-idioma-gate-before.md` (2026-08-08) — 2026-08-08-1938-permissoes-classe-d-idioma-gate-before.md
