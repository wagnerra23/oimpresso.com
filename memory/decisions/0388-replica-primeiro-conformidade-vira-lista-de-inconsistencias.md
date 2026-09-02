---
slug: 0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias
number: 388
title: "Réplica primeiro: o protótipo é o contrato de layout e a conformidade do DS vira lista de inconsistências pós-aplicação"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-02"
module: governance
tags: [design, prototipo, cowork, ds, gates, replica, inconsistencias, forja]
supersedes: []
superseded_by: []
related:
  - 0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0282-protocolo-v2-colapso-ratificacao
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0264-governanca-executavel-trio-dominio-e2e
---

# ADR 0388 — Réplica primeiro: o protótipo é o contrato de layout e a conformidade do DS vira lista de inconsistências pós-aplicação

## Contexto

Em 2026-09-02, ao medir o que falta para a Forja ficar igual ao protótipo, a pilha de gates de
conformidade do Design System reprovou a cópia **fiel** antes de ela existir. Medido, não opinado
(sessão [2026-09-02](../sessions/2026-09-02-forja-paridade-medida-espelho.md), PR #6544):

| gate | o que acusou na cópia integral do `forja-page.css` / no JSX do protótipo |
|---|---|
| `foundation-guard` | CSS novo sem autorização |
| `stylelint` ratchet | +13 seletores duplicados · +6 hex · +2 `!important` |
| `css-size` · `fontramp` | arquivo novo · 291 `font-size` fora do ramp |
| `conformance-gate` | 0 cor crua — o regex de tela não enxerga `.fj-`; 326 `oklch` literais passam cegos |
| `ds-guard` | **BLOQUEIA**: família `--dev*` (4) = "paleta inventada" — a paleta que o próprio Cowork desenhou |
| `ui:lint` R3 | 505 glifos `✦ ⚿ ⚠ ★ →` nos 5 `.jsx` do protótipo |
| `ui:lint` R4 | 0 `PageHeader` canon · 0 `DataTable` canon · 0 `KpiCard` no protótipo |
| `ui:lint` R1 | 19 `oklch()` e 2 hex inline no JSX |

[W], textual: *"tem muita regra preexistente que proíbe de fazer igual ao protótipo. Isso é
errado. Tem baseline e pilhas de proteção que estão todas erradas atrapalhando agora."* E, sobre o
alcance: *"quero que isso sirva para todo o protótipo, isso não é erro isolado. Eu acho que
poderia ter uma lista de inconsistências para o Code resolver depois de aplicar. Senão não sei o
que acontece, simplesmente não funciona e nem sei o que fazer."*

A [ADR 0385](0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias.md) já tinha dito
que *"diferente não é erro"* e classificado a diferença em três categorias. Esta ADR fecha o outro
lado: **a ordem**. Hoje a conformidade vem antes e a réplica nunca chega; a partir daqui a réplica
vem primeiro e a conformidade vira uma lista com dono, gerada por máquina, resolvida depois.

## Decisão

### D-1 — O protótipo do Cowork é o contrato de LAYOUT, para todo módulo

Onde existe âncora (`related_prototype` no charter, resolvida por `ancora.mjs`), a aparência a
entregar é a do protótipo — composição, vocabulário de classes, densidade, tipografia, cores.
"Igual" se prova pela sonda (`design-diff --compare --check` = 0 `DIVERGE(bug)`), nunca pelo olho
(LC-06). Regra que mande **mudar o layout** para caber no DS inverte a precedência e perde.

### D-2 — Conformidade do DS deixa de ser veto e vira LISTA DE INCONSISTÊNCIAS pós-aplicação

Toda regra que mede aderência ao DS sem mudar comportamento — cor crua (R1), glifo (R3), PT-01
canon (R4), paleta por prefixo (`ds-guard`), `font-size` fora do ramp, `!important`, hex em CSS,
`flex`/`grid` cru — passa a ser **reportada**, não **bloqueada**, quando o arquivo é réplica de
protótipo. O dono da lista é a máquina
[`scripts/governance/replica-inconsistencias.mjs`](../../scripts/governance/replica-inconsistencias.mjs):
roda os mesmos detectores dos donos (e delega ao `ds-guard --report`), escreve
`memory/requisitos/<Mod>/INCONSISTENCIAS-replica.md` (a lista humana) e
`governance/replica-inconsistencias/<mod>.json` (o estado por item). Exit 0 quando mediu; 2 quando
**não conseguiu medir** — "não medi" nunca vira verde (§5 2026-07-29).

Cada item tem `status`: `aberta` (o Code resolve depois **sem mudar o layout**), `aceita` (só [W]:
é decisão de design, fica visível sem alarmar) ou some quando a medição seguinte não o encontra.
Ninguém apaga linha à mão nem relaxa a regra no dono para o item sumir.

### D-3 — O que CONTINUA bloqueando, e por quê não é contradição

- **Tier 0** ([ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md)): multi-tenant, PII, fiscal, valor/estoque.
  Layout não toca nisso; réplica não é passe.
- **Higiene de registro no mesmo PR**: allowlist do `foundation-guard`, baselines de ratchet
  regravados com a dívida **nomeada** no corpo do PR, `.snap` da regressão visual regravado quando
  a tela muda, casos/charter reconciliados (precedência *teste > casos > charter > SPEC*, §Regra de
  Precedência). São recibos do que mudou, não vetos ao que muda. A lista de inconsistências é o
  lugar onde a dívida fica visível — regravar baseline sem ela é esconder.
- **Advisory continua podendo ficar vermelho** ([ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)):
  esta ADR não pinta gate de verde; ela tira o **veto** de quem media forma, e dá um destino ao
  achado.

### D-4 — Gates que vetavam forma ganham modo relatório

`ds-guard.mjs --report` imprime o mesmo achado e sai 0. `ui:lint` R1/R3/R4 seguem como estão no
CI (`ds-gate`, advisory); a lista os espelha porque o worktree do agente raramente tem PHP e o
agente precisa ver o achado **antes** do PR. Nenhuma regra é apagada; a mordida vira dado.

### D-5 — O que esta ADR NÃO autoriza

- Apagar gate, script ou baseline. A pilha continua medindo; o que muda é o **destino** do achado.
- Promover ou demover `required` — isso segue [ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md), flip de [W].
- Copiar o protótipo **sem** gerar a lista. Réplica sem lista é o que [W] descreveu: *"simplesmente
  não funciona e nem sei o que fazer"*. A lista é parte da entrega, no mesmo PR.
- Usar "é réplica" para tocar comportamento (rota, permissão, dado, cálculo). A ADR é de aparência.

## Consequências

- **Positivas:** a cópia fiel deixa de ser impossível por construção; cada módulo ganha uma lista
  de dívida de DS com dono e contagem, derivada e regenerável (ADR 0256: derivado sobrevive); [W]
  passa a ver **o que aconteceu** em vez de um gate mudo.
- **Custo declarado:** a dívida de conformidade passa a existir por escrito e cresce a cada réplica
  — é exatamente o que ela já era, só que agora contada. A régua de "cor crua zero" deixa de valer
  como estado e passa a valer como direção.
- **Primeira lista real:** [`memory/requisitos/Forja/INCONSISTENCIAS-replica.md`](../requisitos/Forja/INCONSISTENCIAS-replica.md),
  gerada no PR desta ADR contra as 34 telas de produção da Forja (`origem = aplicado`) e contra os 5
  `.jsx` + o CSS do protótipo (`origem = prototipo` — o que vai entrar nas ondas 1–10 do [PARIDADE §11](../requisitos/Forja/PARIDADE-area-forja-diagnostico-e-ondas.md)).

## Ratificação

Nasce `aceito` porque a decisão é de [W], textual e datada (2026-09-02), e o próprio PR que a
registra já liga a máquina que depende dela (painel, `ds-guard --report`, reporter) — o
`memory-health` Check L reprova ADR `proposto` citada por código que roda. Mesmo precedente da
[ADR 0384](0384-design-sync-recibos-executaveis-por-tela.md). O merge de [W] deste PR é o ato.
