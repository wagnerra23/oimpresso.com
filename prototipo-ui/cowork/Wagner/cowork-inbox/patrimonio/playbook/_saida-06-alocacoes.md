---
sessao: "06-alocacoes"
titulo: "Alocações — a 3ª tela Inertia do Patrimônio (reusa o `_shared` da Bens)"
dono: "[C]"
criado: 2026-09-08
base: e6d53daf1f (origin/main fresco; rebaseado antes do PR)
thread: 06-ui-bloqueada.md (aba 3 de 7)
prefixo_escrito: "resources/js/Pages/Patrimonio/{Alocacoes.tsx,Alocacoes.charter.md,Alocacoes.casos.md} · Modules/AssetManagement/Http/Controllers/AssetAllocationController.php (só o index() + 3 métodos privados) · memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md · +2 FORA do prefixo, cada um exigido por gate required: Modules/AssetManagement/Tests/Feature/AlocacoesContratoTest.php (casos-gate G-2) · memory/requisitos/AssetManagement/SUPERFICIE.md (derivado, regerado)"
pr: "#7046 — aberto, NÃO mergeado"
veredito: "entregue — tela migrada, 4 UC com teste verde no CT 100 (5 passed · 34 assertions) · 1 resíduo Tier 0 herdado MEDIDO com alcance MENOR que o do gêmeo · 1 achado inédito que afeta a Bens já mergeada"
invalida: "NADA de thread irmã. CORRIGE por precisão o enunciado do meu próprio prompt em 2 pontos (`Routes/web.php` no prefixo — não há rota a criar; e o alcance do resíduo Tier 0, que NÃO é igual ao da Bens). ACRESCENTA um achado inédito sobre a Bens já mergeada (§6). CONFIRMA integralmente o `_saida-04.md §5` (o `StoreAssetAllocationRequest` é órfão) e o `_saida-06-bens.md §1` (charter antes do .tsx) — reproduzi as duas e batem 1:1."
---

# 06-alocações · Saída — a tela migrada, e as duas coisas que o enunciado errava

> **Estado da dependência ao começar:** `resources/js/Pages/Patrimonio/_shared/PatrimonioSubNav.tsx`
> **existia** no `main` — a Bens ([#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035))
> mergeou às 17:42Z. A sessão começou **antes** disso e o gate de dependência bateu; o relatório
> de parada foi entregue, e a re-medição na retomada encontrou a fundação no lugar. **Reusei o
> SubNav — não criei um segundo.**

---

## 1 · O que a tela entrega

Onda 1 = **listagem migrada, leitura pura**. Nada aqui grava.

| Entrega | Nota |
|---|---|
| listagem paginada, ordenada por data de alocação **desc** | mesmo default do Blade (`aaSorting:[[7,'desc']]`) |
| busca **server-side** — código, bem, modelo, pessoa | busca no cliente só enxerga as 25 linhas que chegaram |
| recorte por situação **no servidor** | `HAVING`, não `WHERE`: "devolvido" é `SUM` de linhas filhas |
| 12 colunas | as **mesmas** do Blade — zero regressão de informação |
| situação por linha | em uso · devolvida em parte · devolvida · prazo vencido |

**"Prazo vencido" é decidido no servidor**, com o relógio dele. Derivar no browser faria o veredito
depender do fuso da máquina de quem olha — está como Anti-hook no charter.

---

## 2 · Um dono só para a query (o padrão que a Bens abriu)

`baseAllocationsQuery()` é lida pelos **dois** ramos do `index()` — DataTables legado e Inertia.
Antes a expressão existia **inline num ramo só**, e a próxima correção pousaria em um caminho apenas.

**Teste de identidade:** 21 linhas, `diff` vazio — a expressão não mudou uma vírgula.

---

## 3 · ⚠️ CORREÇÃO do enunciado (1/2) — `Routes/web.php` não tinha o que fazer

O prompt listava `Modules/AssetManagement/Routes/web.php` no prefixo, com o aviso de que é
compartilhado e exige rebase imediato. **Não havia rota a criar:**
`Route::resource('allocation', ...)` (`:14`) já cria `allocation.index` → `GET /asset/allocation`.

Rota nova seria **segundo dono da mesma tela**, e mudaria a URL de uma tela em produção sem
necessidade — exatamente o veredito que a Bens já tinha registrado (`_saida-06-bens.md §3`).
**Arquivo não tocado**, o que como efeito colateral tirou este PR do caminho das threads irmãs.

*Autorização não é obrigação.*

---

## 4 · ⚠️ CORREÇÃO do enunciado (2/2) — o resíduo Tier 0 daqui NÃO tem o alcance do gêmeo

O `leftJoin` de `asset_transactions as PT` por `parent_id` (`:70`), que alimenta
`revoked_quantity`, **não filtra `PT.business_id`**. O defeito de código é real e a expressão foi
**preservada byte-a-byte**.

**Medido no CT 100 em 2026-09-08** (leitura pura, as duas formas da agregação lado a lado):

```
alocações varridas ............................... 36
linhas DIVERGENTES ...............................  0
alocações com filha de OUTRO business (pré-cond) .  0
```

**A diferença que importa, e que eu ia errar se copiasse o número da irmã:** a Bens mediu **20 de
128** divergentes — mas naquela query a pré-condição é *"`asset_id` com transação de outro
business"*, que os fixtures cross-tenant (`AST-CRS-TNT01`) produzem. **Aqui a ligação é por
`parent_id`**: o vazamento exigiria uma **devolução de outro tenant apontando para uma alocação
deste**, e essa base não tem nenhuma.

Ou seja: **mesmo defeito de código, alcance menor**. Afirmar que é igual seria inflar o risco a
partir do número do vizinho — a família de erro que o §5 chama de *derivar do lugar errado*.

**Ressalva de honestidade:** a medição prova que a query vaza **se** a pré-condição existir. Ela
**não afirma nada sobre produção**, que não foi medida. Quem for fechar precisa rodar a mesma
sonda contra o banco de prod antes de apresentar impacto ao [W].

Não corrigi: mexer em quantidade é REGRA MESTRE Tier 0 (prova por dois caminhos + antes→depois +
[W]) e 1 PR = 1 intent. Thread dona: 01/02.

---

## 5 · O que a tela RECUSA, e por quê

| Recusado | Motivo |
|---|---|
| rodapé "N unidades alocadas" (protótipo `:462`) | **soma de quantidade** → REGRA MESTRE Tier 0. O número **por linha** entra |
| contagem nas pílulas das sub-abas | contar a página corrente faria a pílula dizer "4" olhando 25 de N linhas |
| alocar / editar / devolver na tela | ver §6 — **medido**, não presumido |
| avatar e "papel" de quem recebeu (protótipo `:437`) | `users` não tem papel na alocação; o protótipo desenha campo que o modelo não tem |

**A trava de saldo não é desta tela** (é a thread 02). E a tela **não finge que ela existe**: não
oferece o caminho de criar, e não desenha aviso de saldo que sugira proteção inexistente.
Validação só no cliente seria segurança de teatro.

---

## 6 · ACHADO INÉDITO — os botões de escrita levam a página em branco (e a Bens já mergeou com isso)

**Medido, não presumido.** Em `AssetAllocationController`, `create()` (`:170`) e `edit()` (`:243`)
só respondem sob `request()->ajax()` — fora dele o método cai no fim e devolve **corpo vazio**.
E as views são **fragmentos de modal jQuery**:

```
asset_allocation/create.blade.php  -> <div class="modal-dialog">   @extends: 0
asset_allocation/edit.blade.php    -> <div class="modal-dialog">   @extends: 0
```

Um `<a href="/asset/allocation/5/edit">` levaria a uma **página em branco** — afordância falsa.
Por isso esta tela **não desenha** esses botões; ela oferece o caminho que de fato funciona:
**Devoluções** (`/asset/revocation`), cujo `index()` devolve view de verdade
(`RevokeAllocatedAssetController:108`, **sem** gate de ajax) — medido.

**⚠️ O mesmo vale para a irmã Bens, que já está no `main`:** `Bens.tsx:533` e `:554` apontam para
`/asset/assets/create`, e `AssetController::create()` tem o mesmo gate `ajax()`, com
`asset/create.blade.php` também fragmento (`<div class="modal-dialog modal-lg">`, **0** `@extends`).
**Os dois botões dela caem em corpo vazio.** Fora do meu prefixo e o PR dela já mergeou —
registrado aqui, é decisão [W].

---

## 7 · Evidência — CT 100, nunca local

```
BASELINE (main materializado, ANTES da minha mudança)
  SmokeRoutesTest ......... 7 passed · 12 assertions

DEPOIS
  AlocacoesContratoTest ... 5 passed · 34 assertions   (4 UC / 5 cenários, novo)
  SmokeRoutesTest ......... 7 passed · 12 assertions   (IDÊNTICO ao baseline)
  suíte do módulo ......... 77 passed · 361 assertions · 0 falhas
```

**O baseline teve a mesma pegadinha que a Bens catalogou, e mais uma.** O checkout do CT 100 está
em `755f6de79`: o `SmokeRoutesTest.php` de lá tinha **4 testes**, contra 7 no `main` — a primeira
execução deu `4 passed` e teria virado baseline falso. Materializei o blob do `origin/main`
(md5 conferido nos dois lados) e o baseline subiu para **7 passed**… **com 2 vermelhos**. Esses 2
eram da tela **Bens** (guarda `asset.view` e lista de garantias), porque o `AssetController.php`
de lá também era pré-merge. Materializei ele e o `Bens.tsx` também, e só então o baseline ficou
**7 passed · 12 assertions** — o mesmo número que a Bens registrou.

**A falha útil do caminho, virada nota no teste:** os 2 cenários que chamam a helper da prop
deferida **duas vezes** (UC-ALOC-03 e UC-ALOC-04) falhavam com *"The response is not a view"*,
enquanto os 3 de chamada única passavam. Causa: o test client do Laravel **acumula** os headers de
`withHeaders()` na mesma instância, então o `X-Inertia` da 1ª chamada vazava para o GET inicial da
2ª, que devolvia JSON em vez da root view. `flushHeaders()` no início da helper: 2 failed → **5 passed**.

**As 3 falhas da suíte completa NÃO são deste PR.** São de `ManutencoesContratoTest`,
`PainelContratoTest` e afins — testes **não-versionados** das sessões irmãs, presentes no CT 100 e
ausentes do `main` (`git ls-tree origin/main` = vazio). A suíte de 77 acima é o `main` + este PR.

**Higiene do ambiente compartilhado:** havia trabalho não-commitado de outras sessões, incluindo o
`AssetAllocationService.php` (thread 01/02, em voo). **Não dei `git pull`.** Copiei só o que toquei
e restaurei ao fingerprint exato do início:

```
AssetAllocationController.php  0d1b291f -> 0d1b291f
AssetController.php            7e41758f -> 7e41758f
AssetAllocationService.php     0ed8bc7c -> 0ed8bc7c   (nunca tocado — é da thread 02)
```

---

## 8 · Gates locais

```
module-surface --namespaces --check ... rc=0
module-surface --all --check .......... rc=0   (SUPERFICIE.md regerado: 109 arquivos)
casos-gate ............................ sem violações novas deste PR (débito −8 vs baseline)
casos-gate --check-baseline-shrink .... rc=0
pages-colisao --check ................. nenhuma chave declarada por duas fontes
ancora.mjs Patrimonio/Alocacoes ....... âncora ✓ [related_prototype] patrimonio-page.jsx
                                        frescor verificado contra o Cowork vivo em 2026-09-08
```

⚠️ **O `module-surface` tem dois modos e o CI roda os dois** — e no meu caso o drift do
`SUPERFICIE.md` aparecia **no meio** da saída, não no `tail`. Vi só porque filtrei por nome do
módulo; um `tail` teria me feito declarar verde com o gate vermelho. É a armadilha do §5 2026-07-28
(*validar um gate rodando UM dos modos*), agora também no eixo **posição na saída**.

`tsc`/`eslint` **não rodaram**: este worktree está sem `node_modules` (mesma limitação que a Bens
registrou). O CI é a régua real.

---

## 9 · Fora do prefixo — registrado, NÃO consertado

1. **Assimetria de permissão.** A thread 03 pôs `asset.view` no `index()` de **Bens**. Este
   controller **não tem** guarda `asset.*` em método nenhum — só o gate de assinatura do módulo.
   Consertar mudaria **quem enxerga a tela**: decisão [W], e 1 PR = 1 intent. Está no §9 do RUNBOOK
   e no `[BACKLOG]` dos casos, com o precedente pronto no `AssetController::index()`.
2. **Os botões de escrita da Bens** (§6) — já mergeados, caem em corpo vazio.
3. **`SPEC.md` ficou atrás** — mesma pendência que a Bens registrou (`_saida-06-bens.md §8.1`): a
   `US-ASSET-W05` segue como backlog feature-wish enquanto a ADR 0394 e o `SCOPE.md` já liberaram
   e estas telas são a entrega dela.
4. **`Alocacoes-visual-comparison.md`** não existe — comparação medida contra o protótipo
   (`design-diff --probe` nos dois lados) é pendência declarada do charter para sair de `draft`.

---

## 10 · Campo `invalida:` — detalhado

| alvo | veredito |
|---|---|
| **prompt desta thread** — `Routes/web.php` no prefixo | **NÃO se aplica.** `allocation.index` já existe via `Route::resource`; rota nova seria segundo dono. Arquivo não tocado. §3 |
| **prompt desta thread** — *"o resíduo Tier 0"* por herança do gêmeo | **PRECISADO.** Mesmo defeito de código, **alcance menor**: a ligação aqui é por `parent_id`, não por `asset_id`, e a pré-condição ocorre **0 vezes** nesta base contra 20 no gêmeo. §4 |
| **`_saida-04.md §5`** (o `StoreAssetAllocationRequest` é órfão) | **CONFIRMADO 1:1.** `store()` (`:186`) recebe `Request` cru, **0** `validate()` no controller. Não escrevi uma linha naquele Request |
| **`_saida-06-bens.md §1`** (charter antes do `.tsx`) | **CONFIRMADO 1:1.** Escrevi RUNBOOK → charter (`related_runbook`) → casos → `.tsx`, e o hook **não bloqueou**. A calibragem dela funciona |
| **`_saida-06-bens.md §2`** (6 abas, `revocation` é aba própria) | **CONFIRMADO e usado.** `active="allocation"`; Devoluções ficou fora desta tela, contra o que o protótipo desenha — a rota manda |
| **`_saida-06-bens.md`** — a tela Bens em si | **ACHADO NOVO contra ela** (§6): os 2 links de `create` caem em corpo vazio. Não invalida a entrega dela; é defeito a decidir |
| **threads 01 e 02** | **INTACTAS.** Não toquei o `AssetAllocationService` — fingerprint idêntico no CT 100 no início e no fim |

---

## 11 · Checklist de saída

| # | item | estado |
|---|---|---|
| 1 | dependência (`_shared`) confirmada no `main` antes de começar | ✅ gate bateu na 1ª medição; re-medido na retomada |
| 2 | RUNBOOK antes do `.tsx` (MWART F1) | ✅ `RUNBOOK-alocacoes.md` |
| 3 | charter antes do `.tsx`, com `related_runbook` | ✅ o hook não bloqueou |
| 4 | baseline do backend antes de tocar o controller (F2) | ✅ 7 passed · 12 assertions |
| 5 | `.tsx` + charter + casos ao lado, linkando-se | ✅ os três |
| 6 | QA (F4) | ✅ 4 UC verdes no CT 100 + suíte do módulo 77/77 |
| 7 | cutover (F5) | ❌ **não feito, como mandado** |
| 8 | PR aberto, **não mergeado** | ✅ [#7046](https://github.com/wagnerra23/oimpresso.com/pull/7046) |
| 9 | ambiente compartilhado restaurado | ✅ 3 fingerprints idênticos ao inicial |
| 10 | campo `invalida:` preenchido | ✅ frontmatter + §10 |

### Como reproduzir os números

```bash
# suíte (CT 100 — nunca local)
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test Modules/AssetManagement/Tests/Feature/AlocacoesContratoTest.php"

# teste de identidade da query (a expressão não mudou)
git show origin/main:Modules/AssetManagement/Http/Controllers/AssetAllocationController.php \
  | sed -n '/\$asset_allocated = AssetTransaction::join/,/groupBy/p'

# gates (os DOIS modos do module-surface)
node scripts/governance/module-surface.mjs --namespaces --check
node scripts/governance/module-surface.mjs --all --check
node scripts/casos-coverage-guard.mjs
```
