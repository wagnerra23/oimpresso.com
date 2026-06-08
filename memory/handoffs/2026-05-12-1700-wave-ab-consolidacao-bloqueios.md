---
date: 2026-05-12 17:00 BRT
slug: wave-ab-consolidacao-bloqueios
prs_session: [670, 671, 672, 673, 674, 676]
prs_mergeadas_session: [670, 671, 672, 673, 674, 676]
prs_pendentes_decisao_wagner: [667]
total_session_linhas: ~6226 (4558 Wave A + 1668 Wave B ComVis V0)
contexto_anterior: handoffs/2026-05-12-1430-fsm-pipeline-canon-live-50prs.md (ADR 0143 LIVE prod)
---

# Handoff 2026-05-12 17:00 — Wave A+B consolidação + bloqueios Wave B (3/4)

## TL;DR pra próxima sessão

**Wave A toda mergeada (5 PRs).** Wave B disparou apenas 1/4 (ComVis V0 — único autorizado por ROADMAP sem pré-reqs Wagner). Outros 3 (Garantia/OficinaAuto/Dashboard) **bloqueados aguardando decisões Wagner** mapeadas neste handoff.

Próxima sessão começa com `brief-fetch` + 3 decisões objetivas (~30min) que destravam 2 implementadores paralelos Wave B na sequência.

## Estado MCP no momento do fechamento

> _Não consultei tools MCP nessa sessão (focused em consolidação + handoff)._ Próxima sessão deve rodar:
> - `brief-fetch` (estado consolidado)
> - `my-work` (tasks DOING/REVIEW)
> - `cycles-active` (cycle ativo + goals + drift)
> - `decisions-search since:2026-05-12` (ADRs novas — esperar 0143 ali se aceita)

Estado git canônico (que MCP indexa):
- **main HEAD:** 15b09055 (após merge #676 ComVis V0)
- **ADR canon:** 0143 FSM Pipeline LIVE prod biz=1+biz=4 (este handoff confirma marco LIVE 2026-05-12)
- **PRs abertas:** #667 Marketplaces (Wagner decidiu "deixa parado" — aguarda sinal cliente)
- **Branches stale a limpar próxima sessão:** `claude/inventory-avancado-kits-batch-dimensional` (deletar — duplica trabalho já mergeado), `claude/session-handoff-2026-05-12-1700-wave-ab` (manter até PR memory merged)

## PRs nesta sessão

| PR | Tipo | Linhas | Domínio | Status |
|---|---|---|---|---|
| [#670](https://github.com/wagnerra23/oimpresso.com/pull/670) | feat(inventory): F1 Kits/BOM CODE | 1033 | `app/Domain/Inventory/` + FSM SideEffects integração + Pest 9 specs | ✅ merged |
| [#671](https://github.com/wagnerra23/oimpresso.com/pull/671) | docs(crm): SPEC+ADR+MATRIZ+ROADMAP | 1036 | Modules/Crm já maduro discovery — gap Whatsapp↔CRM #1 | ✅ merged |
| [#672](https://github.com/wagnerra23/oimpresso.com/pull/672) | docs(pcp): SPEC+ADR+MATRIZ+ROADMAP | 787 | Repair Kanban shared-infra extensible vs Modules/Pcp novo | ✅ merged |
| [#673](https://github.com/wagnerra23/oimpresso.com/pull/673) | docs(comissao): SPEC+ADR+MATRIZ+ROADMAP | 1002 | 5 artefatos UPos fragmentados — consolidar `sale_commissions` | ✅ merged |
| [#674](https://github.com/wagnerra23/oimpresso.com/pull/674) | docs(financeiro): SPEC+ADR+MATRIZ+ROADMAP | 700 | Modules/Financeiro maduro; gaps: DRE, fluxo 12m, OFX, drill-down | ✅ merged |
| [#676](https://github.com/wagnerra23/oimpresso.com/pull/676) | feat(comvis): F1 V0 scaffold CODE | 1668 | 5 cv_* migrations + Entities + FSM seeder + Tier0GuardTest | ✅ merged |

**Total session:** ~6226 linhas (1033 + 1668 CODE; 3525 docs estratégicos).

## 🚨 PRÉ-DEPLOY Hostinger (rodar via SSH antes do próximo cliente bater nos módulos)

Regra [`feedback_migrate_obrigatorio_pos_deploy.md`](../requisitos/Infra/) — quick-sync.yml NÃO roda migrate. SSH manual:

```bash
ssh -4 -o ConnectTimeout=900 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd domains/oimpresso.com/public_html && composer dump-autoload --no-scripts && php artisan migrate --force'
php artisan db:seed --class=Database\\Seeders\\FsmProcessoComunicacaoVisualSeeder --force
```

**Migrations a aplicar (3):**
1. `2026_05_12_080001_create_product_bom_table.php` (Inventory PR #670)
2. `2026_05_12_000010..14_create_cv_*_table.php` (ComVis 5 migrations PR #676)
3. FK pré-requisito: `sale_process_stages` precisa estar migrada (FSM canon ADR 0143 — já LIVE prod desde 14:30 BRT)

**Validação smoke pós-deploy:**
```sql
SELECT name FROM cv_substratos WHERE business_id=1 LIMIT 5;  -- vazia (esperado V0)
SELECT COUNT(*) FROM sale_processes WHERE slug='comvis_os_padrao';  -- ≥1 per business
SELECT COUNT(*) FROM sale_process_stages WHERE process_id IN (SELECT id FROM sale_processes WHERE slug='comvis_os_padrao');  -- 16 per business
```

## 🔒 Bloqueios Wave B pendentes Wagner

### 1. Garantia Fase 1 — Wagner decide D1-D6

ROADMAP §F1 pré-req: *"Wagner decide D1-D6 → numerar próximo ADR após 0143"*.

ADR proposal: [`memory/decisions/proposals/drafts/garantia-cross-vertical-workflow.md`](../decisions/proposals/drafts/garantia-cross-vertical-workflow.md)

Decisões pendentes (D1-D6):
- **D1** Schema 5 tabelas: `warranty_policies` + `warranty_claims_eligibility` + `warranty_claims` + `warranty_resolutions` + `warranty_reimbursements`. Aprovar?
- **D2** Cross-vertical OS-filha pattern: garantia em Sells (Vestuario) cria OS-filha em Repair pra reparar peça defeituosa? OU Cancel+ReVenda?
- **D3** Listener strategy: `WarrantyEligibilitySnapshotter` escuta quais events? (`concluir_producao` Sells + `entregue_completo` Repair + `boleto_pago` Autopecas)
- **D4** CFOP policy fiscal: NFe substituição (CFOP 5.949) ou devolução (CFOP 1.949)? Toggle per-business?
- **D5** Termo Rejeição PDF jurídico: Eliana[E] valida template antes Fase 3?
- **D6** LGPD opt-in: registro consumidor consent armazena foto/dados garantia 5 anos (defesa)?

**Pós-decisão:** mover ADR proposal `draft → proposed` → numerar próximo ADR (≥0144) → mover `proposed → accepted` → desbloqueia Wave B Garantia F1 (~15h IA-pair × 2 margem = ~30h Felipe + Pest local).

### 2. OficinaAuto Fase 1 — Wagner decide rename + MATRIZ-ROI sign-off

ROADMAP §F1 pré-req:
- *"Wagner aprovou rename `vehicles` → `oa_vehicles` (ADR proposal D2)"*
- *"Wagner sign-off matriz ROI top 5"*

ADR proposal: [`memory/decisions/proposals/drafts/oficina-auto-modulo-canonico-fsm-wireup.md`](../decisions/proposals/drafts/oficina-auto-modulo-canonico-fsm-wireup.md)

MATRIZ-ROI: [`memory/requisitos/OficinaAuto/MATRIZ-ROI.md`](../requisitos/OficinaAuto/MATRIZ-ROI.md)

Decisões pendentes:
- **D2 naming**: `vehicles` (atual V0) vs `oa_vehicles` (canon ADR proposal). Migration rename trivial.
- **Top 5 ROI sign-off**: revisar e marcar quais 5 features de Fase 1-2 prioritárias (US-OFICINA-002 importer Martinho? US-OFICINA-009 defeitos JSON? US-OFICINA-005a/b/c cleanup tools?).

**Pós-decisão:** desbloqueia Wave B OficinaAuto F1 partial — algumas entregas são safe sem acesso Firebird (defeitos JSON, dedupe PESSOAS fuzzy, UI cleanup pendências). Importer Martinho real requer Wagner provê dump Firebird.

### 3. Dashboard Executivo — defer Wave C

Não consta nos pré-reqs Wave B. Aguarda 1+ cliente piloto reportando uso real (ADR 0105 sinal qualificado). Não disparei pesquisador.

## Lições novas (consolidadas em git canônico)

1. **Pattern paralelização N agents na mesma worktree** — adicionado §novo em [`how-trabalhar.md`](../how-trabalhar.md). Cada agent em PASTAS ISOLADAS + sem git ops + parent consolida em PRs separadas. Comprovado Wave A (5 agents) + Wave B (1 agent).

2. **Prompt agent com regra "comparar-não-duplicar"** — Wagner Tier 0 exige agent LER módulos referência ANTES de criar. ComVis V0 agent reusou Sprint 1 + pulou `cv_orcamentos` por causa dessa instrução explícita no prompt.

3. **ROADMAPs têm pré-reqs Wagner antes de Fase N** — ler ROADMAP da fase ANTES de disparar agent. Disparar sem checar = retrabalho ou decisões assumidas erradas. Conservador: pedir desbloqueio Wagner primeiro.

4. **PRs separadas via stash → fresh branches** — quando working tree tem N domínios pra dividir: stash all → checkout -B claude/<dominio-N> origin/main → seletivo add → commit + PR. Limpo e auditável (Wave A 5 PRs comprova).

## Como retomar (sessão próxima)

1. `brief-fetch` (estado consolidado MCP — ADR 0143 LIVE prod + Wave A+B status)
2. `my-work` (tasks DOING/REVIEW)
3. Ler este handoff (5min) + session log [2026-05-12-1700-wave-ab-inventory-comvis-v0.md](../sessions/2026-05-12-1700-wave-ab-inventory-comvis-v0.md)
4. Confirmar pré-deploy Hostinger rodado (validação smoke SQL acima)
5. **Decisão objetiva A (Garantia D1-D6)**: abrir [`garantia-cross-vertical-workflow.md`](../decisions/proposals/drafts/garantia-cross-vertical-workflow.md) + decidir 6 → mover `proposed`
6. **Decisão objetiva B (OficinaAuto rename + ROI)**: abrir MATRIZ-ROI + decidir top 5 + naming `oa_*`
7. Disparar Wave B continuação: 2 implementadores paralelos (Garantia F1 + OficinaAuto F1 partial)

Tempo estimado próxima sessão até disparo: ~30min decisões + ~5min sync git + ~5min spawn agents = ~40min handoff→ação.

## Refs

- [Session log 2026-05-12 17:00](../sessions/2026-05-12-1700-wave-ab-inventory-comvis-v0.md)
- [Handoff 2026-05-12 14:30 FSM canon LIVE](2026-05-12-1430-fsm-pipeline-canon-live-50prs.md)
- [ADR 0143 FSM Pipeline LIVE](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [Garantia ROADMAP §F1](../requisitos/Garantia/ROADMAP.md)
- [OficinaAuto ROADMAP §F1](../requisitos/OficinaAuto/ROADMAP.md)
- [ComVis ROADMAP §F1 — DONE este PR](../requisitos/ComunicacaoVisual/ROADMAP.md)
- [Inventory PR #670](https://github.com/wagnerra23/oimpresso.com/pull/670)
- [ComVis V0 PR #676](https://github.com/wagnerra23/oimpresso.com/pull/676)
- [Marketplaces PR #667 — Wagner decide depois](https://github.com/wagnerra23/oimpresso.com/pull/667)
