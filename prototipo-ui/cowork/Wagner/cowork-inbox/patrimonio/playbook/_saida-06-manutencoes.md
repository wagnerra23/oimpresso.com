---
sessao: "06-manutencoes"
titulo: Manutenções — a MIGRAÇÃO não começou (fundação ausente); saiu o conserto de autorização
dono: "[C]"
base: 0f39a46a06 (origin/main fresco, lido 2026-09-08) → rebaseado em 62c9a0f623
thread: 06-ui-bloqueada.md (aba 4 de 7)
prefixo_escrito: Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php · Modules/AssetManagement/Tests/Feature/MaintenanceAuthGateTest.php
veredito: "PARCIAL — tela NÃO feita (gate de dependência bateu); defeito D1 de autorização CONSERTADO em PR próprio (#7034, mergeado por [W] em 2026-09-08 17:11Z · commit 0ff7ff328e · EM PRODUÇÃO, ver §8)"
invalida: "NADA. Não invalida thread nenhuma. CORRIGE uma afirmação MINHA, feita neste mesmo PR e refutada pela minha própria medição (ver §4). CONFIRMA integralmente o `_saida-04.md §1` (D1) e o `§2a-bis` (campos auditados inexistentes) — reproduzi as duas medições e batem 1:1."
---

# 06-manutenções · Saída — a tela não começou, e o motivo é o gate que [W] definiu

> **A migração de `Manutencoes.tsx` NÃO foi feita.** Não por falta de tempo ou de escopo: a
> dependência declarada não existe no `main`. O que saiu desta thread foi o **conserto do
> defeito de autorização** — que é PHP no controller e **não** depende da fundação.

---

## 1 · O gate de parada — medido, não presumido

A instrução da thread: *"A tela Bens funda `Pages/Patrimonio/_shared/**`. Se ainda não existir
no main, PARE e reporte."*

**Três fontes independentes, todas negativas:**

| fonte | comando | resultado |
|---|---|---|
| árvore do `origin/main` | `git ls-tree -r --full-tree --name-only origin/main \| grep -i "Pages/Patrimonio"` | **0** |
| PRs abertos, cruzados **por arquivo** (não por título) | `gh pr view <n> --json files` nos **4** abertos | nenhum funda `_shared`; o único de Patrimônio é `Resources/lang/pt/lang.php` (#7031) |
| dono do inventário | [`06-ui-bloqueada.md`](06-ui-bloqueada.md) | *"Nada dos 46 arquivos existe. **Destravado ≠ começado**."* Os 2 `_shared` estão nessa lista |

O padrão `_shared` **existe** em 4 outros módulos (`Financeiro`, `Jana`, `Ponto`, `governance`) —
ou seja, é convenção real, e Patrimônio simplesmente ainda não foi fundado. A ordem que o
`06-ui-bloqueada.md` sugere põe **Bens** antes justamente porque é ela quem funda.

**Parei. Não escrevi `.tsx`, charter, casos nem RUNBOOK** — escrevê-los ancorados numa fundação
inexistente é o retrabalho que o gate previne (o formato de props/subnav/tokens sai do `_shared`).

---

## 2 · O que saiu: D1 conservado — os DOIS defeitos do mesmo `if`

O `_saida-04.md §1` já tinha CONFIRMADO o defeito. **Reproduzi a medição e bate 1:1**: mesmo sha
(`b9a20bcbb13c`), mesmos 6 sítios (`:63` `:208` `:243` `:286` `:322` `:354`), mesma forma.

O conserto **não depende de `_shared`**, e com a tela barrada a alternativa *"entra nesta tela"*
deixou de existir — então virou **PR próprio**, que é o que `1 PR = 1 intent` já pedia.

**(a) o `&&` era insatisfazível por construção.** As duas permissões são `is_radio` com o mesmo
`radio_input_name` = `view_maintenance` (`DataController.php:51` e `:58`) — mutuamente exclusivas
na UI de papéis. **Nenhum não-admin consegue marcar as duas.**

**(b) o `|| subscription` anulava o gate.** Colapsava em *"o módulo está assinado"*.

Forma aplicada: a de `AssetController::create()` (`:271`) e `index()` (#7008) — **dois `if`
sequenciais**, permissão de tela primeiro, assinatura depois com escape `superadmin`. Nunca em `OR`.

**Permissão não inventada.** As duas já estão em `DataController::user_permissions()`. **Nenhuma
permissão de escrita foi criada** — o módulo não declara uma, e inventá-la seria decisão de produto.

---

## 3 · A prova — bite-test + mutação (CT 100, MySQL real; nunca local)

| variante do controller | MORDE | CN view_own | CN view_all |
|---|---|---|---|
| **ORIGINAL** (`&&` + `\|\| subscription`) | **FALHA — 200** | passa | passa |
| **CONSERTADO** (`\|\|` + assinatura separada) | passa 403 | passa | passa |
| **MUTANTE** (`&&` + assinatura separada) | passa 403 | **FALHA 403** | **FALHA 403** |

`Expected response status code [403] but received 200` com o controller original: **o defeito era
material, não teórico.**

**Suíte do módulo, mesma árvore, mesmo comando:**

```
baseline (árvore original)   72 passed, 0 failed
com o conserto + o teste     75 passed, 0 failed   (2 medições)
```

Delta **+3 testes** = exatamente os 3 cenários. Zero regressão.

⚠️ **Assertions NÃO são recibo aqui.** A mesma árvore deu `232` e depois `236` em duas execuções.
A base do CT 100 persiste entre runs e o Pest roda em ordem aleatória — o denominador se move
sozinho. O recibo é o **contador de testes** e o `0 failed`.

✅ **Rastro limpo no container:** controller restaurado ao HEAD (`b9a20bcbb13c`, `git status`
vazio), teste removido, `users_orfaos=0 roles_orfaos=0`. O `try/finally` funcionou onde o
`afterEach` do #7008 falhava.

---

## 4 · Errata MINHA (é o que o campo `invalida:` aponta)

O docblock que escrevi no 1º commit afirmava *"o cenário que prova (a) é o `CN view_own`"*.
**A minha própria medição refutou**: ele passa nas DUAS primeiras variantes, logo não discrimina
(a) contra o código original. Quem discrimina o quê está na matriz do §3.

E **(a) não é observável isoladamente por HTTP** — propriedade do sistema, não limitação do teste:
com a assinatura falsa, original e consertado devolvem 403 igual (no consertado quem barra é o gate
de assinatura). Corrigido no código (2º commit); o commit errado **fica no histórico**.

---

## 5 · Resíduo declarado, NÃO consertado — decisão de [W]

`edit()`, `update()` e `destroy()` filtram apenas por `business_id`, **não por dono**. Quem tem só
`view_own_maintenance` vê apenas as suas na listagem, **mas pode editar/remover a de outro** se
souber o id.

**Não é regressão** — por (b), hoje *qualquer* usuário do business já pode. Fechar exigiria uma
permissão de escrita que o módulo não declara: **é decisão de produto, não conserto silencioso.**

---

## 6 · O que a próxima thread de Manutenções vai encontrar

1. **A fundação continua ausente.** Enquanto `Pages/Patrimonio/_shared/**` não existir, o gate
   bate de novo. **Bens primeiro.**
2. **Custo de manutenção segue ABERTO** (item 3 do §6 do [`00-INDICE.md`](00-INDICE.md)). Confirmei
   o schema: `asset_maintenances` tem `id · business_id · asset_id · maitenance_id · status ·
   priority · created_by · assigned_to · details · maintenance_note · timestamps`. **Não tem** `cost`,
   `description`, `maintenance_date` nem `completion_date`. O protótipo pode mostrar custo; o banco
   não guarda. **Não inventar coluna nem migration** — é [W].
3. **`getActivitylogOptions()` audita 3 campos inexistentes** (`start_date`, `end_date`, `amount`) —
   confirmado, bate com o `_saida-04.md §2a-bis`. **Não replicar esses nomes** na tela.
4. **A guarda de autorização já estará lá** (se o #7034 mergear) — a tela **não** precisa reimplementá-la,
   e o `Inertia::render` deve nascer **depois** dos dois `if`, não no lugar deles.

---

## 7 · Pegadinhas medidas nesta thread (custaram tempo real)

| pegadinha | sintoma | o que fazer |
|---|---|---|
| **`.gitattributes` = `* text=auto eol=lf`** | `fatal: CRLF would be replaced by LF` no `git add` | arquivo **novo** nasce **LF**. O CRLF do `AssetMaitenanceController` é **legado dentro do blob** — o blob do `SmokeRoutesTest` (criado hoje) é LF |
| **python `io.open` modo texto** | `git diff` deu **446/371** (arquivo inteiro) em vez de 81/6 | leitura em modo texto normaliza CRLF→LF; reescrever em `wb` reescreve tudo. Diagnóstico: `git diff --ignore-cr-at-eol` |
| **heredoc colapsa `\\` → `\`** | `SyntaxWarning: invalid escape sequence` e depois `unexpected EOF` | escrever PHP com namespace via ferramenta de arquivo, não por heredoc; conferir **cada** barra depois (§5 2026-08-19) |
| **`git show origin/main:.claude/...`** | `unknown revision` (o `:` vira `;`) | path com ponto exige `MSYS_NO_PATHCONV=1` (§5 2026-08-23) |
| **lane `modules-pest` sem `synchronize`** | push seguinte não redispara | `types: [opened, reopened, ready_for_review]` — precisa `workflow_dispatch` |
| **a lane roda SQLite** | o teste **skipa** no CI, e skip sai exit 0 | a prova é o CT 100 (§3). Verde de CI aqui **não** prova a guarda |

⚠️ **O checkout do container é compartilhado e estava SUJO** — 5 arquivos do `AssetManagement`
com alterações não-commitadas de outras sessões (`AssetController`, `AssetAllocationService`,
`CrossTenantAssetTest`, `MultiTenantIsolationTest`, `SmokeRoutesTest`). **Não dei `pull`, `stash`
nem `checkout` global**; só toquei os 2 arquivos que confirmei limpos antes, e restaurei ao sair.

---

## 8 · Estado no fechamento

- **PR [#7034](https://github.com/wagnerra23/oimpresso.com/pull/7034)** — **mergeado por [W] em 2026-09-08 17:11Z**, commit `0ff7ff328e`, CI 80 verdes / 0 falhas.
  _(a 1ª redação desta linha dizia "aberto, não mergeado"; apodreceu no instante do merge — LC-10, afirmação em presente. Corrigida para fato datado.)_

- **EM PRODUÇÃO, verificado — não é declaração de deploy, é o arquivo lá.** `Deploy to Hostinger`
  `success`; `HEAD` em prod = `0ff7ff328`; e as **mesmas 3 sondas** rodadas em prod e no main
  batem: controle positivo `8` · `can('superadmin')` (só existe na forma nova) **`6`** · gate
  velho **`0`** com `rc=1` (não-encontrado, não erro de execução).

  Smoke HTTP com status literal: `/login` → **`200 OK`** (fatal de PHP derrubaria toda rota com
  500); `/asset/asset-maintenance` → **`302`**; adjacentes `/asset/assets`, `/asset/allocation`,
  `/asset/dashboard` → **`302 → /login`**, idênticas — zero regressão de roteamento.

  ⚠️ **O que este smoke NÃO prova:** o `302` vem do middleware `auth`, que roda **antes** do
  controller — logo ele **não exercita o gate**. Provar o 403 em produção exigiria sessão
  autenticada. Quem prova o comportamento é a bateria do CT 100 (§3), com MySQL e sessão reais.
- **Impacto em produção NÃO medido.** O `oimpresso-staging` tem `total_businesses=4` e **não é**
  clone de prod (errata do #7008) — nenhum número dele extrapola. Quem perde acesso é o usuário
  **não-admin sem nenhuma das duas permissões**; o dono do negócio passa pelo `Gate::before`
  (`AuthServiceProvider:34-46`, role `Admin#{business_id}`). **Canary é decisão de [W].**
- **A tela `Manutencoes.tsx` continua por fazer**, e a frente de 7 abas segue como o
  [`06-ui-bloqueada.md`](06-ui-bloqueada.md) descreve.
