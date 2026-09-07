# COLAR NO CODE — PROTOCOLO COWORK → CODE (consolidado)

> **Uma pergunta só:** qual é a unidade do pedido, o que ela carrega, por qual rota desce e como se prova que chegou inteira.
> **Consolida e SUBSTITUI** os 3 arquivos de ponte anteriores (`…paridade-por-secao-e-comportamento`, `…PROTOCOLO-DE-EXPORTACAO`, `…METODO-DE-PEDIDO`) — 3 docs sobre o mesmo assunto **era o scatter que o `COWORK-ESTRUTURA` proíbe**. Apagados neste mesmo ciclo.
> **Ponte, não canon.** Destino no `main`: `prototipo-ui/` (root) — nunca `prototipo-ui/cowork/` (guard R1: zero `.md` lá). Eu **não escrevo no git**: desce por `cowork-inbox`/Issue → PR.
> **Não substitui:** `protocolo.config.mjs` (fases/gates/comandos) · `PRE-FLIGHT-TELA.md` (pré-requisitos) · `PARIDADE-area-<Mod>-*.md` (ordem e veredito) · `HANDOFF.md` (mapa DS→arquivo).

---

## 1 · Por que o pedido vazava

Três buracos, todos no pedido — não no executor:

1. **Unidade grande demais.** "View Trabalho" tem 5 seções; 1 PR ≤300 ln ⇒ entrega 3, omite 2.
2. **Sem denominador.** Se o pedido não enumera as seções, "tudo" é opinião: ninguém sabe que faltou.
3. **DoD não falsificável.** "ficar igual" não reprova nada. `.fj-row` com **13 filhos na ordem** reprova.

E a assimetria que explica por que importar funciona e exportar vaza: **importar é ler** (a verdade está a montante e é lida no momento); **exportar é transferir contrato** — o canal leva **arquivos**, e a intenção só desce se estiver **escrita**.

**Regra-mãe:** a unidade é a **SEÇÃO** (um seletor raiz). Layout e comportamento no MESMO pedido — separados, produzem tela bonita que não responde ao clique.

---

## 2 · Os 3 comandos (é isto que [W] digita)

| comando | exemplo | devolve | escreve? |
|---|---|---|---|
| **`MAPA <Mod>`** | `MAPA Forja` | uma linha por seção: `view · seção · seletor raiz · arquivo-âncora · existe em produção (s/n)` | **não** — mapa é COMANDO, não arquivo (L-42 · ADR 0256). Colhido do DOM vivo, nunca de lembrança |
| **`ALVO <Mod>.<view>.<seção>`** | `ALVO Forja.trabalho.lista.linha` | tabela de alvo: estrutura (nós · filhos **na ordem** · tokens resolvidos) + comportamento | **não** — read-only. Medir e aplicar no mesmo passo é onde a fidelidade se perde |
| **`EXPORT <Mod> ONDA <n>.<s> <seção>`** | `EXPORT Forja ONDA 4.3 lista.linha` | o pedido pro Code nos 4 blocos + placar obrigatório | sim: build + pedido + bloco de charter |
| **`SINCRONIZAR <Mod>`** | `SINCRONIZAR Hrm` | encadeia LEVANTAR → PUXAR → REACT → PLAYBOOK → VERIFICAR (§12): quadro por estado + playbook de threads + placar da lista | sim: build + `cowork-inbox/<mod>/playbook/` |

**Manutenção:** `PLACAR <Mod>` (cobertura cumulativa, o que falta e por quê — com playbook, confere cada `prova:` do `00-INDICE.md` no `main`) · `RESÍDUO <Mod>` (ausentes reincidentes = fila de decisão de [W], não dívida invisível).

### Receita do `MAPA` (reexecutável, 1 comando — não congelar em arquivo)
```js
// para cada view: seções = filhos diretos de .fj-page (menos o header) + classes repetidas ≥4×
const root=document.querySelector('.fj-page');
const secs=[...root.children].filter(e=>!e.classList.contains('os-page-h'))
  .map(e=>({sel:'.'+[...e.classList].join('.'), filhos:e.children.length}));
```
**Colher, não supor:** na primeira tentativa deste levantamento eu chutei `.ap-hero`, `.fj-kb-col`, `.fj-cl-item`, `.fj-token` — **quatro seletores que não existem** (n=0). Os reais são `.ap-page`, `.fj-gantt`, `.fj-feed-item`, `.fj-perm`. Mapa por lembrança inventa seção.

---

## 2-bis · Granularidade da onda — depende do tamanho do módulo

| módulo | onda = | quantas | exemplo |
|---|---|---|---|
| **multi-view** (6 views, ~28 seções) | 1 view ou 1 seção grande | 10-12 | Forja: shell · chrome · lista · quadro · gantt · aprovações · saúde · mcp · changelog · integrador · triagem |
| **página única** (1 view, 5-8 seções) | **1 SEÇÃO** | 5-8 | Visão geral: header+stats · barra de período · select loja · gráficos · grades+drawer · pendências |
| **overlay sem receptor** | 1 por superfície, **depois** de [W] declarar rota | — | drawer · ⌘K · notifs · composer |

**Regra:** onda nunca é maior que 1 PR ≤300 linhas. Se a seção não cabe, ela se divide (linha × cabeçalho × rodapé); nunca se agrupa.

## 2-ter · Anti-scatter — se o módulo já tem ponte, ATUALIZE

Antes de escrever pedido novo: procurar `COLAR-NO-CODE-*<modulo>*` e `cowork-inbox/PEDIDO-*<modulo>*`. Se existir, **reescrever aquele** na forma padrão, preservando as perguntas ⛔ [W] já catalogadas. Três docs sobre o mesmo módulo é a doença que fez estes três protocolos virarem um.

---

## 2-quater · Uma onda = uma sessão LIMPA — obrigatório, e com teste

**Sim, é obrigatório**, e não por higiene: é a única forma de o pedido ser verdadeiro. Contexto de chat **não** desce junto com o PR — quem executa a onda 7 não viu a conversa da onda 3. Se o pedido depende de algo que só existe no histórico, ele mente sobre estar completo.

**Regra:** cada onda abre em sessão nova, e o pedido dela tem de ser **auto-suficiente**. O que a sessão limpa lê (nesta ordem, do `main`, nunca de cópia):

1. `COWORK-ESTRUTURA-E-TELAS.md` · `FRESCOR` · `PRE-FLIGHT-TELA.md` — como se opera.
2. **O pedido da onda** (este pacote, a seção dela) — alvo, comportamento, DoD, placar.
3. **O charter + `casos.md`** da tela — inclusive o bloco de contrato destilado que as ondas anteriores deixaram.
4. `PARIDADE-area-<Mod>-*.md` — ordem, veredito e os ausentes com motivo das ondas passadas.
5. `memory/proibicoes.md` + `LICOES_CC.md` — o erro já catalogado.
6. **Os 2-4 arquivos da âncora** no `main` (§3-bis) — no momento da onda.

**Teste do estranho (o que torna a regra checável):** entregue o pedido a quem não viu nenhuma conversa. Se ele precisar perguntar *qualquer coisa* sobre o alvo, a âncora, o dado ou o critério de aceite, **o pedido está incompleto** — a falha é do pedido, não dele. Concretamente, o pedido passa quando responde sem histórico: quais arquivos editar · o que reusar · o alvo em número e ordem · o dado real por slot · o que **não** tocar · quando **parar** · como se prova.

**O que a sessão limpa NÃO precisa:** o chat anterior. É para isso que existem os 3 resíduos do §8 — o conhecimento da onda passada mora no charter, na tabela de ondas e nas lições, não na memória de quem estava lá.

**Corolário do meu lado:** o `ALVO` também roda em sessão limpa, medindo o protótipo servido — nunca "o que eu me lembro de ter medido". A prova de que isso importa está no §7.1: eu errei a ordem da `.fj-row` de cabeça, no mesmo dia em que a havia medido.

---

## 3 · Vocabulário fechado (os nomes certos)

| diga | significa | não diga |
|---|---|---|
| **seção** | um seletor raiz — a unidade do pedido | parte, pedaço, área |
| **view** | uma das 6 telas de topo (hoje · trabalho · saúde · mcp · changelog · integra) | página, aba |
| **âncora** | o arquivo do build que tem o markup daquela seção (não o shell) | "o protótipo" |
| **alvo** | os números medidos que reprovam | referência, modelo |
| **placar** | "entregue X de Y; os Y−X ausentes são `<nome>` por `<motivo>`" | status, quase pronto |
| **gatilho** | `clique` · `clique-no-filho` · `arrasto` · `tecla <k>` · `atalho global` · `foco` · `esc` | interação, ação |
| **efeito** | o que muda: estado · filtro · rota · rede · overlay | "funciona" |
| **invariante** | regra do módulo que vale sem repetir por seção | padrão |
| **divergência declarada** | difere do protótipo **de propósito**, com motivo escrito | ajuste, adaptação |
| **ausente por falta de fonte** | sem coluna/endpoint ⇒ renderiza `—` e entra no PR | inventar número |
| **prova** | teste/sonda que reprova se quebrar | "verifiquei" |

**Frase-modelo:** *"EXPORT Forja ONDA 4.3 lista.linha — âncora `forja-lista.jsx`; alvo medido em anexo; escopo fechado nesta seção; placar obrigatório; contrato destilado no charter no mesmo PR."*

---

## 3-bis · Quando o `main` precisa ser lido (e quando NÃO)

**Não.** O `main` não decide o layout — [W] decidiu em 2026-09-02 que **o protótipo é a implementação que sobrevive** (§11 do PARIDADE). Comparar com o `main` para saber "como deve ficar" inverteria o dono: o alvo vem do **build daqui**, sempre.

O `main` é lido para outra coisa: **onde a seção cai e com que dado**. Por seção, **no momento do pedido** (nunca de cópia local — L-42), e sempre poucos arquivos alvo:

| bloco do pedido | precisa ler o `main`? | o que se lê |
|---|---|---|
| **C · alvo estrutural/comportamento** | **não** | mede-se o protótipo servido (`ALVO`). Ler o `.tsx` aqui é o erro de inverter o dono |
| **A · identidade** (rota, Page, receptor) | **sim** | `route:list`/`routes.php` + o `.tsx` da tela receptora — só para saber **onde** entra |
| **B · não inventar** (dados) | **sim** | Model/Service/coluna real (`ForjaMcpService`, `mcp_tasks`…) — é o que evita campo inventado |
| **B · componentes/tokens** | **não** | REGISTRY + bundle CSS + tokens já são canon conhecidos |
| **MAPA · coluna "existe em produção"** | **sim** | 1 `git grep` do seletor/Page |
| **decisão que contradiz** | **sim** | `charter`/`casos`/ADR da tela — precedência: teste > casos > charter > SPEC |
| **fidelidade final** | **não é leitura** | é `design-diff --compare --check` nos **dois renders**, com deploy |

**Custo:** ~4 leituras por seção (rota · Page receptora · Service/colunas · charter), não a árvore. Se algum desses **não** foi lido no turno, a frase certa é **"não verifiquei"** — fato sobre o repo só existe com leitura no turno.

**Regra de bolso:** `main` responde *onde* e *com que dado*; o protótipo responde *como*. Trocar isso é o que faz o Code negociar o design em vez de replicá-lo.

---


## 4 · O pedido de seção — 4 blocos (espelha o `PRE-FLIGHT`, invertido)

Bloco vazio **reprova o pedido**.

```
EXPORT <Mod> · ONDA <n>.<s> — <view> · seção <nome> (<seletor raiz>)

A · IDENTIDADE
  âncora        : prototipo-ui/cowork/<arquivo>.jsx   ← o da seção, NÃO o shell
  átomos        : window.Fj* em <arquivo-atomos>.jsx
  arquétipo     : lista | quadro | gantt | dashboard | drawer | overlay | form
  persona       : Larissa | Wagner | Técnico | Eliana | Iniciante
  rota destino  : <rota real do main> · Page <Mod>/<Tela>

B · NÃO INVENTAR (reusar, não recriar)
  CSS           : <mod>-bundle.css (classes <pfx>-) — ZERO CSS novo, zero
                  utilitária Tailwind de cor/espaço no que o bundle cobre
  componentes   : @/Components/ui (REGISTRY) — nunca hand-roll
  tokens        : roxo oklch(0.55 0.15 295) light / oklch(0.70 0.15 295) dark;
                  zero hex cru
  dados         : Model/Service/coluna REAL (nomeie). Sem fonte ⇒ "—" + PR
  copy          : literal do protótipo · PT-BR · sentence case

C · ALVO MEDIDO (o que reprova) — layout E comportamento na mesma folha
  estrutural    : <seletor> → N nós · N filhos NESTA ordem [...] · gap/px/cor
                  (getComputedStyle, dark, após __oiLazyDone + 2 leituras iguais
                  de querySelectorAll('*').length)
  comportamento : tabela do §5, uma linha por elemento interativo

D · COMO VALIDAR (recibo do PR)
  1 contagem e ORDEM = alvo estrutural
  2 cada linha de comportamento com o teste que a prova
  3 design-diff --compare --check → 0 DIVERGE(bug) neste seletor
  4 screenshot prod autenticado · dark · 1280px
  5 casos.md da seção com ≥1 UC citado por teste — MESMO PR
  6 PLACAR no corpo do PR
  7 bloco de contrato destilado (§6) no charter — MESMO PR
  8 github.md: linha do ciclo + "bundle regenerado (<data> · N arquivos)"

ESCOPO FECHADO: só esta seção. Não tocar <vizinhas nomeadas>. Não tocar no shell.
```

O **item 6 é o que fecha o buraco**: sem placar, omitir é grátis.

---

## 4-bis · ANCORAGEM DUPLA — decisão [W] 2026-09-03: o pedido passa a ser ancorado no código do `main`

**Funciona, e é melhor — desde que os dois papéis fiquem separados.** Medido hoje lendo o `main` (`TrabalhoLista.tsx` · `trabalhoAtomos.tsx` · `Index.design-spec.json`):

| papel | dono | por quê |
|---|---|---|
| **alvo de layout** ("como fica") | **protótipo medido** (`ALVO`) | ADR 0388 / decisão [W] 2026-09-02: réplica primeiro, o protótipo é o contrato de layout |
| **âncora de implementação** ("onde e com o que se faz") | **arquivo real do `main`**, com o átomo que já existe lá | o Code aplica **diff em código que ele já mantém**, sem retraduzir markup |
| **veredito** | `design-diff --compare` nos dois renders (T7) | inércia da produção não vence: divergência continua medida contra o protótipo |

### Os três ganhos, medidos (não supostos)
1. **A produção já é réplica e está À FRENTE em a11y.** `TrabalhoLista.tsx` usa `.fj-list`/`.fj-group`/`.fj-row` do bundle, e os átomos de `trabalhoAtomos.tsx` trazem o que **o meu protótipo não tem**: `<button type="button">` com **`aria-pressed`** em `Star`/`Pin`, **`aria-expanded`** no `fj-group-toggle`, **`aria-hidden`** em todo `svg` decorativo, `role="img" aria-label` no cadeado, e `data-testid` por nó. Ancorar ali **conserta de graça 3 dos 6 🔴 do §5-bis** — em vez de exportar os meus defeitos.
2. **As ausências já estão declaradas no código, com motivo** — `fj-rowcheck` (mutação em massa sem endpoint; afordância falsa seria LC-15), `frescor` e `carry` (campos que `mcp_tasks` não tem), e o épico (`epic_id` é FK pra `McpEpic`, **outra entidade** — a hierarquia do protótipo não tem equivalente). O placar "11 de 13" já existe escrito. Ancorar reaproveita esse trabalho em vez de repetir a descoberta.
3. **O receptáculo de máquina que eu disse que faltava existe:** `Index.design-spec.json`, **derivado** por `scripts/design-spec-gen.mjs`, com contadores de violação (`raw_oklch: 0`, `raw_hex: 0`, `inline_style: 1`, `native_input: 1`). É onde o alvo deve ser serializado (fecha o gap "spec executável" do §9) — e é **derivado**, então ninguém o edita à mão.

### O que muda no pedido (bloco A)
```
A · IDENTIDADE — ANCORAGEM DUPLA
  alvo (layout)   : prototipo-ui/cowork/<arquivo>.jsx  ← medido, read-only
  âncora (código) : Modules/<Mod>/Resources/js/Pages/<...>/<Arquivo>.tsx
                    + átomos já existentes em <_components/...>
  reusar átomo    : <nomes> (NÃO recriar — eles já têm aria/testid)
  spec derivada   : <Tela>.design-spec.json (serializar o alvo; nunca editar à mão)
```

### Riscos declarados desta ancoragem
- **Inércia da produção** — ancorar no código convida a "já está bom assim". Antídoto: o veredito continua sendo T7 contra o protótipo, e o placar continua obrigatório.
- **Snapshot** — minha leitura do `main` vale **no turno**. Reler os 2-4 arquivos da seção no momento do pedido; sem leitura, a frase é "não verifiquei".
- **Meu alvo pode ser o pior dos dois.** Quando o `main` está à frente (a11y, semântica, `data-testid`), **o alvo se corrige aqui** (§5-bis) — não se pede ao Code para regredir para a minha versão.

### Mapa protótipo → âncora no `main` (Forja, leitura de 2026-09-03)
`forja-lista` → `Forja/Trabalho/_components/TrabalhoLista.tsx` (+`trabalhoAtomos.tsx`) · `forja-quadro` → `_components/TrabalhoQuadro.tsx` · `forja-gantt` → `Forja/Roadmap/Gantt.tsx` · `forja-aprova` → `Forja/Aprovacoes/Index.tsx` · `forja-page` (shell) → `team-mcp/Forja/Cockpit.tsx` (+`ForjaHub.tsx`, `ForjaTabBar.tsx`) · `forja-mcp` → `_components/ForjaMcp.tsx` + `ForjaHandoffs.tsx` · `forja-changelog` → `_components/ForjaChangelog.tsx` · `forja-saude` → `_components/ForjaSaude.tsx` + `team-mcp/Scorecard/Index.tsx` · `forja-integra` → `_components/ForjaIntegrador.tsx` · `forja-triagem` → `_components/ForjaTriage.tsx` · `forja-dossie` → `_components/ForjaDossier.tsx`.
**Sem receptor conhecido hoje (declarar, não inventar):** `forja-issue-drawer` · `forja-cmdk` · `forja-notifs` · `forja-novo-issue` · `forja-runbook` · `forja-handoff` · `forja-ia` · `forja-rag`.

---


## 4-ter · Instrução de execução por onda — a forma padrão (ancorada no código de produção)

Cada onda abre com este bloco. O que **não** foi lido no turno não entra preenchido ("não verifiquei"):

```
ONDA <n> — <seção>
  ARQUIVOS A EDITAR   : <caminho:componente>   (1 a 3 arquivos, nada além)
  REUSAR (não recriar): <átomos/serviços que já existem lá — eles têm aria/testid>
  CRIAR               : <só o que não existe — nomear>
  NÃO TOCAR           : <vizinhas, shell, bundle CSS>
  PASSO A PASSO       : 1) … 2) … 3) …   (a ordem em que o diff nasce)
  DADO                : <Service/coluna real por slot>
  PARAR SE            : <dado inexistente · decisão [W] aberta · afordância falsa>
```

**`PARAR SE` não é formalidade** — é onde o Code para em vez de inventar. Medido na Forja: o checkbox da linha exige mutação em massa sem endpoint (afordância falsa) e o chevron de épico não existe porque `epic_id` aponta pra outra entidade.

## 4-quater · Estrutura obrigatória do pacote de EXPORT (todo módulo, sempre igual)

`0` leis que não se renegociam · `1` ordem das ondas + âncora por onda · `1-bis` instrução de execução (§4-ter) · `2` **Onda 0a: a11y do alvo** (A1–A12 do §5-bis — o que falhar corrige-se no build, não vira pedido) · `3` **ALVO medido por seção** · `4` comportamento + invariantes · `5` não inventar (CSS/átomos/dados/copy) · `6` DoD + placar · `7` **o que a ancoragem NÃO resolve** (dado inexistente · superfície sem receptor · decisão [W] · verificação bloqueada) · `8` não medido, declarado · `9` recibo (pacote + `github.md`).

Falta de qualquer bloco **invalida o pacote** — foi a ausência do `7` e do `2` que fazia o pedido parecer completo e voltar pela metade.

---

## 5 · Contrato de COMPORTAMENTO (a tabela que faltava nos pedidos)

Uma linha por elemento interativo; nenhuma coluna é opcional.

| coluna | o que entra | erro que evita |
|---|---|---|
| **elemento** | seletor + TAG esperada (`.tf-kpi` = `BUTTON`) | KPI virar `DIV` e perder foco/teclado |
| **estados** | default · hover · **focus-visible** · active · selected · disabled · loading · empty · error (só os que a peça tem) | entregar hover e esquecer teclado |
| **gatilho** | vocabulário do §3 · declarar `stopPropagation` | o check dentro da linha abrindo o drawer |
| **efeito** | estado · filtro · rota · rede · overlay | filtro que pinta e não filtra |
| **persistência** | chave exata de `localStorage` ou "não persiste" | densidade que volta ao default no reload |
| **reversível?** | clicar de novo desliga? esc fecha? | filtro que liga e não desliga |
| **prova** | o teste/sonda que reprova | "está funcionando" |

### As 10 invariantes do módulo (valem sem repetir por seção)
1. Toda escrita é **PROPOSTA** (grava proposta + atividade; nunca aplica direto).
2. **Filtro é reversível** — clicar no ativo desliga.
3. **Clique aninhado declara `stopPropagation`.**
4. **`esc` fecha um nível** da pilha, nunca dois.
5. **Teclado escopado** à seção montada (`j/k/x/p`); `⌘K` e `?` são globais.
6. **Persistência declarada** — a chave vem no pedido; ninguém inventa nome.
7. **Estado vazio é conteúdo**: diz por que está vazio e o que fazer.
8. **`focus-visible` accent** em tudo clicável.
9. **Marcador de rede sobrevive ao clique** (D1 parcial): filtro/aba não recarrega.
10. **Sem número inventado** — sem fonte ⇒ `—` + linha no PR.

---

## 5-bis · Checklist de a11y do ALVO — e a descoberta de que **o alvo não é sagrado**

Os erros que a literatura de 2026 aponta como os que a geração por agente mais comete foram **testados no nosso próprio protótipo** (2026-09-03, view Trabalho·Lista/Quadro). Resultado: **4 defeitos reais no alvo**. Exportar o alvo como está **exporta o defeito** — logo estes 11 itens entram no `ALVO`, e o que falhar **se corrige aqui, no build, antes de virar pedido**.

| # | erro comum | como medir | medido no protótipo | veredito |
|---|---|---|---|---|
| A1 | **falso interativo** (`DIV` com clique) | `tagName` + `role` + `tabIndex` dos elementos clicáveis | **23 `.fj-row` são `DIV` sem `role` nem `tabindex`** | 🔴 corrigir no build |
| A2 | **foco removido sem substituto** | varrer folhas: `outline:none|0` × regras `:focus-visible` | **139 `outline:none/0`** · 57 `:focus-visible` · 103 `:focus` sem `-visible` | 🟠 auditar por seção |
| A3 | **ícone sem nome** | `svg` em clicável sem `aria-hidden` nem nome | **66 de 66** sem nenhum dos dois | 🔴 corrigir no build |
| A4 | **overlay sem foco/trap** | `role`/`aria-modal` + `activeElement` dentro | drawer abre, `role`=∅, `aria-modal`=∅, foco fica no `BODY` | 🔴 corrigir no build |
| A5 | **ARIA de estado estática** | `aria-selected/pressed/current` nas abas | topnav **0 de 6**; segmented do DS **3 de 3** ✔ | 🔴 topnav |
| A6 | **estado só por cor** | texto/`title`/`aria-label` no indicador | 23 `.fj-prio-dot`, **0** sem título | ✅ passa |
| A7 | **alvo de toque** (WCAG 2.2 2.5.8 ≥24×24) | `getBoundingClientRect` de todo `button` | **81 de 118 < 24px** (`fj-rowcheck` 16×16 · `pin`/`star` 22×22) | ⚪ decisão [W]: ERP denso 1280 × mínimo WCAG |
| A8 | **contraste** | OKLCH→sRGB, razão vs bg resolvido | **4 falhas AA**: `.fj-id` 3,02 · `.tf-kpi-l` 3,18 · `.fj-groupby-lbl` 3,94 · `.fj-fresco` 3,94 (ok: `.fj-title` 10,84 · `.fj-group-title` 13,02 · `.fj-gb-btn` 4,85) | 🔴 corrigir tokens de texto pequeno |
| A9 | **arraste sem alternativa** (2.5.7) | `[draggable]` × alternativa por teclado | quadro: 5 arrastáveis · `aria-keyshortcuts` **0** | 🟠 é o gap já medido pela 0367 |
| A10 | **conteúdo dinâmico sem `aria-live`** | `[aria-live]` no documento | **0** — toast e mensagem de onda não anunciam | 🔴 corrigir no build |
| A11 | **bypass blocks** (skip link) | 1º focável da página | é o switcher da sidebar, não "pular para o conteúdo" | 🟠 fundação (shell) |
| A12 | **estado vazio** | filtrar até 0 e ler o nó | `.fj-empty` com "Nenhum issue casa com o filtro." + ação | ✅ passa |

**Consequência de método (o mais importante desta rodada):** até aqui o protocolo tratava o protótipo como alvo **inquestionável**. Ele é o alvo do **layout** — não é certificado de a11y. Regra nova: **`ALVO` roda o A1–A12 e o que falhar vira correção no build (aqui), não pedido pro Code.** Pedir ao Code que replique `DIV` clicável e ícone anônimo é exportar dívida com selo de aprovação.

### E a sonda também precisa de T5 — meus dois primeiros números de contraste eram falsos
A 1ª sonda leu `getComputedStyle(...).color` = `oklch(0.94 0.005 90)` com um regex de `rgb()` e produziu **2,62** para o `.fj-title`. A 2ª "correção" via `canvas.fillStyle` **não converteu** (`fillStyle` devolveu a string `oklch(...)` intacta) e repetiu os mesmos números — coincidência que parecia confirmação. Só a 3ª vale: conversão OKLCH→OKLab→sRGB com **teste de sanidade** (branco sobre `--bg` = **15,52**, plausível) — e aí o `.fj-title` é **10,84**, não 2,62.

**Regra:** toda sonda nova roda um **caso de sanidade de valor conhecido** antes de qualquer veredito. Sem isso, ela inventa bug (falso positivo) ou absolve defeito (falso negativo) com a mesma confiança. Isto é o T5 aplicado **à sonda**, não à tela.

---


## 6 · O canal (o que sai, por qual rota)

**4 saídas, e só 4:** ① **build** (`jsx/css/html`) → `prototipo-ui/cowork/` · ② **pedido** (ponte `.md`) → `prototipo-ui/` root · ③ **contrato destilado** → `<Tela>.charter.md`/`.casos.md` · ④ **recibo** → `github.md`.
**Nunca sai:** memória · process doc · charter duplicado · screenshot · dupe `?v=` · `.bak` · manifesto/mapa/inventário **derivado do build**.

**Rota — com a consequência medida da divisão em 1 arquivo por tela.** Fronteira do `DesignSync.get_file` (`protocolo.config.mjs:214-217`): **> ~48 KB** volta persistido e desce pela rota avulsa; **< 48 KB** volta inline e **não desce** por ela. Medido em 2026-09-03: o `forja-page.jsx` era o único grande da área (90.365 B) e virou shell de ~41 KB ⇒ **os 17 arquivos da Forja estão todos abaixo do piso**, e a descida **exige o pacote**:
```
node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
```
Não roda do lado do agente (o gerador exige os arquivos em disco; escrever pelo contexto é transcrição — ADR 0374). Paridade do espelho é do `cowork-mirror-freshness.mjs` + `cowork-ssot-guard` — **não pedir script novo nem exceção do R1**.

### Bloco de contrato destilado (colar no charter, 1 por seção, no PR da seção)
```md
### Seção <nome> (<seletor raiz>) — contrato de exportação <data>
- **Fonte:** prototipo-ui/cowork/<arquivo>.jsx · átomos window.Fj*
- **Estrutura:** <N> filhos na ordem [ … ] · <tipografia/cor/gap resolvidos>
- **Comportamento:** <1 linha por elemento: gatilho → efeito → persistência → reversível>
- **Invariantes aplicadas:** <as que valem aqui>
- **Divergências declaradas:** <campo sem fonte → "—"> · <verbo do FSM difere>
- **Não fazer:** <anti-padrão desta seção>
- **Prova:** <teste/sonda> · recibo: <PR/deploy>
```
Nunca em lote e nunca retroativo: `casos.md` com UC sem teste quebra o `casos-gate` G-2.

---

## 7 · PROVA — dois testes rodados em 2026-09-03 (não prometidos)

### 7.1 · `ALVO` produz número que reprova
App rodando, dark, após `__oiLazyDone`, **duas leituras iguais** de `querySelectorAll('*').length` (**1491 / 1491**):

| seção | alvo medido |
|---|---|
| `.fj-row` | 23 nós · **13 filhos nesta ordem**: `fj-rowcheck`(BUTTON) · `fj-row-indent` · `fj-prio-dot` · `fj-id` · `fj-type` · `fj-title` · `fj-tam` · `fj-row-mid` · `fj-fresco` · `fj-exec` · `fj-role` · `fj-pin`(BUTTON) · `fj-star`(BUTTON) · altura **34px** · 13px · raiz `DIV` |
| `.fj-kpirow` | 5 filhos · gap 10px · KPI é **BUTTON** · valor **17px** · rótulo 10px · align **left** |
| `.fj-toolbar` | 4 filhos · gap 14px · padding 11px 18px · 18 `.fj-gb-btn` |
| `.fj-filterbar2` | 9–12 filhos (cresce com visões salvas) · gap 6px |
| `.fj-group-head` | 5 grupos · 2 filhos · título 11px |
| `.fj-totalbar` | `flex` · 6 filhos · gap 14px |
| `--accent` (dark) | **`oklch(0.70 0.15 295)`** — não o `0.55` do light |

**O método me reprovou nesta mesma sessão:** duas mensagens antes eu escrevi a `.fj-row` de cabeça como *check·pin·star·id·titulo·type·phase·exec·owner·vinc·fresco·tam·chevron*. O total (13) batia; **a ordem não** — `pin`/`star` são os dois **últimos**, `fj-row-indent`/`fj-prio-dot`/`fj-row-mid` existem e eu não citei, `fj-phase` e chevron **não** estão nessa linha. Um pedido com a minha lembrança teria produzido linha plausível e errada.

### 7.2 · Teste de falsificação — a sonda reprova de fato
Sonda do `.fj-row` (filhos + ordem + altura), rodada três vezes: **antes** → `ok: true` (13 filhos, ordem ok, 34px) · **removendo `.fj-star` do DOM** → `ok: false`, `filhos: 12`, `faltam: ["fj-star"]`, `ordemOk: false` · **restaurando** → `ok: true`.

Duas leituras importantes:
- A sonda **nomeia** o ausente — é isso que alimenta o placar automaticamente.
- **A altura ficou 34px nos três estados.** Um critério "visual/parecido" (ou screenshot no olho) **não pegaria** a perda do botão de favoritar; contagem + ordem pegam. É o argumento inteiro do método em uma medição.

### O que a prova NÃO prova
Que a produção vai ficar igual — isso é `design-diff --compare --check` nos **dois** renders, com deploy. O que ficou provado é mais estreito e mais útil: **o pedido passou a carregar um número que reprova, e ele reprova quando deve.**

---

## 7-bis · A bateria — os 7 testes que uma seção tem que passar

Ordem é obrigatória: T1 antes de tudo (sem estabilidade, todo número abaixo é ruído).

| # | teste | reprova quando | quem roda | roda daqui? |
|---|---|---|---|---|
| **T1** | **estabilidade da medição** — duas leituras iguais de `querySelectorAll('*').length` após `__oiLazyDone` | leituras diferentes (mediu durante o lazy-load) | `ALVO` | **sim** |
| **T2** | **estrutura** — contagem de nós/filhos **e a ORDEM** | falta filho, sobra filho, ordem trocada | sonda da seção | **sim** |
| **T3** | **tokens resolvidos** — `getComputedStyle`, tema dark, no elemento (nunca a classe declarada) | cor/px/gap diferentes do alvo; hex cru; utilitária de cor | sonda da seção | **sim** |
| **T4** | **comportamento** — gatilho → efeito → persistência → reversibilidade, por elemento interativo | clique não abre; filtro não filtra; filtro não desliga; `esc` não fecha; chave de `localStorage` ausente; `focus-visible` ausente | sonda + teste de interação | **sim** (sonda) · suíte: **não** (E2E da Forja = 0) |
| **T5** | **falsificação** — remover 1 elemento do alvo e conferir que a sonda **reprova nomeando** | sonda continua verde sem o elemento (sonda cega ⇒ inútil) | `ALVO` | **sim** |
| **T6** | **vizinhança** — a sonda da seção anterior roda no PR da seguinte | seção fechada regrediu | PR | **sim** |
| **T7** | **paridade pareada** — `design-diff --compare --check` nos **dois** renders + a11y + VRT + `casos-gate` G-2 | qualquer `DIVERGE(bug)`; UC sem teste | CI + prod deployada | **não** — exige deploy e sessão autenticada |

**T1–T6 rodam no protótipo servido; T7 é o único que afirma "paridade".** Daí a regra: *nenhuma onda é declarada "0 bug" antes do T7* — o que T1–T6 garantem é que o pedido é **reprovável**, não que a produção ficou igual.

### Três armadilhas de medição que a bateria já pegou (não repetir)
1. **Altura não denuncia filho faltando.** Removido o `.fj-star`, a `.fj-row` continuou **34px** — T2 pegou, "parecido"/screenshot não pegaria.
2. **Seletor de lembrança inventa seção.** `.ap-hero`, `.fj-kb-col`, `.fj-cl-item`, `.fj-token` = **0 nós**; os reais são `.ap-page`, `.fj-kcol`, `.fj-feed-item`, `.fj-perm`. `MAPA` colhe do DOM.
3. **O dono do scroll pode ser o filho.** No quadro, `.fj-quadro-wrap` é `overflow-x: hidden` e as 6 colunas somam **1488px** — parece corte, **não é**: o scroll vive em `.fj-kanban` (`overflow-x:auto`, `scrollWidth 1580 > clientWidth 649`). Medir o pai e concluir corte seria bug falso no pedido.

---


## 8 · Como isto APRENDE (e o que o torna mecânico)

Nada aprende sozinho. O aprendizado existe porque cada onda deixa **3 resíduos em lugares de leitura obrigatória**:

| resíduo | onde cai | quem é forçado a ler |
|---|---|---|
| alvo medido da seção | bloco do §6 no `<Tela>.charter.md` | quem abrir a seção depois (charter é leitura do pre-flight) |
| ausentes + motivo (placar) | tabela de ondas do `PARIDADE-area-<Mod>-*.md` | quem numerar a próxima onda |
| erro de método (ex.: §7.1) | `memory/LICOES_CC.md` | o pre-flight injeta erro catalogado |

**3 métricas, todas derivadas do placar — nenhuma máquina nova:**
1. **Cobertura cumulativa** Σentregue ÷ Σalvo, por módulo — tem que subir monotonicamente.
2. **Reincidência por motivo** (mesmo motivo em 2+ ondas) — não é acidente: é decisão de [W] ou lacuna de backend. Tem que cair.
3. **Retrabalho** (seção reaberta depois de fechada) — alvo 0; >0 significa alvo incompleto, e a correção é **no alvo**, não na tela.

**Ratchet:** o alvo que entrou no charter vira **piso** — a onda seguinte não entrega menos, e como o alvo é contagem, a sonda da seção anterior roda no PR da seguinte (1 comando). Mesmo mecanismo do `ds:report`: o número só anda pra baixo.

**Limite, dito na cara:** o loop só é mecânico se o **placar for exigido no corpo do PR** e o **bloco cair no charter no mesmo PR**. Sem essas duas, isto vira checklist decorativo — e falha igual aos pedidos que já falharam.

---

## 9 · Onde este método está contra o estado da arte (41/50)

| dimensão | nota | melhor da classe | leitura |
|---|---|---|---|
| aterramento de contexto (just-in-time) | **5** | Anthropic *context engineering* | empate: `MAPA`/`ALVO` materializam no momento da decisão; nada em cache (L-42) |
| tokens como SSOT | **5** | DTCG + Style Dictionary | empate: é o que o repo já faz; medi `--accent` **resolvido**, não a classe |
| rastreabilidade decisão→código | **5** | ADR + traceability | empate: ADR datada, US dona, charter, recibo, 301 dos caminhos mortos |
| tamanho de lote | **5** | trunk-based / small PRs | empate: 1 seção = 1 PR ≤300 ln; big-bang barrado pelo `casos-gate` |
| reusar componente real | **4** | Figma Code Connect / registry | REGISTRY + guard R7, mas o mapa DS→arquivo é **manual** (`HANDOFF.md`) |
| ratchet anti-regressão | **4** | Betterer / `ds:report` | piso vive em texto no charter, não em baseline versionada por seção |
| aprendizado institucional | **4** | "erro no contexto" + postmortem | resíduo existe; a **injeção automática** ainda é spec |
| **spec executável** | **3** | Spec-Kit / Kiro | meu `EXPORT` é prosa com números; o receptáculo existe (`contrato/*.contract.json`, ADR 0286) e **não** está sendo escrito |
| **fidelidade visual** | **3** | Chromatic / Percy (VRT com baseline) | `design-diff --compare` existe mas o **snapshot é órfão** nesta área; VRT medido = 0 |
| **comportamento verificado** | **3** | Storybook play + Playwright | exijo a coluna `prova`, mas E2E na Forja = 0: hoje a prova é sonda, não suíte |

**Padrão do placar:** empato onde a questão é *de onde a verdade vem* (governança, contexto, tokens) — vocês já pagaram esse preço. Perco onde a questão é *transformar o alvo em coisa que roda*.

**Os 3 movimentos que fecham, em ordem de custo/benefício:**
1. **`ALVO` serializa no `contrato/*.contract.json`** em vez de só no chat (spec executável 3→5). O arquivo e o gate já existem.
2. **Desorfanar o snapshot do `design-diff --compare`** (fidelidade 3→5) — é o bloqueio nomeado no §9.5 do PARIDADE: "medição órfã, não teto". Sem isso, "0 DIVERGE" nunca é afirmável.
3. **Um teste de interação por seção**, citando o UC do `casos.md` (comportamento 3→5), oportunístico como o `casos.md` já é.

---

## 9-bis · Quem já resolveu melhor (busca pública, 2026) e o que se rouba

**A direção do nosso caso é incomum.** A indústria exporta **do Figma** para código; aqui se exporta de um **protótipo React rodando** — onde o comportamento é **observável**, não anotado. Logo o análogo certo não é "Figma→código": é *mapping* (Code Connect) + *live-app verification*.

| eixo | quem está melhor | o que eles têm que nós não |
|---|---|---|
| ponte design→código | **Figma MCP + Code Connect** (2026) — contexto estruturado (árvore de componentes, variáveis, mapeamento pro import real) em vez de pixel/PDF | resolução de componente → **caminho de arquivo real**, consultável por máquina. Nosso mapa DS→arquivo é manual (`HANDOFF.md`) |
| disciplina de spec | **Kiro** (EARS: `WHEN <condição> THE SYSTEM SHALL <comportamento>`) · **Spec Kit** (agent-agnostic, spec no repo) | notação de critério de aceite **padronizada** e testável de leitura |
| verificação | SDD "com grafo de requisitos" + **verificação no app vivo**; e no visual, **Chromatic/Percy** (baseline VRT) e **Storybook play + Playwright** (comportamento) | baseline versionada que reprova sem humano olhar |
| pipeline de tokens | **Style Dictionary / Token Studio** | nada — **aqui já é isso** (DTCG → OKLCH) |

**O que a própria literatura de 2026 diz que a geração por agente ainda erra** — e é exatamente o que o §5 deste protocolo exige por escrito: estados de foco, ARIA, teclado e reduced-motion; estados de formulário (validação/erro/loading); casos de borda (vazio, texto longo, i18n); e padrões divergentes entre sessões. A recomendação prática deles é *embutir acessibilidade e estados no mapeamento* para a geração ter de onde herdar — é o papel do bloco de contrato destilado (§6).

**A armadilha nomeada da categoria:** *spec-first-then-drift* — gera-se a spec para abrir a sessão e o código volta a ser a fonte no primeiro `generate`; e EARS **não roda** (é disciplina de redação, não teste). Nosso antídoto já está escrito: **alvo é contagem** (T2), **piso no charter** (ratchet) e **T7 pareado**.

**Os 3 roubos, em ordem de custo:**
1. **EARS na tabela de comportamento** — reescrever cada linha como `QUANDO <gatilho> O SISTEMA DEVE <efeito>`; custo zero, ganha ambiguidade zero.
2. **Índice consultável DS→arquivo** (papel do Code Connect) — o `REGISTRY` + guard R7 viram índice que impede citar componente inexistente.
3. **Baseline por seção** (papel do Chromatic) — desorfanar o snapshot do `--compare`; sem isso a verificação continua sendo a coluna onde perdemos.

> Ressalva de método: isto é leitura de fontes públicas e **relatos de campo** (inclusive números de fornecedor); não é estudo controlado nem medição nossa.

---


## 10 · Anti-padrões (recusar o pedido se aparecerem)

- **"Exporte a view inteira"** — sem seção e sem placar volta 3 de 5. Devolver pedindo o `MAPA`.
- **Medir e aplicar no mesmo PR** — a medição do alvo é pedido separado, read-only.
- **DoD com adjetivo** ("igual", "parecido") — trocar por contagem, ordem e `getComputedStyle`.
- **Mapa/manifesto/inventário derivado virando arquivo** — L-42 · ADR 0256: é comando.
- **`.md`/memória/screenshot em `cowork/`** — guard R1. **Pedir exceção do R1 ou script de paridade novo** — o dono existe.
- **Transcrever arquivo pelo contexto do agente** em vez de descer pelo pacote — ADR 0374.
- **Editar `prototipo-ui/cowork/` à mão do lado do git** — espelho read-only; remendo some no próximo `--export-from`.
- **Dizer "ficou igual ao design"** sem `design-diff` nos dois renders — isso é medição, nunca leitura.
- **Big-bang de `casos.md`** — UC nasce na seção tocada, com teste citando.

---

## 11 · Cobertura declarada (o que este protocolo NÃO resolve)

- **Rota do `app.jsx` sem componente (C6)** — sem dono no repo hoje.
- **Dupe `?v=` e host único** — regra minha, não máquina: nenhum gate reprova.
- **Peça que o snapshot do pacote não publica** (ex.: `Segmented` do DS no pacote de 24/08) — medição local cega até o pacote ser regerado.
- **Órfãos do build declarados:** `FjTriagemView` e `ForjaTarefas` têm arquivo e global, mas nenhum ponto de render os monta (triagem é tipo `Proposta` em Aprovações; `tarefas` colapsou em `trabalho`).

---

## 12 · `SINCRONIZAR <Mod>` — o comando que encadeia (pedido [W] 2026-09-05)

> Um comando, cinco passos, **nenhum dono novo**. Cada passo reusa peça que já existe no `main`; o que este comando acrescenta é a **ordem**, o **playbook de threads** (1 arquivo por pedaço, sessão limpa) e o **verificador da lista** — hoje o placar é por tela, e "o Code terminou tudo?" não tem máquina.
> Nasceu do erro de 2026-09-05: listei HRM como "zero receptor" olhando só `resources/js/Pages/`; o módulo está em curso em `Modules/Essentials` (blades `nav_hrm`, 3 testes `Hrm*Test`) e já tinha pedido em `cowork-inbox/hrm/`. Levantamento por pasta mente; por rota + 4 sinais, não.

| passo | faz | dono já existente | sai |
|---|---|---|---|
| **1 LEVANTAR** | **4 denominadores**: rota (`routes/*.php` + `Modules/*/Routes/*.php`) · nav/menu legado (itens que apontam pro core) · rotas do `app.jsx` · **runtime: `Inertia::render(` nos controllers do módulo = "a rota JÁ tem Page React?"** (faltou no HRM: Metas estava feita 32 s antes da base e o playbook mandava reconstruí-la) — nos DOIS sentidos; **4 sinais por tela**: rota existe · Page `.tsx` existe · blade legado existe · em curso (teste Feature, migration, `cowork-inbox/<mod>/`, `COLAR-NO-CODE-*`); caminho das Pages = **o que a árvore já usa** (glob nos `.tsx` irmãos), nunca decisão nova; dicionário de apelidos escrito antes (Essentials=RH · Sells=Vendas · Purchase+Compras · Repair=assistência · NfeBrasil+Fiscal+Nfse); **uma sha**, dita | `MAPA` (§2) · `FRESCOR` · `PARIDADE-area-<Mod>` | quadro por estado **no chat** (em produção React · legado blade · em implementação · só protótipo) + `00-INDICE.md` |
| **2 PUXAR** | onde a produção está **À FRENTE** (🔵): ler o `.tsx` e trazer pro build os átomos/aria/`data-testid`/dados reais; onde o layout do protótipo fica, **divergência declarada** com motivo | `FRESCOR` regra 1 · §4-bis ancoragem dupla · §5-bis | build em `prototipo-ui/cowork/` |
| **3 REACT** | legado blade sem Page: Ficha `BL-*` → charter + `casos.md` + `contract.json` → rota no `oimpresso.com.html` (`<mod>-page.jsx` + `app.jsx` + `data.jsx`; variação = Tweak) | `PLANO-BLADE-PARA-REACT` E2–E4 · `PRE-FLIGHT` | build + trio |
| **4 PLAYBOOK** | 1 arquivo por thread, **auto-suficiente** (teste do estranho); 1 thread = 1 seção = 1 PR ≤300 ln = 1 prefixo | `ponte/03` Leis 1–4 · §4 · §4-ter · §2-quater | `cowork-inbox/<mod>/playbook/NN-*.md` |
| **5 VERIFICAR** | por thread: `_saida-NN.md` + `prova:` nomeada · da lista: `PLACAR <Mod>` confere cada `prova:` do índice no `main`, no turno | §6 placar · PR-A6/A8 | `entregue X de Y · ausentes <thread> por <motivo>` |

### Forma do playbook — `prototipo-ui/design-docs/cowork-inbox/<mod>/playbook/`
```
playbook.json        FONTE (schema cowork-inbox/_schema/playbook.schema.json): sha · variáveis (${PAGES}…) · decisões [W]
                     · threads[{id, dono, prefixo, nao_toca, depende_threads, depende_decisoes, bloqueio, provas[]}]
                     · ESTADO NÃO EXISTE AQUI — é derivado (Lei 2 por construção)
00-INDICE.md         render humano: LEVANTAR por estado · abertura de thread · placar (saída do script) · revisão 3× · resíduo
NN-<view>.<secao>.md 1 thread. Cabeçalho: sessão · prefixo · NÃO toca · base sha
                     · read-order do main · A–D (§4) com ancoragem dupla · 4-ter · PROVA · PARAR SE
_saida-NN.md         escrito pela própria thread — prova IMPLÍCITA de toda thread; sem ela nada é "feito"
```
**Placar da lista = máquina, hoje:** `node cowork-inbox/_scripts/placar-indice.mjs --indice <playbook.json> --root . --proximo` (zero deps; ponte pro Code → `scripts/qa/placar-indice.mjs` ou `placar.mjs --indice`, PR-A8). Deriva `feito · em curso · proximo · pendente · bloqueada`, nomeia ausente por thread e arquivo, imprime `PRÓXIMO:`; `--todos 'cowork-inbox/*/playbook/playbook.json'` soma os módulos — **é o unificador**, não um doc. Testado 2026-09-05 com repo simulado: 7 casos incl. T5 (apagar 1 prova → X−1 nomeando) e contrato fora do schema (nomeia as chaves).
- **Anti-scatter:** se `cowork-inbox/<mod>/` já tem `PEDIDO-*`/`EXPORT-*` (HRM, CMS, Connector, Notificações…), o playbook **absorve** aquele pedido como threads — não nasce um terceiro doc sobre o mesmo módulo.
- **Granularidade e unificação (decisão de forma, 2026-09-05):** **1 playbook por MÓDULO** (tela/seção = thread dentro dele), nunca por tela. Nasce **só quando o módulo entra em vaga** — playbook antecipado é cache que envelhece (L-42). Tela 🔵 não ganha playbook: é 1 thread PUXAR dentro do módulo dela. **O unificador já existe e não se recria:** `cowork-inbox/ponte/00-INDEX.md` (programa S1–S10) ganha **1 linha por módulo com playbook** (`módulo · caminho do 00-INDICE · feito/pendente/bloqueada · vaga`), escrita **só pelo S0**; a máquina que soma tudo é o `placar.mjs --indice` iterando `cowork-inbox/*/playbook/00-INDICE.md` (PR-A8). Limite: módulos em execução simultânea = vagas abertas (3–4), não "todos".
- **Onde NÃO mora:** `prototipo-ui/cowork/` (guard R1). O índice é **pedido** (lista de threads a executar, com sha), não inventário — é o mesmo estatuto da `ponte/01-LISTA-COMPLETA.md`.
- **Landing (limite do canal, medido pelo [CL] 2026-09-05):** só `.md` roteia pelo DesignSync (`get_file` → `--export-from`, destino `design-docs/`); `.json`/`.mjs` soltos não chegam. Logo a fonte da máquina é o **primeiro bloco ```json do `00-INDICE.md`** (o script lê `.md` ou `.json`), e schema/script viajam como anexos do `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md`. **A unidade de descida é a pasta inteira** — índice sem as `NN-*.md` aponta pra arquivos inexistentes (o defeito que o próprio PEDIDO-CL-hrm tem com 2 planos de `memory/sessions/`).
- **Âncora de implementação de Page nova = a irmã golden viva do módulo** (no HRM: `Pages/Essentials/Metas.tsx`, #6869) e o pacote dela — tsx · charter com frontmatter · casos · `contrato/<mod>-<tela>.contract.json` · Pest · e2e · RUNBOOK · lane — não "trio". `PARAR SE` sempre inclui: gerador sair da convenção da árvore → parar, não escolher à mão.
- **Abertura de thread** = o bloco de `ponte/03` ("Sessão fresca. Leia nesta ordem, do main…") + o `NN-*.md`. Sem chat anterior — se a thread precisa perguntar algo, o arquivo está incompleto.

### Verificador — dois níveis, e o que conta como "terminou"
1. **Thread:** `_saida-NN.md` com os 5 itens (feito por caminho · não feito e por quê · pedido literal · descobertas · prefixo tocado). Sem `_saida`, a thread **não conta** — nem que o PR esteja mergeado.
2. **Lista:** `PLACAR <Mod>` lê `00-INDICE.md` e, **lendo o `main` no turno**, confere cada `prova:` — arquivo existe · teste Feature existe · `contract.json` valida no schema · `design-spec.json` derivado · `_saida` presente. Devolve `entregue X de Y · ausentes <thread> por <motivo>`; reincidência de motivo = fila de [W] (`RESÍDUO`).
3. **Máquina:** `cowork-inbox/_scripts/placar-indice.mjs` **já existe como ponte** (testado); PR-A8 o leva para `scripts/qa/` (ou `placar.mjs --indice`) — **não** script paralelo. Aceite T5 da lista: apagar uma `prova:` faz X cair para X−1 **nomeando a thread e o arquivo** (verificado).
4. **Fim** = 100% das provas verdes **e** T7 por seção. Antes disso o estado é "em curso", nunca "pronto".

### O que o comando NÃO faz (e não deve)
Não decide qual seção entra na onda (julgamento) · não mergeia `.tsx` (ADR 0283) · não regenera o pacote (ADR 0374 — aviso o comando) · não afirma paridade sem T7 · não grava mapa/inventário fora do índice-pedido (L-42).
