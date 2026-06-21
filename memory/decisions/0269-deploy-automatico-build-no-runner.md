---
slug: 0269-deploy-automatico-build-no-runner
number: 269
title: "Deploy automático em push pra main + build no runner (manual → automático, JS sai do shared host)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-10"
module: null
quarter: 2026-Q2
kind: decision
tags: [deploy, ci-cd, hostinger, github-actions, build-no-runner, opcache, automacao]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0062-separacao-runtime-hostinger-ct100", "0246-tipo-outros-default-migracoes-legacy"]
pii: false
---

# ADR 0269 — Deploy automático em push pra main + build no runner

## Status
Aceito — 2026-06-10 · [CC], sob autorização explícita de [W] ("autorizou automação máxima do deploy").

## Contexto

Até 2026-06-10 a publicação em prod tinha 3 caminhos sobrepostos, todos na mesma `concurrency: deploy-production`:

1. **`quick-sync.yml`** — auto em push pra main. Buildava o JS **no Hostinger** (npm no shared host via nvm). Sem `composer install`, sem `migrate`.
2. **`deploy.yml`** — 100% manual (`workflow_dispatch`). Full: backup + composer + migrate + caches. **Não buildava o JS** (assumia que o quick-sync já tinha buildado).
3. **`force-clean-rebuild-trigger.yml`** — nuclear manual, também buildava no Hostinger.

Problemas:
- **"Merge ≠ publicado":** pra publicar de verdade o operador tinha que orquestrar 2 workflows na mão (deploy.yml + force-clean), e o build do JS dependia do shared host.
- **Build no shared host é frágil:** rayon/lightningcss (Tailwind v4) estoura o limite de threads do Hostinger e **esvazia `public/build-inertia/` → site 500** (incidente 2026-06-03); hashes stale quando o build não regenerava (incidente 2026-05-20). Catalogado em `memory/reference/deploy-recovery-patterns.md` §2.3.
- **OPcache reset nunca confirmado:** o step de reset (`_ops_opcache_reset.php`) era warning-only e o secret `OPCACHE_RESET_TOKEN` nunca existiu — LSPHP segurava bytecode velho entre deploys.

## Decisão

**O auto-deploy canônico passa a ser `deploy.yml`, disparado automaticamente em push pra main**, com o JS **buildado no runner** (ubuntu-latest determinístico), não no Hostinger.

1. **Auto-trigger:** `push: branches:[main]` com `paths-ignore: [memory/**, **.md, prototipo-ui/**, cowork-inbox/**]` (docs não deployam). `workflow_dispatch` mantido como fallback com inputs de escape (skip backup/migrate, artisan extra).
2. **Build no runner:** job `build` (setup-node 24 + `npm ci` + `build:inertia` + `build`) publica artefato; job `deploy` baixa e envia os bundles via **tar/ssh com swap atômico** (`.new` → `mv` por cima, mantém `.old` pra rollback). Wayfinder é auto-guardado (sem `vendor/` no runner, o plugin pula).
3. **`quick-sync.yml` perde o trigger `push`** (vira `workflow_dispatch`-only) pra não rodar um segundo deploy concorrente. Continua como escape manual leve.
4. **OPcache reset vira OBRIGATÓRIO** (warning → falha). Secret `OPCACHE_RESET_TOKEN` criado; o deploy escreve o token em `storage/app/opcache_reset_token` (fora do git/webroot, sobrevive a `git reset --hard`) e o endpoint lê dessa fonte — script PHP cru não lê o `.env` da app via `getenv()` no LSPHP, então o arquivo é a fonte confiável. Só tolera `OPCACHE_UNAVAILABLE` (extensão genuinamente ausente).
5. **Smoke valida bundle:** compara o hash dos `/assets/` servidos antes×depois; se `resources/js|css` mudou no push mas o hash não mudou, o deploy **falha** (publicação não chegou ao prod).
6. **Redes de segurança mantidas:** backup com rotação (mantém 5 mais recentes), maintenance on/off, composer (sem `--no-dev` — Faker em prod), migrate, caches (sem `route:cache` — hotfix 2026-05-27), smoke estrito.

## Consequências

**Positivas:** merge em main publica sozinho; fim do build frágil no shared host (causa raiz dos 500/hashes stale); OPcache reset confirmado a cada deploy; gate de bundle pega regressão de publicação.

**Negativas / trade-offs:** todo push não-docs roda deploy full (backup + composer + migrate ~minutos) — mais pesado que o quick-sync leve, mitigado pela rotação de backup e idempotência de composer/migrate. Janela de microssegundos no swap de bundles (sob maintenance, invisível).

**Reversível:** `deploy.yml` mantém `workflow_dispatch`; reverter o auto = remover o trigger `push`. `quick-sync.yml` preservado como escape.

## Referências
- `memory/reference/deploy-recovery-patterns.md` (lições de deploy, §2.3 estouro de threads)
- ADR 0062 (separação runtime Hostinger ≠ CT 100)
- `public/_ops_opcache_reset.php` (endpoint de reset, fonte-arquivo do token)
