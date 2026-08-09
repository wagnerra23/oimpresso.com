---
date: "2026-08-08"
time: "23:40 UTC"
slug: crons-governanca-mortos-em-prod
tldr: "Dois crons de governança morriam diariamente em prod. Causas isoladas, corrigidas e provadas: node só existe no PATH de login (não no cron) e o GovernanceServiceProvider nunca registrava suas 5 migrations — eram 4 tabelas ausentes, não 1."
decided_by: ["W"]
prs: [5443, 5444]
related_adrs:
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
  - "0062-separacao-runtime-hostinger-ct100"
  - "0269-deploy-automatico-build-no-runner"
next_steps:
  - "~35 comandos agendados falham diariamente em prod, sem diagnóstico (causas distintas — só 3 comandos invocam node)"
  - "Mesma classe latente em Forja (5 migrations) e Auditoria (auditoria_audit_notes AUSENTE) — barato, reusa o guard novo"
  - "app/Console/Kernel.php está fora do infra-contract-required (paths cobrem só o Http/Kernel.php)"
  - "Modules/Governance/SCOPE.md declara 1 tabela em db_tables_owned e omite as 6 do módulo"
  - "Deletar 2 branches remotas com merge-commit pendurado e 0 diff vs main"
---

# Handoff — Dois crons de governança mortos em prod (2026-08-08 23:40 UTC)

Fecha o chip `task_4677a802`, aberto na sessão das 18:04 ([handoff pai](2026-08-08-1804-migracao-blade-3-pecas-e-o-dedup-que-cegava-a-catraca.md)),
que registrou os dois achados **sem** isolar causa. As duas causas foram isoladas, corrigidas,
mergeadas e **provadas em produção**.

## PRs (ambos MERGED por [W], deploy aplicado)

| PR | o que | evidência |
|---|---|---|
| [#5443](https://github.com/wagnerra23/oimpresso.com/pull/5443) | `GovernanceServiceProvider` nunca chamou `loadMigrationsFrom` | 103 checks ✅ · merged 21:50 UTC |
| [#5444](https://github.com/wagnerra23/oimpresso.com/pull/5444) | snapshot SDD estava agendado **2×**; dono é o CT 100 | 100 checks ✅ · merged 21:31 UTC |

## Achado 1 — `sdd-scorecard-snapshot`: não era bug do comando, era dono duplicado

O comando tinha **dois** agendadores: o cron do CT 100 (dono declarado, decisão [W] 2026-07-01)
e um `$schedule->command(...)` 07:10 `['live']` no `Kernel.php`. O segundo falhava todo dia.

**Por que falhava (medido, não inferido):** o comando roda `new Process(['node', …])` e no
Hostinger o node existe **só** em `~/.nvm/versions/node/v24.15.0/bin/` — não há `/usr/bin/node`
nem `/usr/local/bin/node`. Reproduzido com PATH tipo-cron: `sh: line 1: exec: node: not found`,
rc=1. Com o PATH de login o mesmo comando devolve JSON válido. **25 falhas** no `laravel.log`.

Isto **confirma e corrige** a medição do handoff-pai: lá o `proc_open(['node','-v'])` deu `rc=0
v24.15.0` e concluiu-se que node não era a causa. O `proc_open` estava certo — só rodou no
**shell de login**, que carrega o nvm. O cron não.

**Por que NÃO apontei o `Process` pro caminho absoluto do node:** consertar isso *causaria* o bug
pior. Re-medir no Hostinger (sem `GH_TOKEN`, sem a órfã `governance/nightly-floor`) produz
composta fantasma, e o insert é `delete+insert` por `snapshot_date` ⇒ **sobrescreveria a row
correta do CT 100**. É a lápide "UMA composta" de 2026-07-13 (64,1 do host × 41,0 do artefato),
que o próprio RUNBOOK registra. Um dono só.

**Errata junto (LC-10):** o RUNBOOK afirmava em **tempo presente** que `schedule:run` *"não
acontece"* no Hostinger. Acontece — e foi essa premissa falsa que deixou o duplicado sobreviver.

## Achado 2 — era maior que o reportado: 4 tabelas, não 1

`GovernanceServiceProvider` nunca chamou `loadMigrationsFrom` — **30 dos 33** módulos chamam.
O `migrate --force` do deploy pulava as **5** migrations do módulo desde 2026-05-16. A única
tabela que existia foi aplicada **fora-de-banda** (path único, pré-req do P06), o que explica
uma migration de junho existir e quatro de maio não.

Bug idêntico ao do `KBServiceProvider` (2026-07-23) — mesma causa, mesmo fix.

## Prova em produção (R1, pós-deploy)

```
mcp_module_grades_history           AUSENTE → EXISTE
mcp_scorecard_runs                  AUSENTE → EXISTE
mcp_observability_spans             AUSENTE → EXISTE
mcp_observability_aggregates_daily  AUSENTE → EXISTE
mcp_governance_initiatives          AUSENTE → EXISTE
mcp_sdd_scorecard_history           EXISTE   (pulada pelo guard hasTable, como previsto)
```

`module:grade-snapshot`, que morria diariamente há ~3 meses:
**`Snapshot OK — 32 módulos persistidos em mcp_module_grades_history (1124ms)`** · `rows=32`.
Conferi `rows_hoje=0` **antes** de rodar — o comando é `insert` puro, não `delete+insert`.

Sem regressão de rota: `/login` 200 · `/governance` 302 · `/governance/module-grades` 302 ·
`/governance/drift` 302 — idênticos ao baseline registrado no Infra Contract antes do merge.

Série do CT 100 intacta: `2026-08-08` composta 55,1. Duplicado sumiu do `Kernel.php` deployado.

## Erros meus nesta sessão (o CI pegou 3; 1 era de fato meu)

1. **Reincidi na lápide §5 2026-07-28 no guard que eu estava escrevendo.** Usei
   `toContain($tabela, "mensagem")`; `toContain` é **variádico** no Pest, a mensagem virou 2º
   needle e o assert falhava sempre. O log saiu literal: `To contain: migration de ... sumiu de`.
   Trocado por filtro + `toBe([])` com controle negativo. **O assert que importa passou no mesmo
   run** — `app('migrator')->paths()` contém o path, provando que o fix faz efeito.
2. `Infra Contract` faltando — legítimo (`Modules/**/Providers/*ServiceProvider.php` está nos
   `paths:`). Escrevi a seção real, não o `evidence-override`; isto não é hotfix.
3. `module-surface` (required, ADR 0370) — adicionar arquivo em `Modules/Governance/` deixou o
   `SUPERFICIE.md` stale (168→169). Regenerado e reconferido rodando **o modo exato do job**
   (`--all --check`), não só o `--write` (§5 2026-07-28).

## Duas armadilhas de verificação que quase viraram relato errado

- **`gh pr checks` verde ≠ nenhuma run falhou.** Havia run falhando no meu HEAD que ele não
  listava: `governance-script-tests.yml` com **`startup_failure` (0 jobs)** ⇒ nenhum check-run
  ⇒ invisível. Não era minha: falhava em `main` desde ~19:05 e o [#5442](https://github.com/wagnerra23/oimpresso.com/pull/5442)
  já a consertara às 21:48; minhas branches eram anteriores. **Não era deadlock** — o required
  `gate selftest (as catracas mordem · GT-G6)` vem de outro workflow e estava verde.
- **O repo faz squash-merge.** `git merge-base --is-ancestor <meu sha> <main>` dizia **não**
  enquanto o conteúdo estava lá. Quem desfez foi `grep loadMigrationsFrom` no `origin/main`.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 10 tasks em REVIEW (US-TR-309/310/311, US-PG-008, US-PROD-027, US-INFRA-023/048,
  US-TR-305/306, US-KB-002) — nenhuma tocada nesta sessão
- `decisions-search` → nenhuma ADR nova cobre esta classe; a mais próxima é a
  [0269](../decisions/0269-deploy-automatico-build-no-runner.md) (deploy automático ⇒ merge **é** o ato de aplicar migration)
- PRs: **#5443 e #5444 MERGED**; nenhum PR desta sessão ficou aberto

## Pendências (não fiz, com razão)

- **~35 comandos agendados falhando diariamente** em prod. Tally do `laravel.log`:
  `charter:health` 39× · `arquivos:health-check` 39× · `governance:audit` 38× ·
  `jana:freshness-check` 37× · `backup:run` 23× … Só **3** comandos no repo invocam node ⇒
  **não há causa comum**. O que os mantém invisíveis é estrutural: `->onFailure()` loga frase
  genérica e o **stderr é descartado** — precisei rodar um a um pra saber o que falhava.
- **Mesma classe, latente:** `Forja` (5 migrations, path não registrado, tabelas já existem ⇒
  migration **nova** seria pulada em silêncio) e `Auditoria` (`auditoria_audit_notes` AUSENTE).
  Fechar isso é barato e reusa o guard que acabou de entrar.
- **`app/Console/Kernel.php` fora do `infra-contract-required`** — os `paths:` cobrem só
  `app/Http/Kernel.php`. Erro de sintaxe lá derruba todo o cron. Alargar exige medir FP antes.
- **`Modules/Governance/SCOPE.md`** declara 1 tabela em `db_tables_owned` e omite as 6 do módulo.
  Drift real, mas editá-lo acorda `module-surface`/`catalog-graph` (required) sobre dívida
  pré-existente ⇒ PR separado.
- **Duas branches remotas com merge-commit pendurado** e **0 diff vs main**
  (`claude/governance-migrations-nunca-registradas`, `claude/sdd-snapshot-agendado-2x-dono-e-ct100`):
  mergei `main` nelas antes de perceber que os PRs já tinham sido mergeados. Inofensivas; podem
  ser deletadas.

## Lição perene

O handoff-pai registrou os dois achados como "causa NÃO isolada" e listou o que já tinha sido
descartado. Isso **encurtou esta sessão** — não repeti nenhuma das medições dele. O que faltava
nos dois casos era a mesma coisa: **medir no contexto certo**. O `proc_open` mediu no shell de
login em vez do cron; o `Schema::hasTable` de uma tabela não respondia pelas outras quatro.
Nenhum dos dois era erro de instrumento — era erro de **onde** o instrumento foi apontado (LC-08).
