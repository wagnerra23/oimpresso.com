---
date: "2026-09-08"
time: "1043 BRT"
slug: "patrimonio-thread-03-guarda-asset-view"
tldr: "Thread 03 do playbook SINCRONIZAR Patrimônio fechada: `index()` de Bens passou a exigir `asset.view` (#7008) e o `orWhereNull` do `dashboard()` deixou de escapar do filtro de tenant (#7015, empilhado). Nenhum mergeado. Três achados ficam SEM DONO e precisam de thread: `Pest Repair` vermelho no main, o gêmeo Tier 0 do `AssetController:110`, e os 2 testes Tier 0 que morrem com `0 assertions` por `created_by` ausente."
decided_by: [W]
cycle: null
prs: [7008, 7015]
us:  []
next_steps:
  - "[W] revisar e mergear #7008 (guarda asset.view) — e SÓ DEPOIS #7015, que está empilhado sobre ele"
  - "Dar dono ao `created_by` ausente em MultiTenantIsolationTest + CrossTenantAssetTest: 2 testes Tier 0 mudos (`0 assertions`), não reprovando — gate mudo é pior que gate ausente"
  - "Dar dono ao `Pest Repair` (DeviceModelsContratoTest UC-DMIDX-03/UC-DMCRE-02) — falha no main, intermitente"
  - "[W] decidir se `dashboard()` ganha gate de ASSINATURA (não de permissão — ver §3 do _saida-03)"
  - "Corrigir `permission_prefix` do SCOPE.md do AssetManagement: diz `assetmanagement.*`, o vivo é `asset.*` (errata de doc, não decisão)"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0358-doutrina-de-teste-tenant-98-supersede-0101", "0130-handoff-append-only-mcp-first"]
---

# Handoff 2026-09-08 10:43 BRT — Patrimônio thread 03: guarda `asset.view` + vazamento no dashboard

## TL;DR

A thread 03 do playbook `SINCRONIZAR Patrimônio` fechou com **2 PRs abertos e nenhum mergeado**.
O defeito principal era material, não teórico: sem a guarda, usuário sem `asset.view` recebia
**HTTP 200** na listagem do patrimônio inteiro. Um segundo defeito Tier 0 apareceu no caminho
(vazamento cross-tenant no `dashboard()`) e ganhou PR próprio em vez de carona.

## O que entregou

| PR | intent | estado |
|---|---|---|
| [#7008](https://github.com/wagnerra23/oimpresso.com/pull/7008) | `index()` de Bens exige `asset.view`, no formato literal de `create()` | aberto, base `main` |
| [#7015](https://github.com/wagnerra23/oimpresso.com/pull/7015) | `orWhereNull` do `dashboard()` para de escapar do filtro de tenant | aberto, **empilhado** sobre a branch do #7008 |

⚠️ **Ordem de merge importa:** #7015 tem base `claude/patrimonio-guarda-asset-view`. Mergear o
#7008 primeiro; o #7015 vira base `main` sozinho depois.

Artefato da thread: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-03.md`
(vai dentro do #7008). Placar: `Patrimonio: entregue 1 de 6 · 03 [feito]`.

## Provas (bite-test, não leitura)

Os dois fixes foram provados fazendo o teste **falhar** com o código original:

- **#7008** — `Expected response status code [403] but received 200.` O `200` é o defeito
  materializado.
- **#7015** — `Expecting […] not to contain 'AST-DASH-TNT99'.` O bem sem garantia do biz=99
  aparecia na lista do biz=98. Vaza sempre, sem depender de dado corrompido.

Suíte do módulo no CT 100, **antes → depois** (mesma árvore nos dois lados):

| | failed | passed | assertions |
|---|---:|---:|---:|
| baseline (árvore 100% original) | 7 | 61 | 139 |
| #7008 | 7 | 63 | 143 |
| + #7015 | 7 | **64** | **147** |

Delta `0 / +3 / +8`, batendo com os 3 cenários novos. Zero regressão.

## Estado MCP no momento do fechamento

⚠️ **As tools MCP não estavam conectadas nesta sessão** — o `brief-fetch` chegou pelo hook
`SessionStart`, e o restante foi pelo **fallback filesystem/gh** que o
[`how-trabalhar.md` §Fallback](../how-trabalhar.md) prevê. Registro isso em vez de simular
uma consulta que não fiz.

- **Brief (via hook, gerado há ~2h):** cycle `—`; HITL pendente [W]: 5; 679 US não atribuídas
  (524 sem dono); SDD composta 41,5.
- **Handoffs do dia já existentes** (conferido em `origin/main`, para não duplicar):
  `0924-onda7-lote-crm-jana-forja`, `1145-devolutiva-rodada-pontual-e-lane-muda`,
  `1200-onda7-financeiro-recurring-paridade-e-2-fixes`.
- **Sessões paralelas (equivalente ao `whats-active`):** três threads irmãs do mesmo playbook
  rodaram em paralelo e **abriram PR**: `#7011` (thread 01, subconsulta de revoke),
  `#7016` (thread 02, trava num Request que nunca roda), `#7009` (thread 04, medição).
  Prefixos disjuntos do meu — confirmado por medição, não por acordo.

## Erratas desta sessão — registradas, não apagadas

1. **A permissão que medi como "pré-existente no CT 100" era o meu próprio rastro.**
   `asset.view id=194 created_at=2026-09-08 10:02:15`, criada pelo `firstOrCreate` do meu
   teste; `asset.create` sequer existe no catálogo. Pior: como só um cenário criava a
   permissão e o Pest roda em ordem aleatória, a **primeira** rodada (seed `1788872533`)
   executou o `MORDE` **antes** do `CN` — aquele verde **passou pelo motivo errado**.
   Consertado (`firstOrCreate` nos dois ramos + canário de 2 asserts).
2. **O CT 100 staging NÃO é o clone de prod.** `total_businesses=4`; prod tem 82. Tentei medir
   ali o risco de deploy (tenant que só lista tomaria 403) e **a medição não conclui** — está
   declarada como ressalva no PR, sem afirmar impacto zero.
3. **O canário não foi provado por mutação** — a permissão existe no ambiente, então removê-lo
   não o faria falhar hoje. Logicamente correto e passa; a mordida exige DB fresco.

## Órfãos — precisam de dono, e NENHUM é meu

1. **`created_by` ausente em `MultiTenantIsolationTest` + `CrossTenantAssetTest`** — 2 testes
   **Tier 0** que morrem na FK `assets_created_by_foreign` antes de assertar. O dado que
   importa é **`0 assertions`**, não `N failed`: eles não reprovam, **não rodam**. Gate mudo é
   pior que gate ausente, porque ocupa a vaga. (Leitura da thread 04, que eu confirmo.)
2. **`Pest Repair`** — `DeviceModelsContratoTest` (`UC-DMIDX-03`, `UC-DMCRE-02`) falha no
   **`main`** (sha `dced5fd3d`, 08/09 10:29) e é intermitente. Fora do prefixo da thread.
3. **`AssetController.php:110`** — gêmeo Tier 0 da subconsulta de revoke sem `business_id`,
   dentro do meu arquivo. Não peguei de propósito: o oráculo dele é o `CrossTenantAssetTest`,
   prefixo da thread 01 — consertar aqui faria o teste dela nascer verde sem provar nada.
4. **`dashboard()` sem gate de assinatura** — é o único método público do controller sem
   `abort(403)` nenhum. Fix pequeno e sem perfil legítimo quebrado, mas é outro intent.

## Decisões tomadas, com o motivo

- **`asset.view` NÃO entra no `dashboard()`.** Ele filtra `receiver = auth()->user()->id`: é a
  tela pessoal do colaborador, e `DataController.php:109` mostra o item de menu para quem tem
  só `view_own_maintenance`, apontando pra lá — a guarda daria 403 num menu que o sistema
  exibiu. É o `PARAR SE (b)` do playbook. A regra ali é **escopo por dono**, decisão de [W].
- **O `orWhereNull` ganhou PR próprio em vez de entrar no #7008.** O coordenador ofereceu duas
  saídas (entra no PR / espera o merge); a terceira preserva 1 PR = 1 intent **e** dá dono
  imediato ao Tier 0, sem dependência de merge.
- **`Pest Repair` não foi consertado** — fora do prefixo, e não é regressão deste PR.

## Pegadinhas encontradas (valem além desta thread)

- **`->afterEach()` encadeado a um `it()` não executou** no CT 100 — 4 rodadas deixaram 8
  usuários e 1 role órfãos numa base que não se limpa entre runs. `try/finally` dentro do
  closure funciona e limpa até quando o assert falha.
- **`dashboard.blade.php:14` estoura no ambiente de teste** (`num_format`, "array offset on
  null"). Testar aquele método pelo HTTP dá 500 antes de chegar na query; chamar o controller
  e ler `view()->getData()` exercita a query real sem renderizar o Blade.
- **O denominador da suíte muda quando outra sessão mexe no mesmo container.** A thread 01
  reverteu um arquivo no meio das minhas medições e o placar foi de 8 para 7 falhas — o par
  antes/depois teve de ser re-medido na mesma árvore.

## Ponteiro podre encontrado

O `§2-bis` do `00-INDICE.md` do playbook manda rodar `node scripts/qa/placar-indice.mjs`; o
script vive em `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs`. Conserto é
do dono do índice.
