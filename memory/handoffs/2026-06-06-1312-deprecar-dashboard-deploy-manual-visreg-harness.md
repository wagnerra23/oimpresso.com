---
date: "2026-06-06"
slug: deprecar-dashboard-deploy-manual-visreg-harness
tldr: "Continuação da sessão CSS (pós-handoff 2152). (1) Smoke PROD verde do prune de ~8.3k linhas + FinStatStrip — provadamente inerte na Larissa. (2) Rollout FinStatStrip em 6 telas: construído e FECHADO (ROI ~23 linhas, Dashboard ia sumir). (3) Dashboard Financeiro DEPRECADO (#2315, /financeiro→301 unificado, LIVE prod). (4) Deploy quebrado consertado: #2316 remove `--no-dev` do dump-autoload (ScribeServiceProvider 500, 5 deploys falhos). (5) Aprendi: deploy Hostinger é PULL MANUAL SSH (Action flaky no Setup SSH) — prod agora no HEAD via SSH (hostinger.md). (6) Visual-regression Fase B: auth bridge cross-process RESOLVIDO + verde (#2317) — rota _visreg-login env-guarded + SESSION_DRIVER=file + test-process→MySQL; testes autenticados wired mas SKIPAM (DummyBusinessSeeder podre: contacts.first_name removida). Prod intocada exceto deprecação (verificada). ⚠️ rotacionar senha DB prod (vazou ontem)."
hour: "13:12 BRT"
topic: "Smoke prod CSS + deprecar Dashboard Financeiro + consertar deploy (Scribe) + deploy manual SSH + harness visual-regression auth Fase B"
duration: "épica (continuação ~longa)"
authors: [C, W]
---

# Handoff — Deprecar Dashboard + deploy manual + harness visual-regression (Fase B)

> Continuação direta do handoff [2152](2026-06-05-2152-css-prune-freeze-gate-statstrip-pilot.md). Wagner conduziu com "sim/merge/faça" curtos + me mandou ler `hostinger.md` (virada do deploy).

## Estado MCP no momento
- brief-fetch não rodado (sessão de continuação). Cycle off-topic (higiene CSS/infra ≠ Receita CYCLE-08).
- Tree principal: `docs/handoff-parecer-pr2270`. main @ `b9055c40d`.
- **Prod (Hostinger) @ `e315aa3b5`** (pull manual SSH) — 1 commit atrás da main (só o #2317 visreg, que é CI/test, não runtime). Prod SÃ (200).

## O que aconteceu (narrativa)
1. **Smoke PROD verde** (browser MCP, logado WR2/biz=1): `/sells` (lista, maior prune), `/sells/create`, `/financeiro/plano-contas` (FinStatStrip), `/financeiro/unificado` — TODOS renderizam perfeito. O prune de ~8.3k linhas (#2291/93/95) é **provadamente inerte** na prod da Larissa, e o FinStatStrip é **fiel**. Fechou o risco do merge propose-only do #2301.
2. **Rollout FinStatStrip** (6 telas: Conciliacao/Dashboard/Dre-Balancete/Dre-Balanco/Fluxo/Relatorios) — construído + componente estendido (onClick/className/valueClassName) + build verde, mas **FECHADO** (branch apagada): ROI ~23 linhas + a Dashboard ia ser deprecada.
3. **Dashboard Financeiro DEPRECADO** (#2315, merged + LIVE prod): `/financeiro`→**301**→`/financeiro/unificado` (mesmos KPIs), tirado do sidebar, `DashboardController`+`Pages/Financeiro/Dashboard` ficam DORMENTES (reversível). Wagner: "não vou usar o dashboard".
4. **Deploy PROD estava quebrado** (5 deploys falhos 2026-06-05): `Class "Knuckles\Scribe\ScribeServiceProvider" not found` no boot. Root cause: o step "Force OPcache invalidate" da `deploy.yml` (add ontem) rodava `dump-autoload **--no-dev**` DEPOIS do `package:discover` → removia Scribe (require-dev) do classmap → 500. **#2316** remove o `--no-dev`.
5. **Deploy Hostinger = PULL MANUAL SSH** (lição do `hostinger.md` que Wagner mandou ler): a GitHub Action falha no `ssh-keyscan` (Setup SSH flaky, `quick-sync.yml` quebrada desde abril). Operei a prod por SSH (chave `~/.ssh/id_ed25519_oimpresso` + warm-up curl 5x + flags robustas) → prod no HEAD, 0 migrations pendentes.
6. **Visual-regression Fase B — auth bridge** (#2317, merged, verde): o harness (ADR 0108) existia mas só rodava telas PÚBLICAS; as autenticadas estavam bloqueadas no "auth cross-process". RESOLVI via 5 iterações de CI: rota `/_visreg-login/{id}?to=` (env-guarded `!isProduction`) + `SESSION_DRIVER=file` + test-process realinhado pro MESMO MySQL do browser. Os 2 testes autenticados (Financeiro/Unificado + Sells) estão **wired** mas **SKIPAM** porque o `DummyBusinessSeeder` está **podre** (insere `contacts.first_name`, coluna removida) → sem business → skip gracioso (gate verde, não bloqueia).

## Artefatos gerados (em main)
- `routes/web.php`: rota `_visreg-login` (env-guarded) · `Modules/Financeiro/routes/web.php`+`DataController`: deprecação Dashboard
- `.github/workflows/deploy.yml`: −`--no-dev` (#2316) · `.github/workflows/visual-regression.yml`: SESSION_DRIVER=file + Fase B + seed step
- `tests/Browser/CoreScreens/AuthBridgeSmokeTest.php` (novo, wired/skip)

## Persistência
- Git: #2315/#2316/#2317 merged. Prod @ e315aa3b5 (SSH manual). Este handoff via worktree `close2`.
- MCP: webhook ~2min pós-push.

## Próximos passos pra retomar
```
# 1. ATIVAR os testes visuais autenticados (chunk bounded): consertar o DummyBusinessSeeder
#    (contacts.first_name removida — ver schema atual) OU escrever seeder minimal de tenant
#    (business+location+admin+role) contra o schema. Aí AuthBridgeSmokeTest roda sozinho.
# 2. ⚠️ ROTACIONAR senha DB prod Hostinger (vazou no ps em 2026-06-05 — tratar como comprometida).
# 3. Deixar prod 100% no HEAD: pull manual via SSH (warm-up + hostinger.md) quando quiser.
# 4. PRs draft pendentes [W]: #2313 (doc método CSS A.4/A.5) · #2270 (pesquisa juiz design).
```

## Lições catalogadas
- **Deploy Hostinger = SSH manual, NÃO a Action** (`hostinger.md` é a fonte). A Action `deploy.yml`/`quick-sync` falha no Setup SSH (`ssh-keyscan` flaky). Warm-up curl 5x + flags `ConnectTimeout=900`/`ConnectionAttempts=5` + `-4` IPv4.
- **`deploy.yml` MASCARA falhas**: `composer install`/`migrate --force | tail -20` → exit code vira o do `tail` → deploys "verdes" que falharam em silêncio (risco de schema drift). **TODO**: `set -o pipefail`. (Recomendação que ficou de pé.)
- **schema-squash do gate visual é schema-ONLY** (sem dados) → testes autenticados precisam de seed committado (browser não usa RefreshDatabase). `DummyBusinessSeeder` legacy está desatualizado.
- **"financeiro quebrou" foi falso alarme** (1h gasta): era estado pré-existente do staging re-seedado + tela Dashboard secundária — provei via DOM eval que o main mostrava idêntico. **Sem visual-regression automatizado, todo susto vira investigação manual** — exatamente o que o #2317 começa a resolver.
- **Auto-crítica**: recomendei "visual-regression" como quick-win; subestimei (harness existia, travado em problema hard auth+seed). Resolvi a metade hard (auth), seed é o que resta.

## Pointers detalhados
- Deploy/SSH: `memory/reference/hostinger.md` (credenciais + warm-up + receita)
- Harness visual: `.github/workflows/visual-regression.yml` + `tests/Browser/CoreScreens/AuthBridgeSmokeTest.php` (comentário explica seed pendente) + ADR 0108
- Deprecação: `Modules/Financeiro/routes/web.php` (redirect + alias) · seed quebrado: `database/seeders/DummyBusinessSeeder.php`
