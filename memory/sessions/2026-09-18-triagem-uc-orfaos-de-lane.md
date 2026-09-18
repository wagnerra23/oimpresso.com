---
date: "2026-09-18"
hour: "11:41 BRT"
duration: "1.5h"
topic: "Triagem das 69 UC cujo teste citado nao roda em lane alguma — 4 categorias, veredito medido no CT 100"
authors: [C]
outcomes:
  - "Reconciliacao 69 x 137: o 137 NAO reproduz em origin/main; a unidade do 69 e citacao (par arquivo::UC)"
  - "Matriz MySQL x SQLite por arquivo de teste — 18 arquivos rodados no CT 100, 501 + 234 assertions"
  - "Classificacao das 69: 22 converter · 1 apos conserto · 3 quarentena · 23 lane inexistente · 0 citacao podre · 15 Forja · 5 teste stale"
  - "Categoria 4 (citacao podre) medida em ZERO, com controle positivo e negativo"
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0264-governanca-executavel-trio-dominio-e2e"]
---

# Triagem — UC cujo teste citado nao roda em lane alguma

> **Escopo:** so as UC **orfas** (`estado: orfao`) do `scripts/qa/uc-lane-coverage.mjs`.
> Fora de escopo por decisao do pedido: as 26 em **quarentena declarada** (estao corretas)
> e as 317 **sem teste citado** (outra categoria, cujo desfecho e escrever teste).

## 1. Reconciliacao 69 x 137 — a divergencia NAO reproduz

O pedido trazia dois numeros do mesmo instante: o resumo dizendo **69** e um `grep` por
`NENHUMA lane roda` dizendo **137**. Medido aqui, em `HEAD == origin/main`
(`git rev-list --left-right --count origin/main...HEAD` devolve `0  0` — os dois sentidos):

| sonda | comando | resultado |
|---|---|---|
| resumo (autoritativo) | `node scripts/qa/uc-lane-coverage.mjs` | **69** |
| `grep` na mesma saida | `grep -c "NENHUMA lane roda"` | **69** |
| `--json` | `violacoes.length` | **69** |
| pares distintos | `new Set(violacoes.map(v => v.arquivo + "::" + v.uc)).size` | **69** |
| UC-ids distintos | `new Set(violacoes.map(v => v.uc)).size` | **69** |

**A unidade do 69 e CITACAO** — um par `<casos.md>::<UC>`, nao arquivo e nao teste. A prova e
a invariante que o proprio script mantem: `661 na-lane + 69 orfao + 26 quarentena + 0 ausente
+ 317 sem-teste = 1073 = citacoes`.

**E o 137 nao sai daquele relatorio, por construcao.** O `main()` emite a string
`NENHUMA lane roda` **uma vez por citacao orfa** (`scripts/qa/uc-lane-coverage.mjs:396`),
entao resumo e `grep` sao o mesmo numero sempre. Nenhum padrao testado sobre a saida chega a
137 (`NENHUMA` / `lane roda` / `UC-` / o glifo de marca dao 69; `lane` da 74). Os numeros
maiores proximos, todos medidos e **nenhum** igual a 137:

- **156** = 69 linhas de UC + **87** linhas de path (uma UC que cita 2 nomes de teste imprime
  2 ou mais paths embaixo);
- **131** = 124 orfaos + 7 arquivo-ausente, que e o que sai se o filtro `prototipo-ui/` do
  script for removido (sonda usando a **API exportada** `citacoesEm`; com o filtro ela
  reproduz 69 exato — controle positivo de que a sonda e fiel);
- **78** entradas no `governance/uc-lane-baseline.json`.

**Nao invento explicacao para o 137.** O que se afirma com recibo: a saida de hoje carrega
69, e o codigo nao admite os dois numeros ao mesmo tempo.

> ⚠️ **O `69` e um retrato de 2026-09-18, e ele ANDA de proposito.** Cada conversao desta
> triagem o derruba, e o `na-lane` / `quarentena` andam por trabalho de terceiros tambem.
> Medido no mesmo dia: o [#7530](https://github.com/wagnerra23/oimpresso.com/pull/7530) leva
> `ORFAO` a **62** (`-7`, o `Wave6PlanCrudTest`), e o
> [#7519](https://github.com/wagnerra23/oimpresso.com/pull/7519), de outra sessao, leva
> `quarentena` de 26 a 22. **Nao restatear estes numeros a mao** — rode
> `node scripts/qa/uc-lane-coverage.mjs` (§6). O que este documento fixa e a **classificacao**
> das 69 e o **veredito medido** de cada teste, nao o placar.

## 2. Contexto que muda a leitura: o gate esta VERDE

O `uc-lane-coverage` **e wirado** — `.github/workflows/governance-script-tests.yml:196` roda
`--check --baseline governance/uc-lane-baseline.json`. Rodado aqui: **EXIT=0**. As 69 estao
**todas** grandfatheradas no baseline (78 entradas, das quais **9 ja estao stale** — o teste
entrou em lane e a entrada ficou la). Ou seja: isto e **divida datada, nao alarme aceso**. O
que morde hoje e citacao NOVA (no-new-lie).

## 3. Veredito medido — CT 100, MySQL x SQLite

Antes de rodar, provei que a arvore do container e a minha **nos arquivos que importam**:
`git ls-tree HEAD` local contra `git hash-object` no container, para os 20 testes citados —
`diff` vazio (`rc=0`). O checkout do CT 100 esta em `755f6de798` com alteracoes
nao-commitadas de outra sessao, por isso a comparacao foi por **blob**, nunca por SHA de
commit.

Dois arquivos **nao** foram rodados, de proposito: `RoadmapGanttControllerTest` e
`PlanoSemFaturaContratoTest` usam `RefreshDatabase`, que na base de staging compartilhada
dropa o schema e limpa o tenant seedado — a mesma regra que as lanes MySQL ja declaram no
proprio YAML.

| arquivo de teste | UC | MySQL | SQLite |
|---|---:|---|---|
| `Modules/Jana/.../ProContractTest.php` | 6 | **verde 6p/65a** | 0 assert (skip) |
| `tests/Feature/Modules/ModuleManagerServiceTest.php` | 10 | **verde 17p/31a** | verde 15p/29a |
| `tests/Feature/Modules/ModuleManagementTest.php` | 6 | **verde 9p/32a** | 0 assert (skip) |
| `Modules/RecurringBilling/.../Wave6PlanCrudTest.php` | 7 | 0 assert (skip) | **verde 6p/23a** |
| `tests/Feature/Modules/Financeiro/CategoriaCrudTest.php` | 4 | **verde 7p/28a** | VERMELHO 7 |
| `tests/Feature/Modules/Financeiro/ContaBancariaIndexTest.php` | 1 | **verde 1p/6a** | VERMELHO 2 |
| `tests/Feature/Modules/Financeiro/UpsertContaBancariaRequestTest.php` | 1 | **verde 3p/9a** | verde 3p/9a |
| `tests/Feature/Modules/Financeiro/RelatoriosTest.php` | 5 | VERMELHO 4 | VERMELHO 8 |
| `tests/Feature/Support/SupportAcessarComoTest.php` | 4 | **verde 9p/31a** | 0 assert (skip) |
| `tests/Feature/Support/SupportEmpresasHttpTest.php` | 3 | **verde 4p/14a** | verde 1p/3a |
| `tests/Feature/Sells/SellsIndexCoworkPayloadTest.php` | 1 | **verde 17p/42a** | verde 17p/42a |
| `tests/Feature/Calculo/CalculoValorSellsTest.php` + 2 de `tests/Unit/Utils/` | 1 | **verde 45p/77a** | 1 vermelho |
| `Modules/Compras/.../PurchaseCalculoValorEstoqueE2ETest.php` | 1 | **verde 2p/48a** | 0 assert (skip) |
| `Modules/Governance/.../DsRolloutControllerTest.php` | 1 | VERMELHO 1 | VERMELHO 1 |
| `tests/Feature/Perfil/PerfilSmokeTest.php` | 2 | 0 assert (skip) | 0 assert (skip) |
| `Modules/Forja/.../ScorecardContratoTest.php` | 5 | verde 9p/72a | verde 9p/71a |
| `Modules/Forja/.../Roadmap/RoadmapGanttControllerTest.php` | 10 | nao rodado (RefreshDatabase) | — |

Totais das duas corridas: MySQL `138 passed, 2 failed, 3 errors, 10 skipped (501 assertions)`;
SQLite `91 passed, 19 failed, 34 skipped (234 assertions)`. **Assertions maior que zero nas
duas** — a suite provou algo (LC-13). Os `0 assert` por arquivo sao skip real, medidos
**por-arquivo** pelo dono `scripts/tests/junit-summary.mjs`, nunca inferidos do exit code.

## 4. Classificacao das 69

| # | categoria | UC | detalhe |
|---|---|---:|---|
| 1 | **Converter** | **22** | teste **provado verde**; a lane existe (allowlist) ou e extensao de 1 linha |
| 1b | Converter **apos conserto** | 1 | `UC-DSR-09`: o arquivo esta vermelho por **outro** UC (`UC-DSR-10`) |
| 2 | **Quarentena declarada** | 3 | veredito medido: 0 assertions, ou decisao ja escrita |
| 3 | **Lane inexistente** | 23 | teste verde em MySQL, e **nenhuma lane MySQL alcanca `tests/Feature/<Area>/`** |
| 4 | **Citacao podre** | **0** | medido, com controle (ver 4.4) |
| — | Forja (defer `US-FORJA-009`) | 15 | fora do escopo desta triagem, por decisao do pedido |
| — | Teste **stale** vs codigo | 5 | `RelatoriosTest` — decisao [W]; nao e conversao nem quarentena |

Soma: `22 + 1 + 3 + 23 + 0 + 15 + 5 = 69`.

### 4.1 Converter (22 UC) — por lane alvo

| lane alvo | UC | teste | recibo |
|---|---:|---|---|
| `jana-pest.yml` (allowlist) | 6 | `ProContractTest` | 6p/65a MySQL |
| `.github/ci-sqlite-pest.list` | 7 | `Wave6PlanCrudTest` | 6p/23a SQLite |
| `financeiro-pest.yml` (o `find` ganha 1 dir) | 6 | `CategoriaCrudTest`, `ContaBancariaIndexTest`, `UpsertContaBancariaRequestTest` | 11p/43a MySQL |
| `compras-pest.yml` (allowlist) | 1 | `PurchaseCalculoValorEstoqueE2ETest` | 2p/48a MySQL |
| `sells-pest.yml` (allowlist) | 1 | `SellsIndexCoworkPayloadTest` | 17p/42a |
| `nfebrasil-pest.yml` (allowlist) | 1 | `CalculoValorSellsTest` + 2 de `tests/Unit/Utils/` | 45p/77a MySQL |

O `Wave6PlanCrudTest` e o caso mais limpo dos seis. O bloco **RecurringBilling** do
`ci-sqlite-pest.list` ja documenta exatamente este criterio — *"o beforeEach de cada um EXIGE
sqlite e :memory: ... na lane MySQL viram skip"* — e o arquivo e `merge=union` no
`.gitattributes`, entao PRs concorrentes nao conflitam. O `beforeEach` do teste
(`Wave6PlanCrudTest.php:52-54`) faz `markTestSkipped('Smoke test rodado apenas em SQLite
in-memory.')`, e a medicao confirma a leitura: MySQL 0 assertions, SQLite 23.

O `nfebrasil-pest.yml` entra na lista porque e a lane onde ja mora o irmao de diretorio
`tests/Feature/Calculo/CalculoTributarioTest.php` — nao por afinidade tematica com NFe.

### 4.2 Quarentena declarada (3 UC)

- **`UC-RBSUB-05`** (`PlanoSemFaturaContratoTest`) — **a decisao ja existe e esta escrita**,
  em prosa, no proprio `ci-sqlite-pest.list`: *"FICA DE FORA ... FAILING-FIRST por desenho ...
  o remedio e decisao [V0] de [W]. Numa lane REQUIRED, o vermelho que e o ACHADO travaria todo
  merge em main."* O `.casos.md` concorda (`Status: vermelho esperado`). **A maquina nao le
  comentario** — `emQuarentena()` so le `.github/*-quarantine.list` —, entao a UC aparece como
  orfa (*"ninguem decidiu"*) num caso em que alguem decidiu, e bem. Recomendacao: mover a
  declaracao para uma `.list` legivel. E mudanca de mecanismo, logo decisao [W].
- **`UC-P01` / `UC-P02`** (`PerfilSmokeTest`) — 0 assertions nos **dois** engines. Causa
  medida (`PerfilSmokeTest.php:15-28`): `markTestSkipped('DEV_LOGIN_USERNAME/PASSWORD nao
  setadas em .env')`. O cabecalho do arquivo diz *"roda em CT100/CI"* e nenhuma lane o roda —
  a prova depende de credencial que o CI nao tem. Entra em `.list` com o veredito, ou ganha as
  env vars: decisao [W].

### 4.3 Lane inexistente (23 UC) — o achado estrutural

As lanes MySQL deste repo sao **todas** escopadas a `Modules/<X>/Tests`. Os testes abaixo
exercitam **codigo core** (`app/`), moram em `tests/Feature/<Area>/`, e **precisam de MySQL**
(sob SQLite viram skip ou vermelho). Nenhuma lane MySQL alcanca esse endereco:

| tela | UC | teste (verde em MySQL) | o que exercita |
|---|---:|---|---|
| `Modules/Index` | 16 | `ModuleManagementTest`, `ModuleManagerServiceTest` | rota `/modulos` (core) |
| `Suporte/Empresas` + `Suporte/Visao` | 7 | `SupportEmpresasHttpTest`, `SupportAcessarComoTest` | `App\Services\Support\SupportAccessService`, rotas `/suporte/*` |

Nao existe `Modules/Support` nem `Modules/Suporte` (so `Modules/Superadmin`). O
`ci-sqlite-pest.list` **e** o unico lar que teste de raiz tem hoje — `tests/Feature/Modules/Copiloto/*`
esta la —, mas estes quatro arquivos **nao** sao sqlite-safe. **Criar lane nova nao entra
nesta triagem** (outro intent; gastar CI e decisao [W]). Fica registrado com o numero.

### 4.4 Citacao podre: ZERO — e o zero foi controlado

Para cada uma das 69, li o arquivo de teste citado e procurei o **UC-id literal**: **69 de 69
contem**. Controles, porque `0` tambem e o resultado de uma sonda cega:

- **negativo** — com um UC-id inventado (`UC-XXXX-99`), o detector acusa 5 de 5;
- **positivo** — `UC-PRO-01` aparece em `ProContractTest.php:19`.

## 5. Achados laterais (nao consertados aqui)

1. **`prototipo-ui/` fica de fora do universo de `.casos.md`, mas nao do indice de testes.**
   O `main()` filtra `prototipo-ui/` dos `casos.md` (`:311`), e o `indiceDeTestes()` faz
   `git ls-files -- '*Test.php'` **sem** o mesmo filtro (`:271`). Resultado: 16 UC de
   `Modules/Index` resolvem para **2** paths cada — o real, e a copia espelhada em
   `prototipo-ui/cowork/Wagner/cowork-inbox/modulos/repo/...`. Nao muda veredito nenhum (o
   real tambem e orfao), mas infla a lista de paths do relatorio e a leitura por-arquivo.
2. **`RelatoriosTest` esta stale, nao quebrado por fixture.** As 4 falhas em MySQL pedem a
   prop `dre`; o `RelatoriosController::index` (`:44-47`) renderiza `filters`, `fluxo` e
   `resumo` — o DRE virou rota propria (`/financeiro/dre`, que o proprio `UC-REL-05`
   menciona). O teste ficou para tras do codigo. Conserto do teste ou do `.casos.md`: decisao
   [W].
3. **9 entradas stale no `governance/uc-lane-baseline.json`** — testes que ja entraram em lane
   e cuja entrada ficou. Encolher o baseline e livre por contrato (`--write-baseline`), mas
   regenerar mistura intents; fica como chip.
4. **`UC-CMP-09` ja declarava a divida em prosa**: `Status: (roda so no nightly, nao no PR)`.
   Honesto — e exatamente o que o `uc-lane-coverage` mede.

## 6. Reproduzir

```bash
node scripts/qa/uc-lane-coverage.mjs --json
node scripts/qa/uc-lane-coverage.mjs --check --baseline governance/uc-lane-baseline.json
node scripts/governance/test-lane-coverage.mjs --json
```

Veredito por arquivo (CT 100, arvore conferida por blob antes de rodar):

```bash
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging sh -lc 'cd /var/www/html; vendor/bin/pest --no-coverage --log-junit test-results/uc-triagem-junit.xml <arquivos>'"
node scripts/tests/junit-summary.mjs <junit.xml>
```
