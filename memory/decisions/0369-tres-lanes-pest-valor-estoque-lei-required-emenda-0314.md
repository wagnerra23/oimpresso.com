---
slug: 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314
number: 369
title: "Emenda à 0314 — Compras, Estoque e Ponto (Pest MySQL) promovidos a REQUIRED (valor/estoque + obrigação legal, com mordida provada no main)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-05"
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, pest, mysql, compras, estoque, ponto, branch-protection, tier-0, valor-estoque, portaria-671]
supersedes: []
superseded_by: []
related: [0314-poda-gates-onda-2-lei-fusoes, 0336-gates-design-promocao-por-mordida-provada-emenda-0314, 0354-teammcp-pest-required-emenda-0314, 0347-deadlink-gate-required-emenda-0314, 0298-teto-de-governanca-anti-proliferacao-gates, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0101-tests-business-id-1-nunca-cliente]
---

# ADR 0369 — Emenda à 0314: `compras-pest`, `estoque-pest` e `ponto-pest` promovidos a REQUIRED

## Contexto

A [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) fixou **"required = só Tier-0"**. As emendas
[0327](0327-anchor-content-required-emenda-0314.md), [0347](0347-deadlink-gate-required-emenda-0314.md),
[0348](0348-briefing-coverage-required-emenda-0314.md), [0354](0354-teammcp-pest-required-emenda-0314.md)
e outras abriram exceções pelo mesmo processo: emenda reabrindo a 0314 pro item + flip [W].

**O gatilho não foi auditoria de gate — foi um efeito colateral.** Na sessão de 2026-08-05 o
`memory-health` ganhou o warn `[M] advisory-prazo-vencido`, fechando uma **delegação órfã**: o
Check M dizia há muito *"o vencimento ≤14d é cobrado pelo ZELADOR, não aqui"*, e o ZELADOR —
vivo, diário às 07:08 — tinha **zero ocorrências de `promote_by`** no charter e no `SKILL.md` que
executa. Ninguém nunca lhe disse que o item era dele. Com o aviso ligado, apareceram **16 de 30**
gates advisory com prazo vencido, o mais velho há 20 dias. Estes três são o subconjunto que
**merece required**; os outros 13 só ganharam prazo novo com razão escrita.

## Sinal de custo (medido em 2026-08-05, não estimado)

Contado com `gh run list --limit 100` + classificação pelo **step que falhou** (não pelo texto do
log — ver §Ressalva):

| lane | mordidas | infra | branches distintas |
|---|---:|---:|---|
| `compras-pest.yml` | **6** | 0 | `main` + `claude/scope-purpose-reconciliacao` |
| `ponto-pest.yml` | **5** | 0 | `main` + `claude/scope-purpose-reconciliacao` |
| `estoque-pest.yml` | **2** | 0 | `main` + `claude/uc-exec-backed-titulo` |

Todas as 13 são o step `Run Pest (<Mod> · MySQL) — ALLOWLIST VERDE (catraca)` reprovando: **teste
vermelho, não runner caído**. Satisfaz o **DR-2** da [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)
(≥2 mordidas reais) com folga.

**O agravante que decide:** as três falharam **no `main`**. Gate advisory que fica vermelho no
`main` é, por definição, um gate que **não segurou** — a regressão entrou e o vermelho virou
paisagem. É a diferença exata entre este caso e o do `foundation-ratchet`, que a 0314 demoveu
por **0 failures em 300+ runs**.

## Por que estas três são Tier-0 (e não "quality")

- **`estoque-pest`** — a lane declara no próprio nome que cobre *"movimentação de saldo
  (venda/compra/devolução) no MySQL real; skip no sqlite = verde mente"*. Cai direto na
  **regra-mestre de VALOR ou ESTOQUE** de `memory/proibicoes.md`, que é Tier 0 IRREVOGÁVEL.
- **`compras-pest`** — compra move estoque e custo; mesma regra-mestre.
- **`ponto-pest`** — marcação de ponto é **append-only por força de lei** (Portaria MTP 671/2021,
  Anexo I). Não é preferência de qualidade: é obrigação legal com fiscalização.

**Precedente de classe:** `PHP / Pest (Financeiro · MySQL)`, `PHP / Pest (NfeBrasil · MySQL)` e
`PHP / Pest (Unit)` **já são required**. Lane Pest MySQL como required é prática estabelecida
nesta casa desde a 0354 — não invenção desta ADR.

## Required-readiness verificada ANTES do flip (anti-deadlock)

O incidente de 2026-07-02 (registrado em `memory/proibicoes.md` §Ambiente) deixou o `main`
**deadlockado** porque contexts required não eram satisfeitos por check-run nenhum. Aqui a
condição foi verificada, não presumida: as três lanes declaram `pull_request` **SEM `paths`**
(always-run) e usam `dorny/paths-filter` **interno** para skip-as-pass — o desenho que a
[ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) onda 2 chamou de
*"required-readiness"*, textualmente no cabeçalho dos próprios workflows. Elas **rodam em todo
PR** e passam em segundos quando o módulo não mudou. São required-safe por construção.

O nome dos 3 contexts carrega `·` (U+00B7). O baseline foi escrito por Node (UTF-8 sem BOM) e os
bytes conferidos (`c2b7`), e o flip deve usar `gh api --input <arquivo>` — **nunca** payload inline
no shell do Windows, que produz o mojibake que causou o deadlock de 07-02.

## Decisão

Promover a **required** os 3 contexts, elevando 34 → 37 no `classic_protection`:

- `PHP / Pest (Compras · MySQL)`
- `PHP / Pest (Estoque · MySQL)`
- `PHP / Pest (Ponto · MySQL)`

No `gates-registry.json`, `terminal` passa de `advisory` para `required` nos três, e o
`promote_by` sai (required não tem prazo de promoção).

Os outros **13** vencidos **NÃO** são promovidos — ganham prazo novo com razão escrita:
7 estão no padrão `foundation-ratchet` (**0 falhas em ≥100 runs** — nunca provaram que mordem),
4 têm amostra pequena demais (5 a 28 runs), e 2 (`module-surface`, `catalog-graph`) mordem de
verdade mas guardam **índice derivado**, não Tier-0 — required ali exigiria emenda própria.

## Ressalva de método (registrada de propósito)

A primeira medição desta ADR classificou **13/13 como "infra"** e teria derrubado a promoção. O
classificador olhava o **texto do log inteiro**, e o teardown do container MySQL (`Stop and remove
container`, `docker rm --force`) casa com qualquer padrão de infra. O discriminador correto é o
**nome do step que falhou** — `Run Pest …` = mordida; `checkout/setup/service` = infra. Trocado o
instrumento, o resultado inverteu para **13/13 mordida**.

Fica registrado porque é a mesma classe (**LC-08**, medir pela fonte errada) que a sessão inteira
caçou nas máquinas — e aqui ela quase produziu a decisão oposta à correta.

## Consequências

**Positivo:** (a) regressão de valor/estoque/ponto passa a **bloquear merge**, não a decorar o
painel; (b) o `promote_by` deixa de ser decoração — 3 dos 16 vencidos viraram decisão em vez de
adiamento; (c) a lane já rodava em todo PR, então **não há custo novo de CI** — só o efeito de
bloqueio.

**Custo/risco:** (a) PR que quebrar uma dessas 3 lanes **para** até consertar — é o objetivo, mas
aumenta o atrito em módulo com dívida de teste; (b) as 3 lanes têm allowlist própria, então o
verde depende da lista estar honesta (a catraca `ALLOWLIST VERDE` já cobre isso); (c) flake vira
bloqueio — se aparecer, o caminho é consertar o teste, **nunca** afrouxar a allowlist.

**Gate de reversão:** se qualquer uma das 3 acusar flake não-determinístico em ≥2 PRs distintos
sem defeito real de produto, rebaixar aquele context a advisory por emenda a esta ADR (append-only,
nunca edição), e registrar o flake como dívida com dono.

## Alternativas consideradas

- **(A) Promover os 16** — rejeitado: 7 têm **0 falhas em ≥100 runs** (padrão exato que a 0314 usou
  para demover o `foundation-ratchet`) e 4 têm amostra insuficiente. Promover gate que nunca mordeu
  é armar alarme que não pode tocar.
- **(B) Não promover nenhum e só estender os 16** — rejeitado: adiar sem decidir é o "advisory
  eterno" que a [0298](0298-teto-de-governanca-anti-proliferacao-gates.md) existe para impedir, e
  aqui há mordida provada **no main** em três lanes que tocam dinheiro, estoque e lei.
- **(C) Promover também `module-surface` e `catalog-graph`** (7 e 6 mordidas) — adiado: mordem, mas
  guardam índice derivado. Merecem emenda própria com o argumento próprio, não carona nesta.
