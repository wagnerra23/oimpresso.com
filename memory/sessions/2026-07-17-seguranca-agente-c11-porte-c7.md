---
slug: 2026-07-17-seguranca-agente-c11-porte-c7
title: "Segurança-do-agente 5,0/10 — C11 corpus invocado · porte lote B · C7 egress default-deny"
type: session-log
authority: informativo
lifecycle: ativo
date: "2026-07-17"
related_adrs: [0224, 0298, 0314, 0271, 0290]
pii: false
---

# Segurança-do-agente (grade 2026-07-17, nota 5,0/10) — 3 chips fechados

Sessão de melhoria da dimensão `seguranca-do-agente`. Diagnóstico da grade: **30 hooks
PreToolUse com exit 2** (a parte forte) e **zero controle de ambiente** (a fraca — defesa
100% sintática; regex não enumera a classe que não conhece).

## O que fechou (3 PRs, todos MERGED por [W] e verificados no estado de main)

| chip | PR | commit main | o que faz |
|---|---|---|---|
| **C11** | [#4409](https://github.com/wagnerra23/oimpresso.com/pull/4409) | `0ad8eba6a0` | invoca o corpus de injection (path-filter `.claude/hooks/**` + cron) |
| **porte lote B** | [#4416](https://github.com/wagnerra23/oimpresso.com/pull/4416) | `dfe2e8491d` | 2 blockers `.ps1`→`.mjs` cross-plataforma |
| **C7 fase 2** | [#4420](https://github.com/wagnerra23/oimpresso.com/pull/4420) | `eff337de35` | devcontainer com egress default-deny (o controle de TIPO que faltava) |

## C11 — o corpus existia e NADA o invocava

`.claude/governance-eval/prompt-injection-corpus.mjs` (auditoria 2026-07-10) rodava só quando
um humano lembrava = nunca. É o meta-padrão "correção-do-mecanismo ≠ invocação" (proibicoes §5
2026-07-09, chokepoint fantasma `flag:set`).

- **Chokepoint medido, não assumido:** 30/30 dos últimos commits tocando `.claude/hooks/`
  vieram por PR (`git log -30`). Path-filter casa o caminho vivo; cron semanal é backstop.
- **Furo fechado no mesmo PR:** o corpus invoca os hooks **pelo arquivo** → prova a lógica, não
  a ativação. **Medido:** com `pii-redactor` desregistrado do `settings.json` (defesa desligada
  no agente) o corpus fica **VERDE**. Fechado com `settings-backstop-registration.test.mjs` (na
  família dos 7 `settings-*registration` já existentes).
- **Controle-negativo:** neutralizar `block-destructive.mjs` → 6/6 vira 1/6, 5 regressões, exit 1.
- **Higiene (proibicoes §5 2026-07-16):** tirados os labels que afirmavam enforcement em presente
  (`advisory · ADR 0314`) — apodrecem no flip. No lugar: fato datado + ponteiro pro dono
  (`required-checks-baseline.json`).
- **Não toquei `Governance/SPEC.md`:** a grade dizia "bloqueado por evidência (ratchet)", mas medi
  o `distiller_freshness` — só olha `memory/requisitos/*/BRIEFING.md`. O C11 vive em `.github/` +
  `.claude/` = fora do gate. Forward-only (lápide 2026-07-12).

## Porte lote B — o escopo real era 2, não "19 .ps1"

A triagem 2026-07-09 já decidira hook a hook. **Medi antes de portar** (rodando, não pelo nome):
dos 20 `.ps1` registrados, 6 têm código de bloqueio e **só 3 bloqueiam por default**. Os outros
(`bom-encoding`/`test-without-red`/`charter-validate`) nascem em `warn` e nunca foram armados.

- Portados: `block-merge-markers` (#1000/#1001) + `block-routes-string-legacy` (#843).
- **NÃO portado** (a triagem me corrigiu): `block-serving-branch-switch` — protege o checkout
  Windows servido pelo Herd; em Mac/Linux o objeto protegido não existe. "Portar = polir commodity".
- **Diferencial `.ps1` vs `.mjs`: 8/8 idêntico** em cada hook. 70 casos derivados do CONTRATO
  (post-mortem/rules/incidente), nunca do output do `.ps1` (anti-tautologia §5 2026-06-05).
- **Por que porta:** em Linux `powershell -File` vira exit 127 → Claude Code trata exit≠2 como
  não-bloqueante → o blocker evapora **em silêncio**. Pré-time MCP (Felipe/Maiara/Eliana/Luiz).
- **Drift sistemático achado:** `AUTOMATIONS.md` apontava pra 4 `.ps1` já deletados por portes
  anteriores (#4028/#4035). Corrigido; hoje toda ref do registry existe em disco. Virou o chip
  `task_5c3028a8` (estender `memory-health` pra pegar essa classe — rodando em sessão paralela).

## C7 fase 2 — o único controle de TIPO diferente

devcontainer + `init-firewall.sh` (iptables/ipset default-DROP + allowlist) + workflow que prova
em CI. Ref: devcontainer do Claude Code + sandbox Anthropic (gVisor + MITM).

- **Decisão [W]: faseado** — porte ANTES do C7. Fazer C7 primeiro deixaria os `.ps1` no-op
  silencioso no container Linux. Com o porte feito, os 2 blockers reais funcionam dentro.
- **Aditivo, não substituto:** R1 (smoke visual) continua no desktop (Chrome MCP dirige Windows;
  container é Linux). Quem não abrir o container não sente nada. `~/.claude` não montado de propósito.
- **Provado em CI, não "na minha máquina":** o Docker Desktop do [W] estava PARADO (named pipe
  ausente) + WSL sem engine. Sem o CI o firewall nasceria sem nunca ter sido visto rodar. O
  workflow rodou **em main** (`29590001202`): `example.com sem firewall→200` · `OK bite: bloqueado`
  · `OK release: api.github.com alcançável` · `sem NET_ADMIN falha alto`.
- **Step 1 (controle do probe) é o coração:** `example.com` responde 200 sem firewall, senão o
  "curl falhou" do step 2 não distingue firewall de rede quebrada — a armadilha que matou a
  ADR 0290 (verde quando os 2 lados quebram). Por isso o alvo é `example.com`, nunca `.example`.
- **Não resolve (honesto, no README):** exfil por canal PERMITIDO (`github.com` na allowlist →
  segredo num PR ainda passa). Os 4 UNGUARDED do corpus seguem UNGUARDED **fora** do container.

## O harness me enganou 3× — o padrão que salvou

Cada vez, resultado suspeito → conferir o instrumento, não a conclusão:
1. **"6 hooks passam" (todos)** — payload por pipeline do PS não chega ao `[Console]::In.ReadToEnd()`.
   O controle-positivo (`block-merge-markers` finalmente bloqueou) revelou. Quase reportei 6 defeitos falsos.
2. **`git show origin/main:<path>`** — MSYS mangling (`:`→`;`) devolveu "workflow AUSENTE". Refeito
   com `ls-tree` + `MSYS_NO_PATHCONV=1`. Pegadinha já catalogada, caí mesmo assim.
3. Payloads que não disparavam os hooks (path relativo, `cwd` ausente) — lidos como "não bloqueia".

## Tensão que registrei pro [W] (não é bug, é rumo)

A grade aponta a pior nota em **inteligencia-de-negocio 3,0** e ratio negócio÷governança
**3,33× `alarme:true`** (77% do fluxo). Estes 3 PRs são governança — pedidos e ancorados, mas
empurram o ratio na direção que o alarme aponta. [W] escolheu **fechar a sessão** em vez de puxar
mais chips de segurança (que seriam mais governança/commodity). Próximo bloco: decisão dele, fresco.

## Estado

Off-cycle. Nenhuma US do MCP tocada (foi governança de infra, não backlog de produto). Os 3 PRs
em main e verificados rodando no estado mergeado.
