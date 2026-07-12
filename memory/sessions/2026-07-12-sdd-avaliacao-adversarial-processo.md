---
date: 2026-07-12
topic: "Avaliação adversarial do programa SDD (7 streams) — composto 69/100 + gargalo-mãe OOM da nightly CT100"
authors: [W, C]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0231-processo-trabalho-canonico-especialista-por-area]
---

# Sessão 2026-07-12 — Avaliação adversarial do processo SDD (composto 69/100)

**Worktree de build:** `sdd-oom-fix` (branch `claude/sdd-avaliacao-2026-07-12`, partiu de `origin/main` @ e8d0b71ff5). A avaliação em si rodou a partir do checkout `quirky-mclaren-cf1013` (stale −5074), mas **todos os 7 skeptics verificaram o estado REAL em `origin/main` live** (1f4fdc98) via `git show`/`gh api` — não o working tree stale, não o plano.

## O que foi feito

Wagner: **"sdd-avaliar"** → rodei o workflow canônico `sdd-avaliador-processo` (skill `sdd-avaliar`). 1 skeptic adversarial por stream (7 streams SA/FV/KL/GT/Charters/Fase2b/Promoções), cada um medindo LIVE (rodou anchor-lint, sdd-scorecard, foundation-ratchet, gate-selftest; leu branch protection via `gh api`; mediu o floor no CT100), + síntese.

Primeira execução (`wf_706d09cc-6f9`): o stream SA morreu com `API Error: Connection closed mid-response` (falha transitória de rede, não do repo). **Resumido do cache** (`resumeFromRunId`) — os 6 streams válidos voltaram instantâneos, só SA re-rodou. 2ª execução: 8 agentes, 0 erros, ~1.12M tokens.

## Scorecard — composto **69/100**

| Stream | Score | Peso | Estado | Maior risco |
|---|---|---|---|---|
| **GT** — Governance scorecard | 86 | ×1.3 | Sólido, morde | Watchdog mede data-do-commit da órfã, não idade-do-dado → floor stale passa verde |
| **SA** — Anchors spec↔código | 85 | ×1.3 | Sólido, morde | Prova existência, não corretude; gate "verde" (`--junit`) dormente |
| **Charters + fluxo-novo** | 78 | ×1.0 | Corpo required, frontmatter advisory | `related_us` só 42.4%; schema fora dos required |
| **KL** — Knowledge/decay | 72 | ×1.0 | Ghost sólido, decay de-forma | Metade decay não-enforçada; `distiller_freshness` passa trivialmente |
| **Fase 2b** — P0 harness | 72 | ×1.8 | Harness real, desfecho sufocado | Frentes A/B provadas live, mas nightly morre por OOM |
| **FV** — Full-suite/testes | 50 | ×1.8 | Write-side quebrado | junit.xml = 0 bytes em 9/10 noites; floor congelado 291 há 6 dias |
| **Promoções required** (Sem 4-6) | 42 | ×1.0 | Bloqueada a montante | OOM estrangula 4 de 6 promoções; R1 inatingível |

**Composto ponderado = 69/100** (Σ 633,9 ÷ Σ pesos 9,2).

## O gargalo-mãe (cadeia de falha, verificada live)

```
OOM mid-suite (Pest ~53%, memory_limit 4G ainda insuficiente em run 20260712)
 → junit.xml 0-byte em 9 de 10 noites (1 crash zera a noite inteira)
  → floor congelado 291 há 6 dias (computed_at 20260706)
   → P04 burn-down impossível de MEDIR
    → R1 (7 nightlies verdes) inatingível → C2 nunca fecha → P13 (full_suite required) travado
```

Sintomas do run 20260712 (pest exit 2): OOM + SQLSTATE 1062 Duplicate entry `payment_gateway_credentials` (pg_cred_biz_gw_amb_unique) + "Base table not found" + 57% dos fails = cascata de isolamento (593 QueryException) numa conexão MySQL compartilhada, NÃO 291 bugs independentes.

As 2 únicas promoções que landaram (SA-A10, GT-G3) são exatamente as que **não** exigiam suíte verde.

## Top 5 riscos sistêmicos

1. **Nightly morta servida como métrica viva.** Floor congelado 291 há 6d. GT-G3 (required) checa valor ≤298, não frescor (P14 by-design); GT-G4 (staleness) é advisory-perene E mede a data do commit do tip da órfã (que avança todo dia com `[skip ci]`), não o `computed_at`. **"Verde não prova frescor."**
2. **Gate de corretude dormente.** Nenhum workflow passa `--junit`/`--check-verde`. Uma US pode dizer "implementada + tem teste que cobre" com o teste RED/skipped e nenhum required morde. Prova existência+rastreabilidade, não corretude.
3. **A dívida real é isenta.** Required é diff-only com 655 entradas grandfathered; `req_sem_covering_test=391`, `req_sem_aceite=262`, 3 dead anchors vivos em main (`.ps1`→`.mjs`, SPEC não tocado).
4. **OOM = ponto único de falha** que trava R1, C2 e T1 ao mesmo tempo. 57% = cascata de isolamento numa conexão MySQL compartilhada, não bugs independentes.
5. **Decay medido-mas-desarmado.** `distiller_freshness` colapsa pra 0 no clone efêmero; `ragas_real_uptime=100` sobre 1 amostra (armed=false).

## Veredito

**No caminho certo, mas com o motor da métrica-mãe quebrado.** O plumbing de governança do SDD é genuinamente estado-da-arte e honesto — gate-selftest 46/46 mordem pelo motivo certo, P14 fail-closed provado live, ledger com 48 entradas reais, ratchet required que barra regressão de floor, e o próprio programa se auto-refutou (KL-E1 "feito" que 3 céticos adversariais derrubaram). O que falha é a **FONTE** que ele governa: a nightly CT100 (write-side de toda a FV) está morta há 5+ noites.

**Maior alavanca, única e clara:** consertar o **OOM mid-suite do Pest** (memory limit + isolamento DB da fatia de 57% + sobrevivência do junit por sharding) pro junit voltar a materializar TODA noite. Conserte a fonte → a onda 4-6 destrava sozinha; deixe morta → todo o resto do plumbing governa um número congelado.

## Follow-up desta sessão

Wagner: **"Sim, e planeje e construa"** + **"Todos em paralelo tem muito tokens consuma o máximo"** → lancei workflow `sdd-oom-nightly-fix-plan` (13 agentes: 6 especialistas por vetor de falha × verificação adversarial + consolidação) pra projetar o fix ancorado no código real. Vetores: V1 sobrevivência do junit (sharding), V2 isolamento DB, V3 OOM root, V4 coverage split, V5 watchdog de frescor, V6 gate de corretude. Build subsequente registrado em handoff próprio.

## Provas (não narração)

- Runs workflow: `wf_706d09cc-6f9` (1ª, SA caiu por rede) → resume → 2ª completa (8 ag, 0 erro, 1.122M tokens, 233 tool calls).
- Cada stream cita `path:linha` + `gh api branches/main/protection` + medição LIVE dos scripts. Detalhe integral no output do workflow (transcript dir da sessão).
