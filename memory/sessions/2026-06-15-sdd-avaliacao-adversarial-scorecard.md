---
date: "2026-06-15"
topic: "Avaliação adversarial do programa SDD (sdd-avaliar, 7 skeptics): score composto 61.9/100 — infra de garantia construída, garantia ainda não exercida (tudo advisory, nada armado, métricas-mãe não-governadas)"
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"]
prs: []
---

# Scorecard SDD — avaliação adversarial (2026-06-15)

> Workflow `sdd-avaliador-processo` (8 agents, ~892k tokens, ~23min). 1 skeptic por stream
> verifica o estado **REAL** em git+gh+CT100 (não o plano). Rodado logo após o burn-down de
> corruptores era-sqlite desta sessão (67→0 no linter). Run: `wf_70af5ad7-f50`.

## Score composto: **61.9/100** (ponderado) · 60 (média simples)
Pesos: FV/Fase2b ×1.8 · SA/GT ×1.3 · demais ×1.0.

| Stream | Peso | Score | Status macro | Maior risco sistêmico |
|---|---|--:|---|---|
| **GT** — Governance scorecard | ×1.3 | **85** | 8 artefatos em main, selftest 8/8 morde | cadeia armar→enforçar é advisory ponta-a-ponta; G7 depende de `node` em prod não-confirmado |
| **FV** — Full-suite / testes | ×1.8 | **81** | Sem 0-2 feitas, reproduzíveis | nightly mede mas scorecard **HARDCODA** `full_suite=notYet` (comentário virou falso) |
| **CH** — Charters + fluxo-novo | ×1.0 | **73** | template/schema/skill em main | fluxo novo 100% passivo/advisory; `us:` só 3/137 |
| **F2b** — Fase 2b P0 harness | ×1.8 | **62** | frentes A/B provadas na máquina | floor "1514→centenas" tem **ZERO medição válida** (1 só run completa) |
| **KL** — Knowledge/ghost/decay | ×1.0 | **58** | gates existem e mordem em selftest | **PR #2761 mergeou com anti-ghost VERMELHO**; tudo advisory + ratchet desarmado |
| **SA** — Anchors spec↔código | ×1.3 | **42** | infra A1/A2/A3 sólida | `anchor_coverage` tem **3 valores divergentes**; A5/A6/A10 não-iniciados; cobertura real ~5% |
| **PROM** — Semanas 4-6 (required) | ×1.0 | **16** | gated pelas ondas 1-4 | promover R1 hoje = repetir o incidente que o ADR 0275 §5 existe pra prevenir |

## FEITO-VERIFICADO (provado vivo — gate morde ou número real)
- **G6 gate-selftest (92)** — a peça mais forte: 8/8 catracas mordem (boa exit0 / ruim exit1 pelo motivo certo). Prova empírica de não-placebo.
- **F1 JUnit tripwire (94)** — pegou truncamento real (run 092405, 0 bytes), strip PII, wired no ci.yml.
- **G1 ADR 0275 (95)** · **CH-template (82)** (AJV: v1✓/v2✗/ausente✓-grace) · **E1 triagem identidade (94)** (decisão Wagner 100%).
- **F3 nightly CT100 (90)** · **A2/A3 anchor-lint (86)** (15 dead REAIS: MemCofre/DocVault/NFE-Certificado não existem) · **Q1 foundation-ratchet (88)**.
- **F2b-A harness (82)** ("mysql:not found"=0, "Base table not found" 688→7) · **F2b-B config_json (90)** (SQLSTATE 3140 212→0).

## ILUSÓRIO / FORMA-NÃO-CORREÇÃO (cuidado ao ler "feito")
- **F2b-floor "1514→centenas" (38)** — 1 só run completa no harness final; o burn-down de corruptores mergeou DEPOIS dela; a interseção que circula mistura 2 regimes de harness → inválida pela própria regra. **+708 skips mascaram fails.**
- **F2b corruptor count=0** — métrica de FORMA (guard estático), **NÃO** o red floor. O próprio PR #2759 diz "prova final = nightly" (pendente). ⚠️ **Inclui o trabalho desta sessão** — o burn-down está estruturalmente feito mas o floor-drop é PREDIÇÃO até a nightly de Jun 16.
- **A4 backfill anchors (42)** — soa pronto, cobertura real ~5% (19-20 de 800 US); 15 dead persistem em main.
- **KL front_door 100%** — atingido por portas-tombstone (lápide conta como verdade); `ghost_count` passa por grandfathering de 34 pares.

## FALTA (não-iniciado / bloqueado)
- **SA-A5/A6/A10 (18/8/10)** — batch IA de anchors nunca rodou (ledger tem 0 entry tipo:anchors).
- **KL-E2b re-seed Meilisearch (12)** — nenhum script; `peso_real` ainda lista módulo morto Copiloto=65.
- **T1/T2 TDAD (3/3), C2 coverage (8)** — `ci.yml: coverage:none`; cadeia inteira ausente.

## TOP 5 RISCOS SISTÊMICOS
1. **Mede mas não governa (FV).** Nightly roda há dias com números reais, mas `sdd-scorecard.mjs:137` HARDCODA `full_suite_pass_rate=notYet` com comentário hoje FALSO ("nenhum run jamais salvo"). O scorecard que o brief/health-check/dashboard leem diz que nunca foi medido.
2. **Floor sem medição válida (F2b).** "1514→centenas" é predição: 1 run completa, burn-down depois, +708 skips convertendo fail→skip. A regra do próprio SPEC (≥2 runs interseção) ainda não pôde ser cumprida.
3. **Advisory-ponta-a-ponta + ratchet desarmado (KL/GT/SA).** 0 dos 17 required é gate SDD; 0 métrica `armed:true` relevante. Prova: PR #2761 mergeou com anti-ghost vermelho.
4. **Métrica com 3 verdades (SA).** `anchor_coverage` = 5.3% (CI) / 7.9% (local) / 2.8-3.5% (strict) — sem fonte única, o gatilho de A10 (100%) é indefinido.
5. **Promover required hoje = repetir o incidente (PROM).** R1 colocaria como required uma suite com >1280 fails e p95 ~70min (3× o teto) com `enforce_admins=true` — o cenário visual-regression #2544/#2548 que o ADR 0275 §5 existe pra prevenir.

## VEREDITO
**No caminho, mas a metade que importa ainda por provar — 62/100, "infra de garantia construída, garantia ainda não exercida".** A arquitetura imunológica é genuinamente forte onde foi terminada (gate-selftest 8/8, dead-anchors reais, harness MySQL morde na máquina, refutador G5 com fixtures). O problema é estrutural e consistente: **tudo advisory, nada armado, e as 2 métricas-mãe (full_suite e floor) ou são ignoradas pelo scorecard ou não têm medição válida** — o sistema reporta saúde sem poder de barrar regressão (PR #2761 vermelho mergeado prova que não é teórico).

**Maior alavanca única: fechar o elo MEDIR→GOVERNAR** — (a) wirar a nightly F3 no scorecard removendo o hardcode `notYet`; (b) unificar a definição de `anchor_coverage` numa fonte só; (c) armar os 2-3 ratchets que já têm 3 medições. **As Semanas 4-6 (promoções) NÃO devem começar** até a nightly entrar no scorecard e o floor ter ≥2 medições honestas — senão o calendário ADR 0275 §5 vira juízo manual (o anti-padrão que ele existe pra matar).

## Próximos passos priorizados (caminho crítico)
1. **MEDIR** — nightly Jun 16 02:00 é a 1ª medição do efeito do burn-down sobre os 181× errno 3730. Depois, ≥2 runs completas → floor por interseção (regra do SPEC).
2. **Wirar nightly→scorecard** (matar o hardcode `sdd-scorecard.mjs:137`) — precisa de design do transporte CT100→scorecard (Hostinger).
3. **Unificar `anchor_coverage`** (3 valores → 1 fonte) + armar ratchets com 3 medições.
4. **Cauda US-GOV-019** (91 quarentena + ~7-11 bugs + 11 unclear) — incremental.
5. **NÃO promover** nada a required (PROM=16) até 1-4 fecharem.

> Candidato a 11ª métrica do scorecard SDD: `score_composto` (ADR 0275). Re-rodar este avaliador a cada fecho de onda / antes de promover gate.
