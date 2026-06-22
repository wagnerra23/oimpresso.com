---
slug: 0298-teto-de-governanca-anti-proliferacao-gates
number: 298
title: "Teto de governança — todo workflow novo nasce com classe terminal e âncora de custo (anti-proliferação)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-22"
module: governance
kind: meta
supersedes: []
related:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# ADR 0298 — Teto de governança (anti-proliferação de gates de CI)

> Aplica o princípio da [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal) à própria
> malha de governança: gate novo só nasce com sinal de custo e fim-de-vida declarado.

## Contexto

Sessão 2026-06-22 — Wagner: "está ficando grande e bagunçado, o que preciso pra me organizar?". Diagnóstico da malha de CI:

- **85 workflows** em `.github/workflows/`, só **~20 `required`** no main. ~600 runs de CI/dia.
- Executadas 3 fusões seguras de consolidação via funil adversarial → **−7 workflows** (PRs #3202/#3203/#3204, mergeados, zero cobertura perdida).
- **Mas o sistema foi de ~81 → 85 no mesmo período**: outras sessões criaram **~10 workflows em 24h** (sdd-scorecard-publish/ratchet, shipped-log-cron/gate, dup-detector, charter-refs, baseline-tamper-guard…). Quase tudo **meta-governança**.

**Achado-mãe:** o problema não é o estoque de gates (o balde), é a **taxa de criação** (a torneira). Poda manual é Sísifo — não acompanha. A máquina de governança se auto-replica mais rápido do que se consolida.

## Decisão

Instituir um **teto de governança**: governança nova só nasce com sinal e com fim-de-vida declarado.

**Regra:** todo workflow novo nasce `required` ou `cron` — nunca "advisory em PR pra sempre" — e só nasce se fechar um buraco que custou dinheiro/cliente/incidente (âncora explícita).

**Mecanismo (Check M no `memory-health`, ADR 0256 — não um workflow novo, coerente com a própria regra):**
1. `scripts/governance/gates-registry.json` ganha 2 campos por workflow: `terminal` (`required` | `cron` | `automacao` | `advisory`) e `anchor` (ADR/incidente/PR de custo). Advisory exige `promote_by` (data — vencimento ≤14d por ADR 0275 §5).
2. `memory-health` **Check M** (`checkGovernanceCeiling`): workflow no registry **fora do baseline grandfather** sem `terminal`+`anchor` (ou advisory sem `promote_by`) → 🔴 fail. Determinístico (só checa presença de campos; vencimento de data NÃO é avaliado aqui, pra não introduzir não-determinismo).
3. **Ratchet/grandfather:** os gates pré-existentes ficam isentos via `baseline.checkM` (igual ao padrão dos Checks C/L). Só o gate NOVO paga o custo.
4. **Cláusula ZELADOR:** o zelador diário sinaliza advisory com `promote_by` vencido → candidato a corte com a **métrica certa** (catches reais via output/baseline, não `conclusion` — ver lição da sessão 2026-06-22).

## Consequências

- **Positivas:** cada gate novo paga o custo de declarar fim-de-vida + justificativa → desacelera a proliferação na origem (a torneira). O check vive dentro de um gate `required` que já existe (memory-health) — não aumenta a contagem de workflows.
- **Custo:** autores de workflow novo precisam preencher 2 campos no registry. Trivial vs o ganho.
- **Não-objetivos:** NÃO deletar workflows existentes em massa (poda é Sísifo + arriscada); NÃO mexer nos escape hatches manual-dispatch (rede de incidente).

## Alternativas consideradas

- **Continuar podando à mão** — rejeitada: a sessão provou que não vence a taxa de criação (81→85 apesar de −7).
- **Check como workflow novo dedicado** — rejeitada: criaria mais um gate (contra a própria regra). Vive no memory-health.
- **Exigir `terminal`+`anchor` em TODOS os 85 retroativamente** — rejeitada agora: quebraria todo PR de imediato. Fica como limpeza incremental opcional (preencher ao tocar cada workflow).
