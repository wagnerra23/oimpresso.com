---
id: sessions-2026-08-11-prototipo-jana-no-git-e-a-defesa-que-era-a-causa
title: "Protótipo da Jana desce pro git — e a defesa contra o erro era a causa dele"
topic: "Fonte de design do Painel da Jana versionada + 4 defeitos de processo do espelho Cowork consertados com mordida provada"
type: session
status: concluido
authority: informational
lifecycle: ativo
kind: session
date: "2026-08-11"
module: governance
tags: [design, prototipo, cowork, designsync, espelho, jana, contrato-visual, lc-08]
---

# Protótipo da Jana desce pro git — e a defesa contra o erro era a causa dele

**PRs mergeados:** [#5551](https://github.com/wagnerra23/oimpresso.com/pull/5551) (contrato visual + baseline) · [#5572](https://github.com/wagnerra23/oimpresso.com/pull/5572) (protótipo no git + 4 consertos de processo, `9aeb66a0a89`)

## O que o [W] pediu, e como o pedido mudou

Começou como *"pode olhar"* (smoke pós-merge) e virou, em três frases dele, uma
revisão do processo inteiro:

> *"não sei se estamos vendo a mesma tela"* → *"essa é a tela do prototipo. todas as
> outras referencias estão erradas"* → *"acho que tem muita coisa para ser arrumada
> nesse processo"* → *"arrume e teste todo processo"*

A razão que decidiu o mérito veio depois e é **operacional**, não jurídica:

> *"vai ter computadores que não vão ter acesso ao design dessa máquina. e precisarão
> trabalhar só com o git. e eu não vou ter acesso ao claude deles e vou trabalhar no
> git. **por isso baixar para git sempre**"*

## O achado central: canon negando canon

`jana-merge.jsx` — a fonte de design do Painel da Jana — vivia **só** no projeto Cowork.
Era citado por **21 sites do repo** (charter, 2 `.tsx` de produção, workflow,
`gates-registry.json`, RUNBOOK, testes Pest e vitest) e **não estava versionado**.

A cadeia de como isso sobreviveu:

| quando | o quê |
|---|---|
| 08-07 | proposal (mergeada) diz: *"ele NÃO ESTÁ NO GIT e ESTÁ NO DESIGNSYNC — as duas coisas são verdade e não conflitam"* ✅ |
| 08-09 | proposal (mergeada) diz o oposto: *"não existe em nenhum dos dois donos (listei os dois)"* ❌ |
| 08-10 | lápide §5 construída sobre a negação — e sua regra `biz=NNN` passa a **banir o protótipo certo** |
| 08-11 | eu herdo a lápide e repito a negação |

**Nenhum gate pega documento contradizendo documento.** `git grep jana-merge` devolvia
21 sites o tempo todo — o oráculo custava um comando.

## A defesa era parte da causa

O hook `design-agente-ativa` existe **exatamente** para impedir "declarar que o protótipo
não existe". Ele mandava, textualmente, rodar `DesignSync{list_projects}` **como prova**.

Medido com `get_project`: aquele tool enumera **só projetos do tipo design-system**, e o
protótipo do ERP vive num projeto **REGULAR** (`type: PROJECT_TYPE_PROJECT`, id
`019dcfd3-…` — o mesmo que o `cowork-mirror-freshness.mjs` declara no cabeçalho há meses).

A reincidência aconteceu **com o hook ativo**, porque ele prescrevia o comando que produz
o falso negativo.

## Os 4 defeitos de processo consertados (bite + controle negativo, mutações rodadas)

1. **Espelho cego (LIVE-ONLY).** `buildManifest` monta o universo pelo lado do espelho;
   o que nunca desceu é invisível **por construção** (`LIVE-ABSENT` cobre só o inverso).
   `liveOnly()` + `--live-only`. Medido: **25 de 1310 paths fora, 14 protótipos de tela**.
2. **Export por transcrição.** `exportPlan()` + `--export-from`, que escreve `raw.content`
   do JSON. Controle positivo: re-export deu `inalterado` + `git diff` vazio.
3. **`ancora.mjs` imprimia `âncora ✓: n/a`** — o `✓` para a ausência de âncora.
   `ehDeclaracaoNa()`. **Não virou gate**: 135 dos 158 charters usam `n/a` legitimamente.
4. **Hook corrigido** para os 3 donos + aviso de que `list_projects` não é prova.

**Mutações executadas** (com guard que aborta se o `replace` não casar — senão o "não
mordeu" é do instrumento): `liveOnly` sempre-vazio → `rc=1` · `shouldFail` mudo → `rc=1` ·
`exportPlan` sem guard → `rc=1` · `ehDeclaracaoNa` sempre-false → `rc=1`.

⚠️ **Um assert meu nasceu fraco e a mutação provou:** com `catch → lancou=true`, remover o
guard do `exportPlan` **ainda passava**, porque `Buffer.byteLength(undefined)` lança
sozinho e mascarava o chokepoint. Agora confere a **mensagem**.

## Governança

- **[ADR 0374](../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md)** (emenda à 0315): espelhar o projeto Cowork → git é a rota
  **prevista**; o *"nunca o inverso"* vale para o **Design System**. O script canônico já
  fazia isso há meses — texto e prática em contradição, e foi nela que tropecei.
- **§5 `proibicoes.md`:** 4 lápides. A principal **revoga a regra `biz=NNN`** de 08-10.
- **Errata em append** na proposal de 08-09 (apagar esconderia como o erro se propagou).
- **`LICOES_CODE`:** LC-08 79 → 81.

## Meus erros, na ordem em que aconteceram

1. Declarei ausência com `list_projects` (fonte que não cobre o universo).
2. Exportei a cópia **errada** (há duas no vivo: raiz 943 ln × `prototipo-ui/cowork/` 923 ln;
   o manifesto mapeia a **raiz**) — por **transcrição manual**, o que o `--compare` acusou
   como `STALE`.
3. Medindo o charter contra essa versão errada, **"corrigi" refs que estavam CERTAS**:
   escrevi que `JM_KPI_DRILL` não existia e que `JmDrillDrawer` estava na 636. No arquivo
   certo são **`:887` e `:640`** — exatamente o que a v3 dizia. **LC-08 dentro da correção
   de um LC-08.**
4. Montei as substrings da allowlist a partir do **log do gate** — que já vem com o valor
   redigido (`R$ <valor>`) — em vez do arquivo. 3 de 11 não casavam nada.

**Em nenhuma delas quem pegou foi releitura.** Foi rodar o instrumento: `--compare`,
`grep` no arquivo certo, e a validação de cobertura que eu mesmo escrevi.

## Declarado e NÃO consertado (decisão [W])

| item | por quê |
|---|---|
| 14 protótipos LIVE-ONLY | 14 `get_file` não cabem numa sessão; comando pronto no PR. Deixaram de ser **invisíveis**, que era o problema |
| visreg sem módulo habilitado | sub-nav some em **10/15** telas, sidebar em **15/15**. É buraco de **cobertura**, não falso-verde. Semear = rebaseline em massa das 15, que é quando regressão real passa |
| rotina de frescor **34d fora do SLA** | causa raiz de os 14 nunca descerem — a máquina existia, ninguém rodava |
| BRL scan | allowlist do mock (decisão [W]); redigir deixaria o espelho `STALE` pra sempre. Declarado o que não pude verificar: o mock cita clientes reais por nome |
| certificado digital vencido há 5d | visto no smoke em produção; sem relação com o PR — com ele vencido não sai NF-e |

## A lição que atravessa a sessão

Errei a **mesma classe** quatro vezes, inclusive dentro das correções dela. O padrão dos
achados é o mesmo: **o que mede funciona; o que descreve apodrece**. Os consertos de hoje
foram todos na direção de transformar descrição em medição — e o próprio hook que falhou
era descrição.

_(Nota de método: o `memory-schema-preflight` bloqueou a 1ª tentativa de gravar este
arquivo por falta do campo `topic`. Custou 10s; sem ele, custaria um PR de errata.)_
