---
slug: 0336-gates-design-promocao-por-mordida-provada-emenda-0314
number: 336
title: "Emenda à 0314 — gates de design PODEM virar required quando provarem mordida REAL (contrafactual coletável + desembrulho do exit code); generaliza o precedente da 0327"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-11"
accepted_at: "2026-07-11"
accepted_via: "Wagner 'sim' 2026-07-11 (sessão charming-swanson) ratificando a POLÍTICA DR-1/2/3 após relatório por gate (evidência git+gh: 5 gates advisory, 0 mordidas reais / jovens 0–2d, wrapper esconde mordida, selftests provam a lógica). Aceite cobre SÓ a política — nenhum gate concreto foi flipado a required; a implementação do bite-log (DR-2a) e cada promoção por item ficam em PRs próprios com R10."
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, advisory, design, promocao, subtracao, anti-teatro, evidencia]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0328-ds-transicao-congelado-para-vivo-git-ssot
pii: false
---

# ADR 0336 — emenda à 0314: promoção de gate de design por MORDIDA PROVADA (não por calendário cego)

> **Status:** `aceito` (2026-07-11, Wagner "sim"). Append-only — **não edito a [0314](0314-poda-gates-onda-2-lei-fusoes.md)**; esta ADR adiciona a *classe de promoção* para gates não-Tier-0, generalizando a exceção pontual que a [0327](0327-anchor-content-required-emenda-0314.md) já abriu.
>
> **O aceite é da POLÍTICA. Nenhum gate foi flipado/deletado por esta ADR.** A promoção de cada gate concreto é um PR próprio, por item, com Wagner (igual a 0327 fez com o `anchor-content`). A implementação do bite-log (DR-2a) é PR próprio pós-aceite.

## Contexto — a revisão adversarial de 2026-07-11

O adversário provou: os **5 gates de consistência de design** são TODOS advisory e, por construção do workflow, **nunca ficam vermelhos** numa violação real — envolvem o `--check` num `if/else` que emite `::warning::` e sai `0`. Um advisory que nunca mostra vermelho → o time que mergeia não é obrigado a ver → "governança de design" vira ~teatro. A [0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)/[0314](0314-poda-gates-onda-2-lei-fusoes.md) já deletaram gates-teatro; a provocação é: por que estes ficam?

**O diagnóstico honesto corrige a premissa do adversário em 3 pontos** (evidência coletada em `git`+`gh` 2026-07-11):

1. **"0 fails" aqui não é sinal de catraca-estática — é artefato de idade + do wrapper.** 4 dos 5 gates **nasceram hoje** (2026-07-11); o 5º (`ds-mirror-drift`) tem 2 dias. E o wrapper `::warning::`+`exit 0` **garante** 0 fails de workflow por construção. Logo "0 fails" **não mede** o valor de design de nada — diferente do caso `foundation-ratchet` (300+ runs, 0 fails → aí sim catraca-estática comprovada).
2. **A LÓGICA de cada gate PROVADAMENTE morde.** Os 5 têm selftest hard rodando no required `governance-script-tests.yml`: *"ds-token-version morde"*, *"ds-mirror-drift SENTINELA morde"*, *"design-coverage CATRACA morde"*, *"pt-conformance morde"*, e o test-âncora de `ds-tokens-build-sync` (#4105). O que está neutralizado é o **exit code do workflow do PR**, não a máquina.
3. **O moat de design ENFORCING já existe e é required** — `DS gate` (cor/token/UI-lint fundidos, F1), `visual-regression` (pixel-diff + `Tier0RenderIsolationTest`), `Ancora de design nao-shell` (F2/F6, [0327](0327-anchor-content-required-emenda-0314.md)). Os 5 advisory são uma **camada de higiene/cobertura por cima** desse moat — não são "a governança de design" inteira.

**O que o adversário DE FATO expôs (e esta ADR conserta):** a política atual tem um **catch-22 de promoção**. A [0298](0298-teto-de-governanca-check-m.md) manda todo advisory nascer com `promote_by ≤14d` (os 5 têm: 2026-07-23 a 25). A [0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) diz "promovível por calendário". Mas **um gate advisory-wrapped NUNCA registra uma mordida real** (emite warning, não fail) → quando o ZELADOR chega no `promote_by`, encontra **0 fails garantidos** e **nenhuma evidência coletável** pra decidir promover vs. manter vs. deletar. O calendário força a decisão, mas não fornece o dado da decisão.

## Decisão — 3 regras

### DR-1 — A política Tier-0 da 0314 fica intacta como default; abre-se uma classe de exceção *coletável*

A [0314](0314-poda-gates-onda-2-lei-fusoes.md) permanece: **required = só evita catástrofe Tier-0 (dinheiro/PII/multi-tenant/fiscal) ou quebra de correção do núcleo.** Um gate de design/higiene **não** vira required por ser "importante" nem por calendário. Vira required **só quando prova mordida REAL** — e "mordida real" passa a ter uma definição *coletável*, porque a conclusão do workflow (sempre verde, advisory) não serve de prova.

> Isto **generaliza**, não contradiz, a [0327](0327-anchor-content-required-emenda-0314.md): lá, `anchor-content` foi promovido porque uma âncora podre **mergeou verde 2×** (07-06 → 07-08) — reincidência real provada. Esta ADR transforma esse precedente pontual em critério repetível pros outros gates de design.

### DR-2 — Evidência de mordida = contrafactual coletável (≥2 PRs reais), não a cor do check

Enquanto advisory, cada gate DEVE, quando o `--check` **daria fail** num PR real, registrar a mordida contrafactual de forma que o ZELADOR conte — ou:

- **(a) bite-log estruturado:** o `::warning::` já é emitido; adiciona-se uma linha append-only num artefato de auditoria (ex. `memory/governance/design-gate-bites.jsonl`) com `{gate, pr, sha, arquivo, quando}` toda vez que o check reprova num PR que **depois mergeou** (= a violação passou); OU
- **(b) reincidência documentada** no padrão 0327 (o mesmo erro pego por humano e revivido), que já é evidência de sobra.

**Critério de candidatura à promoção:** **≥2 PRs reais distintos** onde o gate teria bloqueado uma violação que mergeou. Menos que isso = sem evidência = **não promove** (o gate não provou que morde no mundo, só no selftest). O selftest prova que a *máquina* está sã; o bite-log prova que o *merge* está sujo — são coisas diferentes (L-24, *"presença ≠ correção"*).

### DR-3 — Promover ≠ flipar `required:true`. Promover = desembrulhar o exit code + bite-log + janela + Wagner (anti-#3143)

O PR de promoção de qualquer gate de design DEVE, no MESMO diff:

1. **Desembrulhar o exit code** — remover o `if/else`/`exit 0` que engole o `--check`, de modo que `--check` fail = job fail. *Promover pra required sem isto = required-sempre-verde = teatro ao quadrado* (pior que o advisory honesto de hoje).
2. **Anexar o bite-log** com os ≥2 PRs contrafactuais (DR-2) no corpo do PR.
3. **Atualizar os 2 registros no mesmo PR** — `gates-registry.json` (mudar `terminal: advisory→required`, remover `promote_by`) e o `checkM` do `.memory-health-baseline.json` — senão o próprio `memory-health` (LEI) fica vermelho (regra de sincronia da 0314).
4. **Rodar verde no `main` uma vez** antes de adicionar o context ao branch protection (evita travar PRs abertos por nome divergente — receita da 0327).
5. **Janela ≥14d + ratificação Wagner por item (R10).** **Flip no calado é proibido** — reafirma a lei da própria 0314 e o veredito sobre o incidente #3143 (flip de `foundation-ratchet` a required em 2026-06-21 sem janela, condenado e revertido pela 0314 D-1).

## Veredito por gate no aceite (2026-07-11) — nenhum promove, nenhum deleta

Evidência: `gh run list` por workflow (só `pull_request`) + `git log` de nascimento + selftests + `gates-registry`.

| Gate | required? | fails reais / runs | idade | selftest prova mordida? | sinal de custo (anchor)? | `promote_by` | Veredito |
|---|---|---|---|---|---|---|---|
| `pt-conformance` | não | 0 / 2 | 0d (nasceu 07-11 #4108) | ✅ `--selftest` PT-02..05 | ✅ count-pump (rev. adversarial 07-11) | 2026-07-25 | **MANTER advisory** |
| `design-coverage` | não | 0 / 3 | 0d (07-11 #4106) | ✅ `design-coverage.test.mjs` bite/release | ✅ 88% das 144 telas silenciosas | 2026-07-25 | **MANTER advisory** |
| `ds-tokens-build-sync` | não | 0 / 0 (nunca disparou) | 0d (07-11 #4105) | ✅ test-âncora #4105 | ✅ `_generated` stale → loop valida errado | 2026-07-25 | **MANTER advisory** |
| `ds-mirror-drift` | não | 0 / 15 | 2d (07-09 #3991) | ✅ `ds-mirror-drift.test.mjs` bite/release/catraca | ✅ 3 cópias divergentes dark canvas (incid. 07-08) | 2026-07-23 | **MANTER advisory** |
| `ds-token-version` | não | 0 / 4 | 0d (07-11 #4103) | ✅ `ds-token-version.test.mjs` seed+MINOR/MAJOR | ✅ versão implícita, consumidor cego | 2026-07-24 | **MANTER advisory** |

**Por que nenhum PROMOVE:** o critério é ≥2 mordidas reais (DR-2). Todos têm **0** — e é *impossível* que tivessem: jovens demais (0–2 dias) **e** o wrapper esconde mordida como warning. Promover agora seria o flip-cego que esta própria ADR condena.

**Por que nenhum DELETA:** o critério de delete (0271/0314 subtração) é *nunca mordeu E sem sinal de custo*. Cada um tem **anchor de custo documentado** + selftest que **prova que a lógica morde**. Deletar um sentinela de 0–2 dias com sinal de custo real seria destruir proteção jovem — o oposto da subtração-segura (que só tira morto/teatro/redundância).

**Ação:** os 5 seguem advisory até o `promote_by` (2026-07-23/24/25). Nessas datas o ZELADOR aplica DR-2/DR-3 com o bite-log em mãos. Se o bite-log tiver **<2** mordidas reais no `promote_by`: (i) renova advisory com novo `promote_by` **se** houver sinal fresco; (ii) reconhece que o moat enforcing (`DS gate` + `visual-regression` + `Ancora`) já cobre o risco e o gate fica **advisory-por-lei permanente** (valor = warning + selftest anti-regressão da lógica); ou (iii) deleta se o sinal de custo evaporou. Decisão por evidência **naquela data**, não agora.

## Consequências

- **Positiva:** mata o catch-22 — a promoção de gate de design passa a ter um dado coletável (bite-log) em vez de depender da cor de um check que é verde por construção. O `promote_by` da 0298 deixa de chegar vazio.
- **Positiva:** a cláusula de desembrulho (DR-3.1) impede o "required-toothless" — a falha exata que o adversário aponta não pode reaparecer como required.
- **Custo:** implementar o bite-log (DR-2a) é ~1 função por script (append num `.jsonl` quando `--check` reprova em `pull_request`). É trabalho, não decisão — fica pra pós-aceite, num PR só, registrado no `gates-registry` se virar workflow.
- **Honesto (resíduo):** o bite-log (a) depende de o gate rodar no PR (paths corretos) e de o PR **mergear com a violação** — se o time corrige antes de mergear, a mordida "boa" (o gate funcionou como advisory) não vira evidência de promoção. Isso é *correto*: um advisory que faz o time corrigir **já está entregando valor sem ser required**; ele só "merece" required quando a violação **passa** repetidamente apesar do warning. A reincidência-documentada (DR-2b) cobre o caso onde o dano foi pego por humano.

## Ramo alternativo considerado e recusado (por ora)

**"Design fica advisory-por-lei; o moat é só os enforcing de token/pixel/âncora que já existem."** É uma posição defensável (o moat required real já existe). Recusada como *default* porque a [0327](0327-anchor-content-required-emenda-0314.md) **já provou** que um gate de design pode merecer required quando morde de verdade (a âncora podre reincidente) — fechar a porta contradiria um precedente aceito há 3 dias. Esta ADR mantém a porta, mas **trancada por evidência coletável**, que é o meio-termo honesto entre "promove cego" e "advisory eterno".

## Gate de reversão (herda 0327)

Qualquer gate promovido por esta política que, em ≥N runs required, der **falso-positivo** (bloqueou merge legítimo sem violação real) → reabrir por ADR e rebaixar (remover o context dos required). Demover ≠ apagar.

## Ratificação (R10)

- [x] Wagner aceita a POLÍTICA (DR-1/2/3) — flip `proposta → aceito` (2026-07-11, "sim").
- [ ] (pós-aceite, PR próprio) implementar bite-log DR-2a nos 5 scripts.
- [ ] (em cada `promote_by`) ZELADOR aplica o veredito com bite-log — cada promoção concreta é PR por item com Wagner.
