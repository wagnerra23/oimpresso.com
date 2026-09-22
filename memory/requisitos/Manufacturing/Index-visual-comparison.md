---
id: requisitos-manufacturing-index-visual-comparison
title: "Comparacao design x producao — Manufacturing/Index (Ordens de producao)"
module: Manufacturing
tela: Manufacturing/Index
owner: W
status: rascunho
# `inertia_target` + `last_updated` sao o que poe este doc SOB VIGILANCIA: o
# `visual-comparison-staleness.mjs` compara a data declarada aqui com a data-git da tela
# apontada (regex `^inertia_target:\s*["']?(\S+?\.tsx)`, datas em last_updated/date/updated_at).
# Sem os dois campos, o doc cai no balde "nao-avaliado" e envelhece calado.
inertia_target: resources/js/Pages/Manufacturing/Index.tsx
last_updated: "2026-09-22"
---

# Comparação design × produção — `Manufacturing/Index` (Ordens de produção)

> **Primeiro registro desta tela, e o primeiro do módulo.** Até 2026-09-22 o
> `memory/requisitos/Manufacturing/` tinha **0** arquivos `*visual-comparison*`, contra **87** no
> resto do repo. As 5 telas da família nasceram sem ele — cada sessão que perguntava "o que já foi
> comparado aqui?" redescobria do zero.

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Index --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-producao.jsx
```

O desenho desta tela é o `MfgProducaoView` (`:31` do protótipo), declarado em `related_prototype`
do [`Index.charter.md`](../../../resources/js/Pages/Manufacturing/Index.charter.md).

**Mudou em 2026-09-22** ([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão
[W]): a família passou a ancorar no `handoff_fabricacao` do Felipe. Antes desse PR esta tela era a
**única** das 5 a resolver pelo `bundle_source` — que casa por **basename** (`ancora.mjs:512`,
`mockupJsx` extrai só `*-page.jsx`) — e havia 3 cópias de `manufacturing-page.jsx` no staging, de
modo que a âncora era o primeiro da varredura.

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor, e é estrutural.** `ancora.mjs:93` tem
`LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`; apontando para `Felipe/`, a linha
`✓ frescor: verificado contra o Cowork vivo` **não é emitida**. Medido antes e depois do #7696.

## O que este documento NÃO é

**Não é veredito de paridade visual.** Nenhuma sonda de DOM foi injetada nesta sessão. O que está
abaixo é o **inventário do que já foi medido por outros**, com data e recibo — e do que nunca foi.

## O que JÁ foi medido

Rodada de [#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (*"as 77 telas executáveis
medidas contra o staging"*), artefato
[`governance/design/targets/medidas/Manufacturing--Index/`](../../../governance/design/targets/medidas/Manufacturing--Index/resultado.json):

| | |
|---|---|
| `medidoEm` | `2026-09-18T12:39:49Z` · `urlFinal` `https://staging.oimpresso.com/manufacturing/production` |
| `source` | `manufacturing-page.jsx` — **o hub do módulo, não o desenho desta tela** |
| veredito | **DIVERGE (bug)**, `rc=1`, 1 bug |
| `sameTheme` | **`false`** |

| dim | campo | prod | design | veredito |
|---|---|---|---|---|
| D4 | título font-size | **24px** | **22px** | ❌ **DIVERGE (bug)** — Δ2px, banda ±1px |
| D2 · D6 · D8 · D9 | layout · cor · kpi align · texto | ok | ok | IGUAL |
| D4 | linha da tabela | — | — | SEM-DADO (`tableRow` ausente no `__DD_ROLES`) |
| SHELL ×4 | atalhoTopo · containerMenu · grupoHeader · itemGrupo | — | — | SEM-DADO (seletor não casou) |

**Leia o denominador, não o rótulo:** de **11** células, **5 IGUAL · 1 DIVERGE · 5 SEM-DADO**.

⚠️ **Essa medição CADUCOU em dois eixos** e precisa ser refeita: mediu contra a fonte **antiga**
(`manufacturing-page.jsx`, e ainda por basename), e rodou com **`sameTheme: false`** — tema
diferente nos dois lados, o que pelo [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
invalida o veredito. O Δ2px do título pode ser real ou artefato; hoje não dá para dizer qual.

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Index.charter.md` — **`status: draft`** |
| casos | ✓ **5 UCs** em `Index.casos.md` |
| scorecard | ✓ `memory/governance/scorecards/screens/manufacturing-index.yaml` |
| e2e / browser | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** (104 no repo, nenhum do módulo) |
| contrato de tela | ✗ nenhum |

## Pendências

1. **Re-medir** com a âncora nova e `sameTheme: true` — é o que decide o Δ2px.
2. Os 3 achados do [LAUDO do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md)
   valem para a família: 2 ALTA de contraste (`--text-mute` reprova AA; `--accent` como texto mede
   **2,64:1** no tema escuro) + 1 MÉDIA de layout. As duas ALTA são **token do DS** (ADRs 0410 e
   0411), decisão [W] — não se conserta nesta tela.
3. `status: draft` → `live` só depois de (1).

## Refs

- [`Index.charter.md`](../../../resources/js/Pages/Manufacturing/Index.charter.md) · [`Index.casos.md`](../../../resources/js/Pages/Manufacturing/Index.casos.md) · [RUNBOOK-producao.md](RUNBOOK-producao.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** (§0: *"o protótipo é ilustrativo"*)
- [SDD-tela-fabricacao-v1.0.md](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md) · [CHECKLIST-15D](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/CHECKLIST-15D-fabricacao.md)
