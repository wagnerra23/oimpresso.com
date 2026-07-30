---
slug: 0361-errata-0354-teammcp-pest-required-nunca-executado
number: 361
title: "Errata à 0354 — a promoção de `teammcp-pest` a required nunca chegou à proteção viva, e ficou sem objeto com a deprecação do TeamMcp"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-07-30"
accepted_via: "Ratificada por [W] em 2026-07-30 (\"aprovo tudo\"). Errata de FATO, não de decisão: mede que o flip decidido na 0354 nunca foi executado na branch protection e registra que a deprecação do TeamMcp ([W] 2026-07-30) tirou o objeto da promoção. Append-only impede corrigir a 0354 no lugar (Constituição Art. 3)."
module: governance
quarter: 2026-Q3
tags: [governanca, adr, errata, gates, ci, required, branch-protection, teammcp, deprecacao, lc-10]
supersedes: []
superseded_by: []
related:
  - 0354-teammcp-pest-required-emenda-0314
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0347-deadlink-gate-required-emenda-0314
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
pii: false
---

# ADR 0361 — Errata à 0354: o required de `teammcp-pest` nunca existiu no vivo

## O que a 0354 diz

A [ADR 0354](0354-teammcp-pest-required-emenda-0314.md) (`decided_at: 2026-07-27`, `status: aceito`) tem por
título *"`teammcp-pest` promovido a REQUIRED"* e, na §Decisão, *"passa a **required** na branch protection de
`main`"*. Lidas sozinhas, as duas frases afirmam um estado consumado.

## O fato medido

Medido em **2026-07-30** contra a **autoridade** — a proteção viva, não o baseline:

```bash
gh api repos/wagnerra23/oimpresso.com/branches/main/protection \
  --jq '.required_status_checks.contexts[]'
```

→ **34 contexts**. Nenhum é `teammcp-pest`. Os únicos com `Pest` são `PHP / Pest (Unit)`,
`PHP / Pest (Financeiro · MySQL)` e `PHP / Pest (NfeBrasil · MySQL)`. O
[`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) também não o lista
(`grep -i teammcp` → 0 linhas).

**O flip nunca aconteceu.** A lane `teammcp-pest` roda e é honesta — mas é **advisory**, não required.

## A leitura justa (e por que ainda é errata)

A 0354 **não mentiu por descuido**: a própria §Require-safe, item 4, escreve que *"Flip do vivo = ato [W].
**NÃO é feito por agente.** A entrada em `required-checks-baseline.json` vem em PR **pós-flip**"*. Ou seja, a
ADR registrou uma decisão cuja **execução era sabidamente um passo separado**, a cargo de [W].

O defeito é de **tempo verbal**, e é o que a [LC-10](../LICOES_CODE.md) nomeia: título e §Decisão no
perfeito (*"promovido"*, *"passa a"*) descrevem como consumado um ato que a mesma ADR, três seções abaixo,
condiciona a um gate humano. Quem lê o título — ou o índice de ADRs — conclui que o gate morde. Não morde.
Foi exatamente essa a leitura que o [DEPRECATION-PLAN do TeamMcp](../requisitos/TeamMcp/DEPRECATION-PLAN.md)
teve de derrubar por medição antes de planejar a remoção do módulo.

Nada aqui contradiz a análise de custo da 0354, que **segue verdadeira e verificável**:
`ForjaRoutesSmokeTest.php` de fato nunca executou, e o mecanismo (*em sqlite a stack UltimatePOS só
`markTestSkipped`*) segue sendo real.

## O objeto desapareceu

Em **2026-07-30**, [W] decidiu deprecar `Modules/TeamMcp` (5º de um conjunto de módulos de governança).
Uma lane required apontando pra um módulo em remoção seria um deadlock garantido no `main` — e é a única
razão pela qual a remoção **não** está bloqueada hoje: como o flip nunca ocorreu, não há context required
órfão a limpar. O risco R2 do plano cai por medição, não por sorte.

## Consequências

1. **Não há gate a demover.** O `required-checks-baseline.json` já está correto (nunca teve a entrada);
   o `protection-drift.mjs` nunca acusou drift porque não havia divergência baseline↔vivo.
2. **A 0354 fica `aceito` e append-only** — esta errata é a sucessora que corrige o tempo verbal, não uma
   edição. Quem consultar a 0354 deve ler esta junto.
3. **O que sobra de vivo migra com a Forja.** A pergunta legítima que a 0354 levantou — *"as rotas `/forja`
   executam de verdade?"* — continua de pé, mas o endereço muda: a Forja vai pro módulo `ProjectMgmt`
   renomeado pra `Forja` (decisão [W] 2026-07-30). A lane e o `ForjaRoutesSmokeTest` acompanham o
   receptor; reabrir promoção lá é **ADR nova**, sob a régua da [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)
   (≥2 mordidas provadas) — e desta vez com o flip como pré-condição registrada, não como pendência.

## Decisão [W] (2026-07-30)

Errata **ratificada**. O flip da 0354 **não será executado**: promover a required uma lane cujo módulo sai
do repo criaria o deadlock que a [0314](0314-poda-gates-onda-2-lei-fusoes.md) e a
[0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) existem pra evitar — e a própria
0354 (§Require-safe #4) já reservava esse ato a [W], nunca a um agente.

A pergunta que a 0354 levantou não morre: ela **muda de endereço** junto com a Forja (ver §Consequências #3).

## Resíduo honesto

- **Não varri as outras ADRs de promoção** (0327 · 0347 · 0336) atrás da mesma classe de tempo verbal.
  Se a classe reincidir, o alvo não é uma errata por ADR — é a régua de redação: *ADR de promoção declara a
  decisão, e o estado de enforcement tem dono único* (`required-checks-baseline.json`), como já manda a
  lápide §5 2026-07-16. Fica como chip, não como gate: o predicado é semântico e a família
  presence-gate já morreu medida 5×.
