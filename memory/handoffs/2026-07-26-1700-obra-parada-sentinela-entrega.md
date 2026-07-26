---
title: "Obra parada — sentinela de entrega, fronteira de módulo e o loop de charter destravado"
date: "2026-07-26"
type: handoff
authority: history
lifecycle: ativo
owners: [W]
related_adrs:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0317-watchdog-crons-governanca
pii: false
---

# Handoff 2026-07-26 17:00 — a classe "obra parada"

> Session log completo: [`2026-07-26-fronteira-modulo-e-obra-parada.md`](../sessions/2026-07-26-fronteira-modulo-e-obra-parada.md)

## Onde parou

**[PR #4795](https://github.com/wagnerra23/oimpresso.com/pull/4795) — MERGEADO.** Fronteira de módulo: Whatsapp ganhou `owner`/`trust_required` (era 1 de 36 sem), 4 tabelas de dono duplo resolvidas (0 conflitos no grafo), rubrica `cross_cutting_infra` criada.

**[PR #4798](https://github.com/wagnerra23/oimpresso.com/pull/4798) — ABERTO, aguardando merge.** 6 commits. Contém a máquina nova + o destravamento do loop de charter.

## A pergunta que originou tudo

[W]: *"meu principal problema não é resolver isso, é ter estrutura para que isso se resolva sem mim"* — e depois: *"por que eu tenho que ficar perguntando isso, me perdendo em labirintos intermináveis, se já tem estrutura montada com garantias reais?"*

A resposta medida: as 34 catracas required **só rodam sobre diff**, e coisa parada não tem diff. O sistema inteiro vigia *o que entra* — nada vigia *o que já entrou e parou*.

## O que foi construído

**Eixo 2 do `cron-watchdog`** — mede ENTREGA, não só liveness. O cron do caso (`governance:scorecard-snapshot --alert`, 07:00) estava **vivo** enquanto os 5 scorecards congelavam há 71 dias; liveness dava verde. Agora mede a consequência: artefato de estado versionado que envelheceu (limite 60d, FP=0 medido).

**Flag no Daily Brief** — `🟠 Obra parada: N artefato(s) sem atualizar — pior: <arquivo> (Nd)`. Chega ao [W] sem ele perguntar.

**`selftest-registry-check --scripts`** — o guard estava verde com 0 testes órfãos enquanto **12 de 88 scripts** não têm invocador. Report-only de propósito: o melhor critério automático deu ~67% de precisão (CLI manual por design é legítimo aqui).

## Estado do CI no #4798

| | |
|---|---|
| ✅ `SUPERFICIE.md == árvore` | passou após regenerar |
| ✅ `module-grades-gate` | all clear, 0 regressões |
| ❌ `crons de governança vivos?` | **vermelho DE PROPÓSITO** — advisory, 5 scorecards parados. É o alarme instalado por este PR |
| ⚠️ `charter related_us join` | deve voltar a verde após o commit de reversão |

## Decisões pendentes [W]

1. **v3 × v4 (Governance).** Medição diz **aposentar o v4**: premissa refutada (`cross_cutting_infra` tem a MAIOR média, 82,1; e gabarita `client_real` a 100%, a dimensão que a rubrica v4 corta), simulação move **−2/−2/0**, `gradeV4()` tem **zero call sites**. Mas blast radius: tela `/admin/governance/v4`, 3 Requests, ~10 testes, **ADRs 0160/0161/0163 `accepted`** (append-only → exige ADR de supersede), 1 cron. Não é deleção, é deprecação faseada.
2. **Os 5 scorecards parados.** Até serem atualizados ou o v4 aposentado, o advisory fica vermelho em todo PR. Alarme aceso muito tempo é alarme que se aprende a ignorar.
3. **`related_us` das 4 telas** (`Sells/Drafts`, `Sells/Edit`, `Sells/Show`, `Site/Home`) — sem isso o promotor não consegue promovê-las (ver colisão abaixo). Alternativa: ensinar o promotor a pular charter sem `related_us`.
4. **Agendar `route-hits:export`** — hoje manual **por design declarado** no Kernel (*"no host de prod, igual governance:prod-flags"*). Mudar é decisão de design.
5. **`meta_governance.yaml`** órfão (0 módulos declaram). Deletar exige ajustar `Wave27GovernanceSaturateTest` no mesmo PR.
6. **12 scripts sem invocador** — o report-only lista; decidir caso a caso (ligar ou aposentar com lápide).

## Achado de interação entre mecanismos

`charter-promote-signal --apply` **toca** o arquivo para flipar o status. O `charter-us-lint` é diff-aware com regra no-new-lie: *charter tocado precisa declarar `related_us`*. Promover automaticamente 4 charters sem `related_us` avermelhou o lint.

Os dois estão certos isoladamente; ninguém previu que **promoção automática conta como toque**. Revertidos os 4 que colidem, mantidos os 2 `kb` (que têm `related_us`). Não preenchi `related_us` para calar o lint — qual US uma tela atende é decisão de escopo, e inventar no charter é pior que ausente (§5).

## O loop de charter — o que mudou

Diagnóstico corrigido **duas vezes** por medição:

- Supus `ROUTE_HITS_ENABLED=false` (o default do config). **Em prod é `true`**, `APP_ENV="live"`.
- O gargalo era o transporte: dry-run em prod deu **32 rotas / 11 pages** (dado até 25/07) contra **10 / 2** no JSON commitado (11/07). A coleta funcionava; o `route-hits:export` é que não roda.

JSON atualizado no PR. Com combustível fresco o promotor foi de ~2 para **6 promovíveis**. Os 179 drafts restantes **não são dívida** — são telas sem hit em prod.

## Estado MCP no fechamento

- `cycles-active` → **nenhum cycle ativo** em COPI
- `my-work` → **6 tasks em REVIEW**: US-TR-309, US-TR-310, US-PG-008, US-PROD-027, US-TR-305, US-TR-306
- `decisions-search` → ADRs 0160/0161/0163 (`accepted`) são a trava do item 1 acima

## Para quem retomar

O eixo 2 do watchdog vai gritar todo dia até os 5 scorecards serem resolvidos. **Isso é o desenho, não bug** — mas se o alarme ficar aceso semanas, ele perde função. O item 1 (v3 × v4) é o que apaga o alarme pela raiz.
