---
date: "2026-07-17"
topic: "Grade de réguas — a folga do module-grades, o veredito que mentia e o pipeline que lia 20% da evidência"
authors: [C]
prs: [4384, 4394, 4398]
outcomes:
  - "Lock de 123pts de folga no baseline module-grades (28 módulos) — 69% do ganho era mudança de grader, provado por contrafactual rodado"
  - "Gate parou de chamar ganho não travado de all clear (corte de materialidade ≥3pts)"
  - "Bug achado: fase Grade do reguas-do-sistema descartava ~80% da evidência em silêncio"
  - "Grade v2 com 11 dimensões / 3 eixos: média 5,5 · servir-o-negócio 3,75 · ratio negócio÷governança 3,33× (alarme:true)"
related_adrs:
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
---

# Grade de réguas 2026-07-17

Sessão que começou na folga da catraca do `Module Grades Gate` e terminou achando um bug
no pipeline da própria grade que a mede.

## O que foi feito (3 PRs, todos verificados)

| PR | O quê | Estado |
|---|---|---|
| [#4384](https://github.com/wagnerra23/oimpresso.com/pull/4384) | Lock do baseline module-grades — 28 módulos, 123pts de folga travados | mergeado (`9855260a2d`) |
| [#4394](https://github.com/wagnerra23/oimpresso.com/pull/4394) | Gate para de chamar ganho não travado de "✅ all clear" | mergeado (`5581d8fa3e`) |
| [#4398](https://github.com/wagnerra23/oimpresso.com/pull/4398) | Fix da truncagem silenciosa no `reguas-do-sistema.js` | aberto |

Artifact da grade: `claude.ai/code/artifact/f775b9b5-2346-4119-91b1-518ae77f02f7`

## Achado 1 — 69% do "ganho" do module-grades não era ganho

O baseline estava 50 dias defasado. O gate reportava `✅ all clear` com 28 módulos 🟢 up e
123pts de folga: `Compras` podia cair 73→58 sem o gate piscar.

**Decomposição por contrafactual RODADO** (não estimado) — `module:grade --all --json` no
CT 100 duas vezes contra o MESMO código, uma com o `ModuleGradeService.php` do HEAD e outra
com o de `d64b31045a^`:

- **85 dos 123pts (69%) = instrumento**, não trabalho. Causa: [#3833](https://github.com/wagnerra23/oimpresso.com/pull/3833)
  (2026-07-05, DEPOIS do baseline) corrigiu 2 falso-negativos do grader — D8.a passou a ver
  `throttle` em array (forma canônica UltimatePOS) e D4.d passou a ver `OtelHelper`/`Activitylog`.
  O commit declara: *"Detecção só ADICIONA caminhos: nota de nenhum módulo pode cair"* —
  monotônico crescente **por construção**, o que explica a assinatura anômala de 36/36 subindo
  com zero caindo.
- **14 módulos subiram com ZERO trabalho** (Woocommerce +6, Connector +6, Spreadsheet +6,
  AssetManagement +5, Whatsapp +5, Arquivos +5, Cms +5, Admin +3, Manufacturing +3, KB +3,
  ProductCatalogue +3, SRS +3, Fiscal +3, Brief +2).
- Travar segue **legítimo** — o #3833 corrige cegueira do instrumento, não infla medição (o
  throttle está de verdade na rota). Mas "28 módulos melhoraram" é **falso**.

## Achado 2 — o CT 100 mede ~+1 acima do CI (e isso muda o "como travar")

**Prova:** os 8 módulos que o CI reporta `eq` são EXATAMENTE os 8 que o CT 100 media +1 com
efeito-de-instrumento zero. **Jana é o controle limpo:** travado em 73 pelo [#4194](https://github.com/wagnerra23/oimpresso.com/pull/4194)
com *"trava medicao do CI"*; CT 100 mede 74, sem trabalho no módulo no intervalo.

Consequência: **travar com número do CT 100 deixaria o piso acima do que o CI consegue medir**
→ vermelho em PR que nem toca o módulo — o modo de falha que causou os rebaselines
v3.5.1/2/3/4 ("destrava gate stale"). O lock usou medição do CI. Registrado no
`last_update_pr` do baseline (git canônico).

## Achado 3 — o gate não mordia menos; o veredito mentia

O comentário do #4378 dizia, no mesmo texto: `✅ all clear` **e** `🟢 28 melhoraram`. O dado
sempre esteve na tabela — faltou o gate chamar 123pts de dívida pelo nome. Não é "gate que não
morde" (é advisory por decisão da [ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md) D-1,
e continua sendo): é **label que mente sobre o estado**.

**Inventário antes de propor mecanismo** (matou a proposta de cadência):

| mecanismo | quando | compara com |
|---|---|---|
| `module:grade-snapshot` | cron daily 06:05 (Kernel:359) | **nada** — só persiste histórico |
| `governance:scorecard-snapshot --alert` | cron daily 07:00 | **ontem** (≥5pts), scorecard v4 |
| `module-grades-gate.yml` | **todo PR** | **o baseline** — único que lê |

Não faltava medição nem detecção. Faltava o veredito. Fix (#4394): ganho ≥3pts vira
`🔓 N ganho(s) não travado(s)`. **Corte ≥3 não é arbitrário** — fica acima do drift natural
(±1-2pp) e abaixo do que mudança de grader produz (#3833 deu +2..+6), que é o vetor cego do
modelo reativo (reclassifica N módulos sem tocar em nenhum). Provado com 5 fixtures rodadas
no CT 100 contra o **bloco PHP REAL extraído do workflow** (réplica seria tautológica).

## Achado 4 (o mais grave) — a grade lia 20% da evidência e saía com cara de completa

Run `wf_5ae5c554-67f`: 84 agentes, 10,5M tokens, 11 dimensões pesquisadas. A fase `Grade`
truncava as 4 entradas com `.slice()` cego:

```
pesquisas    175.975 → 38.000  (-78%)
verificadas  114.969 → 18.000  (-84%)   ← só 5 das 24 fraquezas visíveis
refutados     65.671 →  9.000  (-86%)
integrados    69.227 → 12.000  (-83%)
```

**Sintoma que denunciou:** logs diziam `11/11 dimensões pesquisadas`; cabeçalho declarava **3**.

**O que sumia não era aleatório** — eram os eixos RODAR-E-OBSERVAR ([ADR 0333](../decisions/0333-emenda-0330-eixo-rodar-e-observar-submedido.md))
e SERVIR-O-NEGÓCIO (ADR 0334). A síntese reproduzia o ponto cego que essas ADRs existem pra
fechar. Violava a regra do próprio tool Workflow (*"No silent caps: log() what was dropped —
silent truncation reads as 'covered everything' when it didn't"*).

Fix (#4398): limite folgado (corpus real 460k cabe) + helper `fit()` que **loga** o que cortar
+ prompt exige cobertura dos 3 eixos. Re-rodado via `resumeFromRunId` (cache das 83 pesquisas:
**316k tokens vs 10,5M**). Antes: 3 dimensões / 15 fraquezas / 14 do mesmo eixo. Depois:
**11 dimensões / 12 fraquezas / 3 eixos**.

## A grade (v2, corpus inteiro)

**Média 5,5** — construir-e-governar **6,3** · rodar-e-observar **5,4** · servir-o-negócio **3,75**.

De 24 claims: **1** acima-de-categoria · **19** à-frente-por-integração · 7 empatadas · 7 refutadas.

| eixo | dimensão | nota |
|---|---|---|
| Construir | design-to-code · memória-conhecimento | 7,0 |
| Construir | spec-governança · orquestração adversarial | 6,0 |
| Construir | evals-outcome | 5,5 |
| Rodar | observabilidade-agente | 6,5 |
| Rodar | custo-eficiência | 6,0 |
| Rodar | segurança-do-agente | 5,0 |
| Rodar | qualidade-drift-ia-producao | 4,0 |
| Servir | erp-ia-produto | 4,5 |
| Servir | **inteligencia-de-negocio** | **3,0** |

**A tese:** 4 semanas · 1311 merges · negócio 233 × governança 776 · **ratio 3,33× · `alarme: true`**
(77% do fluxo). Tendência: mai 38% → jun 64% → jul **78%**.

**O alarme nunca foi ligado** — `negocio-vs-governanca-ratio.mjs` aparece 3× fora de `memory/`:
a skill, o workflow da grade, e o próprio unit test. **Zero cron.** A ADR 0334 promete sentinela
semanal; os 3,33× só existem porque um verificador rodou na mão. É a lápide do "chokepoint
fantasma" (`flag:set`) reincidindo.

**Top 3 que mudam o placar e custam quase nada:** liberar modelo frontier + fallback (a config
já prevê, `config.php:531` — é conta de fornecedor) · pendurar o ratio no `brief-fetch` (6×/dia,
caminho vivo provado, sem gate) · heartbeat `langfuse_trace_uptime_24h` no `HealthCheckCommand`,
espelhando o `brief_uptime_24h` que já está lá.

## Ressalva de honestidade

Os PRs de hoje **não foram creditados** pela grade, e creditar **não ajudaria**: #4384 e #4394
são governança pura — entram nos 776, não nos 233. Esta sessão inteira, inclusive achar o bug
da truncagem, **é o 77%**.

Causa do não-crédito: o `args` da Skill não chega aos agentes (o script só aceita
`base`/`dimensoes`). A fase Grade credita lendo o repo — creditou 10 itens até 07-14, mas não
os de horas atrás.

## Pendências

- **Cadência do baseline**: [W] adiou conscientemente. O inventário sugere que o #4394 já cobre
  o vetor (o gate avisa sozinho). Reabrir só se a folga voltar mesmo com o aviso.
- **`memory/reguas/YYYY-MM-DD-notas.json`**: regra 12 da skill exige artefato versionado pra Δ
  entre retratos ser auditável. Não existe — retratos vivem em artifact/session log. Formato
  pendente de decisão [W].
- **C4 da grade**: cron pro `negocio-vs-governanca-ratio` (barato: 1 cron; o alarme existe e
  nunca dispara).
- Worktree base da grade: `.claude/worktrees/_reguas-base-20260717` (sem junction — seguro pra
  remover).
