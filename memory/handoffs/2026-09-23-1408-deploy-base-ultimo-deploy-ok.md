---
date: "2026-09-23"
time: "14:08 BRT"
slug: deploy-base-ultimo-deploy-ok
tldr: "deploy.yml passa a decidir sync leve × deploy completo contra o último deploy bem-sucedido, não contra event.before. Push com migration cancelado pela concurrency não some mais. #7820 mergeado; o próprio deploy do merge já exercitou o caso real (#7810 cancelado) e foi completo."
prs: [7820]
decided_by: [W]
next_steps:
  - "Conferir o 1º run de deploy SEM runtime desde o último sucesso: deve logar 'base = último deploy OK' e ir pelo sync leve (ainda não observado em produção)."
  - "Decidir se Modules/*/Tests/** deve entrar no NAO_RUNTIME (hoje conta como runtime e força deploy completo)."
---

# Deploy decide leve × completo pelo último deploy OK

## Estado MCP no momento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- Handoffs irmãos do dia: 0729, 1140, 1430, 1436, 1653 — nenhum toca `deploy.yml`.

## O que aconteceu

Incidente de 2026-09-23: o push do #7789 (`120340ba2`, migration `dre_linha`) teve o deploy
completo **cancelado** pela concurrency `deploy-production`; o push seguinte (`49c420e25`) não
tocava runtime e foi pelo `sync-light` (git reset, sem `migrate`) → `/financeiro/dre` 500 até
migrar à mão. Causa: `runtime_changed` comparava `github.event.before..github.sha`.

Conserto ([#7820](https://github.com/wagnerra23/oimpresso.com/pull/7820), `332abebbf`):

- `scripts/deploy/classifica-push.sh` — base = headSha do último run `push` do deploy com
  `conclusion=success`, ancestral do SHA atual. `workflow_dispatch` fora (pode ter
  `skip_migrate`). Runtime por `git log --no-renames --name-only` (arquivos tocados no
  intervalo — base atrasada só empurra pra completo). Fail-closed em toda dúvida.
- **Achado:** `gh run list --status success` devolveu runs de **2026-08-28** enquanto havia
  sucessos do dia (índice atrasado). O filtro de conclusion roda no cliente, e o teste tem mutação
  que proíbe `--status`.
- Concurrency intocada. Medido nos 61 sucessos de 21–23/09: 7 passam de leve → completo
  (todos por runtime escondido em run cancelado, incl. `.tsx` da Jana em 22/09 que foram sem
  republicar bundle). Custo ≈ +2 min e ≈1 min de 503 cada; fila não cresce.

**Prova em produção:** o deploy do próprio merge (run 35892826784) achou base `ae3c4d92b`, viu os
arquivos do #7810 (Patrimônio, cujo deploy fora cancelado pelo meu push) e foi **completo**. Pela
regra antiga teria ido pelo sync leve. Smoke OK; `/login` 200, `/` 200.

## Artefatos gerados

- `scripts/deploy/classifica-push.sh` (novo)
- `scripts/governance/deploy-classifica-push.test.mjs` (novo — repo hermético + gh falso, RELEASE
  do incidente, controles, 6 BITE; passou no runner Linux)
- `.github/workflows/deploy.yml` (step de classificação chama o script)
- `.github/workflows/governance-script-tests.yml` (step novo)

## Persistência

git: #7820 no main. MCP: sem task aberta (trabalho sem US). BRIEFING: não aplica (infra de deploy).

## Próximos passos pra retomar

```bash
gh run list --workflow deploy.yml --branch main --limit 10 --json headSha,conclusion
```
Achar um run sync leve pós-`332abebbf` e conferir no log `base = último deploy OK`.

## Lições catalogadas

- LC-26 duas vezes na sessão (`\n`/`\$` colapsando em heredoc Python) — contornado com Edit.
- Filtro de status do lado do servidor da API de runs é índice atrasado (família LC-24): filtrar
  no cliente.

## Pointers detalhados

- PR body do #7820 (Infra Contract + replay + medição da fila)
- `scripts/deploy/classifica-push.sh` (docblock com o porquê de cada escolha)
