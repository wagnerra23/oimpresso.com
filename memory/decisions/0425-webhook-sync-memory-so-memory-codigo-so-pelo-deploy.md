---
slug: 0425-webhook-sync-memory-so-memory-codigo-so-pelo-deploy
number: 425
title: "Webhook sync-memory atualiza só memory/ — código de produção chega só pelo deploy.yml"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-02"
module: null
tags: [deploy, hostinger, webhook, mcp, memoria]
supersedes: []
supersedes_partially:
  - 0216-deploy-webhook-rodar-composer-dump-autoload
superseded_by: []
related:
  - 0216-deploy-webhook-rodar-composer-dump-autoload
  - 0269-deploy-automatico-build-no-runner
  - 0062-separacao-runtime-hostinger-ct100
pii: false
---

# ADR 0425 — Webhook sync-memory atualiza só `memory/`; código chega só pelo deploy

## Contexto

Medido em 2026-10-02, cruzando as entregas do webhook `614879953` (`POST /api/mcp/sync-memory`)
com o reflog do git no servidor: a cada push no `main`, o `SyncMemoryWebhookController` fazia reset
do working tree inteiro de produção para o topo do `main`, cerca de 3 s depois do merge. Isso
publicava PHP sem `composer dump-autoload`, sem OPcache reset e sem os bundles, que só o
`deploy.yml` ([ADR 0269](0269-deploy-automatico-build-no-runner.md)) faz.

A [ADR 0216](0216-deploy-webhook-rodar-composer-dump-autoload.md) registra que esse caminho já
derrubou produção por 2h31 em 2026-05-28, e decidiu reforçá-lo com dump-autoload depois do pull.
Isso nunca foi implementado no controller, e a 0269 depois criou o caminho de deploy de verdade sem
mencionar o webhook. Os dois conviviam.

O webhook não pode simplesmente parar de mexer no disco: o `deploy.yml` ignora pushes em
`memory/**`, e o webhook é o único caminho que leva doc de memória ao servidor.

Análise completa e alternativas: [proposta](proposals/webhook-sync-memory-sem-reset-do-codigo.md).

## Decisão

Opção A da proposta, aprovada por [W] em 2026-10-02 (*"opção A aprovada"*):

1. O webhook troca o reset do working tree por
   `git restore --source=origin/main --staged --worktree -- memory`. A pasta `memory/` fica igual ao
   `main`, inclusive com doc apagado. O HEAD e o código não se movem.
2. Código de produção chega **só** pelo `deploy.yml`.
3. Esta ADR **supersede parcialmente a 0216**: a parte que mantinha o webhook como caminho de
   deploy e mandava rodar dump-autoload nele. O diagnóstico da 0216 continua valendo.

**Desvio da proposta, medido:** a proposta previa mudar `IndexarMemoryGitParaDb::lerGitSha()` para
ler do `origin/main`. Não foi feito. `lerGitSha()`, `StalenessDetectorService::lerGitShaAtual()` e
`ContentReconciler::lerGitSha()` leem o commit a partir do HEAD; mudar só um deles faria os outros
compararem commits diferentes e acusarem doc desatualizado à toa. E no Hostinger `shell_exec` está
em `disable_functions` (medido no PHP de linha de comando), então o indexador do webhook já grava
sem commit e preserva o que existia.

## Consequências

- Fix de código passa a esperar o deploy, não ~3 s. Em 2026-10-02 o deploy fez o reset 1h14 e 1h40
  depois dos merges medidos.
- PHP e bundles voltam a sair juntos.
- O `DeployDriftChecker` volta a poder acusar deploy atrasado: o HEAD do servidor deixa de ser
  movido para o `main` a cada push.
- Entre um deploy e outro, `memory/` em produção fica à frente do HEAD (mudanças staged). O próximo
  `deploy.yml` reseta para o SHA do run, e o webhook seguinte volta a atualizar `memory/`.
- O filtro `pushExigeDeployManual()` (pulava o reset quando o push mexia em `composer.*`,
  migrations etc.) foi removido: sem reset de código, não há o que pular, e a memória deixa de ficar
  parada nesses pushes.

## Verificação

- Teste `Modules/Forja/Tests/Feature/SyncMemoryWebhookSoMemoryTest.php` (lane `forja-pest`): o
  controller pede `fetch` e `restore -- memory` e nunca pede `reset`, `checkout` ou `pull`.
- Depois do deploy: o reflog do servidor não pode ter nova entrada `reset: moving to origin/main` —
  só `reset: moving to <sha>` do `deploy.yml`.
