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
| **Pro** | **`<header>` hand-rolled** | — | — | Voltar ao chat |

O Pro é a única fora do header do sistema. O charter justifica com "modo FOCO" — mas modo FOCO é *sem SubNav*, não *sem PageHeader*: um `<PageHeader>` sem a prop `subnav` daria o mesmo resultado dentro do canon.

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
| **Origem do número** | ❌ 0 |
| **Escopo** | ❌ 0 |
| **Editar meta** | ❌ 0 |
| **Falar com a Jana** (`onFalarComJana`) | ❌ 0 |

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

Isto é instância concreta do **teto de fidelidade do `get_file`** já aberto como decisão [W] em [#5757](https://github.com/wagnerra23/oimpresso.com/pull/5757). A lápide de 2026-08-14 prescreve o desfecho e ele foi seguido aqui: **não conserta — mede, registra, e o teto é decisão [W]**. Nada foi escrito no espelho.

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

## 8 · Ondas de correção

| onda | o quê | toca pixel? | estado |
|---|---|---|---|
| **0** | RUNBOOK do Pro + declarações + fix do "Voltar ao chat" + UC-PRO-07 + `Jana/Pro` no visreg + baseline + smoke | sim | **[#5891](https://github.com/wagnerra23/oimpresso.com/pull/5891)** — aguarda merge + F1.5 |
| **3** | breadcrumb morto removido (Index, Memoria) + separador do Chat | **não** | **[#5907](https://github.com/wagnerra23/oimpresso.com/pull/5907)** |
| **E** | **descer os 10 arquivos do DS pro espelho** (§7.4) — sem eles o protótipo local é degradado e toda leitura visual dele é suspeita | não | ✅ **FECHADA 2026-08-18** — [#5915](https://github.com/wagnerra23/oimpresso.com/pull/5915) + `--preview-ds`; `DS_carregado: true`, 0 falhas 4xx (ver errata §7.4) |
| **S** | **ressincronizar o espelho com o Cowork vivo** (§7.5) — `jana-merge.jsx` 943 → 1.117 ln. **Bloqueada por transporte**, não por trabalho: o `.css` e o `chat-jana.jsx` voltam inline do `get_file` e transcrever é proibido | não | proposta — **primeira** |
| **P** | **ligar `--omission` no CI** — a catraca que pega omissão sem declarar item a item | não | proposta |
| **T** | **primeiro `design-diff` medido** (§7.6) — D2/D6 batem, D4/D8 divergem **a favor da produção** | não | ✅ **RODADO 2026-08-18** — 0 itens de backlog gerados |
| **1** | Pro entra no `PageHeader` canon (sem `subnav`, preserva modo FOCO) | sim → F1.5 | proposta |
| **2** | `janaContext` no Chat e na Memória (empresa + `biz=` no header) | sim → F1.5 | ✅ **FEITA 2026-08-18** — o header já aceitava as props; faltava o controller mandar. Smoke pós-merge pendente |
| **4** | selo de plano + Configurar + Exportar + skeleton nas 3 telas | sim → F1.5 | 🟡 **PARCIAL 2026-08-18** — **Configurar** entregue nas 3; **selo de plano BLOQUEADO** (sem fonte de dado — ver §8.1); **Exportar** virou US própria (decisão [W]); **skeleton** só onde há `defer` (§8.1) |
| **5** | drawer de metas: Origem do número · Escopo · Editar meta · Falar com a Jana | sim → F1.5 | proposta |
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
