---
date: "2026-09-07"
time: "21:30 BRT"
slug: regime-evolucao-loops-blade-fora-fluxo-dora
tldr: "Regime de evolução por loops virou programa MEDIDO (10 etapas, cada uma com detect por comportamento e teste) + ADR 0391 (Blade fora, morre na migração) + medidor de fluxo DORA/Flow do sistema inteiro. 3 PRs mergeados (#6948 #6949 #6956). Achado: o medidor DORA da casa estava cego (marcador [CC] vs [C]) — chip aberto e em execução."
prs: [6948, 6949, 6956]
decided_by: [W]
related_adrs: [0391-regime-de-evolucao-por-loops-blade-fora, 0344-two-strikes-cobre-processo, 0104-processo-mwart-canonico-unico-caminho, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Ratificar a 0391: PR flip status proposto→aceito + índice + label adr-metadata-normalization (ADR 0257)"
  - "E1 do regime: 67 telas Inertia sem casos — 1 PR por tela com sdd-from-source (o banner de SessionStart aponta a próxima)"
  - "Decidir a fonte das duas métricas de cliente: registro de incidente (1 linha por evento + deploy SHA) e datas created_at/done_at na tool tasks-list — [W]/[F]"
  - "Chip task_3735b9ec (marcador [CC]→[C] no agent-pr-outcomes) roda em sessão irmã — conferir merge"
---

# Handoff — regime de evolução por loops, Blade fora, fluxo DORA medido

## Estado MCP no momento do fechamento

Tools MCP **não conectadas** nesta sessão (worktree filho; fallback filesystem por [how-trabalhar §Fallback]). Snapshot = brief #617 do SessionStart (gerado 2026-09-07, "há 2h" na abertura) + estado real via `gh`:

- Cycle: — · HITL pendente [W]: 5 · EM VOO: 11 itens com `wagner` (WIP máx 2) · US não atribuída 680 (525 sem dono, mais antiga 129d).
- PRs desta sessão: #6948 MERGED 22:49 UTC (`606e388df6`) · #6949 MERGED 22:52 UTC (`6912bb7477`) · #6956 MERGED 2026-09-08 00:23 UTC (`769b6930e3`). Os dois primeiros mergeados por [W]; o terceiro por este agente sob autorização textual ("merge").
- Handoffs irmãos do dia: `2026-09-07-0810-descida-inline-0389-jana-cowork-sem-disco.md` (outro tema, sem colisão).

## O que aconteceu

1. [W] perguntou *"como fazer o sistema evoluir com o tempo?"* → resposta: 12 loops (6 universais, 6 por área). Depois *"vai servir para todas as máquinas ou só algumas partes?"* → medido contra `origin/main`: trio/design/teste só alcançam telas Inertia e 7 lanes required; Blade (1.085 views) fora de todo loop de tela.
2. [W]: *"blade fica fora. ele vai morrer depois de migrar. pode fazer isso tudo"* + *"crie o plano e torne todas as etapas válidas com teste"*.
   - **#6948** estende `loop-fechar-check.mjs` (dono de "manifesto + detect") com `--manifest`, detect `comando` (roda a porta viva e compara o número com o alvo), tri-estado `feito|pendente|nao_medido`, `alvo_grupo` derivado da saída, veto `done:false`, cache 24h em `.claude/run/`, memo, `--medir`, `--json`. Manifesto `.claude/regime-evolucao.json` com E1..E10. Selftest roda o detect REAL de cada etapa (10/10 mediram, 64,8s a frio). SessionStart registrado.
   - **#6949** ADR 0391 `proposto`: D1 Blade fora; D2 regime universal fora do Blade; D3-D6 programa medido; D7 o que não vira etapa (required só Tier-0, sem cadência de grade, US sem dono fica no brief).
3. [W]: *"isso é o que eu preciso agora? é o que as grandes fazem?"* → resposta honesta: instrumento, não evolução; o que falta são sinal de cliente, execução das etapas, 2-3 métricas de fluxo e um SLO. Depois *"quais fluxos eu deveria ter? compare com as grandes"* + *"pode fazer tudo"*.
   - **#6956** `scripts/governance/fluxo-sistema.mjs`: DORA 4 chaves (pipeline) + retrabalho + PRs>300 + fila de US e WIP (brief MCP, proxy) + adoção de telas (route-hits, denominador da fonte única `page-path.mjs`). `cfr_cliente`, `recovery_cliente`, `flow_time_us` saem `not_yet_measured` com fonte declarada. Workflow semanal advisory + registry + selftest (26 checks). CI ficou vermelho 1× por drift do `MAQUINAS-INVENTARIO.md` (gerado) — regenerado.

## Números medidos (2026-09-07, janela 30d — comandos no docblock do medidor)

925 deploys (30,8/dia) · lead time PR p50 0,7h / p90 7,2h · CFR pipeline 6,8% · recuperação p50 22 min · retrabalho 27,8% (381/1369 commits `fix`) · PRs>300 linhas 32,1% · telas servidas em prod 18,8% (41/218) · WIP wagner 8/2 · fila 680/525/129d.
Regime: E1 67 telas sem casos · E2 186/218 scorecard · E3 54/218 E2E · E4 20/218 A11Y · E5 anchor 86,1% · E6 11 módulos sem lane Pest · E7 12 classes LC sem gate · E8 espelho SLA rc=1 · E9 crons ok · E10 1.085 Blade.

## Artefatos gerados

- `.claude/hooks/loop-fechar-check.mjs` (+177/−19) · `.test.mjs` (+89) · `.claude/regime-evolucao.json` (221) · `.claude/settings.json` (+4) · `_HOOKS-INDEX.md` (regen)
- `memory/decisions/0391-regime-de-evolucao-por-loops-blade-fora.md` (104) · `_INDEX-GENERATED.md` (regen)
- `scripts/governance/fluxo-sistema.mjs` · `.test.mjs` · `.github/workflows/fluxo-sistema.yml` · `gates-registry.json` (+1) · `governance-script-tests.yml` (+3) · `memory/reference/MAQUINAS-INVENTARIO.md` (regen)

## Persistência

git (3 PRs mergeados em `main`) · MCP via webhook (ADR + handoff + session log) · BRIEFING: nenhum módulo de produto tocado — não se aplica.

## Próximos passos pra retomar

```
node .claude/hooks/loop-fechar-check.mjs --manifest .claude/regime-evolucao.json --medir
```
O banner aponta a próxima etapa (hoje E1). `node scripts/governance/fluxo-sistema.mjs` dá a foto de fluxo.

## Lições catalogadas

- **Contagem manual vs porta viva (LC-08, quase):** contei 38 telas sem charter e 84 sem casos por `git ls-tree`; a porta viva diz 0 e 67. Corrigido antes de virar canon; a ADR e o manifesto só carregam números de porta viva. Mesma família na contagem de telas do medidor (181 à mão vs 218 da `page-path.mjs`) — consertado importando a fonte.
- **Medidor DORA da casa cego:** `agent-pr-outcomes.mjs` filtra `[CC]`; 642 de 800 PRs vêm com `[C]` → contou 3 PRs em 30d. Chip `task_3735b9ec` em execução em sessão irmã. Classe: medir a população errada e reportar como verdade (§5 2026-07-17 / LC-08).
- **`/tmp` Bash≠Node no Windows** (§5 2026-08-21) e **BOM do `gh`**: caí nos dois na 1ª sonda; scratchpad + `stripBom()` no medidor.
- **`block-memory-drift` bloqueia Edit em ADR ainda untracked** — o hook não distingue novo de canon; caminho: remover o untracked e recriar (sem override).
- **Sem gate novo** para nada disto, de propósito: as classes são semânticas; o que fecha é o próprio selftest do manifesto (etapa sem detect que mede quebra o teste).

## Pointers detalhados

- ADR 0391 (contexto medido + decisão + alternativas rejeitadas) · PR #6948 body (recibo do selftest) · PR #6956 body (tabela de fluxo + prior art) · session log `memory/sessions/2026-09-07-regime-evolucao-loops-blade-fora-fluxo-dora.md`.
