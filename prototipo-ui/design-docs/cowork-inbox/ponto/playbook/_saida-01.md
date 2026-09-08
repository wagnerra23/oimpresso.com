---
sessao: "01"
titulo: "Rede mínima — E2E de fumaça das 3 telas âncora do Ponto"
autor: "[CL]"
criado: 2026-09-08
base: a364bd65ed
thread: 01-rede-e2e.md
ancora: "e2e/global-setup.ts + playwright.config.ts + e2e-gate.yml — lidos no main a364bd65ed; harness cobre Inertia autenticado, PARAR SE #1 NÃO disparou"
veredito: "entregue — 2 specs executáveis + 1 fixme com razão medida · 0 run local (impossível neste worktree, declarado abaixo) · 7 achados"
---

# _saída 01 · Rede mínima (E2E de fumaça do Ponto)

## Checklist de saída — item a item

### 1 ✅ Feito — os 3 arquivos do prefixo

| arquivo | estado | âncora usada |
|---|---|---|
| `e2e/ponto-dashboard.spec.ts` | **executável** (`test`) | `h1` nível 1 + `data-contract` `painel-nota-fechamento` e `painel-kpis` |
| `e2e/ponto-espelho.spec.ts` | **executável** (`test`) | `h1` nível 1 + `getByLabel(/Mês de referência/)` |
| `e2e/ponto-espelho-show.spec.ts` | **`test.fixme`** — razão medida (item 2) | `h1` + `espelho-dados-colaborador` (quando destravar) |

O acesso foi **provado mecanicamente**, não suposto: o admin da lane (`E2E_BYPASS_LOGIN_ID=1`,
`VisregTenantSeeder`) tem role `Admin#1`; o `Gate::before` de `app/Providers/AuthServiceProvider.php:34`
devolve `true` para quem `hasRole('Admin#'.$user->business_id)`; e `CheckPontoAccess` passa com
`$user->can('ponto.access')`. Logo `/ponto` e `/ponto/espelho` **abrem** na lane.

### 2 ⚠️ Não feito, e por quê

**(a) Não rodei os specs — nem 2×, nem 3×.** Não é escolha: este worktree **não tem `node_modules`**
(0 pacotes) e **não tem PHP** no PATH; sem eles não há `playwright test` nem `artisan serve`. A
limitação é a que o próprio `playwright.config.ts` já declara em comentário (*"o agente desktop não
tem PHP/serve"*). **Não afirmo estabilidade que não medi** — o primeiro run real destes 3 specs será
o `e2e-gate` na abertura deste PR, e o veredito é dele, não meu.

O que consegui provar aqui, e com controle positivo: **sintaxe válida nos 3** (`node --check`, via
type-stripping do Node 24). O controle negativo — arquivo `.ts` propositalmente quebrado, criado no
**mesmo diretório** para não trocar o contexto de resolução — **reprovou**, então o `OK` dos 3 não é
carimbo. Arquivo temporário removido (`git status` limpo).

**(b) `ponto-espelho-show.spec.ts` nasce `fixme`** — a rota responde **404** na lane, medido em três
pontos: `EspelhoController@show:70` resolve por `findOrFail()`; `VisregTenantSeeder` tem **zero**
colaborador de ponto (os 2 hits de "ponto" no arquivo são a expressão *"ponto cego"*); e o `db:seed`
geral chama só `Barcodes/Permissions/Currencies/BusinessLegacyOrigin`. `DevPontoSeeder` e
`PontoWr2DatabaseSeeder` existem no módulo, mas **a lane não os invoca**.

Não fabriquei o colaborador dentro do spec: é o anti-padrão de `memory/proibicoes.md` §5 (2026-08-24)
— o dono de *"o que existe neste ambiente"* é o **seed**, e o seeder está fora do meu prefixo.

### 3 📣 Pedido literal (o que preciso de quem pode)

1. **Seed:** 1 colaborador com `controla_ponto` ativo em `biz=1` no `VisregTenantSeeder` destrava o
   `ponto-espelho-show.spec.ts` (trocar `test.fixme` por `test`). Não é meu prefixo.
2. **Decisão [W] · trigger do gate:** `e2e-gate.yml` dispara em `types: [opened, reopened,
   ready_for_review]` — **sem `synchronize`**. Push depois de abrir o PR **não re-roda o gate**. Se
   este PR precisar de correção, o re-run é manual (`workflow_dispatch`) ou fecha/reabre. Registro
   como fato; mexer no trigger é governança, fora desta thread.

### 4 🔎 Descobertas (medidas nesta sha)

1. **`e2e/` tem 15 specs, não 14 nem 17.** O meu pedido de abertura dizia 14; a thread `01-rede-e2e.md`
   diz 17 (e o §1 do índice repete "17 specs"). Contado: `ls e2e/*.spec.ts | wc -l` = **15**. Os dois
   acertam o que importa — **zero** `ponto-*` antes deste PR.
2. **`e2e/README.md` está desatualizado sobre o próprio gate.** Afirma, em presente, que o
   `e2e-gate.yml` é *"`workflow_dispatch` (manual) + NÃO-required"*; o workflow **já roda em
   `pull_request`** desde o flip de 2026-06-11 declarado no cabeçalho dele. É a classe LC-10
   (artefato afirmando o próprio enforcement em presente). **Não corrigi** — `e2e/README.md` está
   fora do meu prefixo.
3. **O `Espelho/Index` não tem `data-contract` nenhum.** Varredura contada em `Pages/Ponto/**`: **9**
   ocorrências — 4 no `Dashboard/Index`, 5 no `Espelho/Show`, **0** no `Espelho/Index`. A thread cita
   "5 no Show" corretamente, mas quem ler "âncoras já no DOM" e assumir que valem para as três telas
   erra: por isso o spec do Index ancora em `label`, não em `data-contract`.
4. **As âncoras vivem sob `<Deferred>`** (`Inertia::defer`) — não estão no primeiro render. Medir o DOM
   ali seria medir o meio do lazy-load (`proibicoes.md` §5, 2026-08-24). As web-first assertions do
   Playwright re-tentam até o timeout, então **zero `waitForTimeout`** nos 3 specs (verificado
   distinguindo a chamada da menção em comentário: **0 chamadas**).
5. **Ponteiro podre no §0 do índice.** Ele manda rodar `node scripts/qa/placar-indice.mjs` — esse path
   **não existe**. O script real é `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs`
   (o outro exemplar vive em `prototipo-ui/cowork/cowork-inbox/_scripts/`). **Não editei o índice**
   (proibido pela abertura de thread); fica registrado aqui.
6. **O buraco que esta thread fecha estava declarado no próprio contrato.**
   `resources/js/Pages/Ponto/Dashboard/Index.casos.md:110` traz um `[BACKLOG]`: *"Ordem no DOM de
   verdade exige E2E (Playwright), e não há lane de browser para esta tela."* Continua `[BACKLOG]` —
   virar UC exige teste que **cite** o id, e mexer em `casos.md` é prefixo da **thread 02**.
7. **Contexto de tenant:** a lane E2E roda em **biz=1** (`VisregTenantSeeder`, o mesmo do
   visual-regression), não no tenant fictício 98 da doutrina Pest (ADR 0358) — que governa Pest/CT 100,
   não Playwright. O que importa para a lei: **biz=4 (ROTA LIVRE) não é tocado em lugar nenhum**.

### 5 🗂 Prefixo tocado — e só ele

```
e2e/ponto-dashboard.spec.ts     (novo)
e2e/ponto-espelho.spec.ts       (novo)
e2e/ponto-espelho-show.spec.ts  (novo)
prototipo-ui/design-docs/cowork-inbox/ponto/playbook/_saida-01.md  (este arquivo)
```

`nao_toca` respeitado, verificado por `git status`: **zero** linha em `resources/js/Pages/Ponto/**`,
**zero** em `Modules/Ponto/**`, **zero** em `e2e/global-setup.ts`, nenhum harness novo.

## Decisão de desenho que foi minha (registrada, não perguntada)

**Os títulos dos specs NÃO citam UC-id.** O coletor `casos:results` (G-7) agrega veredito **por UC-id
lido do `<testcase name>`** — medido em `scripts/casos-results-collect.mjs:132`
(`[...name.matchAll(ucScanRe())]`); título sem id simplesmente não entra no manifesto, sem quebrar
nada. Os `UC-PAINEL-01..06` já estão **verdes** pelo `PontoDashboardContratoTest`, que prova **copy e
ordem**. Este smoke prova que a âncora **existe no DOM** — o que a thread pede, e ela proíbe asserção
de copy. Citar o UC aqui somaria um "verde" que o teste não sustenta e, num flake, **avermelharia um
UC legitimamente verde**. A rastreabilidade G-2 desses UCs continua com o Pest, que é quem a tem.
