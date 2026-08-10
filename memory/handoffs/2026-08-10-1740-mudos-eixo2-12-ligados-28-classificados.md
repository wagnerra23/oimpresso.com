---
date: "2026-08-10"
time: "17:40 BRT"
slug: mudos-eixo2-12-ligados-28-classificados
tldr: "Dos 40 testes que uma lane de PR alcança e o driver faz pular (eixo 2, #5522), 12 foram ligados (+105 passed, +409 assertions) e 28 ficaram classificados com motivo. Ligar o teste do Arquivos revelou um crash de produção que o skip escondia. --mudos 40 → 28, medido no main pós-merge. CT 100 ficou fora a sessão inteira; validei pelas lanes dos próprios PRs, declarado."
prs: [5524, 5526, 5528]
decided_by: [W]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0298-teto-de-governanca-anti-proliferacao-gates
next_steps:
  - "[W] decidir o fix do $businessId em RetentionCleanupCommand.php:194 — 1 linha, MAS muda comportamento destrutivo (sem transação, o comando passa a apagar mais por invocação)"
  - "[W] decidir os 13 sem lane MySQL (ComVis 7 · Repair 3 · Vestuario 3): exige workflow novo = gates-registry Check G + teto ADR 0298"
  - "Dívida dos 15 restantes já está datada nos comentários dos workflows — o maior lote é PermissionDoesNotExist (5), o seed da lane não cria as permissions que o teste exige"
  - "NfeEventoMultiTenantIsolationTest (Tier 0, ADR 0093) segue mudo — registrado, não escondido"
  - "ExportZipCommandTest: 3 das 4 falhas são Artisan::output() chamado 2× (fetch esvazia o buffer); a 4ª (audit log 'exported' ausente) NÃO foi diagnosticada"
---

# Handoff — os mudos do eixo 2, e o crash que o `skip` escondia

## Estado MCP no momento do fechamento

⚠️ **MCP INDISPONÍVEL.** O hook de SessionStart reportou fallback por timeout
(*"servidor MCP não respondeu no tempo"*), e o `ToolSearch` não encontra nenhuma tool
`oimpresso` registrada nesta sessão — logo `cycles-active` / `my-work` / `sessions-recent` /
`decisions-search` **não foram executados**.

**Declarado, não inventado** — mesmo precedente do handoff das 13:30 de hoje e do de
2026-08-09 23:00. Fallback usado (documentado em [`how-trabalhar.md` §Fallback](../how-trabalhar.md)):
`git log` + `gh` + as portas vivas do repo (`test-lane-coverage --mudos`, `--selftest`,
`junit-summary`).

## Continuidade

Este handoff fecha o próximo-passo deixado pelo
[handoff das 13:30](2026-08-10-1330-jana-lida-inteira-56-gaps-e-o-eixo-que-faltava.md):
*"Os 40 testes mudos exigem CT 100 antes de ligar — se falhar, a lane trava merge de todos"*.

O CT 100 **ficou fora do ar** (3 tentativas, `502` no dial da `:22`). A alternativa usada foi
a **lane do próprio PR como executor**: um vermelho ali bloqueia só aquele PR, nunca o merge
de terceiros. A substituição foi declarada em cada PR **antes** de qualquer merge.

## Onde o trabalho parou

**Mergeado e verificado no `main`** (`6a536a8f5f0`):

| commit | PR | ligou | delta vs `main` |
|---|---|---|---|
| `f5a2d0f3884` | #5524 | 2 sqlite (Jana + KB) | +20 passed · +92 assertions |
| `32b91cc5920` | #5526 | 3 Arquivos | +20 passed · +96 assertions |
| `6a536a8f5f0` | #5528 | 7 NfeBrasil + Fiscal | +65 passed · +221 assertions |

**`--mudos`: 40 → 28**, medido no `main` depois dos merges (não previsto).

A régua em todos foi **delta de `passed`/`assertions`**, nunca "check verde" — que é o próprio
defeito que o eixo 2 mede (`skip` sai `exit 0`).

## O que precisa de decisão [W]

**1. Bug de produção, não consertado de propósito.**
`Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php:194` usa `$businessId` numa
closure que não o capturou (`:124`). Crasha sempre que há linha a limpar; com 0 linhas o
`chunk` nem chama a closure — por isso passava despercebido, e o teste que o provaria saía
verde por `skip`.

O fix é 1 linha, **mas não é cosmético**: não há transação no `chunk`, então hoje o comando
apaga um lote, remove os arquivos do disco e crasha **sem rollback**. Consertar faz ele
percorrer todos os chunks — **apagar mais por invocação**. Mudança de comportamento
destrutivo. Não está agendado no `Kernel.php` (só o `arquivos:health-check`), então o alcance
é invocação manual.

**2. Os 13 sem lane MySQL** (ComVis 7 · Repair 3 · Vestuario 3). Criar lane é workflow novo →
entrada obrigatória em `gates-registry.json` (Check G) + `terminal`/`anchor`/`promote_by`
(Check M, teto [ADR 0298](../decisions/0298-teto-de-governanca-anti-proliferacao-gates.md)),
ambos dentro do required `Governance Gate`. É governança, não conserto mecânico.

## Armadilhas catalogadas (para a próxima sessão não repetir)

- **`--mudos` não existia no worktree stale.** O script **ignorou a flag em silêncio** e
  imprimiu o eixo 1, que responde outra pergunta. Flag desconhecida em CLI Node costuma ser
  ignorada, não rejeitada — conferir a interface antes de confiar na saída plausível.
- **Fantasma de 2ª ordem.** `Fiscal/NfseCockpitControllerTest` deu `passed=0 · skipped=4`
  **na lane com o driver certo**: tem um segundo guard. "Mover pra lane certa" nem sempre
  basta, e o `--mudos` declara esse limite de si mesmo (*"só skip por DRIVER é medido"*).
- **Console do Pest mente sobre `passed=0`.** Esse arquivo aparece como `WARN`/verde no
  console; quem expõe é o **sumário JUnit** (`junit-summary.mjs`). Ler o sumário, não o
  console — é a régua que o cabeçalho da `nfebrasil-pest.yml` já mandava aplicar.
- **`executionOrder="random"` (phpunit.xml:7)** invalida comparação de placares entre runs.
  Atribuí um vermelho do KB à minha mudança por aritmética antes de descobrir isso; era o
  flaky nominal do `V2b`, documentado em `Modules/KB/Tests/Helpers.php:311+`. A lane está
  **12 vermelho / 16 verde em 30 runs no `main`**, por causas **mistas** (ordem + infra:
  um dos vermelhos foi `self-signed certificate` no `dorny/paths-filter`).
- **Convenção de nome de arquivo não é inventário de lane.** Concluí "Fiscal não tem lane
  MySQL" porque não existe `fiscal-pest.yml` — ele é coberto pela `nfebrasil-pest.yml`.
  Medir com `driverDaLane`/`extrairAlvos` do próprio `test-lane-coverage`, não pelo nome.

## Dívida do commit (higiene)

O título mergeado do #5526 (`32b91cc5920`) diz **"liga os 5 testes mudos"**; entraram **3** —
reduzi na revisão e não atualizei o título antes do merge. Histórico é append-only, então o
registro é a [errata no PR](https://github.com/wagnerra23/oimpresso.com/pull/5526#issuecomment-5243643378).
O mesmo defeito foi corrigido no #5528 **antes** daquele merge.

## Session log

Narrativa completa (incluindo os 6 erros meus e como cada um foi pego) em
[`memory/sessions/2026-08-10-mudos-eixo2-ligados-e-o-bug-que-o-skip-escondia.md`](../sessions/2026-08-10-mudos-eixo2-ligados-e-o-bug-que-o-skip-escondia.md).
