---
date: "2026-09-13"
topic: "Thread 01 do playbook compras (rede E2E) executada a partir do playbook apagado pela #7224 — 2 specs novos, 12 testes (6 executam, 6 fixme declarados), 3 achados e o recibo canônico sem endereço"
authors: ["C"]
prs: [7249]
outcomes:
  - "e2e/compras-cockpit.spec.ts e e2e/purchase-create.spec.ts criados — eram a única lacuna de código do módulo (medido: e2e/ tinha 20 specs e zero de compras/purchase em origin/main)"
  - "D-LANE estava listada como pendente no playbook e está RESPONDIDA pela máquina — testMatch por glob + paths-filter e2e/** já colocam os specs na lane e2e-gate; zero YAML tocado"
  - "3 achados que a ordem de serviço não sabia: o SortHeader do cockpit não tem aria-sort nem button (a thread afirmava que tinha), a âncora data-contract compras-tabela some no estado vazio, e a visibilidade de coluna não tem UC em Index.casos.md"
  - "Recibo canônico (_saida-01.md) está SEM ENDEREÇO: a pasta do playbook foi apagada e o cowork-ssot-guard R3 não admite recriá-la sob prototipo-ui/ — decisão de onde o programa passa a viver é de [W]"
---

# Session log 2026-09-13 — Compras · thread 01 (rede E2E)

## TL;DR

Executei a thread **01** do playbook `compras` — *"Rede: 2 specs E2E do módulo"*. O playbook não
está no working tree: foi apagado do `main` pela [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224)
(ADR 0397 D5, *"conteúdo histórico recuperável permanece no Git"*), e foi lido de `4f51a9ec78^`.
Entrega: [PR #7249](https://github.com/wagnerra23/oimpresso.com/pull/7249) com 2 specs novos,
339 linhas, 12 testes — **6 executam, 6 `test.fixme` com a razão escrita**.

O trabalho real não foi escrever os testes: foi **decidir o que assertar** quando a ordem de
serviço descreve uma tela e o contrato descreve outra.

## O que a medição disse antes de escrever qualquer linha

Tudo contra `origin/main` fresco (o worktree estava 23 commits atrás; branch nova criada de
`origin/main`, não do checkout stale):

| premissa do enunciado | medido | veredito |
|---|---|---|
| `e2e/compras-cockpit.spec.ts` ausente | ausente | ✅ de pé |
| `e2e/purchase-create.spec.ts` ausente | ausente | ✅ de pé |
| "20 specs em `e2e/` para imitar" | 20 `.spec.ts` (+ README, probe, global-setup) | ✅ exato |
| os 2 `contract.json` existem | 3.961 B e 4.290 B em `governance/design/contracts/` | ✅ |
| zero colisão em PRs abertos | 8 PRs abertos, **nenhum** toca `e2e/`, workflows ou `playwright.config.ts` | ✅ |

Re-medido **depois** do commit e antes de publicar (§5 2026-09-05 — o `main` anda enquanto se
trabalha): os dois arquivos seguiam ausentes, e `git log HEAD..origin/main -- e2e/` voltou vazio.

## A tradução de caminhos que evitou 6 falsos defeitos

A #7224 reorganizou `prototipo-ui/` por dono. Os caminhos do playbook são todos pré-#7224:
`prototipo-ui/contrato/` virou `governance/design/contracts/`, `prototipo-ui/ancora.mjs` virou
`scripts/design/`. Procurar por caminho antigo e concluir *"o trabalho falta"* seria a classe
[LC-08](../LICOES_CODE.md) na forma mais barata — o enunciado já avisava, e o aviso pagou.

## A decisão que o trabalho de fato exigiu: protótipo × contrato

A seção A da thread descreve **o protótipo**: `table.purchases` com **9 colunas**, aba *Pedidos*,
`SortTh` com `aria-sort` e `<button>` interno. O `compras-cockpit.contract.json` descreve **a tela
viva**: 4 abas de FILTRO (`Todas`/`A pagar`/`Rascunhos`/`Em trânsito`) e 7 rótulos de tabela — e
declara o porquê na própria `_nota_fonte`:

> *"a copy abaixo deriva da TELA VIVA, não do protótipo, e a diferença é deliberada (…) Declarar a
> copy do protótipo faria o gate reprovar a tela no primeiro run, e um contrato que nasce vermelho
> é ruído, não catraca."*

A própria thread resolve o conflito: *"os contratos JÁ existem e são o roteiro do spec, não o
produto dele — asserte **exatamente** aquilo"*. Segui o contrato. O gap protótipo × produção está
em `_pendente_w` e é trabalho de produto, não defeito de spec.

Duas consequências concretas:

- o caso 1 pedia *"contagem de colunas = a do contrato"* — **o contrato não declara contagem**,
  declara copy. Assertar um número que ninguém declarou seria inventar o contrato;
- o caso 3 pedia *"total reduzir"* — com o seed vazio o contador é `0 de 0`. A metade observável
  (a querystring) ficou verde; a outra foi para o `fixme`.

## Os 3 achados

1. **O `SortHeader` do cockpit não tem `aria-sort` nem `<button>`.** A thread afirmava que tinha, e
   o §6 item 5 do índice chega a chamá-lo de *"a referência certa"* para portar ao DS.
   [`Compras/Index.tsx:478-486`](../../resources/js/Pages/Compras/Index.tsx) é um `<th onClick>` sem
   semântica. O assert canônico de ordenação acessível não tem onde morder. O spec **não inventou o
   seletor** (é o PARAR SE (a) da thread) — o gap está escrito dentro do `test.fixme`, e a semântica
   é um PR próprio da tela.
2. **A âncora `data-contract="compras-tabela"` desaparece no estado `vazio`.** Ela vive no
   `<table>`, que só renderiza com linhas, mas o contrato declara `estados: ["com-linhas","vazio"]`.
   Não é regressão (o gate de contrato lê o fonte, não o DOM) — mas quem escrever assert de DOM por
   âncora tropeça, então o assert do estado vazio foi pela copy.
3. **A visibilidade de coluna não tem UC.** O caso 5 da ordem de serviço não corresponde a nenhum
   `UC-CMP-*`. O teste foi escrito **sem UC-id** em vez de emprestar um vizinho — creditar prova a
   um UC que não a pediu é a lápide de [§5 2026-09-04](../proibicoes.md). Criar o UC exige editar o
   `casos.md`, que está no `nao_toca` da thread.

## D-LANE: a decisão estava respondida, e quem respondeu foi a máquina

O playbook listava `D-LANE` (*"os specs entram em lane existente ou nasce workflow do módulo?"*)
como pendente, e o PARAR SE (c) mandava deixar os specs **fora do CI** até [W] responder. Medido:
`playwright.config.ts` usa `testDir: './e2e'` + `testMatch: '**/*.spec.ts'`, e o `e2e-gate.yml` já
tem `e2e/**` no `paths-filter`. Os specs entram na lane **por glob** — zero YAML tocado, nenhum
workflow novo, e o `E2E Playwright · UCs críticos` aparece no `gh pr checks` do #7249.

Deixá-los fora do CI "porque a decisão está pendente" teria sido obedecer a letra de um documento
que a árvore já tinha desmentido.

## Por que 6 testes são `test.fixme` e isso é a parte honesta

O `VisregTenantSeeder` cria business 1, 1 location, 1 contact e **1 produto `type=single`**.
Nenhuma `transactions type=purchase`, nenhum produto variável. Logo:

- sem compra → não há linha para ordenar nem para abrir o drawer;
- sem produto variável → a grade tam×cor (US-COM-005) não tem o que carregar;
- só o admin `Admin#1` (todas as abilities via `Gate::before`) → não há como provar o 403.

Criar a fixture aqui seria **fixture paralela ao Pest** — o PARAR SE (b) da thread, e o Pest é o
dono do dado de domínio (`PurchaseGradeMatrixTest`, `MultiTenantTest`,
`PurchaseCalculoValorEstoqueE2ETest`). O caso 9 da ordem de serviço (*estoque só após `received`*)
**não foi escrito de propósito**: duplicaria régua consolidada ([§5 2026-07-09](../proibicoes.md)).

`test.fixme` com a razão no corpo é o idioma do diretório (`e2e/README.md`: *"`test.skip(...)`
explícito quando falta seed — NUNCA falso-verde"*), e é o que separa *não medido* de *medido e são*.

## O recibo canônico está sem endereço

O playbook pede `_saida-01.md` na pasta dele. A pasta não existe mais, e não pode ser recriada sob
`prototipo-ui/`: o `scripts/governance/cowork-ssot-guard.mjs` R3 só admite `.md` em
`prototipo-ui/cowork/<dono>/handoffs/<nome>.md`, flat. O conteúdo do `_saida-01` está no corpo do
#7249 e aqui. **Decisão de [W]**: onde o programa de playbooks passa a viver.

## Nota de método (duas, e valem mais que o resultado)

1. **O heredoc do shell comeu o arquivo na primeira tentativa** (aspas desbalanceadas no transporte,
   família [LC-26](../LICOES_CODE.md)). Reescrito pela ferramenta de escrita direta, e verificado
   por varredura de bytes.
2. **A 1ª varredura de bytes usou `grep -P`, que falhou por locale (`-P supports only unibyte and
   UTF-8 locales`) e teve o erro engolido pelo `||`** — imprimindo *"ok"* sem ter medido nada. É
   literalmente a armadilha de [§5 2026-07-31](../proibicoes.md) (*vazio que era falha de execução*)
   cometida por quem a conhece. Refeita com `node`, que não pode falhar em silêncio:
   `controle=0 · BOM=false · CR=false` nos dois arquivos.

## Fila de [W] (a thread não destrava nenhuma)

`D-GHOST` (ghost `/compras/create` × Non-Goal C1 do `Purchase/Create`) · `D-FORN` (Fornecedores é
`contacts type=supplier` e não tem receptor) · `D-GRADE` (smoke/canary da grade tam×cor,
US-COM-005) — threads 03/04/05, bloqueadas por decisão, não por código. E a nova: **onde o programa
de playbooks passa a viver**.

## Não verificável daqui

Verde no CI · `design-diff --compare --check` (T7) · screenshot de prod. **Nenhum teste rodou
local** ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)): o
`npx playwright test --list` parseia os arquivos e confirma que a lane os enxerga (12 testes em 2
arquivos), mas não os executa.
