---
title: Migração massiva 32 telas MWART (Wave 1+2+3 simultâneas — modo agressivo Max 20x)
date: 2026-05-15
type: session
status: in_progress
authority: [Wagner]
related_adrs: [0104, 0114, 0149, 0143, 0093, 0106]
---

# Migração massiva 32 telas — Wave 1+2+3 simultâneas

## Decisão Wagner

Plano Max 20x liberado. Pediu modo agressivo + senior habilidoso. Sai do pattern sequencial wave-por-wave, vai simultâneo 5 agents paralelos.

## Escopo aprovado (35 telas — B2 entra Wave 2 quando Cowork pronto)

| Bucket | Telas | Agent | Status | Cowork blueprint |
|---|---|---|---|---|
| B1 Sells | 5 (Show/Edit/Drafts/Quotations/Subscriptions) | W1-A | rodando | `vendas-cockpit/` |
| B2 POS | 3 (Index/Create/Edit) | — | aguardando Cowork Wagner | **gap — Wagner gera 2026-05-15** |
| B3 Cliente | 7 (Index/Create/Show/Edit/Import/Ledger/Map) | W1-B | rodando | `clientes/` |
| B4 Produto | 7 (Index/Create/Show/Edit/BulkEdit/StockHistory/SellingPrices) | W2-C | rodando | `produto-cockpit/` |
| B5 Stock/Purchase | 6 (Purchase Create/Edit + StockTransfer + StockAdjustment) | W2-D | rodando | `compras/` + `inventario-migracao/` |
| B6 Repair OS | 7 (JobSheet × 5 + Repair × 2) | W3-E | rodando | `os/` + `producao-oficina/` |

Total telas simultâneas: **32 / 35**. B2 spawn assim que Cowork entregue (~1h Wagner).

## ADRs aceitas hoje

- **ADR 0149** screen-pattern reuse: Show/Edit/Detail derivam pattern do Index Cowork aprovado, sem F1.5 novo. Reduz Cowork necessário de ~24 → ~6-8.

## Constraints aplicadas a todos os agents

- Multi-tenant Tier 0 IRREVOGÁVEL (business_id global scope)
- PT-BR UI/commits/labels
- PII NUNCA em commit/log
- NÃO modificar tabelas core UltimatePOS
- NÃO criar migration nova
- ZERO git ops (parent consolida)
- Áreas isoladas explícitas (sem overlap inter-agents)
- Preflight obrigatório: SPEC + RUNBOOK + charter + ADRs canon + Cowork blueprint
- ADR 0143 FSM em Repair: NUNCA UPDATE direto em current_stage_id, usar ExecuteStageActionService
- Inertia::defer DEFAULT em props caras (ADR via skill inertia-defer-default Tier B)
- TypeScript estrito sem `any`
- Pest cross-tenant biz=1 vs biz=99 obrigatório em F4

## Plano consolidação parent (eu, quando agents terminarem)

1. Stash all + branch novo por bucket:
   ```bash
   git stash push -u -m "wave-1-2-3-all-agents"
   git checkout -B claude/mwart-b1-sells origin/main
   git stash pop
   git add resources/js/Pages/Sells/ app/Http/Controllers/SellController.php memory/requisitos/Sells/ tests/Feature/Sells/
   git commit -F COMMIT_MSG_B1
   git push -u origin claude/mwart-b1-sells
   gh pr create ...
   # repetir B2..B6
   ```
2. PR por bucket = 6 PRs total (5 hoje + B2 amanhã)
3. CI rodando Pest cross-tenant em todos
4. Wagner aprova F1.5 batch (~6 screenshots blueprint pela ADR 0149)
5. Merge após CI verde + F1.5 aprovado
6. Smoke biz=1 ROTA LIVRE
7. Canary 7d antes de remover Blade legacy

## Cronograma realista (modo agressivo)

- **2026-05-15 ~12h**: 5 agents spawnados (W1-A, W1-B, W2-C, W2-D, W3-E)
- **2026-05-15 ~13h**: B2 POS spawn (Wagner Cowork pronto)
- **2026-05-15 ~20h-2026-05-16 ~06h**: agents concluindo (6-8h cada)
- **2026-05-16 ~06h-12h**: parent consolida 6 PRs + sobe pra revisão
- **2026-05-16 ~13h-15h**: Wagner aprova F1.5 batch
- **2026-05-16 ~15h-18h**: merges após CI verde
- **2026-05-17**: smoke biz=1 + canary deploy
- **2026-05-18 a 2026-05-25**: canary 7d ROTA LIVRE
- **2026-05-22 (target original)**: ✅ 32+ telas em produção pra ROTA LIVRE

## Riscos monitorados

1. **Agent travar** em tela com Blade complexo (SellController::show 70+ linhas) → priorização ordem no prompt mitiga
2. **F1.5 pattern reuse não bater** pra Map/Import/Ledger/BulkEdit/StockHistory/AddParts → agents marcam `divergence_from_blueprint` no YAML; Wagner valida no batch
3. **Pest cross-tenant falhar** em alguma tela → agents marcam blocker + parent decide retry pós-consolidação
4. **Conflito área isolada** → pasta-por-agent verificado pré-spawn; risco baixo
5. **Overflow Wagner crédito** → Max 20x liberado; risco descartado

## Output esperado por agent

- 5-7 .tsx Inertia + .charter.md (YAML mwart_pattern_reuse obrigatório)
- Controller métodos refatorados pra Inertia::render + Inertia::defer
- RUNBOOK por tela (11 seções)
- visual-comparison.md referenciando Cowork blueprint
- Pest Baseline F2 + Pest Inertia F4 (cross-tenant verde)
- Relatório final em `memory/sessions/2026-05-15-wave<N>-<bucket>.md`

## Acompanhamento

Estou em modo "preparação consolidação" enquanto agents rodam. Posso ser interrompido a qualquer momento por notification de conclusão de agent — vou consolidar e dar update.

## Próximos passos imediatos parent

1. Aguardar Wagner entregar Cowork B2 POS → spawn agent B2 (prompt pronto)
2. Verificar shared components reusáveis pra padronizar Pages
3. Validar Pest fixtures pra biz=1 vs biz=99 (ADR 0101)
4. Pre-redigir commit messages dos 5 PRs
