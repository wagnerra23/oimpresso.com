---
roadmap_item: MV2
slug: cron-metabolismo
onda: 0
status: executed
executed_at: "2026-07-05"
depende_de: [MV1]
destrava: [MV3]
related_adrs: [256, 264, 314]
esforco_estimado: "0.5d codável (seletor + workflow; orquestra MV1 + padrão auto-PR existente)"
---
# MV2 · Cron-metabolismo (stream MV — Módulo Vivo)

> **Origem:** aprovação Wagner 2026-07-05 ("ok vai aprovado") na sequência do MV1.
> Contrato: [arte 2026-07-05](../../../sessions/2026-07-05-arte-maquina-governanca-telas.md) §3.3 (loop a→g) + §3.4 (batimento) + §3.6 (paradas).

## Problema (2-3 frases)
Com a espinha dorsal (MV1) o estado da frota é legível, mas continua sendo Wagner quem
olha a fila e dispara trabalho tela a tela. Falta o batimento: um processo noturno que
LÊ os sinais vitais, seleciona o próximo lote respeitando criticidade/budget, e o coloca
na mesa do Wagner como decisão de 1 clique — sem jamais criar task ou mergear sozinho.

## O que foi entregue (verificável)
- **`scripts/qa/mv-metabolismo.mjs`** — seletor determinístico, zero LLM: lê
  `vital-signs.json` → aplica na ordem (a) **gate pendente não empilha** (batch
  `proposto|aprovado` existente → sai sem gerar), (b) **verde+fresca pula** (nota ≥80 +
  casos + charter + não-stale), (c) **batimento por classe** (dinheiro 1d · vertical 3d ·
  resto 7d desde a última aparição do módulo em batch), (d) **budget**
  (`MV_BATCH_MAX_TELAS`, default 5) → escreve `memory/governance/mv-batches/YYYY-MM-DD.md`
  (`status: proposto`) com ação proposta por tela derivada do gap (contrato-first:
  sem-prontuário → ciclo completo; sem-casos → casos.md + Pest ancorado; stale → re-grade).
- **`scripts/qa/mv-metabolismo.test.mjs`** — 19 checks ancorados no contrato (§3.4/§3.6),
  incl. counterfactuals: batch pendente bloqueia; verde SEM casos NUNCA pula (contrato-first);
  verde completa mas stale NÃO pula (frescor manda).
- **`.github/workflows/mv-metabolismo.yml`** — nightly 06:30 BRT: selftests hard →
  vital-signs (snapshot + trend) → metabolismo → auto-PR label `mv-batch` (padrão
  peter-evans + `COWORK_BOT_PAT` do sdd-scorecard-publish) **SEM auto-merge — a omissão
  é a feature**: merge do Wagner = batch aprovado; fechar = rejeitado; PR aberto = noite
  seguinte pula (dupla checagem: label no GitHub + status no filesystem).
- **Enriquecimento aditivo do MV1:** fila do snapshot agora top-50 com
  `casos`/`charter`/`idade` (o seletor precisa; selftest MV1 inalterado e verde).

## Ciclo completo (quem faz o quê)
| Etapa | Executor | Gate humano? |
|---|---|---|
| (a) sinais vitais + (b) seleção | workflow nightly (determinístico) | não |
| (c) proposta de batch | auto-PR `mv-batch` | **SIM — merge Wagner = aprova · fechar = rejeita** |
| (d) execução | sessão pós-merge: audit-to-backlog cria tasks MCP + screen-qa por tela (sessão limpa/tela) | gate visual Wagner por tela (.tsx) |
| (e) registro | scorecards atualizados + batch `status: executado` | não (catracas existentes) |
| (f)(g) próxima volta | nightly detecta executado → novo batimento | não |

## DoD (counterfactual)
- `node scripts/qa/mv-metabolismo.test.mjs` → exit 0 (19 checks).
- Counterfactual anti-fila-fantasma PROVADO no run real 2026-07-05: 1º run gerou o batch
  `2026-07-06.md` (5 telas: Impostos+ProvaViva dinheiro sem prontuário no topo); 2º run
  imediato → `⏸ gate humano pendente, não empilho` exit 0.
- NÃO é gate CI (lei 0314): workflow é scheduled/dispatch, nunca em required; nunca
  bloqueia PR de ninguém.

## Primeiro batch real (dogfood)
`memory/governance/mv-batches/2026-07-06.md` viaja NESTE PR — o merge do PR do MV2 já é
a primeira aprovação de batch do metabolismo. Execução: próxima sessão.

## Residual honesto
- `aprovado`≠`proposto` não é distinguido automaticamente no merge (arquivo entra em main
  como `proposto`; a semântica "chegou via merge = aprovado" está documentada no batch e
  o bloqueio cobre os dois estados). Se incomodar, MV2.1: action que troca o status no merge.
- Execução (etapa d) ainda é sessão manual pós-merge — automatizar spawn de sessão é
  decisão separada (custo/token), não entra sem OK Wagner.
