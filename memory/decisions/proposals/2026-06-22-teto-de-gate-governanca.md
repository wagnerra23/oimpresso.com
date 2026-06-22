---
status: proposal
title: Teto de governança — todo workflow novo nasce com classe terminal e âncora de custo (anti-proliferação)
proposed_by: Wagner + Claude
proposed_at: 2026-06-22
relates_to:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-catraca-sentinela-gate-cadencia
  - 0271-required-readiness-onda-2
  - 0275-sdd-scorecard-promocao-gates
---

# PROPOSAL — Teto de governança (anti-proliferação de gates de CI)

## Contexto / problema

Sessão 2026-06-22 — Wagner: "está ficando grande e bagunçado, o que preciso pra me organizar?" → escolheu podar a malha de CI. Diagnóstico:

- **85 workflows** em `.github/workflows/`, só **19-20 `required`** no main. ~600 runs de CI/dia (64 commits/24h × ~40 wf/push).
- Executei **3 fusões seguras** (consolidação de irmãos) com funil adversarial → **−7 workflows** (#3202/#3203/#3204, todos mergeados, zero cobertura perdida).
- **Mas o sistema foi de ~81 → 85 no mesmo período**: outras sessões criaram **~10 workflows novos em 24h** (sdd-scorecard-publish/ratchet, shipped-log-cron/gate, dup-detector, charter-refs, baseline-tamper-guard…). Quase tudo **meta-governança**.

**Conclusão:** o problema não é o estoque (balde), é a **taxa de criação** (torneira). Poda manual é Sísifo — não acompanha. A máquina de governança se auto-replica mais rápido do que se consolida.

## Decisão proposta

Instituir um **teto de governança**, no espírito do ADR 0105 (cliente como sinal): governança nova só nasce com sinal e com fim-de-vida declarado.

**Regra (1 frase):** todo workflow novo nasce `required` ou `cron` — nunca "advisory em PR pra sempre" — e só nasce se fechar um buraco que custou dinheiro/cliente/incidente (âncora explícita).

**Mecanismo (leve, reusa o que já existe):**
1. `gates-registry.json` ganha 2 campos por workflow: `terminal` (`required` | `cron` | `automacao`) e `anchor` (ADR/incidente/PR de custo). Advisory só é permitido com `promote_by: <data ≤ 14d>` (ADR 0275 §5).
2. Check no `memory-health` (Check G estendido — já faz o censo workflow↔registry): workflow novo sem `terminal`+`anchor`, ou advisory com `promote_by` vencido → FAIL.
3. Cláusula ZELADOR: o zelador diário sinaliza advisory que passou do `promote_by` sem promoção → candidato a corte com a **métrica certa** (catches reais via output/baseline, não `conclusion`).

## Por que agora

Sem o teto, os próximos 100 workflows nascem do mesmo jeito. Com ele, cada gate novo paga o custo de declarar fim-de-vida + justificativa — o que naturalmente desacelera a proliferação. É a torneira, não o balde.

## Não-objetivos

- NÃO deletar workflows existentes em massa (poda é Sísifo + arriscado — ver session log 2026-06-22).
- NÃO mexer nos escape hatches manual-dispatch (são rede de incidente: force-clean-rebuild, quick-sync, etc).

## Status

Proposto. Aguarda Wagner: (a) aprovar a regra; (b) decidir dono + escopo do check (MVP recomendado = estender o `memory-health` Check G, não criar workflow novo — coerência com a própria regra).
