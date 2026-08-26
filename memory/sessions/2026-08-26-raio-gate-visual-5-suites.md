# 2026-08-26 — O raio do gate visual existia e alcançava 1 das 5 suítes de baseline

> **Origem:** [W] apontando o defeito na cara — *"a regra do baseline está errada, tudo que tento
> mudar ele pega. adicionar uma coluna em tela deveria ser normal"* e, depois, *"carimbo de merda
> que pega tela que nem mexi"*. Estava certo nas duas.

## O que foi feito

**[PR #6280](https://github.com/wagnerra23/oimpresso.com/pull/6280) — mergeado por [W] às 11:26Z, `3ee2378d03`.**

A partição dívida PRÓPRIA × HERDADA do gate visual (criada no #6188, lápide §5 2026-08-24) estava
correta e testada — mas o campo `source`, **único** por onde ela decide, só era passado por **1 das
5** suítes de baseline. As outras 4 (`IsolatedStates`, `FinanceiroFlow`, `ComprasFlow`,
`SellsCreateFlow`) caíam no ramo fail-closed, e o docblock registrava isso como se fosse desenho.

Conserto: `source` declarado nos 3 manifestos, as 4 suítes passando `screenSource`,
`screenSourceFromCharter()` exportado do `ui-impact.mjs` (dono do vocabulário — sem 2ª
implementação do mapa charter→tela), e os 3 lints conferindo declarado == derivado com bite-test
dos dois lados. Os lints já rodavam no CI dentro do próprio `visual-regression` — nenhum workflow
novo.

## A medição que sustentou (13 runs de `visual-regression`, 25–26/08)

12 tinham zona cinza. Em **11 delas (91,7%)** o único item bloqueando estava **fora do raio do PR**
— sempre `financeiro-unificado · estado={default,loading,error}` a **0.1199%**, em **5 branches**
distintas (raios: `Modules`, `Jana`, `Arquivos`, `governance/DriftAlerts`). O único bloqueio
legítimo do lote (`Arquivos → 0.1047%`, dentro do raio) veio da suíte que **já tinha** `source`.

Ratio idêntico a 4 casas **antes e depois** do rebake das 27 baselines (#6275) refuta flake e
refuta base velha.

## Duas doenças diferentes, não uma

| tela | natureza | rebake resolve? | dono |
|---|---|---|---|
| `Arquivos` | baseline **velha** — o redesenho da onda Arquivos mergeou e o #6275 **não** incluiu essa tela | sim | [#6283](https://github.com/wagnerra23/oimpresso.com/pull/6283), que está editando `Arquivos/Index.tsx` agora |
| `financeiro-unificado` (3 estados) | render **não-determinístico** | **não** — o #6275 regravou as 5 e voltou idêntico | task aberta (abaixo) |

Evidência do `financeiro-unificado`, **lida nos PNGs** do artifact `pixel-diff-views`, não deduzida:
a linha semeada aparece com vencimento **06/06 · "Atrasado"** onde a baseline tem **11/06 ·
"Vencendo"**. O `VisregFinanceiroFlowSeeder` grava o literal `2026-06-11`, então há um **2º
escritor de `fin_titulos`** com data relativa a `Carbon::now()` (= `testNow − 5d`) — e o docblock
daquele seeder (17/08) afirma o contrário, o que o render desmente.

## Erro meu no meio, corrigido antes de custar

Disparei `workflow_dispatch` do modo update pra rebakear a baseline de `Arquivos` em main e
**cancelei** ao ver que o #6283 estava editando aquela tela. Nenhum PR órfão foi criado. Eu tinha
checado PRs abertos tocando os arquivos do *gate* e o `.snap`, mas não os PRs tocando a *tela* —
é a §5 2026-08-13 (colisão em sintoma acusado por máquina compartilhada) num eixo que eu não olhei.

Também perdi minutos perseguindo `mergeStateStatus: UNKNOWN` do #6280: a resposta era que [W] já
tinha mergeado e o GitHub apagara a branch. Eu estava medindo um PR que não existia mais como PR
aberto — em vez de perguntar ao estado, fiquei repetindo a sonda.

## Contexto que mudou no meio da sessão (decisão [W], outra sessão)

`visual-regression` **saiu do required** ([#6278](https://github.com/wagnerra23/oimpresso.com/pull/6278)),
já aplicado no vivo — conferi a união protection clássica ∪ ruleset (45 contexts) e ele não está em
nenhum dos dois. O workflow segue rodando, advisory. Isso **aumenta** o valor do #6280, não diminui:
advisory que grita por tela alheia vira ruído que todo mundo ignora.

## A raiz, medida e ainda aberta

`visual-regression` só dispara em `pull_request` — **não tem `push: main` nem `schedule`**. Dívida
de baseline em main é invisível por construção: só aparece quando o próximo PR inocente fica
vermelho. Foi assim 3 vezes em 3 dias — `Governance/DsRollout` → `financeiro-unificado` →
`Arquivos`. Rodar o gate em main (nightly) é a peça que falta; é decisão [W] (*o quê* construir),
não foi feita.

## Registro

- **§5** ([`memory/licoes-rejeitadas.md`](../licoes-rejeitadas.md)) — lápide 2026-08-26, derivada
  pro `proibicoes.md` com `sec5-derive.mjs --write`
- **[LC-27](../LICOES_CODE.md)** — Ocorrências 3 → 4, com o refinamento da regra: *quando um
  parâmetro **opcional** carrega a decisão inteira de um gate, `null` não é valor ausente, é o
  gate desligado — a auditoria é "quem passa esse parâmetro? N de N"*

## Placar do #6280

111 de 112 checks verdes · **45 required (união clássica ∪ ruleset) todos verdes** · `Frontend /
Vite build` ✅ · `TypeScript` ✅ · `ESLint` ✅ · `Stylelint` ✅. Único vermelho: `visual-regression`
(advisory), pelo `Arquivos` acima somado ao fato de este PR editar os próprios arquivos de contrato
do gate — que injetam o CSS de estabilização e portanto **mudam o pixel capturado**, logo
`raio_confiavel: false` e bloqueio absoluto, que ali está **correto**.
