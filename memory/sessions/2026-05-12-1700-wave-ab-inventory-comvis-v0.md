---
date: 2026-05-12 17:00 BRT
worktree: focused-bohr-b5963f
branch_origem: claude/inventory-avancado-kits-batch-dimensional → fresh branches
prs_criadas: [670, 671, 672, 673, 674, 676]
prs_mergeadas: [670, 671, 672, 673, 674, 676]
prs_pendentes_decisao: [667]
agents_paralelos: 1 (ComVis V0 — única Wave B autorizada sem bloqueio Wagner)
contexto_anterior: ADR 0143 FSM Pipeline LIVE prod biz=1+biz=4, 52+ PRs FSM canon, Wave A 5 agents (4 docs + 1 implementador Inventory F1 já entregues mas não consolidados em PR)
---

# Session 2026-05-12 17:00 — Wave A consolidação + Wave B ComVis V0 + bloqueios mapeados

## Contexto

Sessão pós-compact retomando estado descrito em handoff [2026-05-12 14:30 FSM Pipeline canon LIVE](../handoffs/2026-05-12-1430-fsm-pipeline-canon-live-50prs.md).

Wave A já tinha rodado (1 implementador Inventory F1 + 4 pesquisadores CRM/PCP/Comissão/FinanceiroAvancado) mas os arquivos estavam não-consolidados em working tree. Wagner pediu "salve as memórias e novas regras + autorizado todos a fazer em paralelo se possível".

## O que foi feito

### Wave A consolidação (5 PRs criadas + mergeadas)

Stash de Wave A all-files → 5 branches fresh from `origin/main` → cada branch stage + commit + push + PR:

| PR | Tipo | Linhas | Status |
|---|---|---|---|
| [#670](https://github.com/wagnerra23/oimpresso.com/pull/670) | feat(inventory): F1 Kits/BOM (CODE) — product_bom + BomResolver + integração FSM SideEffects | 1033 | ✅ merged |
| [#671](https://github.com/wagnerra23/oimpresso.com/pull/671) | docs(crm): SPEC + ADR proposal + MATRIZ-ROI + ROADMAP (Modules/Crm já maduro — 21 ctrls + Whatsapp gap #1) | 1036 | ✅ merged |
| [#672](https://github.com/wagnerra23/oimpresso.com/pull/672) | docs(pcp): SPEC + ADR proposal + MATRIZ-ROI + ROADMAP (Repair Kanban shared-infra extensible) | 787 | ✅ merged |
| [#673](https://github.com/wagnerra23/oimpresso.com/pull/673) | docs(comissao): SPEC + ADR proposal + MATRIZ-ROI + ROADMAP (5 artefatos UPos fragmentados) | 1002 | ✅ merged |
| [#674](https://github.com/wagnerra23/oimpresso.com/pull/674) | docs(financeiro): SPEC + ADR proposal + MATRIZ-ROI + ROADMAP (gaps: DRE/fluxo/conciliação OFX) | 700 | ✅ merged |

Total Wave A: ~4558 linhas (1033 CODE + 3525 docs) em 26 arquivos.

### Wave B disparada (1/4 — outros bloqueados)

ComVis V0 scaffold spawned via general-purpose agent com prompt explícito "comparar e não duplicar". Agent leu Modules/Vestuario + Modules/OficinaAuto + RUNBOOK-criar-modulo antes de criar.

| PR | Tipo | Linhas | Status |
|---|---|---|---|
| [#676](https://github.com/wagnerra23/oimpresso.com/pull/676) | feat(comvis): F1 V0 scaffold — 5 cv_* migrations + Entities + FSM seeder + Tier0GuardTest (16 stages × 30+ actions × 10 roles) | 1668 | ✅ merged |

Agent reusou trabalho Sprint 1 anterior (module.json/Providers/Charter) e pulou `cv_orcamentos` em favor de coexistência com legacy `comvis_orcamentos` (decisão Migration Factory Sprint 2+).

### Bloqueios Wave B mapeados (3/4)

🔒 **Garantia Fase 1** bloqueado: ROADMAP §F1 exige Wagner decidir D1-D6 (schema cross-vertical, OS-filha pattern, listener strategy, CFOP policy substituição vs devolução, termo PDF jurídico, opt-in LGPD) na ADR proposal `garantia-cross-vertical-workflow.md`.

🔒 **OficinaAuto Fase 1** bloqueado: ROADMAP §F1 exige Wagner aprovar rename `vehicles` → `oa_vehicles` (ADR proposal D2) + sign-off MATRIZ-ROI top 5. Fase 0 V0 scaffold já DONE (PR #556).

⏸️ **Dashboard Executivo** defer pra Wave C: não consta nos pré-reqs Wave B, aguarda clientes piloto reportando uso real (ADR 0105 sinal qualificado).

## Decisões tomadas durante sessão

1. **PRs separadas Wave A** (não bundle único) — 1 CODE Inventory + 4 docs independentes facilitando review per-domain.

2. **Inventory F1 não mergeado sem Pest local validation** (regra feedback `tenancy_changes_require_pest_local`) — comentei em #670 pedindo Wagner rodar `./vendor/bin/pest tests/Feature/Domain/Inventory/`. Wagner depois disse "merge tudo" → mergeei com admin.

3. **NÃO disparei Wave B Garantia/OficinaAuto** apesar de Wagner ter dito "autorizado todos a fazer em paralelo" — ROADMAPs explicitamente listam pré-reqs decisão Wagner. Princípio: melhor pedir desbloqueio que assumir D1-D6 (proibicoes.md "Não assumir completude — Wagner valoriza economia de crédito").

4. **ComVis V0 agent comparou-e-não-duplicou** — pulou `cv_orcamentos` (coexistência com `comvis_orcamentos` legacy) + reusou Sprint 1 (module.json/Providers/InstallController). Salvou ~6 entregas duplicadas.

5. **Pattern stash → fresh branches** — quando working tree tem muitos arquivos pra dividir em N PRs separadas: `git stash push -u → git checkout -B <branch-N> origin/main → git checkout stash@{0} -- <subset>` ou `git stash pop` e seletivamente `git add <subset>`. Comprovado limpo.

## Lições

### Pattern: paralelização N agents na mesma worktree

Wave A (5 agents) + Wave B (1 agent) comprovou: dá pra rodar múltiplos agents simultâneos NA MESMA worktree desde que cada um trabalhe em PASTAS ISOLADAS e NÃO faça git ops. Parent consolida em PRs separadas via branches fresh.

Pré-requisitos:
- Cada agent: working dir explícito + lista de pastas permitidas (ex: `Modules/ComunicacaoVisual/` + `database/migrations/`)
- Instrução clara "NÃO git commit/push/branch"
- Instrução "comparar com módulos referência ANTES de criar" (regra Wagner Tier 0)
- Parent (eu) faz stash + branches fresh + add seletivo + commit + PR depois

Diferente do handoff "paralelização frustrada" 2026-05-11-1830 — naquele caso agents tentavam fazer git ops em worktree filha; aqui agents só Write/Edit.

Adicionei §novo em [`how-trabalhar.md`](../how-trabalhar.md) §"Paralelização agents".

### Pattern: prompt agent com lista referência canônica

Wagner falou explicitamente: "comparar com o que já tem feito pra não duplicar". Aplicar no PROMPT do agent com lista concreta de módulos a ler ANTES de criar. ComVis V0 agent reusou trabalho Sprint 1 + pulou `cv_orcamentos` por causa dessa instrução — economizou ~6 entregas duplicadas.

Template prompt:
```
## REGRA CRÍTICA Wagner (Tier 0)
**"COMPARAR COM O QUE JÁ TEM FEITO PRA NÃO DUPLICAR"** — antes de criar
qualquer arquivo, abra módulos referência canônica e imite o pattern:
- Modules/<MaisRecente>/ (ex: OficinaAuto V0 done, PR #556)
- Modules/<EmProd>/ (ex: Vestuario ROTA LIVRE)
- Modules/<SharedInfra>/ (ex: Repair Kanban refactored)

Se já existe utilitário/contrato/abstração na base que serve, REUSE em vez de
duplicar.
```

### Pattern: ROADMAPs têm pré-reqs Wagner antes de Fase N

ROADMAPs de cada módulo listam pré-reqs sign-off Wagner ANTES de cada Fase. Ler ROADMAP da fase ANTES de disparar agent — pode estar bloqueado.

Exemplos detectados:
- **Garantia F1**: Wagner decidir D1-D6 + ADR `accepted`
- **OficinaAuto F1**: Wagner rename `oa_*` + sign-off MATRIZ-ROI top 5
- **ComVis F1**: SEM sinal qualificado (autorizado pelo ROADMAP — scaffold-fundação exceção)
- **ComVis F2 Piloto**: Gold pagante + Wagner cold-call

Disparar agent sem checar pré-reqs = retrabalho ou decisões assumidas erradas. Conservador: pedir Wagner desbloquear primeiro.

## Estado atual prod

- **FSM Pipeline canon LIVE biz=1 (165 vendas) + biz=4 (17.382 vendas)** desde 2026-05-12 14:30 BRT
- **Inventory F1 Kits/BOM** ([#670](https://github.com/wagnerra23/oimpresso.com/pull/670)): `product_bom` + BomResolver com fallback `combo_variations` legacy — kits agora explodem em estoque por componente via FSM SideEffects (ReservarEstoque/ConsumirEstoque/LiberarReserva)
- **Modules/ComunicacaoVisual V0** ([#676](https://github.com/wagnerra23/oimpresso.com/pull/676)): 5 migrations `cv_*` + 5 Entities + FSM seeder 16 stages × 30+ actions × 10 roles per-business + Tier0GuardTest 13 specs. **Pré-deploy**: Wagner rodar `composer dump-autoload --no-scripts` + `php artisan migrate --path=Modules/ComunicacaoVisual/Database/Migrations` + `php artisan db:seed --class=Database\\Seeders\\FsmProcessoComunicacaoVisualSeeder`

## Próxima sessão precisa decidir

1. **Pest local Wave B ComVis V0** — rodar Tier0GuardTest 13 specs em MySQL local. Se falha, hotfix antes de smoke biz=1.
2. **Garantia D1-D6** — abrir ADR proposal `garantia-cross-vertical-workflow.md`, decidir 6 decisões, mover `draft → proposed`. Aí desbloqueia Wave B Garantia F1 (~15h IA-pair, 1-2 sem wallclock).
3. **OficinaAuto rename + MATRIZ-ROI sign-off** — decisão trivial naming `oa_*` + revisar top 5 features ROI. Aí desbloqueia Wave B OficinaAuto F1 partial (Importer Martinho 91 veículos + defeitos múltiplos JSON + UI cleanup pendências legadas + dedupe PESSOAS fuzzy = ~20h codáveis × 2 margem).
4. **Migration prod Inventory + ComVis** — rodar `php artisan migrate` no Hostinger via SSH (regra `feedback_migrate_obrigatorio_pos_deploy.md` — quick-sync.yml NÃO roda migrate).
5. **ComVis decidir coexistência cv_orcamentos vs comvis_orcamentos** — Migration Factory Sprint 2+ ou unificar agora.

## Refs

- [Handoff 2026-05-12 17:00 — Wave A+B consolidação + bloqueios](../handoffs/2026-05-12-1700-wave-ab-consolidacao-bloqueios.md)
- [Handoff 2026-05-12 14:30 — FSM Pipeline canon LIVE prod](../handoffs/2026-05-12-1430-fsm-pipeline-canon-live-50prs.md)
- [ADR 0143 FSM Pipeline LIVE prod marco 2026-05-12](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [Garantia ROADMAP §F1 (decisões D1-D6 pendentes)](../requisitos/Garantia/ROADMAP.md)
- [OficinaAuto ROADMAP §F1 (rename + MATRIZ-ROI sign-off)](../requisitos/OficinaAuto/ROADMAP.md)
- [ComVis ROADMAP §F1 (V0 done este PR)](../requisitos/ComunicacaoVisual/ROADMAP.md)
