# Sessão 2026-05-11 — Fix schema drift mcp_dual_brain_decisions

## TL;DR

Wagner reportou "quebrou o site". Investigação: site web nunca esteve quebrado (rotas 200, Inertia mount válido, zero ERROR Laravel pós 19:00 UTC). Erro temporário Vite manifest às 16:02 BRT (userId 569) já auto-resolvido pelo build às 19:02 UTC.

**Problema real encontrado em paralelo:** Brain B autônomo parado desde 15:50 BRT — drift entre migration `2026_05_03_200001_add_learning_loop_columns` registrada como Ran [73] e schema real (7 colunas faltando). Cron `ads:plan-decisions`/`ads:review-decisions`/`ads:auto-generate-tasks` falhando.

**Fix aplicado:** migration idempotente `2026_05_11_190001_repair_dual_brain_learning_loop_drift` usando `Schema::hasColumn` — segura em qualquer ambiente, no-op onde não há drift. PR #574 admin-merged → SSH Hostinger `php artisan migrate --force` → 270ms → Brain B funcionando.

## Diagnóstico

### Site web — saudável
- 8 rotas probadas (home, login, atendimento/inbox, atendimento/canais, financeiro/unificado, whatsapp/conversations, repair/dashboard, copiloto) — todas HTTP 200 + `id="app"` mount Inertia
- Zero ERROR Laravel após 19:00 UTC (16:07 BRT no momento da checagem)
- Vite manifest existe em `public/build-inertia/` (May 11 19:02 UTC, 403KB)

### Erro temporário Vite manifest (auto-resolvido)
- `[2026-05-11 16:02:40] live.ERROR: Vite manifest not found ... userId:569`
- Wagner (user 569) bateu em `/atendimento/inbox` durante deploy do PR #571
- Manifest sumiu por ~1 min, build refez às 19:02 UTC
- Conclusão: condição de corrida deploy-vs-request, agora OK

### Drift real — mcp_dual_brain_decisions
- Migration `2026_05_03_200001_add_learning_loop_columns` em `migrations` table como [Ran 73]
- 7 colunas que deveriam ter sido adicionadas **não existiam em prod**:
  - `parent_decision_id` (T9 PlannerAgent)
  - `auto_generated` (T7 Auto Task Generator)
  - `attempts`, `next_retry_at` (T18 Retry inteligente)
  - `review_score`, `review_breakdown`, `review_confidence` (T11 ReviewerAgent)
- Causa: desconhecida. Hipóteses: DDL manual (drop column), restore backup mais antigo após migration registrada, falha parcial de transação não-rastreada
- Sintomas: cron ADS falhando com `Unknown column` desde 15:50 BRT
- Brain B autônomo parado (não afetava UI/clientes)

## Fix

### Estratégia
Migration nova `2026_05_11_190001_repair_dual_brain_learning_loop_drift` ao invés de re-rodar a original ou fazer DDL direto:

- Cada `ADD COLUMN` precedido de `Schema::hasColumn` → idempotente
- Cada `CREATE INDEX` precedido de `SHOW INDEX FROM ... WHERE Key_name = ?` → idempotente
- `down()` NO-OP intencional pra não re-introduzir o drift se alguém reverter
- Comentário no docblock explica o porquê e referencia ADR 0094 §5

### Execução
1. ✅ Branch `claude/fix-ads-dual-brain-drift` partindo de origin/main
2. ✅ Migration escrita com idempotência + 4 índices condicionais
3. ⚠️ PR #574 commitado (commit `6a279676`) mas working tree compartilhado com outra sessão Claude paralela carregou commit `dc65e040` (docs OficinaAuto) junto no push — squash merge agregou os 2 intents em 1 commit (`26a1e708`). Não quebrou nada mas viola commit-discipline. Lição: stash + commit em rápida sucessão antes que outra sessão chegue
4. ✅ Admin squash merge PR #574 → `26a1e708` em main
5. ✅ SSH Hostinger `php artisan migrate --force` → 4 migrations DONE em ~1.5s total:
   - `2026_05_11_000010_create_vehicles_table` (OficinaAuto pendente legada)
   - `2026_05_11_000020_create_service_orders_table` (OficinaAuto pendente legada)
   - `2026_05_11_170001_add_legacy_date_fields_to_transactions` (Sells pendente)
   - `2026_05_11_190001_repair_dual_brain_learning_loop_drift` (270ms — esta)

### Validação pós-deploy
- `php artisan db:table mcp_dual_brain_decisions` → 7 colunas presentes + 4 índices novos
- `php artisan ads:plan-decisions --limit=1` → "8 subtarefas criadas (conf=0.7)" — Brain B funcionando
- `grep ERROR` em `storage/logs/laravel.log` após 19:10 UTC → zero linhas

## Lições

1. **Drift catalogado pra alimentar `procedure_drift` check** (ADR 0094 §5 / `jana:health-check`). Esse check provavelmente não detectou porque mira procedures stored — não colunas individuais. Considerar expansão pra column drift na próxima iteração.
2. **Pattern de fix idempotente vira referência** pra futuros drifts. Reusar shape: `Schema::hasColumn` + `SHOW INDEX FROM ... WHERE Key_name` + down NO-OP justificado.
3. **Working tree compartilhado entre sessões Claude paralelas é fonte de commit-pollution** — uma sessão pode arrastar commits da outra no push. Mitigação: cada Claude em worktree própria (mas atenção pra não criar worktree filha como na sessão 18:30).
4. **Vite manifest race condition** durante deploy é cenário real. Solução robusta: `php artisan view:cache` + `npm run build` ANTES de `git pull` final ou usar atomic deploy (symlink swap).

## Commits

| SHA | Mensagem |
|---|---|
| `6a279676` | `fix(ads): reparo idempotente schema drift mcp_dual_brain_decisions` |
| `dc65e040` | (outra sessão) `docs(officeimpresso): consolidar licoes criticas + fix drift SPEC OficinaAuto` |
| `26a1e708` | squash merge PR #574 em main |

## Próximos passos sugeridos

- Investigar causa raiz do drift (audit log MySQL? Hostinger backup history? hPanel access log?)
- Considerar adicionar column drift detection em `jana:health-check`
- Avaliar se outras migrations [Ran] em prod sofreram drift similar (snapshot canônico vs SHOW CREATE TABLE em todas tabelas core)
