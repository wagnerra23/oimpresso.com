---
date: "2026-08-05"
time: "1438 BRT"
slug: "promocoes-required-e-hooks-observaveis"
tldr: "2ª metade da sessão das máquinas. Os 16 promote_by vencidos viraram decisão: 3 lanes Pest (valor/estoque/lei) + 2 gates de índice derivado promovidos a required (34 → 40 contexts, 2 flips validados), 13 com prazo estendido e razão escrita. Os 34 hooks mudos caíram a 23 (os 11 BLOQUEADORES ganharam tag) e o único bloqueador sem prova de mordida ganhou bite-test. RISCO ABERTO: o PR #5069 reintroduz `paths:` no module-surface — se mergear sem rebase, trava o merge do repo."
decided_by: [W]
cycle: null
prs: [5313, 5314, 5315, 5318, 5320]
us: ["US-GOV-059", "US-KB-008"]
next_steps:
  - "PR #5320 (US-GOV-059 + US-KB-008) está ABERTO — falta merge"
  - "⚠️ PR #5069 NÃO pode mergear sem rebase: ele readiciona `paths:` em module-surface.yml, que agora é required — reintroduz o deadlock de 2026-07-02. Avisado nos 2 PRs; NENHUM gate pega isso"
  - "US-GOV-059: triar as 43 permissões órfãs nas 3 categorias (falta declarar / chamada morta / FP do detector)"
  - "23 hooks advisory seguem não-observáveis — forward-only, cada um ganha tag quando for tocado"
  - "perf-static-guard segue órfão por decisão: instrumento heurístico demais pra virar gate (ver §Decisões)"
related_adrs: ["0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314", "0370-module-surface-catalog-graph-required-emenda-0314", "0314-poda-gates-onda-2-lei-fusoes", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0130-handoff-append-only-mcp-first"]
---

# Handoff 2026-08-05 14:38 BRT — promoções a required e hooks que passaram a falar

## TL;DR

Continuação direta do [handoff das 09:49](2026-08-05-0949-maquinas-que-nao-avisavam.md). Aquele
ligou o `hook-bites` e fez os `promote_by` vencidos aparecerem. **Esta metade decidiu o que
apareceu:** 5 gates promovidos a required (2 ADRs, 2 flips validados), 11 hooks bloqueadores
tornados observáveis, e o último bloqueador sem prova de mordida ganhou bite-test.

Continua valendo o invariante da sessão: **zero máquinas novas**.

## Cronologia

| Quando | Evento |
|---|---|
| 10:30 | [W] pede os 16 vencidos. Medição por gate: mordidas reais × runs, não só a data |
| 11:00 | **ADR 0369** — 3 lanes Pest a required. A 1ª classificação deu 13/13 "infra" e teria DERRUBADO a promoção; o discriminador certo é o STEP, não o log → 13/13 mordida |
| 11:47 | Flip 34 → 37 · `protection-drift` 🟢 · `enforce_admins` preservado |
| 12:30 | 11 hooks bloqueadores ganham tag (2 por ALIAS, preservando histórico; 9 por prefixo) |
| 13:00 | Dos 22 zero-entrega, **21 já provam que mordem**; o 22º (`block-ancora-no-olho`) ganha bite-test com mutação |
| 14:00 | **ADR 0370** — `module-surface` + `catalog-graph`. Achado: **não eram always-run** → promover = deadlock |
| 14:20 | Flip 37 → 40 · `protection-drift` 🟢 |
| 14:35 | US-GOV-059 e US-KB-008 registradas (o `tasks-create` não chega ao git sozinho) |

## PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#5313](https://github.com/wagnerra23/oimpresso.com/pull/5313) | merged | ADR 0369 — Compras/Estoque/Ponto (Pest MySQL) a required |
| [#5314](https://github.com/wagnerra23/oimpresso.com/pull/5314) | merged | 11 hooks bloqueadores observáveis (34 → 23 mudos) |
| [#5315](https://github.com/wagnerra23/oimpresso.com/pull/5315) | merged | bite-test do `block-ancora-no-olho` |
| [#5318](https://github.com/wagnerra23/oimpresso.com/pull/5318) | merged | ADR 0370 — module-surface + catalog-graph a required |
| [#5320](https://github.com/wagnerra23/oimpresso.com/pull/5320) | **ABERTO** | US-GOV-059 + US-KB-008 no SPEC |

## Decisões tomadas

| Pergunta | Decisão [W] | Justificativa |
|---|---|---|
| Os 16 vencidos | 3 promovidos, 13 prazo estendido | 7 têm **0 falhas em ≥100 runs** (padrão do `foundation-ratchet`, que a 0314 demoveu); 4 têm amostra de 5–28 runs |
| Os 2 de índice derivado | promovidos (ADR 0370) | mordem de verdade (10 e 6) e o advisory não estava segurando |
| Ligar o `perf-static-guard`? | **não** | instrumento heurístico demais: 1 dos 2 "ofensores" é FP confirmado (model sem relação), o outro é incerto. Ligar treinaria a ignorar vermelho |
| Consertar os ofensores de perf? | **não** | adicionar `->with()` pra calar contador de regex seria pessimização em código de produção |

## ⚠️ Risco aberto — leia antes de mergear qualquer coisa

O **[PR #5069](https://github.com/wagnerra23/oimpresso.com/pull/5069)** (aberto 30/07, parado,
1 check vermelho) faz o **oposto** do #5318 em `.github/workflows/module-surface.yml`:

- **ADICIONA** 8 `paths:` — o #5318 os **removeu** (required-readiness)
- **FUNDE** o `catalog-graph --check` dentro do `module-surface.yml`

Se mergear **depois** sem rebase, os `paths:` voltam — e um context **required** path-triggered
fica `PENDING` para sempre nos PRs que não tocam aquele path. É o **deadlock de 2026-07-02**
(`main` BLOCKED com 54/54 verdes), agora com o gate já required.

**Nenhum gate pega isso:** `protection-drift` e `baseline-tamper-guard` comparam baseline × vivo,
não o **gatilho** do workflow. Está avisado nos dois PRs, com as 2 condições do rebase (não
reintroduzir `paths:`; preservar o nome do job se mantiver a fusão). É vigilância humana.

Medição que contextualiza: dos 40 required, os 34 que resolvem a um job são **todos always-run**
— **zero deadlock latente** hoje. O #5069 seria o primeiro.

## Estado MCP no momento do fechamento

> Obrigatório (ADR 0130 §6) — snapshot do que as tools devolveram.

### cycles-active
```
Nenhum cycle ATIVO em COPI.
```

### my-work
```
12 tasks — 1 DOING (US-INFRA-048 · documentação técnica ponta a ponta) + 11 REVIEW.
Nenhuma tocada nesta metade: o trabalho foi de governança/CI, fora do backlog de produto.
As 2 US criadas hoje (US-GOV-059 p2, US-KB-008 p3) ainda não aparecem — o SPEC está no
PR #5320, ABERTO; o webhook só sincroniza após merge.
```

### Glob memory/handoffs/2026-08-*
```
3 handoffs hoje: 0746 (sessão paralela) · 0949 (meu, 1ª metade) · 1211 (sessão paralela).
Este é o 4º. Nenhum editado — append-only respeitado.
```

### decisions-search
```
2 ADRs novas, ambas minhas e ambas emendas à 0314: 0369 e 0370. Índice regenerado
(374 ADRs), supersede íntegra, 0 colisão nova.
```

## O que esta metade ensinou

**O instrumento errado quase inverteu 2 decisões.** (1) A classificação de falhas pelo TEXTO do
log deu **13/13 "infra"** e teria derrubado a ADR 0369; pelo STEP que falhou, **13/13 mordida**.
(2) O `gh run list` mostrava 480s/382s e quase matou a ADR 0370 por custo — era **fila de
runner**; o job real leva 19s/33s.

**Verificar o gatilho antes de promover não era formalidade.** Os 2 gates da 0370 não eram
always-run. A promoção literal teria travado o repositório inteiro.

**LC-08 apareceu 7× nesta sessão** (as 3 da 1ª metade + `--selftest` que não existe, texto-vs-step,
fila-vs-execução, `ls-tree` sem `-r`). Todas pegas por desconfiar de resultado implausível.

**5 máquinas morderam em mim:** `hooks-manifest --check` (3×), `dup-detector` (2×, uma achando
conflito real de sessão paralela), `block-destructive` (2×), `block-instrumento-sem-porta-viva`,
`brl-scan`.

## Referências

- Handoff da 1ª metade: [2026-08-05-0949-maquinas-que-nao-avisavam.md](2026-08-05-0949-maquinas-que-nao-avisavam.md)
- Session log: [2026-08-05-maquinas-que-existiam-e-nao-avisavam.md](../sessions/2026-08-05-maquinas-que-existiam-e-nao-avisavam.md)
- ADR 0369 · ADR 0370 (ambas emendas à 0314)
