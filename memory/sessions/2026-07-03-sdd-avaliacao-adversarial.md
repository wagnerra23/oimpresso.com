---
date: "2026-07-03"
topic: "Avaliacao adversarial SDD (7 skeptics) - composto ponderado 70/100; skeptics mais duros acharam 2 achados NOVOS latentes (truncamento anchor-lint node22 na espinha GT required + inflacao _pendente_ 45.6% do coverage); burn-down P04 segue proposed = fronteira medir->consertar"
authors: [C]
type: avaliacao-adversarial-processo
metodo: "workflow sdd-avaliador-processo (8 agents, 1 skeptic por stream, verificação LIVE em worktree limpa origin/main b5278c2, não no plano)"
gatilho: "Felipe — 'como esta o plano sdd? avalie' (sessão remota claude.ai/code)"
score_composto: 70
score_medio: 68
run_id: wf_f89ada23-04c
tokens_subagentes: 1056672
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-floor-full-suite-intersecao-runs-validos
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Scorecard SDD — Avaliação Adversarial (2026-07-03)

## TL;DR

Programa SDD **no caminho, nota honesta 70/100 ponderado** (streams-alavanca FV/Fase2b ×1.8, SA/GT ×1.3). ⚠️ **Não comparar cru com o 79 de ontem** (`wf_b96eea31`): o número entre runs é não-determinístico (skeptics diferentes, profundidade diferente) — esta run rodou verificação MAIS dura (worktree limpa + reprodução local de bugs) e achou **2 achados novos latentes** que as runs anteriores não viram. O diagnóstico estrutural é o MESMO de ontem: infra de medição sólida e mordendo; a fraqueza é a transição **medir → consertar** — o burn-down P04 segue `proposed` com zero PRs.

## Scorecard por stream (verificado LIVE em origin/main b5278c2)

| Stream | Score | Peso | Status dominante | Maior risco |
|---|---|---|---|---|
| GT — Scorecard/gates | **81** | 1.3 | Feito-verificado (G1/G3/G6 mordem) | Espinha inteira funila pelo `anchor-lint --json` que trunca em node 22 (CI verde por node-20 pinado) |
| SA — Anchors spec↔código | **80** | 1.3 | Feito-verificado (85.6% coverage; refutador pegou 9 lotes) | 45.6% do "coberto" é `_pendente_`; ledger-check GT-G5 é ADVISORY |
| Fase 2b — Harness P0 | **75** | 1.8 | Feito-verificado (harness fechado) | Floor 298 travado como baseline aceito; nightly flaky congela floor stale em silêncio |
| KL — Knowledge/decay | **72** | 1.0 | Parcial | Métricas-título (ragas_real, recall_eval) = null; crons CT100 nunca rodaram (1ª exec 07-05) |
| Charters + fluxo-novo | **67** | 1.0 | Parcial | "Nasce ancorado" é template + advisory: SPEC sem campo mergeia verde; related_us 38.4% sem floor |
| FV — Full-suite/testes | **66** | 1.8 | Medição feita, correção não | Suite mede honestamente mas não conserta: P04 `proposed`, floor 298 escondido na órfã |
| Sem 4-6 — Promoções | **38** | 1.0 | Bloqueado | Cadeia R1→C2→T1→T2 travada na raiz (pcov nunca rodou + nightly não-verde) |

**Composto ponderado: 70/100** (média simples 68).

## Achados NOVOS desta run (não vistos nas runs anteriores)

1. **SPOF de versão de node na espinha GT (latente, fix barato e urgente).** `scripts/governance/anchor-lint.mjs:587` faz `process.stdout.write(157KB)` + `process.exit(0)` imediato → trunca sob pipe (**reproduzido 6/6 em node v22**: JSON cortado em 146016/156955 bytes → `JSON.parse` crash no `measureAnchors()` do `sdd-scorecard.mjs:60`). G2 aggregator, **G3 ratchet REQUIRED**, G7 snapshot e G8 brief-line funilam todos por aí. O CI está verde (925 runs) **só porque pina node-20**. Um bump de node no runner ou no host do scheduler = deadlock de merge do repo inteiro OU histórico G7 silenciosamente corrompido. O counterfactual do gate-selftest usa fixtures minúsculas — não exercita o pipe tamanho-real, exatamente onde o bug mora.
2. **Coverage inflável sem refutador obrigatório.** 369 de 810 US "cobertas" (45.6%) são sentinela `_pendente_` com justificativa OPCIONAL (gramática ADR 0273 §1); só 322 `anchored_ok` (34%) têm âncora de código real. Combinado com `ledger-check` (GT-G5) ainda ADVISORY (`continue-on-error`, sem `--enforce` — `governance-gate-umbrella.yml:52-55`), um backfill futuro pode converter `sem_campo→_pendente_` em massa pra bater 100% e destravar F3 required **sem código ancorado e sem nada required que refute**. Os 9 lotes mentirosos desta campanha foram pegos por disciplina de processo, não por gate.

## O que está sólido (feito-verificado, morde de verdade)

- **GT-G6 gate-selftest (93)** — 46/46 catracas provadas boa/ruim; required.
- **ADR 0275 (95) + GT-G3 ratchet required (90/80)** — meta-catraca hard, fail-closed P14, auto-testada.
- **A1-A4 anchors (90-92)** — lint morde (fixture bad → exit 1); placeholder=0, dead=0 no main.
- **F1 junit-tripwire (90) + F2 composite MySQL required (92) + F3 nightly floor (88)** — floor real 298 transportado à órfã `governance/nightly-floor` (fresca, tip 07-03) e lido pelo CI.
- **KL catraca anti-ghost (90) + codemod (86)** — injeção de fantasma → FAIL provado live.
- **Fase 2b Frentes A.1/B/C (84-88)** — harness fechado com contrato mordente; A.2 revertido por prova empírica (auto-correção honesta).

## O que falta de ondas

| Onda | Rodou | Resta |
|---|---|---|
| Sem 0 (fundação KL+GT) | ~95% | Composta v1 nunca emitida (5 métricas not_yet_measured); fila KL nova (tombstone portas-fantasma) |
| Sem 1-2 (anchors + medição FV + harness) | ~85% | Promover ledger-check a `--enforce` required; F3 full-tree (136 `sem_campo` em 19 módulos); higiene bookkeeping (P10 `proposed`, §Status ADR 0273) |
| Sem 2-4 (CORREÇÃO) | ~10% (só diagnóstico) | **TUDO**: burn-down P04 (floor 298→0), triage A-F per-arquivo, E3 lotes destilados (skim Wagner pendente), trilhas C/D medindo (crons 07-05 futuro) |
| Sem 4-6 (promoções) | GT-G3 pronto; SA-A10 parcial (flip via override, 85.6%≠100%) | R1 (7 nightlies verdes + p95), C2 (pcov + 3 medições), T1, T2 |

**Caminho crítico** (serial, relógio real): isolar a conexão MySQL compartilhada (57% das falhas = cascata de 1 causa, não 298 bugs) → burn-down P04 por bucket (tests-raiz 89 / OficinaAuto 29 / PaymentGateway 20…) → 7 nightlies verdes → R1 → pcov CT100 → C2 → T1 → T2.

## Top 5 riscos sistêmicos

1. **SPOF node na espinha GT** (achado novo acima) — fix trivial: flush antes do exit no anchor-lint:587.
2. **Coverage inflável + refutador advisory** (achado novo acima) — promover ledger-check a `--enforce`.
3. **O floor mede, não conserta — e o número real fica escondido.** Ratchet só barra regressão >298; floor congelado passa verde (nightly morreu 02/03-jul → 298 vs 298). Scorecard do main mostra full_suite=null; o 298 vivo só existe na órfã. Sem P04, R1 é matematicamente inalcançável.
4. **"Feito que depende do que nunca rodou" (KL).** ragas_real_uptime e recall_eval_violations = null; cadeia cron CT100 com 1ª execução 2026-07-05 e instalação inverificável; todos os gates KL advisory → falha silenciosa = null indefinido.
5. **Fluxo-novo não é garantido mecanicamente.** anchor-lint tolera `sem_campo` (verde); memory-schema-gate e charter-us-lint não são required; grace-period sem data-flip. related_us parado em 38.4% sem floor armado.

## Veredito

**No caminho — mas o programa está na transição perigosa "medir → consertar" e parou na fronteira.** A infraestrutura de honestidade é real e rara (46/46 catracas mordem, refutador reprovou 9 lotes, reversão empírica A.2, floor feio transportado sem maquiagem). O que não existe é a correção: P04 `proposed` com zero PRs, e as métricas de decaimento KL nunca executaram em prod. **Alavanca nº 1:** atacar o isolamento da conexão MySQL compartilhada (desaba ~57% das falhas de uma vez). **Nº 2 (mesma semana, custo trivial):** corrigir o flush do anchor-lint:587 antes que um bump de node congele o merge-gate required do repo inteiro + promover ledger-check a `--enforce`.

## Referências

- Run: `wf_f89ada23-04c` (8 agents — 7 skeptics Opus + síntese Fable, ~1.06M tokens, 231 tool calls, ~40min).
- Run anterior: [`2026-07-02-sdd-avaliacao-adversarial-pos-balde-d.md`](2026-07-02-sdd-avaliacao-adversarial-pos-balde-d.md) (composto 79, `wf_b96eea31`) — trajetória 60→67→76→79→**70** (queda reflete skeptics mais duros + 2 achados novos, não regressão do repo; o floor entre runs é a leitura estrutural, não o número).
- Skill `sdd-avaliar` · trio imunológico SDD (avaliador + refutador G5 + reprodução).
