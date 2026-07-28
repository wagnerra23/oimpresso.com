---
date: "2026-07-23"
topic: "Avaliação adversarial do programa SDD — scorecard composto 76/100 (7 streams verificados LIVE em git+gh+CT100)"
authors: [C]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
---

# Avaliação Adversarial do Programa SDD — 2026-07-23

> Disparada via skill `sdd-avaliar` → workflow `sdd-avaliador-processo` (run `wf_a3be6450-a18`).
> 7 skeptics Opus (1 por stream) + síntese. Cada skeptic verificou o artefato em `origin/main`
> e **rodou o script LIVE** (anchor-lint, sdd-scorecard, foundation-ratchet, gate-selftest,
> floor-compute no CT100) — mede o comportamento real, não o número que o documento afirma.
> 8 agents · 0 erros · ~1.44M tokens · ~18min. Read-only.

## Scorecard por stream

| Stream | Score | Peso | Status | Maior risco |
|---|---|---|---|---|
| Fase 2b — P0 harness (US-GOV-018) | 80 | ×1.8 | 🟢 landed + funcional | Floor caiu 1514→346, mas a catraca que protege está **desarmada** |
| FV — Full-suite/testes | 75 | ×1.8 | 🟡 mede, não governa | `full_suite_pass_rate` **armed:false há 10 dias** |
| SA — Anchors spec↔código | 87 | ×1.3 | 🟢 maquinaria completa | "84,1%" mascara **34% real** de US ancoradas; dívida 100% grandfathered |
| GT — Governance scorecard | 85 | ×1.3 | 🟢 aparato mordendo | GT-G3 verde **porque seu dente mais importante foi removido** |
| Charters + fluxo-novo | 79 | ×1.0 | 🟡 backfill parado | `related_us` estagnado em **45,6%** desde 02/07; skill preflight ensina `status:aceito` que quebra o gate SPEC |
| KL — Knowledge/ghost/decay | 76 | ×1.0 | 🟡 decay aberta | Distiller kill-switched (**0/76 portas**); recall/ragas não-armados; canário CT100 congelou ~2 semanas |
| Semanas 4-6 — promoções | 38 | ×1.0 | 🔴 estruturalmente aberta | Das 6 promoções, só **GT-G3 landou** (e já era de Sem 1-2); R1/C2 travados, T1/T2 cortados |

**Composto ponderado: Σ(score×peso)/Σpeso = 695,6/9,2 ≈ 76/100.**

## Top 5 riscos sistêmicos

1. **A jóia da coroa está medida mas NÃO governada.** `full_suite_pass_rate` (o "a suite mente" que motivou a ADR 0275) está `armed:false` desde 2026-07-13 e regrediu **298→346 arquivos-falhando (+16%)** no canal warn, sem trava. GT-G3 fica verde porque seu dente mais importante foi removido. Aparece em 4 streams (Fase2b/FV/GT/Sem4-6).
2. **Mentira ativa no comentário do gate required.** `.github/workflows/sdd-scorecard-ratchet.yml:54` afirma "full_suite está armed:true (HARD)" — falso vs o baseline real (`armed:false`). Idem `.claude/skills/memory-schema-preflight/SKILL.md` ensinando `status:aceito` (enum inexistente que quebra o gate SPEC required).
3. **Headline "84,1% + gate required" mascara 34% real.** Só 334/981 US ancoram código vivo; 375 (38%) são `_pendente_` contadas como cobertas; 155 `sem_campo`. 100% da dívida de entrada (baseline 655) grandfathered com **zero paydown** desde o arming (02/07).
4. **Loop de decaimento não fecha por máquina.** Distiller kill-switched (0/76 portas git-backed), `recall_eval_violations` é stub `notYet()` permanente, `ragas_real_uptime` armed:false com canário CT100 congelando ~2 semanas (uptime real ~67% vs target 95%).
5. **A onda-coroamento (Sem 4-6) está estruturalmente aberta.** R1/C2 travados num burn-down CT100 (P04) que não começou; T1/T2 cortados 12/07; A10-estrito (100%) não atingido. O relógio real (OOM matando suíte, janela envenenada) segue instável.

## Caminho crítico

```
P04 burn-down floor (346→0, 7 noites CT100 · NÃO iniciado)
  → RE-ARMAR full_suite (3 nightlies v2 já existem: 0720/0722/0723 — PR manual pendente 10 dias)
    → R1 promoção a required (semanas de relógio real)
```

O **re-arme do `full_suite_pass_rate` é o único passo de baixo custo já destravável hoje** (o dado existe). É decisão de política [W]: armar em value=pior-das-3 (~358, aceita a regressão 298→346 como novo floor) OU segurar. Os demais passos dependem do relógio CT100 instável.

## Veredito

**No caminho certo, mas com o elo mais importante deliberadamente aberto — 76/100.** A maquinaria de governança é genuinamente forte e não-teatral onde importa: `gate-selftest` prova 70/70 catracas mordem, `enforce_admins:true`, floor MEDIDO com dado real (non-determinismo tratado por interseção — o elo **MEDIR** está fechado de verdade). O que trai o programa não é fraude, é **dívida de governança assumida e transparentemente declarada**: a métrica-mãe está `armed:false` enquanto a condição de re-arme já está cumprida, e o programa mede a própria saúde e escolhe não enforçá-la justo onde mais dói.

**Maior alavanca (custo trivial, destrava R1 e fecha a narrativa "a suite mente"):** executar o PR de re-arme do `full_suite_pass_rate` + corrigir o comentário mentiroso do YAML :54. Decisão [W] pendente sobre armar-em-346.

_Origem: workflow `wf_a3be6450-a18`. O `score_composto` (76) é candidato a 11ª métrica do scorecard SDD (ADR 0275)._
