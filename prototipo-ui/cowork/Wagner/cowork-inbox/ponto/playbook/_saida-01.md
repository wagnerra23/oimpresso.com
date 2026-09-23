---
sessao: "01"
titulo: "Rede mínima — E2E de fumaça das 3 telas âncora do Ponto"
autor: "[CL]"
criado: 2026-09-08
base: a364bd65ed
thread: 01-rede-e2e.md
ancora: "e2e/global-setup.ts + playwright.config.ts + e2e-gate.yml — lidos no main a364bd65ed; harness cobre Inertia autenticado, PARAR SE #1 NÃO disparou"
veredito: "entregue — 2 specs executáveis VERDES no e2e-gate (run real, 14 passed) + 1 fixme skipped como projetado · 8 achados"
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

**(a) Não rodei os specs localmente — nem 2×, nem 3×.** Não é escolha: este worktree **não tem
`node_modules`** (0 pacotes) e **não tem PHP** no PATH; sem eles não há `playwright test` nem
`artisan serve`. É a limitação que o próprio `playwright.config.ts` declara (*"o agente desktop não
tem PHP/serve"*). Não afirmei estabilidade que não tinha medido — e o veredito veio do lugar certo:

**O `e2e-gate` RODOU DE VERDADE na abertura do PR e passou.** Prova colada do log
(run `34267658387`, job `E2E Playwright · UCs críticos`), não `conclusion=success` sozinho — que
seria compatível com skip-as-pass (medido: **0** ocorrências de "Skip-as-pass" no log, porque o
`paths-filter` viu `e2e/**` mudado):

```
✓  29 [chromium-1280] › e2e/ponto-dashboard.spec.ts:27:1 › painel do ponto abre autenticado e monta as âncoras do contrato (1.2s)
-  30 [chromium-1280] › e2e/ponto-espelho-show.spec.ts:24:6 › espelho mensal de um colaborador abre com o cabeçalho legal
✓  31 [chromium-1280] › e2e/ponto-espelho.spec.ts:20:1 › lista do espelho abre autenticada com o seletor de mês (1.1s)

30 skipped
14 passed (38.9s)
```

O run confirma, contra o app real, o que eu só tinha deduzido estaticamente: as duas rotas **abrem**
para o admin da lane, e as âncoras sob `<Deferred>` **chegam ao DOM** dentro do timeout das
web-first assertions (1,2 s e 1,1 s — sem sinal de espera no limite). O `-` da linha 30 é o `fixme`
sendo pulado, exatamente como projetado.

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
7. **Gate `SUPERFICIE.md == árvore` chegou vermelho por dívida de TERCEIRO, e a causa é de base.**
   O `--all --check` acusou `DRIFT em AssetManagement` — módulo que este PR **não toca** (`git diff
   --name-only origin/main...HEAD | grep -i asset` = vazio). Diagnóstico: o `origin/main` avançou 3
   commits durante a sessão (#7045 trouxe a tela `Manutencoes`, #7051 regenerou o `SUPERFICIE`
   **listando** os arquivos novos mas deixando os **contadores** velhos — 116/4/4/4 onde a árvore
   pede 120/5/5/5). Trouxe o main por `merge` (nunca rebase) e regenerei com o comando oficial
   (`module-surface.mjs AssetManagement --write`): 6 linhas, só contadores, `--all --check` **exit 0**.
   ⚠️ **Há 3 PRs abertos com o MESMO conserto** (#7050, #7053, #7055 — todos indo para `120`). Mantive
   o meu porque o gate é **required + always-run** e sem ele o PR não mergeia; como o gerador é
   determinístico e o resultado é idêntico ao dos três, o risco de conflito é baixo. **É dívida
   herdada, não introduzida por esta thread** — registro para que o merge não a atribua a mim.
8. **Contexto de tenant:** a lane E2E roda em **biz=1** (`VisregTenantSeeder`, o mesmo do
   visual-regression), não no tenant fictício 98 da doutrina Pest (ADR 0358) — que governa Pest/CT 100,
   não Playwright. O que importa para a lei: **biz=4 (ROTA LIVRE) não é tocado em lugar nenhum**.

### 5 🗂 Arquivos tocados — o prefixo, mais 1 declarado

```
e2e/ponto-dashboard.spec.ts     (novo)   ← prefixo
e2e/ponto-espelho.spec.ts       (novo)   ← prefixo
e2e/ponto-espelho-show.spec.ts  (novo)   ← prefixo
prototipo-ui/design-docs/cowork-inbox/ponto/playbook/_saida-01.md  (este arquivo)
memory/requisitos/AssetManagement/SUPERFICIE.md  (+6/-6)  ← FORA do prefixo, ver achado 7
```

O `SUPERFICIE.md` **não é meu assunto** e está declarado como tal: entrou porque o gate
`SUPERFICIE.md == árvore` é **required + always-run** e chegou vermelho por dívida de terceiro
(achado 7). É saída do gerador oficial (`--write`), não edição à mão — só contadores.

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
