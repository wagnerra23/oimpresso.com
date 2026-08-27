---
id: requisitos-jana-runbook-metas
title: "RUNBOOK — Metas da Jana (Blade → Inertia)"
type: runbook
authority: canonical
lifecycle: ativo
status: ativo
owner: W
created: '2026-08-26'
last_validated: "2026-08-27"
modulo: Jana
telas:
  - Jana/Index
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w
  - 0366-fronteira-jana-forja-governance-kb
---

# RUNBOOK — Metas da Jana

> **F1 do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Nasceu em 26/08 para destravar o hook `block-mwart-violation` no primeiro `Edit` em
> `resources/js/Pages/Jana/Metas/*.tsx` — e **não há override** (o `/mwart-override` que a
> mensagem do hook anuncia não tem handler; ver lápide §5 2026-08-08).
>
> ⚠️ **Com o destino decidido em 27/08 (drawer no Painel — §9), esses `.tsx` não vão existir, e
> o gate do hook para esta migração passa a ser o `RUNBOOK-index.md`** (que existe): o hook
> resolve o RUNBOOK pelo **nome do arquivo** derivado da tela editada, e a tela é `Jana/Index`.
> Este documento deixa de ser a chave do hook e continua sendo a **F1 do trabalho** — o
> inventário (§2), o contrato (§3), o Tier 0 (§4), a permissão (§4.1) e as ondas (§9.4).

## 0. O que `last_validated` cobre (e o que NÃO cobre)

O schema define `last_validated` como *"última data que rodou o RUNBOOK e o resultado bateu"*.
Em **2026-08-26** rodou e bateu o **inventário** (§2) e o **contrato** (§3): as 9 rotas, o
`MetasController` inteiro e as 4 views Blade foram lidos em `origin/main` e conferem com o que
está escrito aqui.

**NÃO cobre a migração** — as ondas do §6 (F2 baseline → PR-1..PR-4) não rodaram; nesta data
elas não existiam. Quem reabrir isto depois de um PR de Metas tem que **re-rodar o inventário**
e bumpar a data; um campo dizendo 2026-08-26 depois que a tela virou React é carimbo, não recibo.

> **Bump para 2026-08-27 — o que foi re-rodado (é a regra do parágrafo acima sendo cumprida).**
> Re-lidos em `origin/main`: `Modules/Jana/Http/routes.php` (bloco `/ia`, linhas 118-145),
> `MetasController.php` (115 ln, íntegro), `Modules/Jana/Resources/permissions.php` e
> `git ls-tree -r origin/main -- Modules/Jana/Resources/views/`. **O inventário mudou** — o §2
> ganhou 2 rotas que faltavam e nasceu o §4.1 (permissão). **A migração continua sem rodar**;
> o que mudou foi o DESTINO dela (§9), não o progresso. Nada aqui declara tela migrada.

## 1. Por que agora

[W] dirigiu o trabalho em 2026-08-26. A trava de sinal **não se aplica** ([ADR 0382](../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)).

**O número, dito uma vez e não repetido:** `jana_metas` = **0** nos 88 businesses (medido no
PR #6116, 2026-08-21, com controle positivo do instrumento na mesma sessão). Está aqui como
dado de contexto para quem for testar — **não** como argumento contra fazer.

**O motivo técnico independente:** as 4 telas são Blade dentro de um app Inertia. O ghost
`metas` foi **removido da faixa de abas** (`DataController.php:329-332`) porque *"MetasController@index
ainda retorna Blade view, o que faz Inertia `<Link>` silenciar (click no-op)"*. Ou seja: a
capacidade existe, está roteada, e **está fora da navegação por ser Blade**. Migrar devolve o
acesso — isso não depende de haver meta cadastrada.

## 2. Superfície atual (medida em `origin/main` — 9 linhas em 2026-08-26, as 2 últimas em 2026-08-27)

| Rota | Verbo | Controller | View Blade | Tamanho |
|---|---|---|---|---|
| `/ia/metas` | GET | `MetasController@index` | `copiloto::metas.index` | 983 B |
| `/ia/metas/create` | GET | `MetasController@create` | `copiloto::metas.create` | 1.390 B |
| `/ia/metas` | POST | `MetasController@store` | — (redirect) | — |
| `/ia/metas/{id}` | GET | `MetasController@show` | `copiloto::metas.show` | 1.516 B |
| `/ia/metas/{id}/edit` | GET | `MetasController@edit` | `copiloto::metas.edit` | 1.059 B |
| `/ia/metas/{id}` | PATCH | `MetasController@update` | — (redirect) | — |
| `/ia/metas/{id}` | DELETE | `MetasController@destroy` | — (redirect) | — |
| `/ia/metas/{id}/reapurar` | POST | `MetasController@reapurar` | — (redirect) | — |
| `/ia/metas/{id}/fonte` | GET | `KB\FontesController@show` | `copiloto::fontes.show` | — |
| `/ia/metas/{id}/fonte` | PATCH | `KB\FontesController@update` | — (redirect) | — |
| `/ia/metas/{meta}/periodos` | POST · PATCH · DELETE | `PeriodosController` | — (redirect) | — |

O docblock do controller se declara **STUB spec-ready**. As views são AdminLTE cru
(`@extends('layouts.app')`, `.box`, `.form-control`, `.btn`), fora do sistema de token.

> ⚠️ **As 2 últimas linhas entraram em 2026-08-27** e a tabela original não as tinha — não é
> detalhe de completude, muda o escopo: `jana.fontes.update` (`routes.php:141`) significa que a
> Fonte **não é só leitura**, e `Route::resource('/metas.periodos', …, ['only' => ['store',
> 'update','destroy']])` (`:135`) é um CRUD inteiro sem nenhuma das 4 views Blade deste RUNBOOK.
> Quem migrar a Fonte como painel read-only, ou ignorar Períodos, entrega menos do que existe.
> **Períodos não tem view Blade** — hoje é superfície de escrita sem UI própria; se ela deve
> ganhar UI no drawer é decisão [W], não conserto de passagem.

## 3. Contrato preservado (o que NÃO pode mudar)

Derivado das views, não inventado:

**Index** — tabela `Nome · Unidade · Origem · Ativo`; nome é link pro detalhe; botão primário
`Nova meta`; vazio literal **"Nenhuma meta cadastrada."**

**Create** — campos `nome` (obrigatório), `slug` (obrigatório, `pattern="[a-z0-9_]+"`),
`unidade` (`R$` · `qtd` · `%` · `dias`), `tipo_agregacao` (`soma` · `media` · `ultimo` ·
`contagem`). Botão `Criar`. Validação real é `StoreMetaRequest` — **não reimplementar no front**.

**Show** — `Slug`, `Tipo`, `Origem`, `Escopo` (`Business #N` ou **`Plataforma`** quando
`business_id` é null); tabela `Últimas apurações` (`Data` · `Valor realizado`, `number_format`
pt-BR 2 casas, **limite 12**); vazio **"Nenhuma apuração ainda."**; 3 ações — `Forçar reapuração`
(POST), `Editar`, `Fonte`.

**Edit** — só `nome` e `unidade` (o create tem 4 campos, o edit tem 2 — **é assim de propósito**,
`slug` e `tipo_agregacao` não se editam). Botões `Salvar` + `Cancelar`.

⚠️ **`destroy` é soft**: faz `update(['ativo' => false])`, não apaga linha. A UI não pode dizer
"excluir" — o verbo honesto é **desativar**.

## 4. Multi-tenant — Tier 0, e aqui tem armadilha

`Meta` tem `business_id` e global scope. **`MetaApuracao` NÃO tem coluna `business_id`** — o
escopo é **indireto, via `meta_id`**. O próprio controller documenta: tocar apuração a partir
do `$id` cru da URL **vazaria entre tenants**; carregue a `Meta` pelo global scope ANTES.

Consequência para a migração: qualquer payload novo que leia `MetaApuracao` tem de partir da
`Meta` já resolvida. Pest cross-tenant obrigatório (tenant fictício **98** vs **99**,
[ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca biz=4**.

`reapurar` já passa `businessId` explícito ao `ApurarMetaJob` porque o worker do CT 100 não tem
`session()`. **Preservar.**

## 4.1 Permissão — a camada que faltava aqui, e ela NÃO está enforçada (medido 2026-08-27)

A permissão existe e é declarada: `jana.metas.manage`, risk `medium`, `requires: ['jana.access']`
(`Modules/Jana/Resources/permissions.php:38`). **O servidor não a exige em lugar nenhum.**

Varredura contada em `origin/main` — `git grep "jana.metas.manage"` devolve **9 ocorrências em 7
arquivos**, e **nenhuma** é um gate de request:

| Onde | O que é |
|---|---|
| `permissions.php:38` | a declaração |
| `topnav.php:23` (`'can' => …`) · `DataController.php:73` e `:225` | **navegação** — menu, ghost, condicional de UI |
| 5 restantes | docs (`memory/**`), não código |

E do outro lado: `MetasController`, `PeriodosController` e `KB\FontesController` têm **0**
ocorrências de `middleware`/`authorize`/`can(`/`Gate::`/`abort_unless` cada; o bloco de rotas
`metas`/`periodos`/`fonte` tem **0** `can:` por rota; o único gate é o do grupo `/ia`, que é
`can:jana.access` (`routes.php:50`).

⇒ **Hoje, quem tem `jana.access` cria, edita, desativa, reapura e altera a Fonte por URL direta,
sem `jana.metas.manage`.** O que a permissão faz é esconder o item de menu.

⚠️ E o comentário do `routes.php:138` diz `// ---- Fontes (aninhado em meta, permissão restrita)`
— afirma um enforcement que o código não tem. É a classe LC-10 (artefato afirmando o próprio
enforcement); quem ler o comentário assume protegido e não confere.

**Por que isso é do RUNBOOK e não "de passagem":** no destino drawer (§9) o container é o Painel
`/ia`, que é `can:jana.access` — ou seja, **todo mundo que vê o Painel veria o drawer**. Migrar o
CRUD pra dentro dele sem resolver isto move um gap de URL-direta para um gap de botão visível.

⛔ **Não ligar o `can:` de passagem nesta migração.** Ligar muda quem consegue trabalhar hoje: é
exatamente o "ou vaza o que era gated, ou trava quem tinha acesso". Qual dos dois lados corrigir
— enforçar no servidor, ou rebaixar a permissão a rótulo de menu — é **decisão [W]**, com
`jana_metas = 0` em 88 businesses (§1) tornando o custo de errar baixo agora e alto depois.

## 5. Padrão de Tela

> ⛔ **SUPERADA em 2026-08-27 por decisão [W] — leia o §9 ANTES de agir por esta seção.** Ela
> descreve 4 telas Inertia próprias; o destino decidido é **drawer no Painel `/ia`**. Seguir a
> tabela abaixo hoje faria criar `Pages/Jana/Metas/*.tsx` ao lado de um dono que já existe
> (`JanaMetaDrawer.tsx`) — a classe LC-19. O texto fica como registro do que era verdade em 26/08.

> _Texto original de 26/08, preservado (não vale mais — ver §9):_
>
> | Tela | PT | Razão |
> |---|---|---|
> | `Metas/Index` | **PT-01 Lista** | lista paginável de entidade |
> | `Metas/Create` · `Metas/Edit` | **PT-02 Form/Drawer** | form de cadastro (modo FOCO — o SPEC põe `/ia/metas*` **fora da fusão**) |
> | `Metas/Show` | **PT-03 Detalhe** | detalhe full-page com histórico |

⚠️ **O que dizia sobre as abas, e por que não decide sozinho:** *"o SPEC (US-COPI-148) diz
literalmente 'Fora da fusão (ficam FOCO, sem abas): `/ia/pro` e `/ia/metas*`'"*. A citação é
**exata** (`SPEC.md:2039`) e continua valendo contra virar **aba** — o drawer não é aba, então a
decisão [W] e essa linha do SPEC não se contradizem nesse ponto. Onde elas **de fato** conflitam
é em `/ia/metas*` "ficar FOCO", isto é, seguir sendo rota própria em modo foco: o destino drawer
absorve a superfície. Precedência canon (teste verde > casos > charter > SPEC) e [ADR 0382](../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)
resolvem a favor da decisão [W] — mas **reconciliar o texto da US é ato de escopo, não conserto
de passagem**; está nomeado como pendência no §9.3.

## 6. Ondas (1 PR = 1 intent, ≤300 linhas)

> ⛔ **SUPERADA em 2026-08-27 — as ondas novas estão no §9.4.** As de baixo criam 4 telas
> próprias; ficam como registro. **O que sobrevive delas inteiro é a F2** (repetida no §9.4):
> baseline Pest ANTES de tocar UI é regra do MWART e não muda com o destino.

- **F2 · baseline** — Pest do `store`/`update`/`destroy`/`reapurar` **antes** de tocar em UI.
  Sem baseline, regressão silenciosa (proibição MWART). ✅ **segue valendo**
- ~~**PR-1 · `Metas/Index`** — PT-01, `Inertia::defer` na lista, vazio com a copy literal.~~
- ~~**PR-2 · `Metas/Show`** — PT-03 + as 3 ações; `Fonte` continua saindo pro KB.~~
- ~~**PR-3 · `Metas/Create` + `Metas/Edit`** — PT-02; validação continua no FormRequest.~~
- ~~**PR-4 · cutover** — remover as 4 views Blade, **devolver o ghost `metas`** ao `DataController`
  (é o que fecha o defeito real), lápide no lugar das views.~~

Cada PR: charter + `casos.md` + teste citando o UC (`casos-gate` é required).

## 7. Riscos declarados

1. **Sem rede de pixel** — `visual-regression` saiu do required em 2026-08-26 (#6278, decisão [W]).
   O gate é olho humano + `contrato-de-tela`.
2. **`ativo=false` não some da lista** — o `index` ordena por `ativo` desc mas **não filtra**.
   Meta desativada continua listada. É o comportamento atual; mudá-lo é decisão [W], não conserto.
3. **US-COPI-031 tem metade aberta** — `reapurar` não aceita range; apagar apurações destruiria
   as 12 janelas que a US-COPI-011 exige. Contrato de rota novo = decisão [W]. **Não resolver de passagem.**
4. **`slug` é imutável de fato** (não está no edit) mas nada no banco impede. Não introduzir
   edição de slug nesta migração.

## 8. Definição de pronto

> ⛔ **SUPERADA em 2026-08-27 — a DoD nova está no §9.5.** A de baixo pede "ghost `metas` de
> volta na SubNav", que no destino drawer **deixa de ser o alvo** (não há rota de aba pra
> acender). Fica como registro.

~~`/ia/metas` renderiza Inertia · ghost `metas` de volta na SubNav e o `<Link>` navega · 4 telas
com charter + casos + teste · Pest cross-tenant 98×99 verde no CT 100 · zero view Blade em
`Modules/Jana/Resources/views/metas/` · smoke real em prod colado no PR do cutover (R1).~~

---

## 9. Reconciliação 2026-08-27 — o destino mudou, e o dono já existe

> **Como ler:** nada do §1-§4 foi reescrito (o inventário e o contrato seguem válidos; o §2 e o
> §4.1 só GANHARAM linhas). O que esta seção substitui está marcado no §5, §6 e §8. Base:
> `origin/main`, worktree em sinc (`git rev-list --left-right --count origin/main...HEAD` = `0 0`).

### 9.1 · A decisão

[W] em 2026-08-27: o destino das Blade de metas é **drawer no Painel `/ia`** — não tela Inertia
própria. E o escopo é maior do que este RUNBOOK assumia: a fonte de design `jana-metas.jsx`
declara no próprio cabeçalho que absorve `metas/{index,create,edit,show}` **+** `fontes/show` —
**5** das 9 views Blade do módulo, de uma vez. O inventário das 9 e a reescrita da onda 7 estão
em [`PARIDADE-area-jana-diagnostico-e-ondas.md` §9](PARIDADE-area-jana-diagnostico-e-ondas.md)
— **não repetidos aqui de propósito** (§5 2026-07-17: doc canônico não restateia número que
outro doc sabe melhor).

### 9.2 · O dono já existe — e é por isso que o §6 antigo era perigoso

`resources/js/Pages/Jana/_components/JanaMetaDrawer.tsx` (**287 ln**) já está no ar desde
2026-08-17, ancorado em `jana-merge.jsx §JmMetaDrawer`, consumido por `Index.tsx` e por **2
testes** (`FarolServerSideTest.php`, `PainelContratoTest.php`) — `git grep -l JanaMetaDrawer`
devolve **11 arquivos**. Ele já faz a meta abrir **na própria tela**.

O que falta não é o drawer: é o **CRUD** (create · edit · desativar · reapurar · fonte ·
períodos), que segue nas Blade. O ponto de costura é literal e está no rodapé dele
(`JanaMetaDrawer.tsx:274`): ``<Link href={`/ia/metas/${meta.id}`}>`` — o botão que hoje **tira o
usuário do Painel rumo à Blade**. É esse link que a migração faz desaparecer.

⇒ A instrução correta é **estender o `JanaMetaDrawer`**, nunca criar `Pages/Jana/Metas/*.tsx`.

### 9.3 · Os dois bloqueadores — **os DOIS caíram em 27/08 (ver §10)**

**B1 — não há fonte de design fiel. ✅ RESOLVIDO em 2026-08-27** pelo
[#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379), que mergeou **na janela entre o
rebase e o merge deste RUNBOOK** — ver §10, que corrige o que este parágrafo afirmava. O texto
abaixo é o estado de quando foi escrito, preservado porque explica as ondas.

> _Estado anterior (falso a partir de 2026-08-27 ~22:4x):_ `jana-metas.{jsx,css}` existem no
> Cowork vivo (`DesignSync.list_files` por ID) e **não** estão no espelho. O bundle da rota
> principal existe (`sync/bundle.manifest.json` + 43 partes) mas é de **2026-08-24T22:49Z**, modo
> `snapshot`, 255 arquivos, e **não contém** `jana-metas.*` nem `jana-telas-novas.*`.

> **O bundle é no-op — medido 2×, independentemente.** O `jana-merge.jsx` **do bundle** era
> byte-idêntico ao do espelho (58.381 B, sha `a265b6e685672887` nos dois). Rodar
> `aplicar-payload.mjs` repõe o que já existe (lápide §5 2026-08-25 na prática). O
> [#6378](https://github.com/wagnerra23/oimpresso.com/pull/6378) chegou à mesma conclusão pelo
> `bundleId` (`5023b274`, mesmo `generatedAt`) sem saber desta medição — duas rotas, um veredito.

**B2 — âncora STALE bloqueando a tela de destino. ✅ RESOLVIDO em 2026-08-27** pelo
[#6378](https://github.com/wagnerra23/oimpresso.com/pull/6378), de uma sessão irmã, **enquanto
este RUNBOOK era escrito**. Re-medido aqui por mordida depois do rebase:
`charter-validate.mjs` devolve **`allow`** para `Edit` em `Pages/Jana/Index.tsx`, e
`ancora.mjs Jana/Index` diz `frescor: verificado … 2026-08-27T21:26:40Z`.

> _Estado anterior, preservado porque explica o desenho das ondas:_ o hook devolvia `deny`; os
> **3** charters da área (`Index`, `Chat`, `Memoria`) declaram `related_prototype:
> prototipo-ui/cowork/jana-merge.jsx`, e o ledger tinha `staleList: ["jana-merge.jsx"]` na
> rodada `--compare` de 2026-08-26T22:07Z. Não há env de bypass, e isso é deliberado.

⇒ **A correção que a queda do B2 obriga:** eles **não** tinham "a mesma raiz e o mesmo
destravador", como este parágrafo afirmava antes. O que destravou o B2 foi a **rota avulsa**
(`get_file` → `--snapshot-from` → `--export-from`, o script escrevendo o `raw.content`) — e ela
**não** alcançava o B1 **por `get_file`**, porque `jana-metas.jsx` é pequeno: volta **inline** em
vez de persistir em arquivo, e o `--export-from` lê **do disco**. É o teto de transporte da errata
§5 2026-08-14.

> ⚠️ **Mas a conclusão que este parágrafo tirava daí — *"o B1 só fecha pela rota do bundle"* —
> estava ERRADA, e o §10 mostra por quê.** Existe uma **terceira** rota, que foi a que fechou:
> [W] exportar o projeto Cowork inteiro e entregar os arquivos **em disco**, alimentando o mesmo
> `--export-from`. O teto é do **`get_file`**, não do `--export-from`; com o byte já em disco, o
> tamanho do arquivo deixa de importar. Eu enumerei duas rotas e concluí sobre o problema — a
> lápide §5 2026-08-20 (*concluir ausência de capacidade a partir das rotas que você testou*).

⇒ **Consequência prática (corrigida pelo §10):** com o B2 fora, `Pages/Jana/*.tsx` ficou
**editável**; com o B1 fora poucas horas depois, **o desenho também existe**. Não há mais
bloqueador de transporte — a F2 (§9.4) deixa de ser "o único passo executável" e passa a ser
apenas **o primeiro**, pela regra do MWART (baseline antes de UI), não por falta de fonte.

**Pendência de escopo nomeada (§5):** reconciliar `SPEC.md:2039` (US-COPI-148, *"Fora da fusão …
`/ia/metas*`"*) com o destino drawer. É ato de escopo de US — **decisão [W]**, não conserto de
passagem.

### 9.4 · Ondas novas

- **F2 · baseline** — Pest do `store`/`update`/`destroy`/`reapurar` **antes** de tocar UI. Herdada
  intacta do §6. ✅ **Executável agora** — não depende do B1 (é PHP, não `Pages/Jana/`). Cobrir
  também `PeriodosController` e `FontesController@update` (§2), que não tinham baseline prevista.
  Cross-tenant 98×99, CT 100.
- **PR-1 · trazer o `jana-metas` (B1)** — ✅ **JÁ FEITA** pelo
  [#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379) em 27/08, por rota que este
  RUNBOOK não previa (export completo do [W] em disco → `--export-from`). **Não refazer**, e não
  regerar o bundle por causa disto. Ver §10.1.
- **PR-2 · CRUD no drawer** — estender `JanaMetaDrawer` (criar/editar/desativar/reapurar),
  preservando o contrato do §3 literal (copy de vazio, 4 campos no create × 2 no edit, "desativar"
  e não "excluir") e a validação nos FormRequests.
- **PR-3 · Fonte e apurações como seções** — `fontes/show` entra no drawer; ⚠️ o dono é
  `Modules\KB` ([ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md)), então
  mexer em props de Fonte é PR que cruza fronteira de módulo.
- **PR-4 · cutover** — remover as **5** views Blade (`metas/*` + `fontes/show`), tirar o `<Link>`
  do rodapé (`:274`), lápide no lugar. ⚠️ **Não antes do drawer entregar o que a Blade fazia** —
  as rotas estão vivas e o topnav lista `/ia/metas` com `can: jana.metas.manage`.

Cada PR: charter + `casos.md` + teste citando o UC (`casos-gate` é required). ⚠️ Charter e casos
da área são **154,5 KiB de UC numerado**, citados de fora (contrato de tela, scorecards):
**emendar, nunca reescrever** — reescrever zera referência cruzada.

⚠️ **Contrato de tela: estender, não criar.** `prototipo-ui/contrato/jana-painel.contract.json`
já existe e está **ativo no CI** com copy pinada por [W]. O drawer entra como seção nova nele.

### 9.5 · Definição de pronto (nova)

Metas criadas, editadas, desativadas e reapuradas **sem sair do Painel `/ia`** · o `<Link>` do
rodapé do drawer não existe mais · Fonte e apurações como seções · charter e casos **emendados**
(não reescritos) com UC novo citado por teste · Pest cross-tenant 98×99 verde no CT 100 · zero
view Blade em `Modules/Jana/Resources/views/metas/` **e** `fontes/show.blade.php` removida · o
`staleList` do ledger vazio para `jana-merge.jsx` na rodada que precede o merge · smoke real em
prod colado no PR do cutover (R1).

**Fora desta DoD, por sinal e não por completude** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):
`alertas/{index,config}`, `superadmin/metas` e `emails/weekly-digest` (e-mail é Blade por
definição). São as outras 4 das 9.

---

## 10. Errata 2026-08-27 (pós-merge) — o B1 caiu na janela do próprio PR, e a fonte responde 2 pendências

> **Por que existe:** o §9.3 foi escrito afirmando o B1 **aberto**, e o
> [#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379) o fechou **entre o rebase e o
> merge** do [#6384](https://github.com/wagnerra23/oimpresso.com/pull/6384) — ou seja, o
> documento nasceu com uma afirmação em presente já falsa (classe LC-10). Isto corrige, sem
> apagar: o texto do §9.3 fica marcado, este é o estado.

### 10.1 · Estado real dos bloqueadores

| | Estado | PR | Prova |
|---|---|---|---|
| **B2** âncora STALE | ✅ caiu | [#6378](https://github.com/wagnerra23/oimpresso.com/pull/6378) | `charter-validate` → `allow`; `ancora.mjs Jana/Index` → `frescor: verificado` |
| **B1** sem fonte de design | ✅ caiu | [#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379) | `jana-metas.jsx` **24.187 B** e `jana-metas.css` **3.087 B** em `prototipo-ui/cowork/` (medido no disco, em `origin/main`) |

O #6379 trouxe junto `jana-telas-novas.{jsx,css}` (35.454 B / 4.004 B) e os 3 pedidos de
27/08 para `prototipo-ui/design-docs/cowork-inbox/` — inclusive o `JANA-ERRATA-CAMADA-ESQUECIDA`,
que é a intake desta migração.

⇒ **O trabalho está DESBLOQUEADO.** A PR-1 do §9.4 ("trazer o `jana-metas`") **já está feita** —
não por mim, e não pela rota que eu previa. Não refazer.

### 10.2 · O escopo, agora lido da fonte (não inferido)

Cabeçalho de `prototipo-ui/cowork/jana-metas.jsx`, literal: absorve `metas/{index,create,edit,show}`
**+** `fontes/show` *"para dentro da tela única da Jana — sem rota nova, sem .html novo"*. E o
mapeamento tela→padrão vem declarado nele:

| Blade | Vira | PT |
|---|---|---|
| `metas/index` | tabela de cadastro (view "Cadastro" da seção Metas) | — |
| `metas/create` · `metas/edit` | **drawer de formulário** | **PT-02** (*"nunca modal full-screen"*) |
| `metas/show` | seções "Apurações"/"Fonte" do drawer de detalhe | — |
| `reapurar` | **modal** de confirmação | **PT-04** |

⚠️ O protótipo declara que *"salvar/reapurar não fala com servidor"* — é ritmo de UI, não contrato
de dados. O contrato de dados continua sendo o §3 deste RUNBOOK.

### 10.3 · A fonte responde a pendência que o §2 tinha mandado pro [W]

O §2 registrou `Route::resource('/metas.periodos')` como *"CRUD inteiro sem nenhuma view Blade …
se deve ganhar UI no drawer é decisão [W]"*. **A fonte responde:** o formulário do protótipo tem
`JM_JANELAS` (mensal · trimestral · semanal) e um campo `alvo` — e nenhum dos dois é coluna de
`jana_metas` (medido na migration `…000001_create_copiloto_metas_table.php`: `slug`, `nome`,
`unidade`, `tipo_agregacao`, `ativo`, `origem`, `criada_por_user_id`, `business_id`).

Eles são de **`jana_meta_periodos`** (`…000002`): `tipo_periodo`, `data_ini`, `data_fim`,
`valor_alvo`, `trajetoria`. ⇒ **Períodos não é superfície órfã — é `janela` + `alvo` no mesmo
formulário do drawer.** O `PeriodosController` é o backend dele e já existe. Deixa de ser decisão
[W] e vira parte da PR-2.

⚠️ **Divergência de enum, medida — não resolver de passagem.** Backend `tipo_periodo` =
`mes · trim · ano · custom`; protótipo `JM_JANELAS` = `mensal · trimestral · semanal`.
**`semanal` não existe no backend**; **`ano` e `custom` não existem no protótipo**. Migration nova
(ou recusar `semanal`) é **decisão [W]** — mexer em enum de tabela viva não é ajuste de UI.

### 10.4 · O que sobra para [W]

1. **Permissão de metas** (§4.1) — enforçar no servidor ou rebaixar a rótulo de menu. **Não caiu.**
2. **`SPEC.md:2039`** (US-COPI-148, *"Fora da fusão … `/ia/metas*`"*) — reconciliar com o drawer.
3. **Enum de `tipo_periodo`** (§10.3) — `semanal` entra no backend, ou sai do formulário?

O que **não** sobra mais: regerar o bundle (o #6379 resolveu por outra rota) e destravar a âncora.
