---
date: "2026-09-08"
time: "17:49 BRT"
slug: "patrimonio-manutencoes-gate-de-dependencia-e-conserto-403"
tldr: "A tela Manutenções NÃO foi migrada: o gate de dependência bateu — `Pages/Patrimonio/_shared/**` tinha ZERO arquivos no main, medido em 3 fontes. Saiu o conserto do D1 de autorização (dois defeitos no mesmo `if`, 6 sítios): #7034 mergeado e verificado EM PRODUÇÃO. A fundação chegou 31min depois pelo #7035 (Bens, outra sessão) — Manutenções destravada, e o bloqueio restante é a decisão de produto do custo (item 3 do §6)."
decided_by: [W]
prs: [7034, 7036]
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0358-doutrina-de-teste-tenant-98-supersede-0101", "0104-processo-mwart-canonico-unico-caminho", "0394-endereco-de-ui-do-patrimonio-pages-patrimonio", "0344-two-strikes-cobre-processo"]
next_steps:
  - "[W] responder o item 3 do §6 do playbook — custo de manutenção entra? A tabela `asset_maintenances` NÃO tem `cost`; o protótipo mostra. Sem essa resposta a thread de Manutenções não pode escrever o charter, porque Non-Goal é campo que só [W] preenche (canon `charter-write`)"
  - "Thread de Manutenções agora DESTRAVADA: `_shared/PatrimonioSubNav.tsx` entrou no main pelo #7035 às 17:42Z. Caminho MWART: `RUNBOOK-manutencoes.md` ANTES do `.tsx` (o hook `block-mwart-violation` bloqueia em runtime e não tem override)"
  - "Resíduo de autorização NÃO consertado, por ser decisão de produto: `edit/update/destroy` de manutenção filtram só por `business_id`, não por dono — quem tem apenas `view_own_maintenance` pode editar a manutenção de outro se souber o id. NÃO é regressão (antes do #7034 qualquer usuário do business já podia); fechar exige permissão de escrita que o módulo não declara"
  - "Canary do #7034 é decisão [W]: quem perde acesso é o usuário NÃO-admin sem nenhuma das duas permissões. Produção não foi medida — o `oimpresso-staging` tem 4 businesses e não é clone de prod"
  - "`Pest Repair` segue vermelho no main (medido: mesmo padrão no meu run e no run do main, `Pest AssetManagement` verde nos dois) — herdado da thread 03, continua sem dono"
---

# Handoff 2026-09-08 17:49 BRT — Patrimônio/Manutenções: o gate barrou a tela, e o defeito de autorização saiu

## Estado MCP no momento do fechamento

⚠️ **As tools `mcp__oimpresso__*` NÃO estavam conectadas nesta sessão.** O Daily Brief chegou
pelo hook `brief-fetch-curl` (SessionStart), não por tool. Logo **não houve** `cycles-active`,
`my-work`, `sessions-recent`, `decisions-search` nem `tasks-update` — o checklist MCP-first da
[ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) **não pôde ser cumprido**, e isto
fica declarado em vez de omitido. Se alguma US precisa de status, é por outra sessão ou pela UI.

O que substituiu, com recibo: `git ls-tree origin/main` para inventário, `gh pr list/view --json files`
para cruzar PRs abertos **por arquivo** (não por título), e o playbook em `prototipo-ui/design-docs/`.

## 1 · A tela NÃO foi feita — e o motivo é o gate, não falta de tempo

A instrução da thread: *"A tela Bens funda `Pages/Patrimonio/_shared/**`. Se ainda não existir no
main, PARE e reporte."* **Bateu.** Três fontes independentes, todas negativas:

| fonte | resultado |
|---|---|
| árvore do `origin/main` fresco | **0** arquivos em `Pages/Patrimonio/` |
| os 4 PRs abertos, cruzados **por arquivo** | nenhum fundava `_shared` (o único de Patrimônio era `lang.php`, #7031) |
| [`06-ui-bloqueada.md`](../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/06-ui-bloqueada.md) | *"Nada dos 46 arquivos existe. **Destravado ≠ começado**"* |

Não escrevi `.tsx`, charter, casos nem RUNBOOK. Ancorá-los numa fundação inexistente é o
retrabalho que o gate previne — o formato de props/subnav sai do `_shared`.

## 2 · O que saiu: D1 conservado — dois defeitos no mesmo `if` ([#7034](https://github.com/wagnerra23/oimpresso.com/pull/7034), MERGED 17:11Z)

O `_saida-04.md §1` já tinha CONFIRMADO o defeito; **reproduzi a medição e bate 1:1** (mesmo sha
`b9a20bcbb13c`, mesmos 6 sítios). O conserto **não dependia de `_shared`**, e com a tela barrada a
alternativa *"entra nesta tela"* deixou de existir — virou PR próprio, que é o que `1 PR = 1 intent`
já pedia.

**(a) o `&&` era insatisfazível por construção.** As duas permissões são `is_radio` com o mesmo
`radio_input_name` = `view_maintenance` (`DataController:51` e `:58`) — mutuamente exclusivas na UI
de papéis. Nenhum não-admin marca as duas. E barrava justamente o perfil para o qual o filtro de
escopo do próprio `index()` (`:73`) foi escrito.

**(b) o `|| subscription` anulava o gate**, colapsando em *"o módulo está assinado"*. É por isso que
(a) nunca apareceu em produção — e por isso consertar só o `&&` seria **inerte em runtime** (LC-30).

Forma aplicada: a de `AssetController::create()` (`:271`) e `index()` (#7008) — **dois `if`
sequenciais**, permissão de tela primeiro, assinatura depois com escape `superadmin`. Permissão
**não inventada**; **nenhuma permissão de escrita criada**.

## 3 · Prova: bite-test + mutação (CT 100, MySQL real)

| variante do controller | MORDE | CN view_own | CN view_all |
|---|---|---|---|
| **ORIGINAL** (`&&` + `\|\| subscription`) | **FALHA — 200** | passa | passa |
| **CONSERTADO** | passa 403 | passa | passa |
| **MUTANTE** (`&&`, conserto pela metade) | passa 403 | **FALHA** | **FALHA** |

Suíte do módulo: **72 passed / 0 failed → 75 passed / 0 failed**. Delta **+3 testes** = os 3 cenários.

⚠️ **Assertions NÃO são recibo nesta base:** a mesma árvore deu `232` e depois `236` em duas
execuções. O CT 100 persiste entre runs e o Pest roda em ordem aleatória — o denominador se move
sozinho. O recibo é o contador de testes e o `0 failed`.

## 4 · Em produção — verificado, não é a declaração do deploy

`Deploy to Hostinger` = `success`; `HEAD` em prod = `0ff7ff328`. **Mesmas 3 sondas** em prod e no main:

| sonda | prod | main |
|---|---|---|
| controle positivo (`grep -c view_all_maintenance`) | **8** | **8** |
| `can('superadmin')` — só existe na forma nova | **6** | **6** |
| gate velho | **0** (`rc=1`) | **0** (`rc=1`) |

Smoke HTTP: `/login` **200 OK**; `/asset/asset-maintenance` **302**; as 3 adjacentes **302 → /login**.

⚠️ **O smoke NÃO exercita o gate** — o `302` vem do middleware `auth`, que roda antes do controller.
Provar o 403 em prod exigiria sessão autenticada. Quem prova o comportamento é a bateria do CT 100.

## 5 · Duas erratas minhas, ambas registradas em vez de apagadas

**(i) LC-08 →150.** Afirmei no docblock que *"o cenário que prova (a) é o `CN view_own`"*. Minha
própria medição refutou: ele passa nas duas primeiras variantes. Corrigido no 2º commit com a matriz
no lugar da afirmação. **Irmã menor:** uma sonda em prod devolveu `0` nos DOIS greps — impossível se
o arquivo existe; o padrão é que estava errado, e quem revelou foi o **controle positivo**.

**(ii) LC-10 →7.** O `_saida-06` afirmou em PRESENTE *"#7034, não mergeado"* — apodreceu no instante
do merge. Custo real: a próxima thread leria isso e poderia reimplementar guarda que já está em prod.
Corrigido em [#7036](https://github.com/wagnerra23/oimpresso.com/pull/7036) para fato datado + recibo
de produção. **O que esta instância acrescenta:** foi escrita **horas antes** do evento que a tornaria
falsa, pelo autor do próprio evento — previsível no ato da escrita.

## 6 · O quadro mudou no fim da sessão

**[#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035) (Bens, outra sessão) mergeou às
17:42Z e fundou `_shared/PatrimonioSubNav.tsx`.** A dependência que me barrou **existe agora**.

**Manutenções está destravada** — e o único bloqueio restante é de produto: o **custo** (item 3 do
§6), que segue aberto. Como Non-Goal de charter é campo que **só [W] preenche** (canon `charter-write`
é proibida de inferir), a tela não pode nascer sem essa resposta sem inventar escopo.

## 7 · Higiene

- CT 100: rastro removido, controller restaurado ao HEAD, **0 fixtures órfãos** (o `try/finally`
  funcionou onde o `afterEach` do #7008 falhava). O checkout de lá é **compartilhado e estava sujo**
  (5 arquivos de terceiros) — não dei `pull`, `stash` nem `checkout` global.
- Não commitei `ONBOARDING-AGENTE-GERADO.md` nem `PAINEL-SISTEMA.md`: o `system-map.mjs` os move, mas
  o delta é **drift de terceiros** (398→399 ADRs, 510→511 handoffs) — commitar misturaria intents.
- 6 pegadinhas medidas estão no [`_saida-06-manutencoes.md`](../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-06-manutencoes.md) §7.
