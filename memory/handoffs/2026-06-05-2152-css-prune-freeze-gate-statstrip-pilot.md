---
date: "2026-06-05"
slug: css-prune-freeze-gate-statstrip-pilot
tldr: "Limpeza de CSS morto dos bundles cowork: 3 PRs (#2291 fin -3920 · #2293 sells-cowork -3572 · #2295 8 bundles -806) ≈ 8.3k linhas removidas, stylelint baseline 820→491. Travado por gate de tamanho (#2298 css-size-baseline.mjs + workflow, baseline 20.294 linhas/34 arquivos — só encolhe). Tooling reusável generalizado (--bundle + auto-wrapper). Piloto tokenização: #2301 <FinStatStrip> (KPI strip .fin-stats → componente) MERGEADO propose-only SEM smoke visual (Wagner cienciou risco contido). Conflitos do #2272 resolvidos 2× + fix slug/tldr. Staging re-seed em andamento (clone estava SEM tabelas permission). ⚠️ senha DB prod vazou no ps → rotacionar."
hour: "21:52 BRT"
topic: "Limpeza de CSS morto (3 PRs) + gate de congelar sprawl + piloto componente FinStatStrip + re-seed staging"
duration: "~longa (sessão CI-driven, multi-PR)"
authors: [C, W]
---

# Handoff — CSS prune + freeze gate + piloto FinStatStrip

> Sessão dirigida por perguntas curtas do Wagner ("sim"/"merge") + eventos CI-monitor. Começou em "qual padrão design uso?" → raio-x Sells/Financeiro → limpeza de CSS morto → gate → piloto de tokenização.

## Estado MCP no momento
- brief-fetch NÃO rodado (sessão de continuação CI-driven). Cycle presumido: CYCLE-08 Receita Onda A (off-cycle: trabalho é higiene de CSS/DS).
- Branch tree principal: `docs/handoff-parecer-pr2270` (do Wagner, intacto — trabalhei sempre em worktrees isolados).
- main andou muito (sessão paralela OpenAI provider do Wagner ativa).

## O que aconteceu
1. **Dead-code CSS** (3 PRs, todos --admin, gate verde exceto drift Jana c/ override):
   - **#2291** `cowork-canon-financeiro-bundle.css` 8.666→4.746 (−45%). Era dump do protótipo Cowork "chat v2" com telas inteiras nunca portadas (cli-*, chat-*, kanban-*).
   - **#2293** `sells-cowork.css` 7.540→3.968 (−47%). Exigiu corrigir bug do tooling (wrapper de escopo envenenava o teste — vide lições).
   - **#2295** 8 bundles menores (sells-cowork-edit −71%, show −63%, quotations −33%, etc) −806 linhas.
   - Método SEGURO: remove rule-block só se TODA classe do seletor é morta (0 ref em .tsx/.ts/.jsx-prod/.blade.php). Gate `verify-prune` (0 vivas perdidas) pegou e barrou 1ª tentativa errada do fin.
2. **Gate de congelar** (#2298): `scripts/css-size-baseline.mjs` ratchet por-arquivo (só encolhe; .css novo barra) + `.github/workflows/css-size-gate.yml` + baseline 20.294 linhas. Espelha stylelint-baseline (ADR 0209). Roda do próximo merge em diante.
3. **Piloto tokenização** (#2301, **MERGEADO**): extraiu `.fin-stats`/`.fin-stat` (KPI strip, CSS bespoke compartilhado ~14 telas) pro componente `<FinStatStrip>`/`<FinStat>` Tailwind+tokens, migrou `PlanoContas/Index`. **Propose-only mas Wagner mandou merge SEM smoke visual** (avisado: risco contido a 1 tela, reversível). Compile-level verde (build/typecheck/lint). **Smoke visual real NUNCA feito.**
4. **#2272** (PR handoff do Wagner): resolvi conflito 08-handoff.md 2× (append-only, mantém ambos bullets cronológico) + fix `slug`+`tldr` no frontmatter (handoff schema gate). Sempre em worktree isolado, push só no ref remoto.
5. **Re-seed staging** (EM ANDAMENTO, bg `b5i6bc4m8`): tentei smokar #2301 no staging → staging quebrado (login falha: clone estava SEM as tabelas permission/role/role_user — migration marcada ran mas tabelas ausentes). Rodei `seed-from-prod.sh` (re-clone anonimizado prod→staging). Estava no passo 1/4 (dump) ~369 tabelas in.

## Artefatos gerados
- `scripts/fin-cowork-coverage.py` · `fin-cowork-prune.py` · `fin-cowork-verify-prune.py` (generalizados `--bundle` + auto-wrapper) — em main
- `scripts/css-size-baseline.mjs` + `config/css-size-baseline.json` + `.github/workflows/css-size-gate.yml` — em main
- `resources/js/Pages/Financeiro/_shared/FinStatStrip.tsx` — em main (#2301)

## Persistência
- Git: PRs #2291/#2293/#2295/#2298/#2301 merged em main. Este handoff via worktree `handoff-close`.
- MCP: webhook sync ~2min pós-push.

## Próximos passos pra retomar
```
# 1. Conferir se o re-seed do staging fechou (bg b5i6bc4m8 morre se PC do Wagner fechou):
tailscale ssh root@ct100-mcp "cd /opt/oimpresso-staging/code && bash docker/oimpresso-staging/seed-from-prod.sh"  # re-rodar se preciso
tailscale ssh root@ct100-mcp "docker exec -w /var/www/html oimpresso-staging php artisan migrate --force"  # + backfill fin se guard
# 2. Smoke visual #2301 (PENDENTE): logar staging (user@... / staging2026) → /financeiro/plano-contas → comparar KPI strip vs prod + reflow 1100/600 + dark. Se destoar: git revert ffc5f5e3d.
```

## Lições catalogadas
- **Bug do wrapper no prune**: o teste "toda classe morta" precisa IGNORAR o wrapper de escopo (`.fin-cowork`/`.sells-cowork`) — ele é vivo (className) e prefixa toda regra. Hardcoded `fin-cowork` deu 0 removidas no sells. Fix: auto-detectar wrapper = classe mais frequente nos seletores.
- **scope-*-css.py NÃO é no-op** — reformata o bundle (4169→3968). Rodar + confirmar idempotência (2ª run "Already scoped; skipping").
- **module-grades-gate**: drift Jana recorrente; melhor rebaselinar (sessão paralela fez 72→71) do que label-muleta toda PR.
- **Staging clone incompleto**: faltavam tabelas permission/role — `seed-from-prod.sh` é o fix canônico. `git pull` em staging SEM `migrate` quebra login (activity_log.business_id).
- **Menu de opções cansa Wagner** (textual: "não sei definitivamente não sei") — quando ele satura, DECIDIR por ele com recomendação clara, não oferecer mais forks.
- **Piloto visual mergeado sem smoke**: violação consciente do gate "Wagner aprova screenshot" — risco aceito por ser 1 tela + reversível, mas smoke segue PENDENTE.

## ⚠️ Segurança (Tier 0)
Senha do DB de **produção** (Hostinger) apareceu no `ps aux` durante check do dump → no transcript. Per feedback-nunca-publicar-credenciais: tratar como comprometida, **rotacionar quando viável**.

## Pointers detalhados
- Tooling prune: `scripts/fin-cowork-*.py` (header explica) · gate: `scripts/css-size-baseline.mjs`
- Componente piloto: `resources/js/Pages/Financeiro/_shared/FinStatStrip.tsx` (mapa CSS→Tailwind no corpo do PR #2301)
- Manual canon: `memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md` (passos 1-6; fizemos 1+3 parcial)
