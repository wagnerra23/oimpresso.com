---
date: "2026-06-21"
topic: "Avaliação adversarial do processo SDD (7 skeptics) — régua de enforcement; composto 60/100"
authors: [C]
type: avaliacao-adversarial-processo
metodo: "workflow sdd-avaliador-processo (8 agents Opus, 1 skeptic por stream, verificação LIVE em git+gh+CT100, não no plano)"
gatilho: "Wagner — 'qual seria a régua correta para avaliar o processo?' → rodar /sdd-avaliar"
score_composto: 60
score_medio: 57
adrs_citados: [0273, 0275, 0279, 0270, 0271, 0094, 0093, 0062, 0226]
run_id: wf_f19da833-977
tokens_subagentes: 826730
---

# Scorecard SDD — Avaliação Adversarial (2026-06-21)

**Composto ponderado: 60/100** · média simples 57 · pesos: FV/Fase2b ×1.8, SA/GT ×1.3, demais ×1.0

> Lente única do veredito: **a infra de MEDIÇÃO é real e honesta; a GOVERNANÇA não morde.**
> 0 dos ~17-18 required checks são SDD. O sistema imunológico está ligado em **modo observação**.

Origem: Wagner perguntou *"qual seria a régua correta para avaliar o processo?"* — a régua é o **teste contrafactual** (se um funcionário tentar quebrar uma decisão já tomada, o processo barra sozinho?). Este avaliador é a régua virada em número. Disparado via skill `sdd-avaliar` → workflow `sdd-avaliador-processo`.

## 1) Scorecard por stream

| Stream | Score | Peso | Status macro | Maior risco sistêmico |
|---|---:|:---:|---|---|
| **FV — Full-suite / testes** | **76** | 1.8 | Mede honesto, não governa | Floor=295 chega ao scorecard só em CI advisory; baseline `armed=false`/`valid_measurements=0` apesar de ~15 runs reais → PR pode piorar a suite sem nada morder |
| **Fase 2b — P0 harness (US-GOV-018)** | **71** | 1.8 | Harness bom, floor é miragem | Floor vivo só na branch órfã (295); scorecard em main mente `not_yet_measured`; "redução 1514→274" mistura unidades + absorve ~600 skipped (não consertados) |
| **Charters + fluxo-novo** | **68** | 1.0 | 4 peças landadas, 0 exercidas | Ilusão de fluxo governado: `v1_files:0`, coverage 7%, todos gates advisory → SPEC novo pode nascer sem anchor e nada barra. Metade `us:` re-escopada sem ADR |
| **GT — Governance scorecard** | **66** | 1.3 | Fundações sólidas, elo final aberto | G7 história + G8 brief são ILUSÓRIOS (migration 0 rows em prod); 4/12 métricas vivas; composta não calcula; baseline-tamper-guard não cobre o baseline do próprio scorecard |
| **SA — Anchors spec↔código** | **52** | 1.3 | Fundação feita, conteúdo travado | Métrica de FORMA vendida como progresso + número stale (7.5% commitado vs 7% live); 15 `anchored_dead` (mentiras detectadas) diferidos sem owner/PR |
| **KL — Knowledge / decay** | **52** | 1.0 | Mede bem, não governa | Buraco grandfather do baseline anti-ghost ABERTO (#2848 ghost 14→16 entrou verde); renames Classe A catalogados mas NÃO aplicados; 2 trilhas de decay estruturalmente incapazes de falhar |
| **Sem 4-6 — promoções a required** | **15** | 1.0 | BLOQUEADO a montante | Promoção ilusória por elo quebrado: write-side floor publica na branch órfã, read-side lê de main onde nunca aterrissa → métrica-mãe NUNCA chega ao governador |

## 2) Caminho crítico (1 fio condutor)

`reconectar read-side do floor (ler da branch órfã OU aterrissar o JSON em main)` → `armar baseline full_suite (valid_measurements 0→3)` → `burn-down quarentena/erros até nightly VERDE` → `migrate prod mcp_sdd_scorecard_history (G7→G8)` → `promover GT-G3 a required` (único com infra pronta).

**Tudo a jusante depende do elo do floor; nada deve ser promovido antes.** Consertar o read-side do floor é **1 mudança** que destrava simultaneamente o armamento do baseline, a composta, G7/G8 e qualquer promoção — único nó cuja correção propaga para 4 streams.

## 3) TOP 5 riscos sistêmicos

1. **Elo MEDIR→GOVERNAR quebrado e silencioso (risco-mãe).** Write-side faz `push -f` p/ branch órfã `governance/nightly-floor`; read-side `sdd-scorecard.mjs:117` lê do tree do **main**, onde nunca aterrissa → `not_yet_measured` perene apesar de 15 nightlies reais.
2. **Métrica de FORMA vendida como progresso + número stale commitado.** `anchor_coverage=7.5%` commitado sobre snapshot velho; live = 7%; "coverage" conta `_pendente_` (tela não construída) como coberta.
3. **Buraco grandfather do baseline anti-ghost — vetor #2848 aberto.** `baseline-tamper-guard` cobre só `.memory-health-baseline.json`, não `knowledge-ghosts-baseline/**`. Já materializado: ghost 14→16 entrou verde.
4. **Entregas "leitura sem esforço" ilusórias (G7+G8).** Migration `mcp_sdd_scorecard_history` nunca aplicada (0 rows) → brief nunca mostra a linha SDD; composta não calcula.
5. **Gates estruturalmente incapazes de falhar (decay C + D).** RAGAS `baseline=0.0` → tautologia; recall só `--mode=mock`; dependem de CT100/secret que nunca rodaram.

## 4) Veredito

**No caminho, mas com o último terço inteiro por construir — e risco real de declarar "feito" o que só está "medido".** A Semana 0 fechou com qualidade rara: a infra de detecção é honesta (gate-selftest 10/10 morde pelo motivo certo, anchor-lint usa `existsSync` e pega 15 mentiras reais, nightly CT100 7 noites, floor por interseção). **O problema é que está ligado em modo observação:** 0 dos ~18 required são SDD, o floor vivo (295) só existe na branch órfã enquanto o scorecard em `main` mente `not_yet_measured`, o baseline nunca foi armado, e G7/G8 são ilusórios (migration 0 rows em prod).

**Nota honesta do processo: 60/100** — fundações de 90, conteúdo+governança de 30.

**NÃO promover nada hoje:** flipar R1 sobre suite de ~1100 falhas/noite, ou armar baseline sobre número-fantasma, transformaria o melhor ativo do programa (gates que mordem) em `main` required-vermelho instantâneo.

## 5) Tradução pra régua de maturidade (L0–L4)

- A detecção (anchor-lint, gate-selftest, foundation-ratchet, nightly floor) chegou a **L2 — medido, porém advisory**.
- **L3 (gate `required` + counterfactual) tem ZERO gates SDD.** A "zona com dentes" está vazia.
- Logo: nenhuma decisão SDD impede um funcionário de quebrá-la na origem. **#2848 já é a prova materializada** (ghost 14→16 entrou verde).
- O salto de 60→world-class **não é mais método** — é arme o que já existe: conserte o read-side do floor, burn-down até verde, promova 1 gate a `required`.
