---
date: "2026-08-05"
time: "0949 BRT"
slug: "maquinas-que-nao-avisavam"
tldr: "Pedido virou auditoria: 5 máquinas de governança existiam e não avisavam nada (uma órfã, uma cega por escopo, uma com premissa falsa, uma delegação sem destinatário, um manifesto stale). 5 PRs mergeados, ZERO máquina nova criada. A partir de agora o SessionStart mostra sozinho quantos hooks entregaram e quantos prazos de gate venceram."
decided_by: [W]
cycle: null
prs: [5294, 5296, 5299, 5301, 5303]
us: []
next_steps:
  - "16 gates advisory com promote_by VENCIDO agora aparecem no memory-health — cada um pede decisão [W]: promover (emenda 0314 + flip), estender COM razão, ou podar"
  - "34 hooks não-observáveis (mensagem sem [tag]) — forward-only: cada um ganha tag quando for tocado por trabalho real"
  - "12 hooks wired com ZERO entrega em 14d — separar 'condição nunca ocorreu' de 'não morde mais' exige bite-test com payload real"
  - "10 scripts órfãos restantes — triagem humana por design (~67% de precisão do melhor critério automático)"
related_adrs: ["0130-handoff-append-only-mcp-first", "0298-teto-de-governanca-anti-proliferacao-gates", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0344-two-strikes-cobre-processo"]
---

# Handoff 2026-08-05 09:49 BRT — as máquinas existiam e não avisavam

## TL;DR

[W] pediu documentação das máquinas do projeto. A documentação **já existia e estava fresca**
(`MAQUINAS-INVENTARIO`, 455 máquinas, derivado). O que não existia era **sinal de que elas
funcionam** — e a sessão virou auditoria disso. Cinco defeitos medidos, cinco PRs mergeados,
**zero máquina nova criada**: a superfície do processo não mudou, só ficou verificável.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 08:10 | Pedido: "documentar as máquinas". Medição mostra que o inventário derivado já existe (455, `--check` verde) |
| 08:25 | Triagem de 8 "órfãos" — 6 eram legítimos; meu corpus de busca era estreito demais |
| 08:40 | Achado: `perf-static-guard` (ratchet com baseline) sem invocador desde 07-05; 1 regressão entrou no período |
| 08:55 | Causa: o detector de órfão varria só `scripts/governance/`. PR #5294 amplia p/ `scripts/**` |
| 09:05 | [W]: *"minhas máquinas existem e eu não consigo saber se estão funcionando"* |
| 09:12 | `hook-bites` (dead man's switch dos hooks) estava **ele próprio órfão** — registrado como morto em 07-27 e ainda morto |
| 09:20 | PR #5296: `--heartbeat` + wiring `SessionStart` (custo medido 3,2s, throttle 20h) |
| 09:25 | Achado do `promote_by`: Check M delega ao ZELADOR; ZELADOR vivo, com **0 menções** a `promote_by`. 16 prazos vencidos |
| 09:35 | PR #5299: inventário sai de `governance/` (não varrido) para `memory/reference/` (acervo → `/documentacao`) |
| 09:42 | PR #5300 (subtração pura) derruba o `brl-scan` — guarda anti-vácuo com premissa falsa. PR #5301 conserta |
| 09:47 | PR #5303 substitui o #5300 (force-push barrado pelo `block-destructive`, corretamente) |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `scripts/governance/selftest-registry-check.mjs` | ✅ | escopo `scripts/governance/` → `scripts/**`; `tests/` entra em PREFIXOS_INVOCADOR; +5 asserts (36/36) |
| `scripts/governance/hook-bites.mjs` | ✅ | modo `--heartbeat` + `--throttle-horas`; wirado em `SessionStart` |
| `scripts/governance/memory-health.mjs` | ✅ | warn `[M] advisory-prazo-vencido` (datado; o **fail** segue determinístico) |
| `scripts/governance/ZELADOR.md` | ✅ | passo do `promote_by` escrito no charter — a delegação tinha destinatário que não sabia |
| `scripts/governance/brl-scan-diff.mjs` | ✅ | subtração pura ≠ instrumento cego; E2E em sandbox git (18/18) |
| `memory/reference/MAQUINAS-INVENTARIO.md` | ✅ | movido de `governance/`; frontmatter emitido pelo gerador; tombstone no path antigo |
| `scripts/deploy-ia-pageheader.sh`, `deploy-cliente-drawer-wave-z-2.sh` | ✅ removidos | deploys one-shot de maio/2026, já executados |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#5294](https://github.com/wagnerra23/oimpresso.com/pull/5294) | merged | radar de script órfão: `scripts/governance/` → `scripts/**` |
| [#5296](https://github.com/wagnerra23/oimpresso.com/pull/5296) | merged | liga o dead man's switch + fecha a delegação órfã do `promote_by` |
| [#5299](https://github.com/wagnerra23/oimpresso.com/pull/5299) | merged | inventário entra no acervo → aparece em `/documentacao` |
| [#5301](https://github.com/wagnerra23/oimpresso.com/pull/5301) | merged | `brl-scan` não confunde subtração pura com instrumento cego |
| [#5303](https://github.com/wagnerra23/oimpresso.com/pull/5303) | merged | aposenta 2 deploys one-shot (substitui #5300, fechado) |

## Decisões tomadas

| Pergunta | Decisão [W] | Justificativa | Referência |
|---|---|---|---|
| Mergear a leva | "merge e pode arrumar" | escopo autorizado ponta-a-ponta | R10/R11 |
| Criar índice novo de máquinas? | **não** — usar o dono existente | §5 já matou "mapa/painel/índice paralelo" 2× (07-23, 07-25) | proibicoes §5 |
| `promote_by` vencido vira fail? | **não** — warn datado | fail datado bloquearia merge por dívida pré-existente (16 de uma vez) e acordaria o legado | §5 2026-07-12 |
| Force-push pra rebasear o #5300? | **não feito** — hook barrou | `block-destructive` exige autorização explícita; usei branch nova | — |

## Bloqueios / pendências

- [ ] **16 gates advisory com `promote_by` vencido** (mais velho há 20d) — owner: [W]; cada um pede 1 decisão
- [ ] **34 hooks não-observáveis** — forward-only, sem owner fixo
- [ ] **12 hooks com zero entrega em 14d** — precisa bite-test com payload real
- [ ] **10 scripts órfãos** — triagem humana (report-only por design)

## Próximos passos (ordem)

1. [W] decide os 16 `promote_by` vencidos — o `memory-health` agora os lista ordenados por dias
2. Bite-test dos 12 hooks com zero entrega (separar condição-nunca-ocorreu de não-morde-mais)
3. Tag `[nome]` nos hooks não-observáveis, oportunisticamente
4. Confirmar em prod que o inventário apareceu em `/documentacao` após o webhook sincronizar

## Estado MCP no momento do fechamento

> **Obrigatório (ADR 0130 §6)** — snapshot do que as tools devolveram, NÃO promessa.

### cycles-active
```
Nenhum cycle ATIVO em COPI. Use `cycles-list project:COPI` para ver todos.
```

### my-work
```
Tasks ativas — @wagner · Total: 10 · todas em REVIEW
US-COPI-123 p0 · US-TR-309 p1 · US-TR-310 p1 · US-PG-008 p1 · US-PROD-027 p1
US-INFRA-023 p1 · US-TR-305 p1 · US-TR-306 p1 · US-TR-311 p2 · US-KB-002 p2
(nenhuma tocada nesta sessão — o trabalho foi de governança, fora do backlog de produto)
```

### sessions-recent limit:3
```
N/A — tool não consultada (MCP reconectou no meio da sessão; cycles-active e my-work
rodaram e estão acima). Contexto de sessões irmãs obtido por `ls memory/sessions/`:
2026-08-05-ancora-medivel-funil-e-teste-que-nao-provava.md (sessão paralela, manhã).
```

### decisions-search since:2026-08-03
```
N/A — não consultada. Nenhuma ADR foi criada nem alterada nesta sessão (o trabalho
foi conserto de máquina existente, sob ADRs já aceitas: 0298, 0256, 0314, 0344).
```

### whats-active (sessão paralela)
```
Detectada pelo `dup-detector`, não pelo whats-active: PR #5298 (agent testador-de-maquinas
+ painel alcançável) tocava `governance/MAQUINAS-INVENTARIO.md`, o mesmo arquivo que o
#5299 move. Trabalhos COMPLEMENTARES — avisei no #5298 e registrei Dedup-ack no #5299.
O #5298 mergeou depois e regenerou no path novo corretamente (verificado: 456 máquinas,
frontmatter intacto, tombstone preservado).
```

## O que esta sessão ensinou (para o próximo agente)

**O padrão único por trás dos 5 defeitos:** o sistema verifica que as coisas **existem**, não que
**funcionam**. Verde e silêncio são indistinguíveis de morto.

**Três erros meus, todos da mesma classe (LC-08 — medir pela fonte errada):**
1. `git cat-file -e origin/main:.claude/…` → "arquivo ausente" (era MSYS mangling do `:`)
2. `grep glob(` no indexador → "memory/reference fora do acervo" (entra por `coletarRecursivo`)
3. `git show origin/main:.claude/settings.json` → "o wiring sumiu" (MSYS de novo)

Nos três o que salvou foi **desconfiar de resultado implausível e remedir** — não rigor prévio.

**Sobre churn de governança** (pergunta do [W]: *"como confiar num processo que muda sempre?"*):
medido na janela de 10 commits desta sessão — workflows **+0**, hooks **+0**, skills **+0**,
scripts `.mjs` **+0**, agents +1 (de outra sessão). Nenhuma máquina nova nasceu daqui. Mudou
**comportamento defeituoso**, não desenho.

## Referências

- Session log: [2026-08-05-maquinas-que-existiam-e-nao-avisavam.md](../sessions/2026-08-05-maquinas-que-existiam-e-nao-avisavam.md)
- Handoff anterior: [2026-08-05-0746-ancora-medivel-funil-e-o-teste-que-nao-provava.md](2026-08-05-0746-ancora-medivel-funil-e-o-teste-que-nao-provava.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
