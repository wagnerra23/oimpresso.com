---
date: 2026-06-24
hour: "14:17 BRT"
topic: Reconciliação SDD do Cliente (contacts) → máquina anti-fachada (live por sinal de prod · covers em lane · frescor)
duration: ~4h
authors: [W, Claude]
---

# Reconciliação SDD do Cliente → máquina que pega "live/cover sem prova" sozinha

## Estado MCP no momento
- **CYCLE-08** (Receita — Onda A) · 86% decorrido · 4 dias. **Esta sessão é off-cycle** (governança/SDD — não toca os 5 goals de receita).
- `my-work`: 30 tasks ativas (7 review · 8 blocked · 15 todo) — nenhuma desta sessão.
- Trabalho todo em branches off `origin/main` (worktree `cliente-sdd-reconcile`). A branch nominal `feat/vendas-link-caixa-do-dia` estava **180 atrás** → abandonada.

## O que aconteceu
Pedido: *"reconciliar o SDD do contacts/clientes"*. Achei que a separação Cliente≠CRM ([ADR 0301](../decisions/0301-separar-cliente-deprecar-crm-pipeline.md)) + a máquina (anchor-lint) já tinham landado; reverifiquei as **15 âncoras (27 caminhos) vivas no main**. Reconciliei o resto e — provocado pelo **[W] "quero um adversário pra ter certeza"** — virou um endurecimento da máquina SDD em **3 eixos**.

**O fio condutor:** em TODO ponto que importou, quem pegou o erro foi o **ADVERSÁRIO/Wagner, não a máquina** — charter promovido a `live` SEM prova (telas flag-gated; zombie-check é cego a flag) e âncora **FAKE** (US-073 → teste `@group quarantine` off-target). Pra saber se estava live, tive que **PERGUNTAR** "biz=4 está no react?". Conhecimento tribal. As PRs de máquina fecham esse buraco.

## Artefatos gerados (9 PRs · 7 merged)
- **#3333** ✅ reconciliação SDD Cliente: re-carimbo 15 âncoras `@3cf2b52→@3b425d8` + degrau-4 (`Testado em`+`DoD`+`@covers-us`) + escopo 7 Pages. _Na 1ª versão promovi charters a `live` citando `Wave1*InertiaTest` — FALSO (são grep de source); adversário pegou → revertido._
- **#3336** ✅ charters Create/Edit/Ledger/Map/Import `draft→live` APÓS [W] confirmar biz=4 no React em prod + fix rota `Map.charter`.
- **#3337** ✅ ADR proposta `2026-06-24-charter-live-derivado-sinal-prod-anti-fachada` (só itens NOVOS; arming/covers já eram propostas de 23/jun).
- **#3339** ✅ `charter-live-signal.mjs` (consumidor) + `governance/prod-flags.json` (seed) + fixtures.
- **#3341** ✅ arming: caso no `gate-selftest` (34/34) + job diff-aware no `anchor-drift`.
- **#3346** ✅ `governance:prod-flags` (PRODUTOR) — comando que deriva prod-flags do `shouldRenderInertiaCliente` real + Pest **na lane** `ci-sqlite-pest.list`.
- **#3342** 🟢 OPEN — frescor: scorecard recomputa no `push→main` (não só cron).
- **#3344** 🟢 OPEN — `req_sem_lane` no anchor-lint: cover fora de lane de JUnit = fachada (advisory/report).

## Persistência
- git: 9 PRs (7 merged) · webhook→MCP propaga.
- `prod-flags.json`: seed manual (biz=4) no main; vira **derivado** quando [W] rodar `php artisan governance:prod-flags --write` no prod.

## Próximos passos pra retomar
1. Mergear **#3342 + #3344**.
2. **Publish do produtor** (infra [W]): `php artisan governance:prod-flags --write` no prod + commitar (manual SSH agora; automatizar = cron/pós-deploy/orphan-branch padrão `nightly-floor`).
3. Armar bites advisory→required (calendário [ADR 0275](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)); item (b) precisa antes **POPULAR as lanes** (hoje toda cobertura está fora de lane → seria all-red).

## Lições catalogadas
- **Charter `live` ≠ tela existe**: telas flag-gated (`MWART_CLIENTE_*` enabled + business_ids; allowlist vazia = todos). Zombie-check do anchor-lint é cego a flag → `live` exige **sinal de prod**, não Wave1 source-test.
- **Cover por grep ≠ cover real**: `@covers-us` em teste quarantinado/vitest/fora-de-lane passa o gate mas nunca fica verde (fachada) → daí `req_sem_lane`.
- **Adversário é o ativo**: 2 rodadas de skeptic pegaram fake-anchor + live-inventado que os gates NÃO pegaram. Com time MCP entrando, nem todo PR terá adversário → mecanizar (foi o que estas PRs fizeram).
- **Wagner mergeia rápido**: PRs auto-mergeados durante a sessão → branch deletada → re-push vira órfã → criar **branch nova off main pro delta** (aconteceu 2×; #3339 e #3336 squash-merged ⇒ `origin/main..HEAD` mostra dup de conteúdo).
- **`gh pr create` 401 transitório**: `gh auth setup-git` + retry (git push tb caiu 1×, mesmo fix).

## Pointers detalhados
- Proposta: `memory/decisions/proposals/2026-06-24-charter-live-derivado-sinal-prod-anti-fachada.md`
- SPEC: `memory/requisitos/Cliente/SPEC.md` · ADRs 0301/0303/0273/0275/0298/0179/0279
- Consumidor: `scripts/governance/charter-live-signal.mjs` · Produtor: `app/Console/Commands/Governance/ProdFlagsCommand.php` · Contrato: `governance/prod-flags.json`
