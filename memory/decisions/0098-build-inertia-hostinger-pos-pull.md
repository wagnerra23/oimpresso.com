---
slug: 0098-build-inertia-hostinger-pos-pull
number: 98
title: "build:inertia roda na Hostinger pós git-pull (substitui GH Actions runner)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
module: infra
quarter: 2026-Q2
tags: [ci, deploy, hostinger, inertia, vite]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0062-separacao-runtime-hostinger-ct100]
pii: false
review_triggers:
  - Hostinger remover Node ou nvm
  - Build inertia exceder 90s
  - Memória disponível na Hostinger cair abaixo de 4GB
---

# ADR 0098 — build:inertia roda na Hostinger pós git-pull

## Contexto

Pipeline anterior (até 2026-05-07):
- `.github/workflows/build-inertia-auto.yml` — roda `npm run build:inertia` em runner Ubuntu pós push em `main`, commita assets em `public/build-inertia/` com tag `[skip-build-auto]` (anti-loop, retry rebase).
- `.github/workflows/quick-sync.yml` — roda em paralelo no mesmo push, SSH na Hostinger + `git fetch && git reset --hard origin/main`.

**3 problemas:**
1. **Race condition**: ambos workflows disparam paralelos no push. Quick-sync (~5s SSH) frequentemente ganhava do build-auto (~9s npm), Hostinger ficava com source novo + bundles velhos por ~30s → version mismatch (`manifest.json`) → 409 + full reload pros usuários online.
2. **Repo poluído**: cada deploy gerava commit auto-rebuild com 600+ arquivos renomeados (Vite hashes). Git history virava ruído ininteligível.
3. **Reflexo "sem Node em shared hosting"**: Hostinger TEM Node 24.15 + npm 11 via nvm desde o setup do nvm. 138GB de memória disponível (host shared sem isolamento). Era custom herdado de quando shared hosting = só PHP.

Testado manualmente em 2026-05-07: `npm run build:inertia` na Hostinger roda em **52s** sem crashes, gerando bundles idênticos ao runner Ubuntu (~9s).

## Decisão

Build dos bundles Inertia/React passa a rodar na Hostinger pós `git pull`, no mesmo workflow `quick-sync.yml`. `build-inertia-auto.yml` deletado. `public/build-inertia/` adicionado ao `.gitignore` e removido do tracking.

Novo pipeline (1 workflow):
1. `git fetch && git reset --hard origin/main`
2. `npm ci` condicional (fingerprint sha256 do `package-lock.json` em `.last-npm-ci-hash` local — skip se inalterado)
3. `npm run build:inertia` (~52s)
4. `php artisan view:clear && config:clear && route:clear`
5. Smoke test HTTP

## Justificativa

- **Single source of truth**: build determinístico do source que está no servidor de prod, zero divergência git ↔ prod.
- **Repo enxuto**: -16.574 linhas em 230 arquivos binários removidos do tracking (commit em #174).
- **Sem race condition**: 1 workflow, ordem garantida.
- **Source-only commits**: dev nunca mais precisa rodar `npm run build:inertia` antes de PR — todos os PRs ficam menores e mais legíveis.
- **Custo aceitável**: deploy de mudança JS/CSS sobe de ~5s pra ~57s. PHP-only continua igual. Não é build watcher, é deploy one-shot pós-merge.

Reabrir se: Hostinger remover Node/nvm; build exceder 90s; memória cair drasticamente.

## Consequências

**Positivas:**
- Repo source-only, history legível, PRs enxutos
- Eliminou race condition `manifest.json` → adeus 409+full-reload em deploys
- Workflow único — menos coisa pra manter, debug e monitorar
- Determinismo: build sempre saído do source que está rodando em prod

**Negativas / Trade-offs:**
- Deploy JS/CSS leva ~57s vs ~5s antes
- SPOF: se Hostinger npm/Node corromper, deploy quebra (mitigação: `npm ci` resiliente, lockfile garantido)
- Outros devs que pulleam main local agora veem `public/build-inertia/` ausente (gerar com `npm run build:inertia` se quiser testar)

**Riscos mitigados:**
- Race condition: eliminada por construção
- Drift git ↔ prod: impossível (build é regenerado a cada deploy)
- Esquecer de buildar antes de PR: irrelevante (servidor builda sempre)

## Pegadinhas conhecidas

1. **Cleanup ÚNICO pós-merge** (feito em 2026-05-07): `git reset --hard` não apaga arquivos untracked. Os 230 arquivos antigos (que estavam tracked) viram untracked após o merge do #174 — sobram como lixo até `git clean -fd public/build-inertia/`. Daqui pra frente, `quick-sync` regenera tudo do zero a cada deploy, sem lixo acumulado.

2. **Bug pré-existente nos secrets SSH** (não-resolvido): `quick-sync.yml` falha em "Setup SSH" porque secrets `SSH_PORT`, `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY` estão vazios no GitHub. Wagner precisa configurar via `gh secret set` ou via UI. Enquanto não configurado, deploys precisam ser feitos manualmente via SSH (rodar os mesmos passos do workflow). Não é regressão deste ADR — bug existe há tempos (ver auto-mem `reference_quick_sync_quebrada.md`).

3. **Primeiro deploy pós-merge falhou** (caso de aprendizagem): `centrifuge` foi adicionado ao `package.json` em PR anterior mas `npm ci` nunca rodou na Hostinger. Quando build tentou rodar pós merge do #174, faltou `centrifuge` em `node_modules` → build failed → manifest ausente → HTTP 500. Solução: `npm ci` reinstalou 448 packages, build passou, prod restaurada. O step `npm ci` condicional do novo `quick-sync.yml` vai prevenir reprise.

## Referências

- [ADR 0062](./0062-separacao-runtime-hostinger-ct100.md) — Separação runtime Hostinger ≠ CT 100. Relevante: Hostinger continua sendo shared hosting (sem daemons), mas one-shot build é OK.
- PR [#173](https://github.com/wagnerra23/oimpresso.com/pull/173) — UI cockpit Whatsapp (introduziu `centrifuge` import em `_components/`)
- PR [#174](https://github.com/wagnerra23/oimpresso.com/pull/174) — Implementação desta ADR
- Auto-mem `reference_quick_sync_quebrada.md` — bug histórico nos secrets SSH
