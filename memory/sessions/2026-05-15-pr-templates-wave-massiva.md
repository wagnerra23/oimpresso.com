---
title: Templates de commit + PR pra consolidação Wave massiva (6 PRs)
date: 2026-05-15
type: session
status: draft
authority: [Wagner]
---

# Templates consolidação Wave massiva (uso pelo parent quando agents terminarem)

## Commit template por bucket

```
feat(mwart-<bucket>): migrar N telas Blade→Inertia (B<N> <Nome>)

Telas migradas (ADR 0104 + 0149 pattern reuse):
- <Tela1>.tsx
- <Tela2>.tsx
- ...

Stack: Inertia v3 + React 19 + TS estrito + AppShellV2 + Tailwind 4
Pattern reuse: blueprint Cowork `prototipo-ui/prototipos/<bucket>/` aprovado anteriormente (ADR 0149)
Cross-tenant Tier 0 validado (biz=1 vs biz=99 Pest verde)
Inertia::defer em props caras (skill inertia-defer-default Tier B)

Refs: SPRINT-CYCLE-06 PASSO migracao-massiva-32-telas
Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
```

## PR description template

```markdown
## Sumário

Migração massiva Blade→Inertia do Bucket B<N> <Nome> (N telas) executada pelo Agent W<X>-<Y> em paralelo Wave <K> 2026-05-15.

Parte de plano de fechamento de migração core-vendas/ROTA LIVRE em 7 dias (target 2026-05-22). 32 telas em paralelo via 5 agents simultâneos (modo agressivo Max 20x).

## Telas migradas

| Tela | Blade legacy | Inertia destino | Pattern reuse blueprint |
|---|---|---|---|
| ... |

## Processo MWART canônico ADR 0104 (5 fases)

- [x] F1 PLAN: RUNBOOK + charter.md com YAML `mwart_pattern_reuse`
- [x] F2 BACKEND BASELINE: Pest 5+ fixtures preserva Blade legacy (verde)
- [x] F3 FRONTEND INCREMENTAL: `Inertia::render` + `Inertia::defer` em props caras
- [x] F4 QA: Pest cross-tenant biz=1 vs biz=99 (verde)
- [ ] F5 CUTOVER: Wagner aprovação F1.5 batch + canary 7d (próximo)

## Constraints Tier 0 respeitadas

- [x] business_id global scope em todas queries
- [x] PT-BR UI/commits/labels
- [x] Zero PII em código/log/commit
- [x] Sem `withoutGlobalScopes` sem comentário SUPERADMIN
- [x] Tabelas core UltimatePOS intocadas
- [x] Sem migration nova
- [x] TypeScript estrito (sem `any`)
- [x] Inertia::defer em props caras (paginate/count/with eager/Service-DB/HTTP-externo)

## Test plan

- [ ] CI Pest cross-tenant verde
- [ ] Wagner aprova F1.5 screenshot blueprint Cowork (ADR 0149 — 1 screenshot por bucket family)
- [ ] Smoke biz=1 ROTA LIVRE manual em /<rota>
- [ ] Canary 7d antes de remover Blade legacy (rota fallback ativa)
- [ ] Comunicação Larissa via WhatsApp pré-cutover

## ADRs respeitadas

- ADR 0104 — processo MWART canônico único caminho
- ADR 0114 — protótipo Cowork loop formalizado
- ADR 0149 — screen-pattern reuse (recém-aceita 2026-05-15)
- ADR 0093 — multi-tenant isolation Tier 0
- ADR 0143 — FSM Pipeline LIVE prod (B6 Repair OS toca jobsheets.current_stage_id)
- ADR 0106 — recalibração velocidade fator 10x IA-pair
- ADR 0107 — emendation visual-comparison gate F3
- ADR 0109 — Claude Design plugin integrado MWART

## Próximos passos

1. CI verde → merge
2. Smoke biz=1 ROTA LIVRE
3. Canary 7d ativo (Blade legacy preservada via flag rollback)
4. Pós-canary: PR remove Blade legacy
5. Atualizar `memory/requisitos/<Mod>/BRIEFING.md` (skill brief-update Tier B)

🤖 Generated with [Claude Code](https://claude.com/claude-code)
```

## Checklist consolidação parent (quando agents terminarem)

Por cada bucket B1..B6:

```bash
# 1. Verificar relatório do agent
cat memory/sessions/2026-05-15-wave<N>-<bucket>.md

# 2. Listar arquivos modificados pelo agent
git status --short | grep -E "(<pasta-isolada>)" | head

# 3. Stash all + nova branch limpa
git stash push -u -m "wave-massiva-all"
git checkout -B claude/mwart-<bucket> origin/main
git stash pop

# 4. Add seletivo (área isolada do agent)
git add <pasta-isolada-1> <pasta-isolada-2> ...

# 5. Validar tamanho ≤300 linhas (skill commit-discipline Tier A)
git diff --cached --stat | tail -1

# 6. Pest cross-tenant verde
php artisan test --filter=Wave<N><Bucket>

# 7. Commit conventional + heredoc
git commit -F COMMIT_MSG_<BUCKET>

# 8. Push + PR via gh
git push -u origin claude/mwart-<bucket>
gh pr create --title "..." --body "$(cat PR_BODY_<BUCKET>)"

# 9. Limpar branch local
git checkout main
git branch -D claude/mwart-<bucket>
```

## Notas de re-trabalho preventivo (lições histórico)

- Cuidado: `git worktree remove --force` no Windows com junction vendor/ APAGA vendor/ do repo principal. JUSTIFICA NÃO USAR --force.
- Cuidado: agents podem reusar pattern em tela que NÃO se qualifica (Print herdou Index → quebra impressão). Mitigação: smoke biz=1 manual em telas críticas.
- Cuidado: agent paralelo NÃO faz git ops (eles podem morrer em worktree filha). Parent consolida 100%.
- Cuidado: Pest cross-tenant exige factories pra biz=99 (não usar dados reais).
