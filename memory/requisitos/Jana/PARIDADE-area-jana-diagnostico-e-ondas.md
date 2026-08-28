# Área Jana — paridade protótipo × produção: diagnóstico medido e ondas de correção

- **Data da medição:** 2026-08-18 · **base:** `origin/main` `4177c033a`
- **Âncora:** `prototipo-ui/cowork/jana-merge.jsx` (§`JanaHeader` vive em `chat-jana.jsx`) — servido em `localhost:5577` via `launch.json` → `cowork-jana-2`; `window.JmMemoria` resolve `function`, e o `sha256` do arquivo bate com `origin/main` (`057bd8ae…`) nos 3 worktrees conferidos
- **Origem:** [W] abriu o protótipo e apontou, um a um: *"não copiou as actions dos botões"* · *"botão configurar, plano pro"* · *"os drawer das metas faltou muita coisa"* · *"não copiou o esqueleton"*. Os quatro se confirmaram.

> **Limite deste documento.** Tudo abaixo é **estrutural** (leitura de código + runtime do protótipo). Não mede fidelidade visual: isso exige `cowork-mirror-freshness --compare --check` = SYNC + sonda `design-diff --probe` nos dois renders, e **nenhum dos dois rodou**.

---

## 1 · Quais páginas a área tem

**Inertia — 4** (o resto de `Pages/Jana/**` é `_components/`, `_shared/`, `components/`, que o Inertia não resolve):

| rota | Page | controller |
|---|---|---|
| `/ia` | `Jana/Index` (Painel) | `IndexController` |
| `/ia/conversa` · `/ia/conversas/{id}` | `Jana/Chat` | `ChatController` |
| `/ia/memoria` | `Jana/Memoria` | `MemoriaController` — **no módulo KB** |
| `/ia/pro` | `Jana/Pro` | `ProController` |

**Blade — 4**, servidas por rotas `/ia/*` e portanto **invisíveis a todo gate de tela** (charter, casos, visreg, screen-coverage):

`/ia/alertas` · `/ia/alertas/config` → `view('copiloto::alertas.*')` · `/ia/superadmin/metas` → `view('copiloto::superadmin.metas')` · `/ia/metas/{id}/fonte` → `view('copiloto::fontes.show')`

## 2 · O que o PageHeader canon oferece

Sete props (`Components/PageHeader/PageHeader.tsx`, ADR 0189 v3.2 + 0190): `leading` · `title` (**única obrigatória**) · `suffix` · `subtitle` · `subnav` · `actions` · `className`.

## 3 · Onde moram os arquivos

| caminho | estado | consumidores |
|---|---|---|
| `Components/PageHeader/` (`PageHeader.tsx`, `PageHeaderPrimary.tsx`, `index.ts`) | **canon** | 31 |
| `Components/shared/PageHeader.tsx` | **`@deprecated` CONGELADO** (ratchet `pageheader-gate`, baseline `count: 97`) | 88 |

⚠️ **`shared/PageHeaderTabs.tsx` NÃO é deprecated** — ele se declara *"slot action canônico do PageHeader (ADR 0180)"*. Está na mesma pasta do congelado, e é canon. Julgar por **pasta** produziria a conclusão errada de que o `JanaSubNav` (que o importa) está em dívida — ele não está. A área Jana **está toda no canon**.

Wrapper da área: `Pages/Jana/components/JanaAreaHeader.tsx` (usa o canon) + `Pages/Jana/_shared/JanaSubNav.tsx`.

## 4 · O header de cada página

| Página | header | `active` | business no subtitle | `actions` |
|---|---|---|---|---|
| Index | `JanaAreaHeader` | `dashboard` | **sim** | Configurar + Exportar |
| Chat | `JanaAreaHeader` | `chat` | **não** | — |
| Memoria | `JanaAreaHeader` | `memoria` | **não** | — |
| **Pro** | `PageHeader` canon (sem `subnav`) — ✅ **corrigido 2026-08-18, onda 1** | — | — | Voltar ao chat |

O Pro era a única fora do header do sistema. O charter justificava com "modo FOCO" — mas modo FOCO é *sem SubNav*, não *sem PageHeader*: um `<PageHeader>` sem a prop `subnav` dá o mesmo resultado dentro do canon. **Feito na onda 1 (2026-08-18)**: a tag `UPGRADE` entrou pelo slot opt-in `titleBadge`, criado no mesmo PR porque `suffix` é `string` e não renderiza pill — sem ele a migração custaria a tag, que é literal do protótipo PASS 90. Era exatamente esse custo que mantinha a tela fora do canon.

`janaContext` tinha **0 hits** em `ChatController` e `MemoriaController`; o `IndexController` tinha. Por isso só o Painel mostrava empresa e `biz=`. ✅ **Corrigido na onda 2 (2026-08-18)** — o `JanaAreaHeader` já aceitava `businessName`/`businessId` e até documentava a lacuna no próprio JSDoc; faltava o controller mandar.

## 5 · Colunas e títulos

| Página | layout |
|---|---|
| Index | `sm:grid-cols-3` (KPIs) + `sm:grid-cols-2 xl:grid-cols-3` (análises) |
| Chat | `copiloto-chat-layout` — master/detail, 320px lista + 1fr thread |
| Memoria | `max-w-4xl mx-auto p-6` — coluna única centrada |
| Pro | `max-w-[1060px]`, hero `lg:grid-cols-[1.05fr_0.95fr]`, tabela `grid-cols-[1fr_130px_150px]` |

**Breadcrumb:** os 4 são inertes — `AppShellV2:559` só renderiza sob `{!hideTopbar && …}` e `hideTopbar` é `true` por default (`:243`). Index e Memoria declaravam `breadcrumbItems` que nunca chegou à tela (removido na Onda 3).

---

## 6 · O que faltou copiar — medido item a item

| elemento do protótipo | Index | Chat | Memoria |
|---|---|---|---|
| **selo "plano Pro"** (`jm-plano`) | ❌ | ❌ | ❌ — **0 ocorrências em toda a área** |
| **Configurar** (`onConfig`) | ✅ (abre `JanaConfigDrawer`, #5878) | ❌ | ❌ |
| Exportar | 🟡 (`title="em breve"`) | ❌ | ❌ |
| **Skeleton** (`JmPainelSkeleton`, variante `compacto`) | ✅ `JanaCockpitSkeleton` | ❌ | ❌ |

**Drawer de metas** — `JmMetaDrawer` (protótipo) × `JanaMetaDrawer` (vivo, 219 linhas):

| seção | vivo |
|---|---|
| Situação · Realizado · Projeção · Série · Fechar | ✅ |
| **Origem do número** | ❌ 0 → ✅ **onda 5 (2026-08-18)** |
| **Escopo** | ❌ 0 → ✅ **onda 5 (2026-08-18)** |
| **Editar meta** | ⚠️ **o `❌ 0` era FALSO** — ver errata abaixo |
| **Falar com a Jana** (`onFalarComJana`) | ⚠️ **o `❌ 0` era FALSO** — ver errata abaixo |
| **Projeção** (não estava nesta tabela) | ❌ → ✅ **onda 5** — o servidor já mandava e ninguém lia |

> **Errata desta tabela (2026-08-18, onda 5).** Dois dos quatro `❌ 0` estavam **errados**, e o
> defeito é meu método, não o código: medi por `grep` do **rótulo literal do protótipo**. Os dois
> existem no `JanaMetaDrawer` desde 2026-08-17, com rótulos **deliberadamente diferentes** e a
> razão escrita ao lado no próprio arquivo:
>
> | protótipo | vivo | por quê (comentário no código) |
> |---|---|---|
> | *Editar meta* | **`Abrir a meta`** | *"o destino é a tela de leitura (`show`) (…) prometer 'editar' mandaria o usuário pra um lugar que não é o formulário"* |
> | *Falar com a Jana* | **`Conversar com a Jana`** | mesma copy do contrato `painel-cta-conversar`, sem semear a pergunta porque `ChatController@novaConversa` não aceita pergunta inicial |
>
> É a classe LC-08 (**medir a partir da fonte errada**): rótulo de protótipo não é chave de
> busca em código que renomeou o rótulo de propósito. O que a onda 5 de fato entregou são os
> **outros três** — Origem do número, Escopo e Projeção.

---

## 7 · O que o protocolo fez errado

A desconfiança de [W] é justificada, mas o alvo não é "protocolo corrompido". O protocolo **mede e registra** — os ❌ do selo de plano, do Configurar e do Exportar já estavam escritos no `Memoria-visual-comparison.md` mergeado hoje. O que falta é **morder**. São três buracos, todos medidos:

### 7.1 — A catraca anti-omissão nunca roda

`scripts/contrato-de-tela.mjs` tem o modo `--omission`, descrito no próprio cabeçalho como *"Catraca 3 INVERTIDA (pega o que o handoff OMITIU)"*. É exatamente o mecanismo que pegaria este caso, **sem** depender de alguém declarar item a item.

Ele tem npm script (`contrato:omission`) e tem teste (`contrato-de-tela.test.mjs`). **Zero invocação em CI** — nenhum workflow o chama. É a classe "máquina que existe e ninguém invoca", que o canon já trata como defeito, não como neutralidade.

### 7.2 — O contrato do Painel é curto demais

`prototipo-ui/contrato/jana-painel.contract.json` declara **5 seções**: `painel-cta-conversar` · `painel-metas-header` · `painel-metas-vazio` · `painel-meta-apurando` · `painel-meta-sem-historico`. Nenhuma é header, actions ou drawer.

O contrato é uma **allowlist do que se quer travar**. Mesmo rodando — e ele **roda**, o CI itera `git ls-files '*.contract.json'` —, não morderia nada do §6.

> ⚠️ **Errata de método (mesma sessão).** A 1ª leitura minha foi *"ninguém invoca este contrato"*, a partir de um `grep` pelo **nome literal** do arquivo nos workflows. Falso: o CI usa **glob**, não nome. Grep por nome literal não prova ausência de invocação quando o consumidor enumera por padrão.

### 7.3 — Três das quatro telas não têm contrato

`prototipo-ui/contrato/` tem 5 arquivos; descontando `EXEMPLO`, `schema` e um `.intent.json`, sobram **2 contratos reais** (caixa-unificada e jana-painel). Chat, Memória e Pro: nenhum.

### Por que isso não é "criar um gate novo"

As três correções **estendem o dono do tema** (`contrato-de-tela`). Gate novo para esta classe cairia na família já morta de guard sintático (§5: allowlist-de-pasta · `@scope` · vocabulário 130 FP · `toHaveKey` 100% FP).

---

## 7.4 · O espelho está INCOMPLETO — e isso cegou a própria comparação

Medido em 2026-08-18, com o protótipo servido em `localhost:5577`:

**`window.OfficeImpressoPontoWR2DesignSystem_019dd0` → `false`.** O Design System **não carrega**. São **10 arquivos** que o host pede e que **nunca desceram** para `prototipo-ui/cowork/` — e os 10 **existem no Cowork**:

| faltando | quantos |
|---|---|
| `_ds/office-impresso-design-system-019dd02f…/_ds_bundle.js` | 1 |
| `…/colors_and_type.css` · `…/cockpit_domains.css` | 2 |
| `…/assets/fonts/ibm-plex-{sans,mono}-{400,500,600,700}.woff2` | 7 |

**Por que isso importa mais do que parece.** O protótipo é escrito para degradar sem o DS:

```js
const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const { Alert, EmptyState } = DS;
{Alert ? <Alert …/> : <div className="jm-mem-lgpd">…</div>}
```

Sem o bundle, `Alert`, `EmptyState`, `Button`, `Toast` e `DropdownMenu` são `undefined` e o protótipo renderiza **fallbacks** — inclusive o `exportar`, que é um `DropdownMenu` do DS. Somando as 7 fontes ausentes, o que se vê no espelho **não é o protótipo**: é uma versão degradada dele. Qualquer comparação visual feita contra esse render mede o lado errado.

⚠️ Os achados do §6 **não** dependem disso: foram medidos por `grep` no `.jsx` e no `.tsx`, não por render. Mas a confiança em *qualquer* leitura visual do espelho fica suspensa até os 10 arquivos descerem.

### Como garantir que TUDO desce — e por que parsear o HTML não basta

Derivar as dependências do `oimpresso.com.html` (`src=`/`href=`) devolve **120** entradas e pega os 3 arquivos do DS. **Não pega as 7 fontes**: elas são de **2º nível** (vêm de `url()` dentro do CSS do DS, que também falta). Derivação estática de 1º nível é **incompleta por construção** — e piora quando o nó que falta é justamente quem declara os filhos.

**O oráculo completo é o runtime**, não o parser:

```js
performance.getEntriesByType('resource').filter(r => r.responseStatus >= 400)
```

O browser reporta tudo que tentou buscar em **qualquer** profundidade do grafo. Foi assim que as 7 fontes apareceram — nenhuma delas está no HTML. É a mesma doutrina de sempre: medir pela **consequência**, não pela declaração.

### Onda E TENTADA em 2026-08-18 — e ela esbarra num TETO DURO

Executar a onda E foi tentado nesta sessão. Resultado, item a item:

| arquivo | caminho | estado |
|---|---|---|
| `colors_and_type.css` | **já está no repo** — `scripts/design-sync/mirror-snapshot/` | ✅ copiável, zero download |
| `cockpit_domains.css` | idem (e ainda é **regenerável** por `scripts/design-sync/ds-domains-companion.mjs` a partir do SSOT `resources/css/tokens/`) | ✅ |
| `ibm-plex-sans-{400,500,600,700}.woff2` | **já estão no repo** — `mirror-snapshot/assets/fonts/` | ✅ 4 de 7 |
| `ibm-plex-mono-{400,500,600}.woff2` | não estão no repo · são **binários** | ❌ o `--export-from` só escreve **texto** |
| **`_ds_bundle.js`** | **IMPOSSÍVEL hoje** | ❌ ver abaixo |

**O teto, medido:** `DesignSync.get_file` do `_ds_bundle.js` volta com **`truncated: true`**, cortado em exatos **262.144 bytes (256 KiB)** — o cap do tool. O conteúdo termina no meio de uma linha (`const MENU = [{
  label: 'Pa`). Escrevê-lo no espelho seria **pior que não escrever**: JS cortado é erro de sintaxe, o DS seguiria sem carregar, e agora com um arquivo que *parece* estar lá.

E o bundle é justamente o **crítico** — é ele que define `window.OfficeImpressoPontoWR2DesignSystem_019dd0`. Sem ele, copiar os 2 CSS e as 4 fontes **não resolve** o `DS_carregado: false`; melhora tokens e tipografia, e mantém os componentes em fallback.

**Duas limitações independentes do `--export-from`**, ambas medidas:
1. **binário** — `exportPlan()` faz `Buffer.byteLength(content, 'utf8')` e o script tem **0 ocorrências** de `base64`/`Buffer.from`. Só escreve texto;
2. **tamanho** — o insumo vem do `get_file`, que corta em 256 KiB.

⚠️ **CORRIGIDO 2026-08-27 — as DUAS limitações acima caíram, e o exemplo estava trocado.** O texto fica como registro do que se mediu na data; o que vale hoje:
- **(1) binário — os TRÊS scripts tratam.** `cowork-mirror-freshness.mjs:311-318` (`if (raw.isBase64 === true) … Buffer.from(compact,'base64')`, com docblock em `:299` dizendo *"escrever a string base64 criaria uma fonte corrompida"*) · `aplicar-payload.mjs:292-297` (valida charset/padding antes de decodificar) · `gerar-payload-partes.mjs:101,145`. O `exportPlan` ganhou binário em **2026-08-18** (#5910) — antes desta linha ser tocada pela última vez. Contagem com controle negativo: `base64` 4/3/1 e `Buffer.from` 1/3 nos três.
- **(2) 256 KiB deixou de ser teto por desenho.** `gerar-payload-partes.mjs:8` — *"arquivos grandes são divididos em chunks SHA-256 remontáveis pelo consumidor"*; `:151` rotula o passo *"arquivo grande deixa de ser teto"*. O cap continua existindo **no `get_file` avulso**; a rota do bundle o contorna.
- **(3) o exemplo nem era binário:** o caso citado no parágrafo (`_ds_bundle.js`, `truncated:true` em 262.144 B) é **JS texto acima do cap** — a limitação que o atingia era a (2), não a (1).

O **resíduo real** não é capacidade, é execução: o `gerar-payload-partes.mjs:9-12` roda do lado que tem os arquivos em disco (Cowork), então enquanto o design não emitir o bundle, o consumidor sozinho não fecha.

Isto foi lido como instância concreta do **teto de fidelidade do `get_file`** aberto como decisão [W] em [#5757](https://github.com/wagnerra23/oimpresso.com/pull/5757). A lápide de 2026-08-14 prescreve o desfecho e ele foi seguido aqui: **não conserta — mede, registra, e o teto é decisão [W]**. Nada foi escrito no espelho.

**Saídas possíveis, todas decisão [W]:** (a) estender o `--export-from` para binário + chunking (mexe no dono, não cria paralelo); (b) publicar o bundle por outro transporte que não o `get_file`; (c) aceitar o espelho degradado e **declarar** que leitura visual dele não vale — hoje isso não está escrito em lugar nenhum, e foi o que permitiu a comparação cega.

### O diretório do DS não é ESTÁVEL — 4 locais para o mesmo conteúdo

Cobrança de [W] (2026-08-18): *"o diretório permitido tem que ser sempre o mesmo para manter versionamento dos arquivos"*. Medido — não é:

| # | caminho | estado |
|---|---|---|
| 1 | `scripts/design-sync/mirror-snapshot/` | **versionado** — `colors_and_type.css` · `cockpit_domains.css` · 4 fontes sans. Existe pro sentinela `ds-mirror-drift` comparar (o CI não tem login claude.ai) |
| 2 | `prototipo-ui/cowork/_ds/office-impresso-design-system-019dd02f…/` | **onde o host pede** (`<link>`/`<script>` do `oimpresso.com.html`) — **vazio** |
| 3 | `prototipo-ui/design-system/` | onde `--export-from --ds` escreveria — **0 arquivos no git**, ninguém consome |
| 4 | `.claude/launch.json` → `.claude/worktrees/<nome>/prototipo-ui/cowork` | **efêmero e gitignored** — 3 das 5 entradas apontam pra worktrees de sessões que podem já não existir |

**A consequência é exatamente a que [W] previu:** o conteúdo **está versionado** em (1) e **nunca foi ligado** a (2). O snapshot do DS existe no git há tempo, e o protótipo local nunca o enxergou — foi por isso que o `DS_carregado: false` passou despercebido.

O **default** do `--export-from` (prefixo `cowork`) escreve em `prototipo-ui/cowork/` + o path do Cowork, o que resolve para (2) — **está correto**. É o `--ds` que aponta pra (3), órfão.

**Regra que falta escrever (decisão [W]):** um destino único e versionado pro DS do espelho, com os outros apontando pra ele — e o `launch.json` deixando de carregar path de worktree efêmero. Enquanto houver 4 locais, "está no git" e "o protótipo enxerga" continuam sendo perguntas diferentes.

### ✅ ERRATA — a Onda E FECHOU em 2026-08-18, e o teto do `get_file` foi contornado

> Medido nesta mesma data, em `d4d7b7308`, **depois** do texto acima ser escrito. O §7.4 fica
> preservado como registro do que era verdade quando foi medido; o que muda é o **estado**.

O [PR #5915](https://github.com/wagnerra23/oimpresso.com/pull/5915) (`fix(design-sync): restaurar
runtime completo dos drawers`) versionou o que faltava em `scripts/design-sync/mirror-snapshot/`:
**`_ds_bundle.js` (289.864 bytes)** + as **3 fontes mono** — os dois itens que o §7.4 dava como
`❌ IMPOSSÍVEL hoje` e `❌ binário`. O caminho **não** foi ampliar o `get_file`: foi trocar o
transporte, que é a saída **(b)** das três que o §7.4 listou como decisão [W].

E o **destino instável** (4 locais para o mesmo conteúdo) fechou junto: o
`cowork-mirror-freshness --preview-ds` materializa o snapshot versionado **no slug que o host
pede**, derivando o id do próprio shell em vez de hardcode — é o elo (1) → (2) que a tabela dos
4 locais dizia nunca ter sido ligado. O `README.md` do `mirror-snapshot/` agora declara
`prototipo-ui/cowork/_ds/` como **cache derivado, gitignored**, encerrando a ambiguidade.

**Recibos (dois oráculos independentes, nesta ordem):**

| prova | resultado |
|---|---|
| `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds` | `10 reposto(s) · 0 sem fonte · 0 inválido(s)` → `✓ PREVIEW COMPLETO` (exit 0) |
| runtime, `localhost:5577` — `window.OfficeImpressoPontoWR2DesignSystem_019dd0` | **`true`** (era `false`) · 14+ componentes reais (`Alert`, `Button`, `Command`, `DataTablePro`…) |
| runtime — `performance.getEntriesByType('resource').filter(r => r.responseStatus >= 400)` | **`[]`** — zero 4xx em qualquer profundidade do grafo |

A sonda de runtime é a **mesma** que o §7.4 prescreveu como oráculo completo — e ela agora volta
vazia. **Consequência prática:** a suspensão que o §7.4 impôs sobre leitura visual do espelho
está **levantada**. Comparação visual volta a medir o lado certo.

---

## 7.5 · O espelho está ATRASADO em relação ao Cowork VIVO — eixo que o §7.4 não cobre

> ⚠️ **Não é o mesmo defeito do §7.4, e por isso ele passou.** O §7.4 mede *completude*
> (o que nunca desceu). Este mede *frescor* (o que desceu e ficou pra trás). O cabeçalho deste
> documento diz que o `sha256` do `jana-merge.jsx` *"bate com `origin/main` nos 3 worktrees"* —
> verdade, e **irrelevante para esta pergunta**: prova que os worktrees concordam entre si,
> nunca que concordam com o **vivo**. São dois inventários diferentes (§5 2026-07-28).

Medido em 2026-08-18 pelo caminho canônico — `DesignSync.get_file` → `--snapshot-from`
(mede **sem** escrever no espelho, conforme a lápide de 2026-08-14):

```
DIVERGE  jana-merge.jsx  (a265b6e68567)
```

| | espelho `prototipo-ui/cowork/` | Cowork vivo |
|---|---|---|
| `jana-merge.jsx` | **943 linhas** | **1.117 linhas** (`truncated: false`) |
| símbolos exportados | 8 | **10** — ganha `JmPropostas` · `JmThreadItem` |
| filtros do histórico | `todas · minhas · compartilhadas · arquivadas` | `todas · arquivadas` + **busca** + seções Fixadas/Recentes |
| categorias da Memória | `JM_CATS` (lista fixa) | `JM_CAT_LABELS` + **relevância /10** |

Confirmado também por runtime, no protótipo servido: `typeof window.JmPropostas` → **`undefined`**.

### A direção do delta é o achado — e ela é o INVERSO do esperado

O `--compare` diz que os hashes divergem; **não** diz quem avançou (é a ressalva do próprio
`cowork-mirror-freshness`, e a lápide de 2026-07-17 sobre agregar veredito de fingerprint).
Medida a direção, item a item, ela aponta para a **produção**:

| o que o protótipo vivo GANHOU | já existe em produção? |
|---|---|
| `JmPropostas` (propostas de meta na conversa) | ✅ `Chat.tsx:167` `PropostaCard` + `:355` "Propostas de metas" |
| `JmThreadItem` — busca + Fixadas/Recentes | ✅ `Chat.tsx:423-456` `ConvSidePanel` (`fixadas`/`recentes`/`query`) |
| categorias + relevância na Memória | ✅ `Memoria.tsx:89-94` `CATEGORIA_LABELS` + `relevancia` |
| farol cinza / meta sem apuração | ✅ `Index.tsx:190` `data-contract="painel-meta-apurando"` |

O próprio `jana-merge.css` vivo rotula o bloco: **`/* ── Leva "produção à frente" (2026-08-17) ── */`**.
Ou seja: **o protótipo foi atualizado para alcançar a produção**, não o contrário. Some-se a isso
que `/ia/painel`, `/ia/cockpit` e `/ia/dashboard` já são **301 → `/ia`** em
`Modules/Jana/Http/routes.php` — a fusão que o cabeçalho do `jana-merge.jsx` ainda descreve como
proposta (*"/ia/cockpit morre"*) **já está feita em produção**.

**Por que isto importa para quem for executar as ondas.** A leitura natural de "aplicar o
protótipo em produção" pressupõe que o protótipo lidera. Na área Jana, hoje, ele **não lidera na
maior parte** — e tratar cada `DIVERGE` como trabalho a fazer produziria ondas que **desfariam**
o que já está entregue. O que sobra de delta real está no §6, e ele foi **reconferido**: os itens
das ondas 4 e 5 (`jm-plano`, `Configurar`, `Exportar`, `JmPainelSkeleton`, `Origem do número`,
`Escopo`, `Editar meta`) existem **tanto no espelho quanto no vivo** — logo a defasagem **não**
invalida nenhuma onda já desenhada. Ela invalida a *narrativa*, não o *backlog*.

### O que fica pendente aqui

Ressincronizar o espelho é **Onda S** (abaixo) e **não** foi feita nesta sessão. Motivo, medido:
`jana-merge.jsx` tem JSON persistido em disco e é exportável, mas `jana-merge.css` e
`chat-jana.jsx` voltaram **inline no contexto** do agente — e escrever de lá é a transcrição que
a lápide de 2026-08-11 proíbe (foi ela que produziu o `STALE` daquele dia). Exportar **só** o
`.jsx` seria pior que não exportar: o `.jsx` novo usa classes (`jm-hist-busca`, `jm-props`,
`jm-meta-apurando`, `jm-rel-n`, `jm-hist-sec`) que o `.css` do espelho **não tem** — medido,
0 ocorrências das 5. Render quebrado com cara de atualizado.

---

## 7.6 · TESTE DE PARIDADE RODADO — o primeiro `design-diff` medido desta área

> Autorizado por [W] em 2026-08-18: *"pode fazer no modo autonomo e testar a paridade.
> permitido 2% de desvio"*. Este é o primeiro `--compare` medido da área Jana — o cabeçalho
> deste documento registrava, até aqui, que *"nenhum dos dois rodou"*. **Um dos dois rodou.**

**Método** — o dono do tema (`prototipo-ui/design-diff.mjs`), com a **mesma sonda** injetada nos
dois lados, nunca no olho:

| lado | onde | como |
|---|---|---|
| produção | `https://oimpresso.com/ia` · **`biz=1`** (R6: nunca `biz=4`) | Chrome autenticado |
| design | `localhost:5576/oimpresso.com.html` · `.jc-page` | preview do espelho, DS carregado, 0 falhas 4xx |

Ambos em **tema `dark`** (`sameTheme: true`) — comparar temas diferentes mediria o lado errado.

### Resultado bruto (`--check` → **exit 1**)

```
✓ [D2] layout: prod=ok · design=ok → IGUAL
✗ [D4] título font-size: prod=22px · design=19px → DIVERGE (bug) (Δ 3px · banda tituloPx ±1px)
✓ [D6] cor: prod=ok · design=ok → IGUAL
✗ [D8] kpi.tag: prod=BUTTON · design=DIV → DIVERGE (bug)
```

### Os 2% aplicados — e onde a régua percentual NÃO se aplica

| eixo | prod | design | Δ | dentro de 2%? |
|---|---|---|---|---|
| D2 · contagem de KPI | 4 | 4 | **0%** | ✅ |
| D2 · overflow-x | `false` | `false` | — | ✅ |
| D6 · `bg` do accent | `oklch(0.7 0.15 295)` | `oklch(0.7 0.15 295)` | **0%** — string idêntica | ✅ |
| D4 · título | 22px | 19px | **13,6%** | ❌ |
| — · valor do KPI | 24px | 22px | **8,3%** | ❌ |
| D8 · tag do KPI | `BUTTON` | `DIV` | **categórico** | ⚠️ não admite % |

⚠️ **Duas ressalvas de método, para não vender o número por mais do que ele é.** (a) O
`valueFontPx` **não** é medido pelo `design-diff` — ele saiu do snapshot bruto; está aqui como
medição minha, não como veredito do gate. (b) **Não existe "paridade = N%"** e ela não será
calculada: agregar `IGUAL` com `DIVERGE` de direções opostas numa nota única é exatamente o que
a lápide de 2026-07-17 proíbe. Os eixos ficam **separados, com a direção de cada um**.

### A direção das 2 divergências — e ela decide se há trabalho

Um `DIVERGE` diz que os lados diferem; **não** diz quem está errado. Medida a direção, **nenhuma
das duas é trabalho de "aplicar o protótipo"**:

| divergência | por quê | veredito |
|---|---|---|
| título **22px** (prod) × **19px** (design) | prod usa `JanaAreaHeader` → **`PageHeader` canon** (`text-[22px]`, ADR 0189 v3.2). O protótipo usa o header **próprio do mockup** (`.jc-header`), que nunca foi o shell do sistema | **prod está no canon.** Aplicar 19px seria **regressão** contra a ADR |
| valor do KPI **24px** × **22px** | prod usa `Components/shared/KpiCard` — o KPI canônico do DS, compartilhado com as outras telas | idem — o protótipo tem tipografia própria |
| KPI `BUTTON` × `DIV` | prod tornou o **card inteiro** clicável (drill-down, charter v10 §Goals). O protótipo mantém `DIV` com um botão interno *"Ver origem de X"* | **prod está À FRENTE** — alvo de clique maior e semântica de botão correta |

**Conclusão do teste:** dos 4 eixos que o comparador cobre, **2 batem exatamente** (layout e cor
do accent — este último com string idêntica, `Δ 0%`, bem dentro dos 2%) e **2 divergem por
produção estar no canon do DS ou à frente**. O teste **não produziu nenhum item de backlog**.
Ele confirma, por medição independente, o que o §7.5 concluiu por leitura de código.

### Cobertura honesta deste teste

O `design-diff` cobre **D2 · D4 · D6 · D8** — computed-style puro. Ele **não** cobre D1
(comportamento/rede), D3 (ícones), D5 (footer) nem D7 (densidade), que seguem no protocolo
manual, e **não** mede os itens do §6 (selo de plano, Exportar, drawer) — aqueles são de
**presença**, não de estilo, e o dono deles é o `contrato-de-tela --contract`. Rodado no mesmo
dia, esse outro gate deu **5/5 seções OK, exit 0** — mas o §7.2 já explica por que esse verde não
é paridade: o contrato declara 5 seções e **nenhuma** é header, actions ou drawer.

**Sobre a defasagem do §7.5 contaminar este teste:** os eixos medidos (header, KPI, accent) vivem
em `chat-jana.jsx`/`.css`, e a leva que o espelho perdeu (`JmPropostas`, `JmThreadItem`, busca,
categorias) é de **Conversa e Memória**. Logo o Painel medido aqui é o mesmo nos dois. ⚠️ Dito
isso, `chat-jana.jsx` **não foi medido** contra o vivo nesta sessão — fica **`UNCHECKED`**, não
`SYNC`.

---

### Onde isto se encaixa no canon

É uma instância da lápide de **2026-08-11** (*"o manifesto é CEGO pro que nunca desceu — LIVE-ONLY"*): o `--compare` prova que o que **está** no espelho acompanha o vivo, nunca que o espelho **cobre** o vivo. Este caso é a prova concreta da classe.

O conserto **estende o dono** (`cowork-mirror-freshness`, que já é o dono do papel "baixar fonte com fidelidade de byte" via `--export-from`) — não nasce script novo. O predicado é determinístico (o arquivo existe ou não), então não há FP a medir; o que falta é decidir se a lista de alvos vem do parse (barato, incompleto) ou de uma sonda de runtime (completa, exige abrir o protótipo).

---

## 7.7 · ADENDO 2026-08-21 — o §7.6 concluiu "nenhum item de backlog"; a re-medição produziu UM (e não é o título de 22px)

> **Não corrige o §7.6 — ANEXA.** O que está acima segue verdadeiro na data em que foi medido
> (2026-08-18) e nos eixos que o `design-diff` cobre. Este adendo registra o que uma sonda
> mais funda achou no MESMO eixo D6 (cor), que o §7.6 deu como `IGUAL` porque a banda absorveu.

### O que NÃO mudou (e fecha o assunto do título)

O eixo **D4 (título 22px × 19px)** segue exatamente como o §7.6 julgou — **não é item de
backlog**. Re-medido em runtime nesta data (`/ia`, `biz=1`, dark, `getComputedStyle`):
`fontSize: 22px` · `fontWeight: 700`, que é o canon.

Duas confirmações que o §7.6 não tinha:

1. **O protótipo comparado é âncora BANIDA.** O lado "design" do `--compare` é o
   `.jc-header` de `prototipo-ui/cowork/chat-jana.jsx:220` (+ `chat-jana.css:24`) — e a
   lápide **§5 2026-08-10** (com a emenda de **08-11**) proíbe `chat-jana.jsx` como âncora
   de design de qualquer tela da Jana. Os 3px são contra uma régua que o canon já rejeitou.
2. **A âncora citada no §7.6 está errada** (ponteiro podre, decisão válida). Aquela linha
   credita o 22px a *"`text-[22px]`, ADR 0189 v3.2"*. Medido com controle positivo — a ADR
   0189 tem 10.588 bytes, o grep casa 7× em `PageHeader` e **0×** em `22px`, `font-size` e
   `v3.2`. O dono real do 22px é
   [`PageHeader-LEARNINGS.md` §"Decisão canon #3"](../_DesignSystem/templates/PageHeader-LEARNINGS.md),
   onde [W] mediu `/sells` via browser MCP e decidiu 22/700 (PR #1477).

### O item que apareceu — a cor do título, e a direção é OUTRA

O §7.6 leu a matiz como *prod × protótipo*. A sonda de runtime mostra que é **prod contra si
mesma** — dois sistemas de token vivendo na mesma tela:

| o que | valor medido (`/ia`, dark) | via |
|---|---|---|
| `h1` do PageHeader | `oklch(0.965 0.004 **240**)` — branco FRIO | `text-foreground` (shadcn) |
| `--text` do cockpit | `oklch(0.94 0.005 **90**)` — branco WARM | `.cockpit[data-theme="dark"]` |
| `--bg` · `--border` · `--surface` | hue **240** | idem |

Alcance na mesma tela, contado por `getComputedStyle` sobre os elementos visíveis:
**706** com cor em `oklch` — **339 em hue 90** (warm) × **284 em hue 240** (frio) × 16 em 295
(o roxo primary da ADR 0190, correto).

Corolário que inverte a leitura do §7.6: o `oklch(0.94 0.005 90)` que aparece lá como "o valor
do design" **é o token que roda em produção**. O protótipo estava alinhado com um dos dois
sistemas; quem destoava era o `h1`.

### O conserto aplicado — e o que ele deliberadamente NÃO decide

`PageHeader.tsx` passa a pedir `color: var(--text, var(--foreground))`. O título era o **único**
ponto do header preso ao shadcn (a borda já usava `var(--border)` desde o *dark-aware* da v3.4).

- **Não escolhe cor.** Consome o token gerado (`resources/css/tokens/_generated-cockpit-dark.css`,
  saída do build DTCG). Retunar o valor segue sendo decisão do DS.
- **O fallback é obrigatório, não estética.** `PageHeader` também renderiza em **portal** —
  `ServiceOrderItemFormSheet.tsx` monta um Sheet Radix no `<body>`, fora do `.cockpit`, onde
  `--text` não existe. É a lápide **§5 2026-07-10**, verificada antes de mexer (varredura das
  36 telas que importam `Components/PageHeader`: 3 fora do `AppShellV2`, das quais 1 é portal
  real, 1 é componente-filho e 1 é `.casos.md`).

### O que fica ABERTO (medido, não concluído)

A [ADR UI-0020](../_DesignSystem/adr/ui/0020-dark-warm-ds-v6-tokens.md) — `accepted` em
2026-07-07, [W] *"autorizo tudo"*, **sem supersede** — manda o dark warm em **hue 282**
(`text 0.965 0.004 282`, `bg 0.165 0.008 282`) e nomeia explicitamente o drift *"hue 240 vs
282"*. Em produção **nenhum dos dois grupos está em 282**: o cockpit gera 90 e o shadcn 240.

⚠️ **Isto é medição de UMA tela, não veredito de DS.** O delta está provado; a *causa* não —
build DTCG defasado × decisão posterior não registrada × duas fontes por desenho são hipóteses
concorrentes, e separá-las exige varredura contada dos consumidores do token (§5 2026-07-15).
Registrado aqui como achado, **não** como item fechado.

---

## 8 · Ondas de correção

| onda | o quê | toca pixel? | estado |
|---|---|---|---|
| **0** | RUNBOOK do Pro + declarações + fix do "Voltar ao chat" + UC-PRO-07 + `Jana/Pro` no visreg + baseline + smoke | sim | **[#5891](https://github.com/wagnerra23/oimpresso.com/pull/5891)** — aguarda merge + F1.5 |
| **3** | breadcrumb morto removido (Index, Memoria) + separador do Chat | **não** | **[#5907](https://github.com/wagnerra23/oimpresso.com/pull/5907)** |
| **E** | **descer os 10 arquivos do DS pro espelho** (§7.4) — sem eles o protótipo local é degradado e toda leitura visual dele é suspeita | não | ✅ **FECHADA 2026-08-18** — [#5915](https://github.com/wagnerra23/oimpresso.com/pull/5915) + `--preview-ds`; `DS_carregado: true`, 0 falhas 4xx (ver errata §7.4) |
| **S** | **ressincronizar o espelho com o Cowork vivo** (§7.5) — `jana-merge.jsx` 943 → 1.117 ln. ⚠️ **CORRIGIDO 2026-08-27: NÃO está "bloqueada por transporte".** A redação anterior dizia que o `.css` e o `chat-jana.jsx` voltam inline do `get_file` e portanto a onda não podia andar — verdade sobre **aquele** transporte, falso como bloqueio. A rota do bundle não tem esse teto: o arquivo pequeno viaja **dentro** de uma parte (≤256 KiB), a parte persiste em disco e o `aplicar-payload.mjs` escreve por `readFileSync` (ver `aplicar-payload.mjs:5-11` + errata §5 2026-08-14). O que falta é **o lado Cowork emitir o bundle** (`gerar-payload-partes.mjs` roda onde os arquivos estão em disco, `:9-12`) — pendência de ato, não de capacidade | não | proposta — **primeira** |
| **P** | **ligar `--omission` no CI** — a catraca que pega omissão sem declarar item a item | não | proposta |
| **T** | **primeiro `design-diff` medido** (§7.6) — D2/D6 batem, D4/D8 divergem **a favor da produção** | não | ✅ **RODADO 2026-08-18** — 0 itens de backlog gerados |
| **1** | Pro entra no `PageHeader` canon (sem `subnav`, preserva modo FOCO) | sim → F1.5 | ✅ **FEITA 2026-08-18** — a tag `UPGRADE` foi pro `leading` (slot que já existia); o `titleBadge` novo saiu por PREÇO, não por mérito — tocar o canon escalava o visreg pra 37 telas, 29 sem baseline. F1.5 aprovado por [W]; baseline da `Jana/Pro` atualizada no PR [#5918](https://github.com/wagnerra23/oimpresso.com/pull/5918). Smoke pós-merge pendente |
| **2** | `janaContext` no Chat e na Memória (empresa + `biz=` no header) | sim → F1.5 | ✅ **FEITA 2026-08-18** — o header já aceitava as props; faltava o controller mandar. [#5919](https://github.com/wagnerra23/oimpresso.com/pull/5919) **mergeado**. Smoke pós-merge pendente |
| **4** | selo de plano + Configurar + Exportar + skeleton nas 3 telas | sim → F1.5 | 🟡 **PARCIAL 2026-08-18** — **Configurar** entregue nas 3; **selo de plano BLOQUEADO** (sem fonte de dado — ver §8.1); **Exportar** virou US própria (decisão [W]); **skeleton** só onde há `defer` (§8.1). [#5922](https://github.com/wagnerra23/oimpresso.com/pull/5922) **mergeado** |
| **5** | drawer de metas: Origem do número · Escopo · ~~Editar meta~~ · ~~Falar com a Jana~~ + **Projeção** | sim → F1.5 | ✅ **FEITA 2026-08-18** — 2 dos 4 itens originais já existiam com outro rótulo (errata §6); entrou Projeção, que o servidor mandava sem consumidor. [#5923](https://github.com/wagnerra23/oimpresso.com/pull/5923) **mergeado** |
| **6** | Dashboard × Painel (título, breadcrumb, componente exportado) | sim → F1.5 | decisão [W] |
| **7** | as 4 telas Blade da área — uma onda por tela, F1 (RUNBOOK) antes de qualquer `.tsx` | sim | proposta |

### 8.1 · O que a onda 4 NÃO entregou, e por quê (medido)

**Selo de plano — BLOQUEADO por ausência de fonte, não por trabalho.** O protótipo mostra
`plano Pro` / `Jana Grátis` no header. Em produção **não existe de onde ler isso**: o billing é
Sprint JANA-B ([ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md)), o
`JanaProController` se declara *"Sprint A foundation"* e só expõe `preview`, e não há coluna nem
tabela de plano — `rg` por `jana_pro|plano_pro|isPro` no `Modules/` e `app/` não devolve nenhuma
fonte de estado. O próprio `useJanaConfig.ts` **já documentava** a mesma conclusão por outro
caminho: o protótipo grava `pro` no `localStorage` e o hook recusa, *"porque o servidor não as
honra"*. No protótipo o selo é `cfg.pro`, um **toggle de simulação** — a legenda do
`JmConfigDrawer` diz literalmente *"aqui o Pro é simulação pra ver o gating"*.

Pintar `plano Pro` no header seria afirmar um estado que o sistema não sabe. Fica para quando o
JANA-B existir — aí o selo é consequência, não enfeite.

**Reforço de evidência — 2026-08-27 (a conclusão acima NÃO muda; a prova ficou completa).**
[W] afirmou *"jana pro esta ativa"* e disse ter marcado o Superadmin (pacote) e Configurações do
negócio. A dúvida procede, e a redação original convidava a ela: a evidência de 08-18 era uma
varredura de **código** (`rg` em `Modules/` e `app/`), e a pergunta *"existe fonte de estado?"* tem
um segundo dono — o **banco**, onde `package_details` é JSON livre e `enabled_modules` é lista.
Código não responde por lá. Medido em produção (biz=1, WR2 Sistemas):

| o que | onde medido | resultado |
|---|---|---|
| `business.enabled_modules` | `DB::table('business')->where('id',1)` | 13 entradas core UltimatePOS (`purchases`, `add_sale`, …) — **nenhuma** de plano/Jana |
| `subscriptions.package_details` (ativa, id=118, pkg=1, até 2030-05-13) | idem | 13 chaves ligadas, entre elas **`jana_module="1"`** — **nenhuma** com `pro`/`tier`/`plan` |
| tabela `jana_pro_subscriptions` (prevista no `JANA-PRO-PRODUCT-PLAN.md`) | `Schema::hasTable` | **não existe** |
| colunas de plano/tier em `business` | `SHOW COLUMNS` | nenhuma |
| chave `*_module` com semântica de tier | `git grep -hoE "'[a-z0-9_]+_module'"` — 41 chaves distintas | nenhuma (`productcatalogue`/`project_mgmt` casam por substring, não por sentido) |

**A distinção que faltava escrita, e é ela que gera a confusão:** o que [W] ativou é **real e está
ligado** — `jana_module`, lido por [`DataController:153`](../../../Modules/Jana/Http/Controllers/DataController.php)
e [`HandleInertiaRequests:480`](../../../app/Http/Middleware/HandleInertiaRequests.php). Mas esse
gate é **binário** (*o business tem a Jana*), não **tier** (*qual plano da Jana*). São eixos
diferentes, e nenhuma das 3 camadas do gate de módulo carrega tier. Quem vir `jana_module="1"` na
subscription e concluir *"Pro ativo"* está lendo o eixo errado — foi o que aconteceu aqui.

E a tela `/ia/pro` **não é** fonte: o [`ProController`](../../../Modules/Jana/Http/Controllers/ProController.php)
manda `'plan' => 'free'` **literal** (com o comentário dizendo que Sprint B deriva de assinatura
Asaas real), e o `activate` da [`Pro.tsx`](../../../resources/js/Pages/Jana/Pro.tsx) é
`setTimeout(() => setState('done'), 900)` — muda estado local e **não grava**. Ela renderiza, o
que é diferente de ativar.

Veredito: **o §8.1 continua correto** — o selo segue sem fonte, e pintá-lo continuaria afirmando um
estado que o sistema não sabe. Não é errata; é a evidência fechada pelo lado que faltava.

**Skeleton — só onde há `defer`.** `ChatController` tem 3 `Inertia::defer`; `MemoriaController`
tem **0**. Skeleton numa tela que renderiza tudo no primeiro paint é animação sem espera. Entra
no Chat quando alguém medir qual dos 3 defers o usuário percebe; na Memória, não entra.

**Exportar — US própria.** Decisão [W] nesta sessão. Hoje é placeholder
(`title="Exportar relatório (em breve)"`, sem handler). As três saídas do protótipo — Painel em
PDF · Metas em CSV · Fatos da memória (LGPD) — são feature com backend e, no caso dos fatos,
superfície LGPD. Junto com a paridade de UI, a onda estouraria as 300 linhas do
`commit-discipline`.

---

**Ordem sugerida (revista em 2026-08-18):** `E` está **feita**. Agora `S` primeiro — enquanto o espelho estiver 174 linhas atrás do vivo, toda comparação nova mede uma fonte velha —, depois `P`. Ligada a catraca de omissão, as ondas 1/2/4/5 param de depender de alguém lembrar do que faltou — o gate passa a dizer.

**Bloqueadas por decisão [W], não por trabalho:** os preços do paywall (restaurar × remover a frase) e o rastro da edição na Memória (DTO da Camada C × prop irmã — ver errata no `Memoria-visual-comparison.md`).

---

## 9 · Reconciliação 2026-08-27 — quatro fatos novos, e um deles muda o denominador

> **Como ler:** nada do §1-§8 foi reescrito. Esta seção acrescenta o que a sessão de 27/08 mediu,
> incluindo dois achados que **invalidam premissas** de seções anteriores. Base: `origin/main`
> `6c894d70d7`.

### 9.1 · Existe uma 5ª tela no Cowork que nunca desceu — e o inventário do §1 não a vê

`DesignSync.list_files` do projeto Cowork traz **`jana-metas.jsx` + `jana-metas.css`**, ausentes
do espelho e ausentes do `bundle.manifest.json`. O cabeçalho do próprio arquivo declara o escopo:
absorve `metas/{index,create,edit,show}.blade.php` **+** `fontes/show.blade.php` — cadastro em
tabela, create/edit em drawer, apurações e fonte como seções, reapuração em modal.

**Isso reescreve a onda 7.** Ela diz *"as 4 telas Blade da área"*; são **9** views Blade em
`Modules/Jana/Resources/views/` (medido: `git ls-tree -r origin/main`), e o `jana-metas` cobre
**5** delas de uma vez. A onda 7 não é "uma onda por tela" — é uma tela de design já pronta,
esperando transporte.

⚠️ E o inventário do §1 **não podia** tê-la visto: ele lê o espelho, e arquivo que nunca desceu
não aparece como ausente. O `ABSENT-LOCAL` responde "ausentes: 0" porque lê o **shell do espelho**,
que sequer cita `jana-metas`. Shell velho é detector cego (o espelho carrega `jana-merge.jsx?v=jm5`;
o vivo está em `?v=jm9`).

### 9.2 · A onda S segue "primeira" — e agora se sabe exatamente o que falta

O §7.5 e a ordem sugerida já a priorizavam. O que 27/08 acrescenta é a **causa precisa**, e ela
não é a que a linha S dizia até hoje (ver a errata na própria tabela do §8):

- o bundle do Cowork é de **2026-08-24T22:49Z**, modo `snapshot`, 255 arquivos — **três dias
  parado**, e `jana-metas` nasceu depois (medido 2×, com o mesmo `bundleId`);
- não há automação: os únicos invocadores de `gerar-payload-partes` no repo são testes de CI e
  documentação — nenhum cron, hook ou workflow;
- o alarme que existe é estruturalmente fraco: o `--sla-live-only` audita a **idade do registro**
  da última medição, e essa medição exige auth interativa (ADR 0315), logo **o CI não a faz**.
  Medido em 27/08: ele dava `✓ 2 live-only` enquanto havia **4** — e os 2 que exibia (`CLAUDE.md`,
  `github.md`) sequer podem pousar em `cowork/` por R1 do `ssot-guard`.

**Fecha com um comando, do lado Cowork:** `gerar-payload-partes.mjs --root <vivo> --previous <manifest>`.

### 9.3 · Nenhuma baseline do VRT jamais fotografou um `KpiCard danger`

`grep -qEi "transaction" database/seeders/VisregTenantSeeder.php` → **rc=1** (com controle
positivo). O tenant do VRT **não tem venda nenhuma**, logo `overdueValue = 0`, logo
`JanaCockpit` (`tone={overdueValue > 0 ? 'danger' : 'default'}`) resolve sempre para `default`.
Confirmado decodificando a baseline L1: o card "A RECEBER VENCIDO" está gravado com
**R$ 0,00 / "tudo em dia" / ícone cinza**.

O padrão se repete em **8 outras telas** com tone condicional a dado (`Backup`,
`governance/Dashboard`, `Ponto/BancoHoras`, `ModuleGrades`, `QualidadeIa`, `Financeiro/Unificado`).
As fixtures capturam o estado **saudável** — o gate não está mudo, mede o que captura; o que
faltava era o estado de alerta entrar no **denominador**.

Endereçado pelo gate L2 ([#6358](https://github.com/wagnerra23/oimpresso.com/pull/6358), `jana`
com `states: [default]` semeado com uma venda vencida) + o conserto de idempotência
([#6361](https://github.com/wagnerra23/oimpresso.com/pull/6361) — o seed persistia e um retry
fabricaria regressão em `sells-index`).

### 9.4 · O ajuste do `KpiCard danger` foi pendurado num eixo que a âncora separa — ABERTO

O valor do KPI passou a usar `text-destructive` sob `tone="danger"`
([#6356](https://github.com/wagnerra23/oimpresso.com/pull/6356)). Auditoria adversarial derrubou o
**fundamento**, não o efeito. A âncora separa dois campos:

| campo do protótipo | seletor | o que pinta |
|---|---|---|
| `emphasize` | `.jc-kpi.emph` | borda + fundo — **não toca o valor** |
| `deltaCls === "red big"` | `.jc-kpi-v.red` | **a cor do valor** |

E o `JanaCockpit` declara que `tone` mapeia a **`emphasize`**. Ou seja: pendurou-se no eixo do
fundo um efeito que a âncora pendura no eixo do delta. Indistinguível porque o dataset tem
**N=1** — o único KPI com `emphasize` carrega os dois campos. A conclusão foi **interpolada**.

É a mesma classe da errata do §6 (LC-08, medir a partir da fonte errada), agora no eixo *campo*
em vez de *rótulo*. **Não revertido** — o efeito é defensável por si e a decisão é [W]. Mas a
errata está no componente proibindo invocar *"a âncora manda"* como fundamento.

### 9.5 · O que esta reconciliação NÃO resolve

O §7.5 abre dizendo que o espelho está atrasado; 27/08 **confirma e agrava** — não é só atraso,
é ausência de uma tela inteira. Enquanto a onda S não fechar:

- toda comparação da área mede fonte velha (o §7.6/§7.7 rodaram contra o espelho de então);
- o `ancora.mjs Jana/Index` reporta **STALE** — *"o que você abrir aqui NÃO é o design atual"*;
- e o achado de hue do §"O que fica ABERTO" continua sem poder ser separado das hipóteses
  concorrentes, porque a fonte de comparação não está fresca.

### 9.6 · Errata do §"O que fica ABERTO" — as hipóteses FORAM separadas (2026-08-27)

> O §9.5 afirma que o achado de hue *"continua sem poder ser separado das hipóteses concorrentes,
> porque a fonte de comparação não está fresca"*. **Isso está errado.** O frescor do espelho decide
> *qual valor é o certo*; não decide *por que o código divergiu*. Essa segunda pergunta se responde
> por história de git + estado dos gates, e ambas estavam disponíveis. Fica registrado, não apagado.

**Base:** worktree em `origin/main` `224bb5f7bb`, 0 commits atrás, árvore limpa, clone completo
(`is-shallow-repository` = false).

#### As três hipóteses, com veredito

| hipótese | veredito | recibo |
|---|---|---|
| **build DTCG defasado** | ❌ **REFUTADA** | `node scripts/design-sync/ds-tokens-build-sync.mjs --check` → exit **0**, *"6 arquivo(s) `_generated` em sincronia"*. `node scripts/governance/dtcg-equivalence.mjs` → **308/308 fiéis, 0 divergências**. Última run do workflow `ds-tokens-build-sync.yml`: **2026-08-26, success**. Controle positivo do ambiente: `import('style-dictionary')` resolve (`RESOLVE OK function`) — o `ls node_modules/` falha por junction, o import não. O `_generated` é fiel ao SSOT; **é o SSOT que está em 240/90** |
| **decisão posterior não registrada** | ✅ **CONFIRMADA — é a causa** | ver linha do tempo abaixo |
| **duas fontes por desenho** | 🟡 **verdadeira, mas não explica o hue** | as duas camadas são declaradas por desenho em [`PIPELINE-TOKENS.md:54-55`](../_DesignSystem/PIPELINE-TOKENS.md) (Tailwind `@theme` × shell `.cockpit`), com tabela de quando usar cada uma. Consumidores contados: cockpit `var(--text|--bg|--surface|--border…)` = **43 arquivos / 1.540 ocorrências** em `resources/**` (denominador varrido: 2.433 arquivos versionados, 1.366 deles `.css/.tsx/.ts/.jsx/.js`); shadcn (`text-foreground|bg-card|border-border|bg-background|text-muted-foreground|border-input`) = **319 arquivos `.tsx` / 4.078 ocorrências**. Ambas vivas. **Mas hoje elas CONVERGEM em 240 nas superfícies** — `--surface` (cockpit) e `--color-card` (shadcn) são os dois `oklch(0.30 0.008 240)`; `--border` e `--color-border` são os dois `oklch(0.34 0.008 240)`. A divergência real é **interna ao cockpit**: superfícies 240 × textos 90 |

#### A linha do tempo (medida por `git log -L` na linha do token, não por leitura)

| quando | commit | o que fez com o dark |
|---|---|---|
| 2026-06-22 | [#3220](https://github.com/wagnerra23/oimpresso.com/pull/3220) | DTCG nasce espelhando o `cockpit.css` de então: textos **hue 90**, superfícies 282 |
| **2026-07-07 17:25** | [#3932](https://github.com/wagnerra23/oimpresso.com/pull/3932) — **ADR UI-0020** | tudo → **282** (`text 0.965 0.004 282`) |
| 2026-07-08 11:23 | [#3958](https://github.com/wagnerra23/oimpresso.com/pull/3958) — **ADR UI-0022** | borda dark `0.30 → 0.335`, **ainda em 282** |
| **2026-07-08 18:43** | [#3981](https://github.com/wagnerra23/oimpresso.com/pull/3981) | **15 tokens do cockpit saem de 282**: 12 → **240** (`bg/bg-2/surface/border/border-2` + 7 `sb-*`), 3 → **90** (`text/text-dim/text-mute`) |
| **2026-07-08 18:56** | [#3982](https://github.com/wagnerra23/oimpresso.com/pull/3982) | `@theme` shadcn dark → **240** (canvas/superfícies/bordas/neutros) |

A UI-0020 valeu **~25 horas**. O que a derrubou não foi drift: foi **decisão [W] explícita**, registrada
verbatim no corpo do #3981 — *"Wagner escolheu POR IMAGEM a opção C (espelho): mais claro, cinza
azul-frio hue 240"*.

#### A dívida exata, e ela já estava nomeada no canon

As duas propostas de 2026-07-08 se declaram, na primeira linha, *"PROPOSTA. NÃO é lei, NÃO é ADR
numerado"*. A [ADR 0328](../../decisions/0328-ds-transicao-congelado-para-vivo-git-ssot.md), aceita
em 2026-07-09, diz na linha 63 que **`D-2` (sidebar) e `D-3` (valores dark reconciliados) são
"UI-ADR/PR à parte — não entram aqui"**.

- **D-2 foi paga** pela [UI-0023](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) (2026-07-16), que inclusive registra a proposta como *"rascunhada e nunca numerada — a dívida que esta ADR paga"*.
- **D-3 nunca foi numerada.** É esta. *(Atualização 2026-08-28: passou a ser — [UI-0027](../_DesignSystem/adr/ui/0027-dark-hue-240-supersede-0020-0022.md), que supersede a UI-0022 integralmente e o item 1 da UI-0020. O parágrafo acima fica como estava no dia da medição.)*

Claim negativa com denominador: varridas **413 de 413** ADRs (387 em `memory/decisions/` + 26 em
`adr/ui/`) — **nenhuma supersede a UI-0020**. Ela segue `accepted`, sem sucessora, descrevendo um
estado que o código abandonou há ~7 semanas.

#### Dois achados que a tarefa não pedia e que mudam o quadro

1. **A UI-0022 também está em conflito, e ela é a mais nova das duas.** Aceita às 11:23 de 2026-07-08
   fixando `border` dark em `oklch(0.335 0.012 282)`, foi contrariada às 18:43 do mesmo dia pelo
   #3981 (`oklch(0.34 0.008 240)`). Ou seja: não é uma ADR órfã, são **duas** — e a UI-0022 declara
   `Amends: UI-0020`, então corrigir só a mãe deixaria a emenda apontando pra um valor que não existe.

2. **O hue 90 dos textos tem fonte, não é lapso — mas a mensagem do commit e o diff discordam.**
   O #3981 afirma ter portado *"13 verbatim do espelho aprovado: bg/bg-2/surface/border/border-2/**text/text-dim/text-mute** + sb-*"*.
   O diff mostra que os 3 de texto foram para valores **byte-idênticos aos pré-UI-0020**
   (`0.94/0.72/0.58 · 90`), não para 240. E o padrão resultante — superfícies 240 + textos 90 — é
   exatamente o que o [`prototipo-ui/Design System v4.html:1771-1779`](../../../prototipo-ui/Design%20System%20v4.html)
   tem no bloco `[data-theme="dark"]`. Fato medido; **não afirmo intenção**. Os `sb-text*` nunca
   passaram por 282 em nenhum commit — nasceram 90 e continuam 90.

#### O que a produção usa hoje (atribuição dos 3 valores do §"ABERTO")

| medido em `/ia` | token dono | camada |
|---|---|---|
| `oklch(0.965 0.004 240)` | `--color-foreground` | shadcn `@theme` |
| `oklch(0.3 0.008 240)` | `--color-card` **e** `--surface` | shadcn **e** cockpit (mesmo valor) |
| `oklch(0.34 0.008 240)` | `--color-border`/`--color-input` **e** `--border` | shadcn **e** cockpit (mesmo valor) |

Corrige o §"ABERTO", que dizia *"o cockpit gera 90 e o shadcn 240"*: o cockpit gera **240 nas
superfícies e 90 nos textos** — os 3 valores medidos vêm todos do lado 240.

#### O que fica para decisão [W] (não executado nesta sessão)

Nada de Fundações foi tocado — a Constituição UI v2 declara a camada imutável via ADR, e as duas
saídas são exclusivas:

- **(a) o código está certo** → **ADR UI-0027 com `supersedes: [UI-0020, UI-0022]`**, numerando a
  decisão D-3 de 2026-07-08 que já está em produção há ~7 semanas. É o caminho que a 0328 previu e
  que a UI-0023 já executou para a D-2 irmã. Zero mudança de pixel.
- **(b) a UI-0020 está certa** → PR de aplicação citando-a, revertendo 15 tokens do cockpit + os
  `@theme` para 282. **Muda o dark do app inteiro** e contraria decisão [W] tomada por imagem — R1/R2/R10.

Recomendação: **(a)**. O conflito não é de valor, é de registro: o código seguiu o dono, a ADR ficou
para trás. Enquanto as duas coexistirem `accepted`, qualquer sessão que abrir a UI-0020 vai ler
"o dark é 282" como lei vigente e propor a reversão — que é o vetor desta própria seção.
