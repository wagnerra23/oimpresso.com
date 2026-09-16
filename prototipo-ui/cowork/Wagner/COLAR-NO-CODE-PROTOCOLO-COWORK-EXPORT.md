# COLAR NO CODE — PROTOCOLO COWORK → CODE (consolidado)

> **Uma pergunta só:** qual é a unidade do pedido, o que ela carrega, por qual rota desce e como se prova que chegou inteira.
> **Consolida e SUBSTITUI** os 3 arquivos de ponte anteriores (`…paridade-por-secao-e-comportamento`, `…PROTOCOLO-DE-EXPORTACAO`, `…METODO-DE-PEDIDO`) — 3 docs sobre o mesmo assunto **era o scatter que o `COWORK-ESTRUTURA` proíbe**. Apagados neste mesmo ciclo.
> **Ponte, não canon.** Destino no `main`: `prototipo-ui/` (root) — nunca `prototipo-ui/cowork/` (guard R1: zero `.md` lá). Eu **não escrevo no git**: desce por `cowork-inbox`/Issue → PR.
> **Não substitui:** `protocolo.config.mjs` (fases/gates/comandos) · `PRE-FLIGHT-TELA.md` (pré-requisitos) · `PARIDADE-area-<Mod>-*.md` (ordem e veredito) · `HANDOFF.md` (mapa DS→arquivo).

---

> **Este arquivo é a NORMA — o que se faz.** O *por quê se sabe* (provas de 03/09, placares 41/50 · 49/60, contra-placar, estado da arte) foi movido para **`DOSSIE-PROTOCOLO-COWORK.md`** em 2026-09-08. Números de seção **preservados**: §1, §7, §9, §9-bis, §9-ter e §9-quater vivem lá e continuam citáveis pelo mesmo número.

> **Quem lê o quê.** Esta norma é documento de **quem GERA o pedido** ([CC]). O executor ([CL]) **não** a lê: ele lê `00-INDICE.md` (≤8 KB) + `NN-*.md` (≤6 KB) + a âncora recortada (≤40 KB) — teto de abertura **54 KB**, §13. Mandar o executor ler este arquivo é o próprio defeito que o §13 descreve.

> **Constituição — citada, nunca copiada.** As leis universais vivem em `CLAUDE.md` + `memory/proibicoes.md` + `memory/INDEX.md`. Todo pacote de módulo cita por sha (`constituição: memory/proibicoes.md@<sha>`) e mantém no seu `§0` **somente a lei daquele módulo**. Regra copiada é regra que envelhece em paralelo — foi assim que a ADR 0374, revogada em 07/09, seguiu citada como vigente em 3 pontos deste arquivo até 08/09.

> **Gate de escrita (do §9-quater, medido):** documento de processo novo só nasce se **destravar uma thread nomeada que abre PR em ≤7 dias**. Senão: editar o que existe, ou não escrever. Na janela 09/08→08/09 a razão foi **61 documentos de processo : 7 PRs nomeáveis**.

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


---

## 2-bis · Granularidade da onda — depende do tamanho do módulo

| módulo | onda = | quantas | exemplo |
|---|---|---|---|
| **multi-view** (6 views, ~28 seções) | 1 view ou 1 seção grande | 10-12 | Forja: shell · chrome · lista · quadro · gantt · aprovações · saúde · mcp · changelog · integrador · triagem |
| **página única** (1 view, 5-8 seções) | **1 SEÇÃO** | 5-8 | Visão geral: header+stats · barra de período · select loja · gráficos · grades+drawer · pendências |
| **overlay sem receptor** | 1 por superfície, **depois** de [W] declarar rota | — | drawer · ⌘K · notifs · composer |

**Regra:** onda nunca é maior que 1 PR ≤300 linhas. Se a seção não cabe, ela se divide (linha × cabeçalho × rodapé); nunca se agrupa.


---

## 2-ter · Anti-scatter — se o módulo já tem ponte, ATUALIZE

Antes de escrever pedido novo: procurar `COLAR-NO-CODE-*<modulo>*` e `cowork-inbox/PEDIDO-*<modulo>*`. Se existir, **reescrever aquele** na forma padrão, preservando as perguntas ⛔ [W] já catalogadas. Três docs sobre o mesmo módulo é a doença que fez estes três protocolos virarem um.

---


---

## 2-quater · Uma onda = uma sessão LIMPA — obrigatório, e com teste

**Sim, é obrigatório**, e não por higiene: é a única forma de o pedido ser verdadeiro. Contexto de chat **não** desce junto com o PR — quem executa a onda 7 não viu a conversa da onda 3. Se o pedido depende de algo que só existe no histórico, ele mente sobre estar completo.

**Regra:** cada onda abre em sessão nova, e o pedido dela tem de ser **auto-suficiente**. O que a sessão limpa lê (nesta ordem, do `main`, nunca de cópia):

1. `COWORK-ESTRUTURA-E-TELAS.md` · `FRESCOR` · `PRE-FLIGHT-TELA.md` — como se opera.
2. **O pedido da onda** (este pacote, a seção dela) — alvo, comportamento, DoD, placar.
3. **O charter + `casos.md`** da tela — inclusive o bloco de contrato destilado que as ondas anteriores deixaram.
4. `PARIDADE-area-<Mod>-*.md` — ordem, veredito e os ausentes com motivo das ondas passadas.
5. `memory/proibicoes.md` + `LICOES_CC.md` — o erro já catalogado. **E `COLAR-NO-CODE-ACERTOS-E-LICOES.md` — o acerto catalogado** (o que a produção já resolveu e não se refaz; pedido [W] 2026-09-09).
6. **Os 2-4 arquivos da âncora** no `main` (§3-bis) — no momento da onda.

**Teste do estranho (o que torna a regra checável):** entregue o pedido a quem não viu nenhuma conversa. Se ele precisar perguntar *qualquer coisa* sobre o alvo, a âncora, o dado ou o critério de aceite, **o pedido está incompleto** — a falha é do pedido, não dele. Concretamente, o pedido passa quando responde sem histórico: quais arquivos editar · o que reusar · o alvo em número e ordem · o dado real por slot · o que **não** tocar · quando **parar** · como se prova.

**O que a sessão limpa NÃO precisa:** o chat anterior. É para isso que existem os 3 resíduos do §8 — o conhecimento da onda passada mora no charter, na tabela de ondas e nas lições, não na memória de quem estava lá.

**Corolário do meu lado:** o `ALVO` também roda em sessão limpa, medindo o protótipo servido — nunca "o que eu me lembro de ter medido". A prova de que isso importa está no §7.1: eu errei a ordem da `.fj-row` de cabeça, no mesmo dia em que a havia medido.

---


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
  9 ACERTOS: bloco do ciclo em COLAR-NO-CODE-ACERTOS-E-LICOES.md (§15) —
    ≥1 acerto com sha OU "nenhum medido"; erro do ciclo com a regra colada

ESCOPO FECHADO: só esta seção. Não tocar <vizinhas nomeadas>. Não tocar no shell.
```

O **item 6 é o que fecha o buraco**: sem placar, omitir é grátis.

---


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


---

## 4-quater · Estrutura obrigatória do pacote de EXPORT (todo módulo, sempre igual)

`0` leis que não se renegociam · `1` ordem das ondas + âncora por onda · `1-bis` instrução de execução (§4-ter) · `2` **Onda 0a: a11y do alvo** (A1–A12 do §5-bis — o que falhar corrige-se no build, não vira pedido) · `3` **ALVO medido por seção** · `4` comportamento + invariantes · `5` não inventar (CSS/átomos/dados/copy) · `6` DoD + placar · `7` **o que a ancoragem NÃO resolve** (dado inexistente · superfície sem receptor · decisão [W] · verificação bloqueada) · `8` não medido, declarado · `9` recibo (pacote + `github.md`).

Falta de qualquer bloco **invalida o pacote** — foi a ausência do `7` e do `2` que fazia o pedido parecer completo e voltar pela metade.

---


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
| **caso que exercita** | o estado do mock em que essa linha **roda** (ex.: "escala SEM colaborador vinculado") | regra aplicada num ramo que nunca executa |
| **prova** | o teste/sonda que reprova | "está funcionando" |
| **executada?** | ✅ rodei o gatilho no protótipo servido e medi o efeito · ⛔ não rodei | "renderizou sem erro" virar prova de comportamento |

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



---

## 6 · O canal (o que sai, por qual rota)

**4 saídas, e só 4:** ① **build** (`jsx/css/html`) → `prototipo-ui/cowork/` · ② **pedido** (ponte `.md`) → `prototipo-ui/` root · ③ **contrato destilado** → `<Tela>.charter.md`/`.casos.md` · ④ **recibo** → `github.md`. **⑤ acerto+lição** → `COLAR-NO-CODE-ACERTOS-E-LICOES.md` (§15, acumulativo) — são **5** desde 2026-09-09.
**Nunca sai:** memória · process doc · charter duplicado · screenshot · dupe `?v=` · `.bak` · manifesto/mapa/inventário **derivado do build**.

**Rota — com a consequência medida da divisão em 1 arquivo por tela.** Fronteira do `DesignSync.get_file` (`protocolo.config.mjs:214-217`): **> ~48 KB** volta persistido e desce pela rota avulsa; **< 48 KB** volta inline e **não desce** por ela. Medido em 2026-09-03: o `forja-page.jsx` era o único grande da área (90.365 B) e virou shell de ~41 KB ⇒ **os 17 arquivos da Forja estão todos abaixo do piso**, e a descida **exige o pacote**:
```
node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
```
⚠️ **CORRIGIDO 2026-09-10 (errata do [CL], medida em `origin/main` @ `11eff17f13`):** a **ADR 0374 NÃO está revogada** — `status: aceito` · `lifecycle: ativo` · `superseded_by: []`, e nenhuma ADR 0375→0392 a supersede. A emenda real é a **ADR 0389** (`decided_at: 2026-09-03`), que **toca uma linha**: escrever pelo contexto do agente só quando o `get_file` devolve **inline** e **não existe rota de máquina**, sob 4 condições, nenhuma opcional. Onde a rota existe, **transcrever segue proibido** e `--export-from` é a rota preferida. O fato de 2026-09-07 (281 arquivos, 43 partes, bundleId 3fe98b64..., auditado remontando as partes: o sha de todos os pedaços confere). **Ressalva que viaja com o pacote:** a canonicalização de bundleId/manifestSha256/changesSha256 é do agente (sha256 sobre "path:sha" + JSON.stringify), não do gerador — se o validador do Code usar outra, ele recusa só esses 3 campos do cabeçalho; os sha por arquivo e por pedaço são independentes disso. Paridade do espelho é do `cowork-mirror-freshness.mjs` + `cowork-ssot-guard` — **não pedir script novo nem exceção do R1**.

### 6-bis · O lote fecha por INCLUSÃO — a estrutura que não deixa errar (2026-09-09)
**O que quebrou:** o handoff (3) e o bundle v2 foram recusados carregando `cowork-inbox/_schema/playbook.schema.json` e `_scripts/placar-indice.mjs` — **máquina do repo**, em cópia anterior a #7063/#7071. Descê-las apagaria `constituicao`/`nota_caminho`/`custo`/`afeta` do schema e o `descobrirIndices` do placar. Controle positivo do diagnóstico: o playbook `patrimonio`, **válido no `main`**, reprovava contra o schema do ZIP — playbook bom reprovando denuncia o schema, não o playbook.

**A causa não é descuido: é lista de exclusão.** Toda proibição por enumeração (`nunca sai: memória · screenshot · .bak…`) fica atrás do próximo tipo de arquivo inventado. Inverte-se:

> **NADA ENTRA NO LOTE POR ESTAR NA PASTA. Entra por pertencer a uma das 4 saídas do §6, e cada arquivo declara qual.** O que não casa **não é filtrado — invalida o lote inteiro**. Fail-closed: na dúvida o pacote não sai.

| classe | forma | veredito no lote |
|---|---|---|
| **build** | `prototipo-ui/cowork/**` **declarado no host** `oimpresso.com.html` (`<link>`/`src`/`data-src`) | entra |
| **ponte** | `cowork-inbox/<mod>/playbook/{00-INDICE,NN-*,_*}.md` · `COLAR-NO-CODE-*.md` | entra |
| **máquina do repo** | `_schema/**` · `_scripts/**` · `scripts/**` · `.github/**` | **invalida o lote** — muda só por PR no eixo dela |
| **derivado** | manifesto de export · mapa tela↔arquivo · inventário · retrato | **invalida o lote** (L-42 · ADR 0256 — é COMANDO, não arquivo) |

**Três travas, em ordem de força:**
1. **Não ter a cópia.** `_schema/` e `_scripts/` **removidos deste projeto** hoje. O schema se lê do `main` **no turno** — foi exatamente essa leitura que pegou o erro de hoje (meu `00-INDICE` da `ds-atomos` não validaria: `sha_base` por `sha`, `decisoes` como objeto, `dono:"[CL]"` fora do enum, `provas` como string). Sem cópia não há cache, sem cache não há regressão: é a única trava que não depende de disciplina.
2. **Cabeçalho prova frescor.** `generatedAt` **igual ao do lote anterior ⇒ lote recusado sem abrir** — não foi regenerado, e o defeito viaja idêntico. O gerador roda **com `--previous`**, senão o manifesto não sabe o que mudou.
3. **Máquina no repo, dono existente.** Proposta de **R4 no `cowork-ssot-guard.mjs`** (não script novo — R1/R2/R3 já moram lá): PR cujo autor é o eixo Cowork tocando `_schema/**` ou `_scripts/**` **falha**. Enquanto R4 não existir, a trava é 1 + 2 e ela é **minha**, não da máquina — e isso fica dito, não subentendido.

**Emitir `00-INDICE.md` exige o `_schema` real no turno.** Mesma família do erro 2 do ciclo (`_ds/` não é evidência sobre o `main`): escrever contra o schema lembrado é escrever contra um espelho velho.

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


---

## 10 · Anti-padrões (recusar o pedido se aparecerem)

- **"Exporte a view inteira"** — sem seção e sem placar volta 3 de 5. Devolver pedindo o `MAPA`.
- **Medir e aplicar no mesmo PR** — a medição do alvo é pedido separado, read-only.
- **DoD com adjetivo** ("igual", "parecido") — trocar por contagem, ordem e `getComputedStyle`.
- **Mapa/manifesto/inventário derivado virando arquivo** — L-42 · ADR 0256: é comando.
- **`.md`/memória/screenshot em `cowork/`** — guard R1. **Pedir exceção do R1 ou script de paridade novo** — o dono existe.
- **Transcrever arquivo pelo contexto do agente** em vez de descer pelo pacote — **ADR 0374, ATIVA** (medido no `main` 2026-09-10; a 0389 só abre a exceção do `get_file` inline sem rota de máquina, sob 4 condições). O que continua proibido é *afirmar* que gerou sem auditar (remontar as partes e conferir sha) e omitir que o cabeçalho usa canonicalização própria.
- **Editar `prototipo-ui/cowork/` à mão do lado do git** — espelho read-only; remendo some no próximo `--export-from`.
- **Dizer "ficou igual ao design"** sem `design-diff` nos dois renders — isso é medição, nunca leitura.
- **Big-bang de `casos.md`** — UC nasce na seção tocada, com teste citando.

---


---

## 11 · Cobertura declarada (o que este protocolo NÃO resolve)

- **Rota do `app.jsx` sem componente (C6)** — sem dono no repo hoje.
- **Dupe `?v=` e host único** — regra minha, não máquina: nenhum gate reprova.
- **Peça que o snapshot do pacote não publica** (ex.: `Segmented` do DS no pacote de 24/08) — medição local cega até o pacote ser regerado.
- **Órfãos do build declarados:** `FjTriagemView` e `ForjaTarefas` têm arquivo e global, mas nenhum ponto de render os monta (triagem é tipo `Proposta` em Aprovações; `tarefas` colapsou em `trabalho`).

---


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
00-INDICE.md         FONTE = **primeiro bloco ```json embutido neste .md** (só `.md` roteia pelo DesignSync; `.json` solto não chega).
                     Schema: `prototipo-ui/design-docs/cowork-inbox/_schema/playbook.schema.json` **no `main`, lido no turno** —
                     `additionalProperties:false` em tudo: topo exige `modulo·sha·gerado·threads`; `decisoes` é ARRAY
                     (`id·pergunta·respondida·resposta·custo·afeta·define·destrava`); thread exige `id·titulo·dono·arquivo·prefixo·provas`;
                     `dono` ∈ CC|CL|W|W+CL|CC->CL (sem colchetes); `provas[]` são OBJETOS `{tipo,path,padrao|chaves|testes,guarda,nota}`.
                     · variáveis (${PAGES}…) · decisões [W] · ESTADO NÃO EXISTE AQUI — é derivado (Lei 2 por construção)
00-INDICE.md         render humano: LEVANTAR por estado · abertura de thread · placar (saída do script) · revisão 3× · resíduo
NN-<view>.<secao>.md 1 thread. Cabeçalho: sessão · prefixo · NÃO toca · base sha
                     · read-order do main · A–D (§4) com ancoragem dupla · 4-ter · PROVA · PARAR SE
_saida-NN.md         escrito pela própria thread — prova IMPLÍCITA de toda thread; sem ela nada é "feito"
```
**Placar da lista = máquina, hoje:** `node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --indice <00-INDICE.md> --root . --proximo` — **no `main`; não existe cópia deste lado** (§6-bis). Ponte pro Code → `scripts/qa/placar-indice.mjs` ou `placar.mjs --indice`, PR-A8). Deriva `feito · em curso · proximo · pendente · bloqueada`, nomeia ausente por thread e arquivo, imprime `PRÓXIMO:`; `--todos 'cowork-inbox/*/playbook/playbook.json'` soma os módulos — **é o unificador**, não um doc. Testado 2026-09-05 com repo simulado: 7 casos incl. T5 (apagar 1 prova → X−1 nomeando) e contrato fora do schema (nomeia as chaves).
- **Anti-scatter:** se `cowork-inbox/<mod>/` já tem `PEDIDO-*`/`EXPORT-*` (HRM, CMS, Connector, Notificações…), o playbook **absorve** aquele pedido como threads — não nasce um terceiro doc sobre o mesmo módulo.
- **Granularidade e unificação (decisão de forma, 2026-09-05):** **1 playbook por MÓDULO** (tela/seção = thread dentro dele), nunca por tela. Nasce **só quando o módulo entra em vaga** — playbook antecipado é cache que envelhece (L-42). Tela 🔵 não ganha playbook: é 1 thread PUXAR dentro do módulo dela. **O unificador já existe e não se recria:** `cowork-inbox/ponte/00-INDEX.md` (programa S1–S10) ganha **1 linha por módulo com playbook** (`módulo · caminho do 00-INDICE · feito/pendente/bloqueada · vaga`), escrita **só pelo S0**; a máquina que soma tudo é o `placar.mjs --indice` iterando `cowork-inbox/*/playbook/00-INDICE.md` (PR-A8). Limite: módulos em execução simultânea = vagas abertas (3–4), não "todos".
- **Onde NÃO mora:** `prototipo-ui/cowork/` (guard R1). O índice é **pedido** (lista de threads a executar, com sha), não inventário — é o mesmo estatuto da `ponte/01-LISTA-COMPLETA.md`.
- **Landing (limite do canal, medido pelo [CL] 2026-09-05):** só `.md` roteia pelo DesignSync (`get_file` → `--export-from`, destino `design-docs/`); `.json`/`.mjs` soltos não chegam. Logo a fonte da máquina é o **primeiro bloco ```json do `00-INDICE.md`** (o script lê `.md` ou `.json`), e schema/script viajam como anexos do `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md`. **A unidade de descida é a pasta inteira** — índice sem as `NN-*.md` aponta pra arquivos inexistentes (o defeito que o próprio PEDIDO-CL-hrm tem com 2 planos de `memory/sessions/`).
- **Âncora de implementação de Page nova = a irmã golden viva do módulo** (no HRM: `Pages/Essentials/Metas.tsx`, #6869) e o pacote dela — tsx · charter com frontmatter · casos · `contrato/<mod>-<tela>.contract.json` · Pest · e2e · RUNBOOK · lane — não "trio". `PARAR SE` sempre inclui: gerador sair da convenção da árvore → parar, não escolher à mão.
- **Abertura de thread** = o bloco de `ponte/03` ("Sessão fresca. Leia nesta ordem, do main…") + o `NN-*.md`. Sem chat anterior — se a thread precisa perguntar algo, o arquivo está incompleto.

### Verificador — dois níveis, e o que conta como "terminou"
1. **Thread:** `_saida-NN.md` com os 5 itens (feito por caminho · não feito e por quê · pedido literal · descobertas · prefixo tocado). Sem `_saida`, a thread **não conta** — nem que o PR esteja mergeado.
2. **Lista:** `PLACAR <Mod>` lê `00-INDICE.md` e, **lendo o `main` no turno**, confere cada `prova:` — arquivo existe · teste Feature existe · `contract.json` valida no schema · `design-spec.json` derivado · `_saida` presente. Devolve `entregue X de Y · ausentes <thread> por <motivo>`; reincidência de motivo = fila de [W] (`RESÍDUO`).
3. **Máquina:** `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs` **já vive no `main`** (não há cópia aqui — §6-bis); PR-A8 o leva para `scripts/qa/` (ou `placar.mjs --indice`) — **não** script paralelo. Aceite T5 da lista: apagar uma `prova:` faz X cair para X−1 **nomeando a thread e o arquivo** (verificado).
4. **Fim** = 100% das provas verdes **e** T7 por seção. Antes disso o estado é "em curso", nunca "pronto".

### O que o comando NÃO faz (e não deve)
Não decide qual seção entra na onda (julgamento) · não mergeia `.tsx` (ADR 0283) · **não regenera o pacote pelo contexto onde há rota de máquina** (ADR 0374 **ativa**; 0389 abre só o caso `get_file` inline sem rota, sob 4 condições) — quando houver arquivo em disco, `--export-from`; sempre declarando o cabeçalho como canonicalização própria · não afirma paridade sem T7 · não grava mapa/inventário fora do índice-pedido (L-42).

---


---

## 13 · ORÇAMENTO DE SESSÃO — medir a capacidade ANTES de gerar (pedido [W] 2026-09-08)

> **O que quebrou, medido:** o `COLAR-NO-CODE-compras-ondas.md` (04/09) pedia 8 arquivos. Em 08/09 a árvore mostrou que **6 já existiam** — os 4 `casos.md` do Purchase (19–28 KB cada) e os 2 `contract.json`. E a âncora que ele dava era **"leia `Compras/Index.tsx`"** = 28.813 B antes de escrever a primeira linha. Dois defeitos distintos: **frescor** (retrato velho) e **granularidade da âncora** (arquivo, não recorte).
>
> Este bloco não substitui §2-bis (granularidade da onda) nem §4-ter (instrução de execução): acrescenta o **gate que roda antes dos dois** e o **formato de âncora que cabe numa sessão**.

### 13.1 · A unidade do handoff não é o módulo nem o arquivo

| unidade | por que falha | veredito |
|---|---|---|
| módulo | 5–20 telas, 100–500 KB de leitura; o Code esgota contexto antes de escrever | ❌ |
| tela | ainda 15–30 KB por `.tsx` + charter + casos | ❌ para tela grande |
| **par (seção do alvo × símbolo do arquivo real)** | leitura recortada, escrita ≤300 linhas, 1 prefixo, 1 PR | ✅ **é esta** |

**A ancoragem dupla passa a ser por símbolo:** alvo = seletor raiz da seção no protótipo; âncora = **`arquivo :: símbolo` + faixa de linhas + sha do arquivo naquele momento**. "Leia `Index.tsx`" é pedido malformado; "leia `Index.tsx` :: `ColunasVisiveis` (:180–:243, sha `4571190…`)" é pedido executável.

### 13.2 · Ficha de capacidade — 6 números por thread candidata, e um veredito

Medir **antes** de escrever o `NN-*.md`. Nenhum número sai de cabeça: bytes vêm de leitura de árvore, faixas de busca dirigida, nós do `MAPA`.

```
FICHA <Mod>.<view>.<seção>
  1 alvo_nos          nós do seletor raiz no protótipo (do MAPA)          teto  ~400
  2 leitura_bytes     soma dos RECORTES obrigatórios (não dos arquivos)   teto  40.000 B
  3 escrita_linhas    linhas a escrever/alterar (estimativa declarada)    teto  300
  4 prefixo_arquivos  arquivos onde a thread escreve (Lei 1)              teto  8
  5 simbolos          símbolos distintos que ela toca                     teto  3
  6 decisoes_abertas  itens do RESÍDUO que ela precisa respondidos        teto  0
VEREDITO
  0 tetos furados ......... CABE      → emite NN-*.md
  1 teto furado ........... DIVIDE    → §13.3, e mede as filhas
  ≥2 tetos furados ........ RECUSA    → pedido malformado; volta pro MAPA
  decisoes_abertas > 0 .... BLOQUEADA → thread existe, com `bloqueio:` e prefixo vazio
```

**Correção de teto nº 1 — 2026-09-08, com o caso registrado** (a regra abaixo manda corrigir o teto, não abrir exceção). O teto do índice passa a ser **8 KB de PROSA**; o bloco `json` da fonte da máquina **não conta**, porque não é carga de leitura humana — é o que o `placar-indice.mjs` parseia. Medição que motivou:

| índice | total | prosa | json | prosa × teto |
|---|---:|---:|---:|---|
| Patrimônio (emitido hoje pelo §13) | 9.317 | **5.505** | 3.812 | ✅ 2.687 B de folga |
| Compras | 13.535 | 9.545 | 3.990 | ❌ fura 1,2× |
| Ponto | 23.700 | 16.614 | 7.086 | ❌ fura 2,0× |
| HRM | 24.767 | 18.038 | 6.729 | ❌ fura 2,2× |

**Dívida declarada:** 3 dos 4 índices furam o teto **de prosa**, e nenhum deles foi gerado pelo §13 (são anteriores). Não se corrigem por reescrita cosmética: o que sobra neles é narrativa de método (revisão 3×, "o que mudou desde", histórico) que pertence ao dossiê. Enquanto não forem enxugados, **o teto vale para índice novo** e os três antigos ficam como dívida nomeada — não como exceção silenciosa.

**Os tetos são calibração declarada, não lei da natureza.** Vêm de: 300 linhas e 8 arquivos já eram o DoD de PR (§6); 40 KB é onde as threads do HRM ainda executaram sem pedir contexto de volta; 3 símbolos é onde o `PARAR SE` ainda cabe colado ao passo. Quando um teto reprovar uma thread que teria dado certo, **corrige-se o teto com o caso registrado** — não se abre exceção silenciosa.

### 13.3 · Fatiar — a ordem é fixa (nunca agrupa)

`módulo → view → seção → símbolo → (cabeçalho | corpo | rodapé | estado vazio)`

Desce **um nível por vez** e remede a ficha em cada filha. Duas proibições: não fundir duas seções que couberam só porque "são pequenas" (a sessão limpa perde o teste do estranho); não fatiar por **tipo de arquivo** (tela numa thread, teste noutra) — isso separa o trabalho da sua própria prova.

### 13.4 · Recorte de âncora — o formato que cabe

```
ÂNCORA (congelada no momento da geração)
  arquivo   resources/js/Pages/Compras/components/Drawer.tsx     19.739 B  sha c3f2da501f16
  símbolo   ItensTabela                                          :112–:186  (75 linhas)
  ler       SÓ a faixa acima + a seção "Itens" do charter
  NÃO ler   Index.tsx (28.813 B) · Index.casos.md (26.468 B) — oráculo, não leitura
  frescor   sha mudou ⇒ a thread REMEDE antes de escrever, e diz isso no _saida
```

Três regras que isso impõe:
1. **`NÃO ler` é obrigatório.** Sem ele, o Code lê o módulo inteiro "pra ter certeza" — é o que esgota a sessão.
2. **Oráculo ≠ leitura.** `casos.md` de 26 KB e os 10 Pest entram como *onde conferir uma dúvida*, nunca como leitura de abertura.
3. **Sha congelado é o antídoto do 04/09.** Âncora sem sha é retrato; com sha, a thread detecta sozinha que envelheceu.

### 13.5 · Sequência incremental — pré e pós-condição por passo

Cada thread declara o estado **antes** e **depois** em predicado verificável. O `depois` de N é literalmente o `antes` de N+1 — é isso que torna a cadeia retomável sem reler a conversa.

```
NN  antes:  e2e/compras-cockpit.spec.ts AUSENTE · contrato compras-cockpit PRESENTE (guarda)
    depois: e2e/compras-cockpit.spec.ts PRESENTE e verde 3× · contrato INTACTO
    quebra: se o "antes" já não vale (o arquivo apareceu), a thread NÃO executa — reporta e para
```

**Prova de preservação (`guarda: true` no schema) é obrigatória em toda thread que passa perto de peça viva.** Foi o que impediu, no Compras, um PR de rede desplugar a grade: `Purchase/Create.tsx` **contém** `GradeMatrixInput` antes e depois.

### 13.6 · Escrever para não esquecer — 5 regras de redação

1. **A invariante fica colada ao passo, não no §0.** `PARAR SE` no fim do arquivo é lido depois do erro. Repetir a regra crítica dentro do passo é redundância **deliberada**.
2. **Toda instrução nomeia caminho completo.** "o drawer" → `resources/js/Pages/Compras/components/Drawer.tsx`. Nome curto exige memória que a sessão limpa não tem.
3. **`REUSAR` antes de `CRIAR`, sempre com o caminho do que reusar.** Lista de reuso vazia é o sinal mais forte de que ninguém leu o repo.
4. **Checklist de saída numerada dentro do próprio `NN-*.md`** — o `_saida` marca item por item. Prosa não fecha thread.
5. **Teste do estranho, literal:** dar o arquivo a quem não viu a conversa. Se ele perguntar qualquer coisa, o arquivo está incompleto — e a pergunta vira **linha nova no arquivo**, não resposta no chat.

### 13.7 · O fluxo inteiro, na ordem (7 passos)

```
0 RELER      árvore do main NO TURNO. Pedido com retrato >24h é INVÁLIDO — reemitir, não "atualizar".
             (04/09 → 08/09 no Compras: 6 de 8 pedidos morreram nesse intervalo.)
1 MAPA       denominador do alvo: seções colhidas do DOM, nunca de lembrança (§2, receita).
2 FICHA      §13.2 nas candidatas → CABE | DIVIDE | RECUSA | BLOQUEADA. NADA se escreve antes disto.
3 FATIAR     §13.3 nas que deram DIVIDE; remedir cada filha até CABE.
4 RECORTE    §13.4 por thread: arquivo :: símbolo :: faixa :: sha, com o NÃO ler explícito.
5 EMITIR     00-INDICE.md (JSON embutido, §12) + um NN-*.md por thread CABE, com pré/pós (§13.5)
             e as 5 regras de redação (§13.6). Thread BLOQUEADA entra com `bloqueio:` e prefixo vazio.
6 VERIFICAR  _saida-NN.md + placar-indice.mjs. Estado é DERIVADO — ninguém o escreve.
```

**O passo 2 é o único novo, e é o que muda o resultado:** hoje o pedido nasce e só na execução se descobre que não cabia. Com a ficha, quem não cabe **nunca é emitido** — é fatiado ou devolvido ao `MAPA`.

### 13.8 · Aplicado ao Compras (ficha real, 08/09, árvore `9101f86af501`)

| thread | alvo_nos | leitura_bytes | escrita_linhas | prefixo | símbolos | dec. abertas | veredito |
|---|---:|---:|---:|---:|---:|---:|---|
| 01 rede E2E | — | ~8.200 (2 contratos) | ~180 | 2 | 0 | 0 | **CABE** |
| 02 Margem (build daqui) | ~30 (`items-tbl`) | ~2.000 (faixa do `Drawer.tsx`) | ~8 | 2 | 1 | 0 | **CABE** |
| 03 Fornecedores | ~120 | — | — | 0 | — | **1** (D-FORN) | BLOQUEADA |
| 04 ghost `/compras/create` | — | — | 1 ou ~400 | 1 ou 6 | — | **1** (D-GHOST) | BLOQUEADA |
| 05 smoke da grade | — | — | 0 | 0 | — | **1** (D-GRADE) | BLOQUEADA |

E a ficha do pedido **que o doc de 04/09 emitia**, reprovada retroativamente: `leitura_bytes` ≈ 97 KB (Index.tsx + charter + casos + os 4 charters do Purchase) para escrever 4 `casos.md`; `prefixo` = 4; `decisoes_abertas` = 2. **RECUSA por 2 tetos** — e, pior, 6 dos 8 arquivos já existiam. O passo 0 sozinho teria matado o pedido.

### 13.9 · O que este bloco NÃO resolve

- **A ficha não julga valor.** Diz se cabe, não se vale — qual seção entra na onda continua julgamento (§12, "o que o comando NÃO faz").
- **Faixa de linhas envelhece mais rápido que arquivo.** O sha protege; a faixa, não. Se o símbolo se moveu, a thread remede — por isso o recorte cita **símbolo E faixa**, nunca faixa sozinha.
- **Tetos não calibrados em módulo grande de verdade.** Vieram de HRM (11 threads) e Compras (5). Vendas/PDV e Forja vão furar algum — o caso furado se registra e o teto se corrige.
- **Nada aqui afirma paridade.** T7 (`design-diff --compare --check` nos dois renders, prod deployada) continua o único que afirma.

---

## 14 · MAPA DE DESTINOS — onde cada arquivo do Cowork mora no `main`

> **Por que isto é arquivo, e o recibo de sha não é.** O destino é **estável**: `CONSTITUICAO-COWORK.md` vai pra raiz do `prototipo-ui/` hoje, amanhã e no mês que vem. O **sha muda a cada edição** — gravá-lo aqui seria L-42 com nome novo (cache que envelhece). Então: **destino no arquivo, sha no chat**, gerado na hora com `MAPA`.

| origem (projeto Cowork) | destino no `main` | tipo | desce quando |
|---|---|---|---|
| `oimpresso.com.html` · `*-page.jsx` · `*.css` | `prototipo-ui/cowork/Wagner/` | **build** | a cada ciclo de UI · **pelo pacote**, nunca por `.md` |
| `_ds/**` (cache do DS) | `prototipo-ui/design-system/` | **espelho do DS** | roteado pelo `destinoDoBundle` do applier — não se cola à mão |
| `CONSTITUICAO-COWORK.md` | `prototipo-ui/cowork/Wagner/cowork-inbox/CONSTITUICAO-COWORK.md` | **lei** | **1×**, primeiro de todos — depois só emenda |
| `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` | `…/cowork-inbox/PROTOCOLO-COWORK-EXPORT.md` | **norma** | quando o método muda |
| `DOSSIE-PROTOCOLO-COWORK.md` | `…/cowork-inbox/DOSSIE-PROTOCOLO-COWORK.md` | **evidência** | junto com a norma |
| `COLAR-NO-CODE-<mod>-*.md` (ponteiro) | `…/cowork-inbox/<mod>/` | **ponte** | junto com o playbook do módulo |
| `COLAR-NO-CODE-ACERTOS-E-LICOES.md` | `…/cowork-inbox/ACERTOS-E-LICOES.md` | **ponte (acumulativa)** | **a cada ciclo** — bloco novo em cima; lições descem como PROPOSTA pra `memory/LICOES_CC.md` |
| `cowork-inbox/<mod>/playbook/**` | `prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/` | **pedido** | **pasta inteira**; pasta que já existe no `main` recebe **`_PATCH-*.md`**, nunca índice sobrescrito |
| `*.contract.json` **promovido ao CI** | `governance/design/contracts/` | **contrato (vigente)** | quando vira advisory/required — **38 arquivos hoje** (36 `*.contract.json` + `EXEMPLO` + `contract.schema.json`) |
| `<tela>-gap.md` · `<tela>.map.json` | `memory/requisitos/<Mod>/` | **canon (NÃO exportar)** | desce como **proposta dentro do playbook** — tem dono fora do `cowork/` |
| `sync/bundle.manifest.json` + `payload.part*.json` | `sync/` | **pacote** | ao fechar ciclo (ADR 0387) — ⚠️ **hoje ausente do `main`, e `sync/` não está no `.gitignore`** |
| zip do projeto inteiro | `/oimpresso-erp-conunica-o-visual/` (ignorado) | **transporte** | insumo do gerador; **não é destino de nada** (§17) |

**⚠️ CORREÇÃO DE DESTINO — 2026-09-14, medida nas árvores `73182439581f`/`420b061817e0`.** Este bloco mandava tudo para **`prototipo-ui/design-docs/`** e, antes disso, para a **raiz de `prototipo-ui/`**. **As duas rotas estão mortas:**
- o **R1** do `cowork-ssot-guard.mjs` (lido inteiro) só aceita **`cowork/`** e **`design-system/`** na raiz de `prototipo-ui/` — qualquer outro item na raiz **falha vermelho**;
- **`prototipo-ui/design-docs/` não existe mais** na árvore: o conteúdo foi dobrado para dentro de `cowork/Wagner/cowork-inbox/`, que é onde hoje moram os 48+ `.md` e os playbooks;
- o **R3** (emenda [W] 2026-09-13) **permite `.md`** — desde que **dentro de um dono** (`cowork/{Wagner,Felipe}/**`). A invariante antiga "zero `.md` em `cowork/`" **foi revogada** e estava sendo usada por mim para recusar ordem de serviço.

**E a pasta de contrato é UMA, não duas.** O `prototipo-ui/contrato/` "vigente" e o `design-docs/contrato-cowork/` "estágio" que este bloco descrevia **não existem na árvore** — o que existe é **`governance/design/contracts/`** (ADR 0286). Os `prototipo-ui/contrato/` que eu jurava ter visto aparecem **só dentro de pacotes espelhados** (`cowork-inbox/{acessos,modulos}/repo/prototipo-ui/contrato/`) — provável origem da falsa memória. Registrado como retratação dupla no `ACERTOS-E-LICOES`.

**Quatro invariantes de destino** (violar qualquer uma reprova no CI):
1. **Raiz de `prototipo-ui/` = só `cowork/` + `design-system/`** (R1). Nada de `design-docs/`, `contrato/`, `_incoming/` ou `.md` solto.
2. **`.md` vive dentro de um dono** (R3): `cowork/Wagner/**`. Doc que acompanha o build vai pro `cowork-inbox/`, nunca ao lado do `.jsx` como arquivo solto na raiz do espelho.
3. **Zero bytes duplicados em `prototipo-ui/`** (R4) — o guard hasheia tudo, **inclusive cache ignorado**. Foi o que pegou 10 pares idênticos entre `cowork-inbox/sidebar/playbook/` e uma pasta de entrega paralela.
4. **Playbook desce em pasta**, e pasta que já existe no `main` **recebe patch**: o `00-INDICE.md` de lá pode estar à frente (medido: Ponto 24.929 B no `main` × 24.411 na cópia local).

**O que este mapa NÃO resolve:** ele diz *onde*, não *se já está lá*. Arquivo que eu emiti e você não colou continua listado aqui e ausente no `main` — a paridade espelho×git é do `cowork-mirror-freshness.mjs` (`--absent-local` e `--check-orfaos`), não deste bloco.

---

## 15 · ACERTOS — o acerto catalogado (pedido [W] 2026-09-09) · vale para TODO módulo

> **A assimetria que isto conserta.** O sistema catalogava **erro** (`memory/LICOES_CC.md`), **ausência** (placar) e **proibição** (`memory/proibicoes.md`). Nada catalogava **o que a produção já acertou** — e é por isso que o mesmo diagnóstico errado nasceu duas vezes: Fiscal 2026-09-03 ("PR-A1 pendente", já entregue via `_lib/botao-fiscal.ts`) e Ponto 2026-09-09 ("não usa o DS", usa em 21 Pages). Placar diz o que falta; **ninguém dizia o que já está certo, e por isso se refazia**.
> **Palavras de [W]:** *"deveria ir acrescentando e informando pro Code o que ele acertou do que você já escreveu, e as novas memórias — isso mantém o Code para não errar novamente."*

**Arquivo único, cross-módulo, append-only:** `COLAR-NO-CODE-ACERTOS-E-LICOES.md` → destino `prototipo-ui/design-docs/` (§14). **Um bloco por ciclo, mais novo em cima; bloco antigo nunca se reescreve** — o erro registrado é o valor. Não é por módulo: o acerto do Fiscal é o que evita o erro do Ponto.

### 15.1 · Quando é obrigatório
**Todo ciclo que leu o `main`** — mesmo o que não emite pedido. Sem bloco, o ciclo não fechou (§4 bloco D item 9). Se nada foi medido, escreve-se **"nenhum acerto medido neste ciclo"**: ausência declarada é dado; silêncio é omissão grátis.

### 15.2 · Forma do bloco (3 partes, nenhuma opcional)
| parte | o que entra | o que **reprova** |
|---|---|---|
| ✅ **acerto** | tabela `A1..An`: o que está certo no `main` + **caminho + sha** + **consequência prática** ("não refazer X", "não re-perguntar Y") | acerto sem sha/caminho — é elogio, não evidência. Acerto que eu não medi **neste turno** |
| ❌ **erro** | o que eu afirmei e era falso + **a regra colada** (o que muda no método, em imperativo verificável) | erro sem regra = desabafo. Erro de gosto ("ficou feio") não entra: só o que uma regra evita |
| 🔁 **reincidência** | quando o erro é o **mesmo** de um ciclo anterior: citar o ciclo e o que a repetição prova sobre o método | inventar reincidência sem o ciclo anterior nomeado |

**Numeração das lições é do [CL] no merge.** Eu emito `L-??` como **proposta** para `memory/LICOES_CC.md` — nunca invento número, nunca commito direto (o `01-LISTA-COMPLETA.md` 7.13 já dizia "proposta no PR").

### 15.3 · As duas regras que nasceram aqui e valem para tudo
1. **Controle positivo antes de afirmar ausência.** "Zero resultado" **não** é evidência de que não existe, até rodar uma busca que **tem** de casar. Causa-raiz medida em 09/09: procurei `from "@/Components/…"` com aspas **duplas**; o repo usa **simples** → "No matches" virou o fato "o Ponto não usa o DS", errado em 21 arquivos. A regra do §5-bis ("toda sonda nova roda um caso de sanidade de valor conhecido antes de qualquer veredito") **passa a valer para busca de código**, não só para sonda de DOM. Ordem: (1) controle positivo · (2) ler 1 arquivo real do módulo · (3) só então afirmar. **Custo: 3 chamadas.**
2. **O espelho não é evidência sobre o `main`.** `_ds/…/_ds_bundle.js` é componente **compilado** do espelho. Toda frase "o DS não tem X" exige o `.tsx` real lido no turno — senão a frase honesta é **"o bundle do espelho não tem X"**, que é outra afirmação, com outro dono. (Em 09/09 afirmei lacuna de passthrough no `Input`; `ui/input.tsx` faz `{...props}`.)

### 15.4 · Onde é lido
Entra no read-order do §2-quater **junto** com `LICOES_CC.md`: o pre-flight injeta **erro** catalogado, este injeta **acerto** catalogado. Consequência direta na hora de escrever o pedido — o bloco `B · NÃO INVENTAR` passa a ter um irmão: **NÃO REFAZER**, com a lista de acertos que já cobrem aquele eixo.

### 15.5 · O que isto NÃO é
Não é changelog (isso é `github.md`) · não é elogio ao [CL] (acerto sem sha não entra) · não é memória (memória é `memory/**`, no git; aqui é **ponte**, e as lições descem como proposta) · não é máquina nova: **zero script, zero gate de CI** — é um arquivo que se acrescenta. Se algum dia precisar de máquina, ela deriva daqui, não o contrário.

---

## 16 · ADVERSÁRIO — o papel que produz as descobertas (pedido [W] 2026-09-09)

> **A evidência que obriga isto.** Neste ciclo, **nenhum** achado veio de auto-revisão. Vieram todos de alguém atacando a afirmação: o Code **recusando** o handoff (3) e o bundle v2 · a leitura do `main` **derrubando o meu próprio** `00-INDICE` (schema lembrado ≠ schema real) · o `patrimonio` **reprovando** e com isso provando que o schema velho era o do ZIP · o caso de sanidade **13,62** validando a sonda de contraste. Revisão que concorda é ruído; o que mede é a tentativa de quebrar.

**Não nasce papel novo:** o **[CD]** (crítica F1.5) já existe nas personas. O que muda é que ele deixa de "revisar" e passa a ter **arma, alvo e veto**.

### 16.1 · A passada adversarial — 3 ataques, arma fixa
Não é bloco novo no pacote (§4-quater segue com 10). É uma **passada sobre os 10**, atacando as únicas três coisas que um pacote afirma:

| ataque | pergunta | arma obrigatória | se não rodar |
|---|---|---|---|
| **A · à medida** | esse número é reprodutível? | remedir **depois** da limpeza, largura declarada, **caso de sanidade de valor conhecido** antes do veredito (§5-bis) · T5: sabotar o insumo tem de **derrubar** o número | número vira "provisório", não alvo |
| **B · à ausência** | isso realmente não existe? | **controle positivo** na busca (um termo que TEM de aparecer) + abrir **1 arquivo real** do módulo | a frase troca para "não encontrei", que é outra afirmação |
| **C · à proveniência** | isso é fato sobre o `main`? | o arquivo real do `main` **no turno** — espelho `_ds/`, bundle compilado e cópia local **não valem** | a frase troca para "o espelho tem X" |

### 16.2 · O que o adversário escreve (5 linhas, ou não conta)
Caro demais é pulado; por isso a forma é curta e fixa:
```
ATAQUE A · <número atacado> · <arma rodada> · <sobreviveu | caiu: valor certo>
ATAQUE B · <ausência atacada> · <controle positivo usado> · <sobreviveu | caiu>
ATAQUE C · <fato sobre o main> · <arquivo + sha lidos no turno> · <sobreviveu | caiu>
NÍVEL    · a frase mais forte do pacote é E<n> e o comando que a sustenta é <…>   (contrato de evidência)
VEREDITO · aceito | recusado por <claim nomeada>   ("está bom" NÃO é veredito)
```
**Quem não quebrou nada declara o que tentou.** Adversário que só diz "ok" é carimbo — e carimbo é pior que ausência, porque produz confiança sem lastro.

### 16.3 · As três regras que impedem o teatro
1. **O adversário não pode compartilhar a hipótese do autor.** "Produção está atrás do protótipo" falhou **2 de 2** vezes em que foi testada (Fiscal 03/09 · Ponto 09/09) — quem ataca começa da hipótese oposta: *a produção está à frente e o meu alvo é que está errado*.
2. **Auto-adversário é obrigatório, e vem antes.** Todo número que eu emito viaja com **o que o falsificaria**. Número sem falsificador não é medida, é lembrança — e §5-bis já mandou corrigir o alvo aqui quando ele falha (exportar 3,18 com selo é o anti-exemplo).
3. **O veto nomeia a claim.** Recusa sem claim nomeada é gosto; com claim nomeada é medição — e vira linha no `COLAR-NO-CODE-ACERTOS-E-LICOES.md` (§15), do lado ❌ se caiu, do lado ✅ se resistiu.

**Onde já roda por máquina (não duplicar):** `--selftest` com BITE+controle em cada sonda (`ds-anchor-check.mjs`, 15 casos) · T5 do placar (apagar prova derruba X→X−1 nomeando a thread) · o gate de recusa do lote (§6-bis). O adversário humano ataca o que **nenhuma dessas** cobre: a hipótese, a proveniência e a palavra escolhida.

**O que isto NÃO faz:** não substitui [CA] (a11y F3.5) nem o T7 · não autoriza recusar por estilo · e não me deixa dizer "revisado" — só "atacado por A/B/C, sobreviveu ao que rodei".

---

## 17 · TRANSPORTE E RECEPÇÃO — o que o ciclo de 2026-09-14 ensinou (zip, manifesto, poda)

> **Origem:** [W] mandou o projeto Cowork inteiro **zipado** pro Code e pediu que o ciclo fosse conferido etapa por etapa. Li no `main` as máquinas donas: `aplicar-payload.mjs` (24.437 B) · `gerar-payload-partes.mjs` (13.082 B) · `cowork-ssot-guard.mjs` (5.403 B) · `design-memory-gate.yml` (43.262 B) · `.gitignore` (4.660 B), **todas inteiras**. Nenhum `node` rodado.

**R1 · O zip é ponte de BYTES, não de PROVA.** Ele destrava o que a ADR 0374 exige (o gerador só roda de onde os arquivos estão em disco) — foi assim que o pacote de 14/09 (278 arquivos) nasceu do lado do Code. O que ele **não** entrega: manifesto, delta, `changes.deleted`, fechamento de grafo. Zip sem manifesto = espelho igual, prova diferente.

**R2 · Pasta de pouso fica FORA de `prototipo-ui/`, e já existe:** `/oimpresso-erp-conunica-o-visual/` (declarada no `.gitignore` como insumo volátil de transporte, ADR 0374). **Motivo mecânico:** o R4 do guard hasheia `walk('prototipo-ui')` **inclusive caches ignorados** ⇒ qualquer staging ali é dupe byte-idêntica = vermelho no lote inteiro. ⚠️ **Contradição a resolver com [W]:** o `.gitignore` também reserva `prototipo-ui/_incoming/` para unzip, e o **R1 lê o disco** — essa pasta existindo já é violação. Uma das duas leis cede.

**R3 · O applier confere BYTES, não digest.** `bytes` declarado ≠ real ⇒ recusa o **lote inteiro**; `bytes` ausente = **NÃO MEDIDO** e reprova em `--require-complete-shell`; sequência de partes 1..N é contrato; `missing` do gerador bloqueia; grafo transitivo tem de fechar; bytes duplicados entre arquivos do lote são recusados. **O digest (FNV-64) é REPORTADO, nunca veredito** — divergiu **0/118 em duas medições** (17/08 e 22/08) com controle positivo de 5/5 vetores publicados: contradição em aberto, registrada no próprio código. **Nunca escrever "confere por sha256" num pedido.**

**R4 · Apply não apaga — poda é decisão [W].** Literal no script: *"arquivos do espelho fora deste lote seguem lá (relato, não poda)"*. O eixo existe no manifesto (`changes.deleted`) e **nenhuma máquina o executa**. Logo "o espelho é fiel = nem a mais, nem a menos" tem a metade "nem a mais" **sem dono**. Pedido que promete poda automática é pedido falso.

**R5 · `cowork-inbox/` nunca pousa por transporte — só por patch.** É a única pasta **bidirecional** (erratas do [CL] e `_saida-NN.md` nascem no `main`; medido 09/09: `00-INDICE` do Ponto 24.411 B no Cowork × 24.929 no `main`, com o `main` à frente). E desde 13/09 o `BUILD_SOURCE_RE` do applier aceita `.md` ⇒ o risco é **vivo**, não teórico. A guarda de regressão do applier **não cobre**: gatilho é perda líquida **> 20 linhas** e é relato — 518 B passam calados.

**R6 · O manifesto tem de estar versionado, e não está.** `^sync/` → **0 de 16.918** arquivos, e `sync/` **não** aparece no `.gitignore` (hipótese minha derrubada por leitura). A entrada do `/oimpresso-erp-conunica-o-visual/` declara que *"o que o repo guarda é o ESPELHO e o **manifesto do bundle**"* — então ele deveria estar lá por decisão escrita. Sem manifesto no git, o recibo da ADR 0387 não tem lastro e nenhum dos dois lados audita completude.

**R7 · Documentação canon mudou de endereço — e saiu do gatilho.** Os 5 process-docs vivem em `memory/reference/prototipo-ui/`; máquinas em `scripts/design/`; contratos em `governance/design/contracts/`; testes em `tests/Design/` (é o cabeçalho do guard que declara isso). O `paths:` do `design-memory-gate.yml` **não** lista `memory/reference/prototipo-ui/**` ⇒ 3º caso da família #7224 (`ds-guard` e `integrity-check` também saíram do gatilho e ninguém repôs a linha).

---

## 18 · APLICAR POR **PAPEL**, NUNCA POR CLASSE (decisão [W] 2026-09-14)

> **A frase do [W]:** *"o Code deve fazer as aplicações no código por papel e não por classe, pois as classes do Code são diferentes."* Está certa, e o repo já tem a máquina que prova.

### 18.1 · Por que classe não viaja
O nome de classe do protótipo é **nomenclatura local do meu build**: `.pt-tbl`, `.pt-toolbar`, `.sb-body`, `.fx-*`, `.upf-*`. A produção não tem esses nomes — ela tem **Tailwind 4 + `@/Components/ui/*` + `@/Components/shared/*` + tokens**. Pedido escrito em classe deixa duas saídas para o Code, e **as duas são ruins**:
1. **recriar a classe** no app ⇒ é literalmente a **pele paralela** que o CI vigia (`scripts/qa/cowork-pele-paralela.mjs`, hard step no gate: *"a tela reimplementa com nome próprio o que já tem dono"*);
2. **traduzir no olho** ⇒ é o `center×left` que já falhou 2× (LC-06, defesa do `design-diff --selftest`).

**E a colisão não é hipótese:** em 2026-09-11 o prefixo `.pf-` do Perfil colidiu com `acessos-page.css` (`.pf-body{display:grid;230px 1fr}`) e pôs **todo card do Perfil em 190px nas 4 abas**. Classe é endereço local; fora do prédio, não entrega.

### 18.2 · Papel = o que a peça FAZ, e quem é o dono canônico dela
A tradução correta é **papel → dono no `main`**, e o protótipo entra só como **alvo de forma**. Os 3 casos medidos em 09/09 mostram por que o nome não serve de chave:

| papel | como eu chamo aqui | dono real no `main` | nome bate? |
|---|---|---|---|
| moldura de painel com título | `Widget` | `resources/js/Components/ui/card.tsx` | ❌ |
| barra de ferramentas de 3 zonas | `Toolbar` | `Components/shared/PageFilters.tsx` (é **outra peça**) | ❌ |
| KPI clicável que filtra | `KpiCard variant="filter"` | `Components/shared/KpiCard.tsx` (dicionário próprio, não `tone` semântico) | ⚠️ parcial |
| abas de módulo com contador | `TabBar` | `Components/shared/PageHeaderTabs.tsx` (5 + ⋯ Mais) | ❌ |

**Três de quatro não casam pelo nome.** Quem pede por classe/nome erra em 3; quem pede por papel acerta nos 4, porque o papel é o que o `HANDOFF.md §3` já mapeia (reusar / estender / criar).

### 18.3 · Não é invenção minha — já é máquina no CI
- **`prototipo-ui/component-registry.json`** = registro **bloco-de-protótipo → componente React**. É exatamente o dicionário de papéis.
- **`component-registry-check.mjs --roles`** roda no gate: *"papel-duplicado … surface os hand-rolls independentes de papel canonizado (barra de abas de topo)"*.
- **`cowork-pele-paralela.mjs`** (hard, com selftest antes) morde quando a tela reimplementa um papel que já tem dono.
Ou seja: **o repo já pensa em papel.** Pedido escrito em classe é pedido que fala uma língua que as três máquinas não entendem.

### 18.4 · A prova de valor (não é preferência, é contagem)
O ciclo do Ponto de 09/09 fechou assim: o pedido *"Ponto usa DS"* **não tinha objeto** (produção já compunha o DS em 21 telas). A diferença real era **anatomia de átomo** — papel, não escolha de componente. Reescrito por papel, virou **3 threads no primitivo** (`card.tsx`, `KpiCard.tsx`, `Toolbar` a criar) em vez de **21 PRs por tela**. Mesma mudança visual, 1/7 do custo, e o ganho vale para todo módulo que consome o átomo.

### 18.5 · A forma obrigatória da linha de pedido
```
PAPEL     · <o que a peça faz: "KPI clicável que filtra a lista">
DONO      · <arquivo real do main :: símbolo :: sha lido no turno>     (reusar | estender | criar)
ALVO      · <medida do protótipo: px/cor/peso resolvidos por getComputedStyle>
CLASSE    · <só como REFERÊNCIA de onde eu medi — NUNCA como instrução>
GUARDA    · <quem mais consome esse átomo> + <o que prova que não regrediu>
```
**Regras duras:** classe do protótipo só aparece na linha `CLASSE`, e sempre rotulada como referência · **âncora por símbolo**, não por arquivo (o padrão bom é `Purchase/{Index,Show} → compras-page.jsx (ComprasPage)/(DrawerView)`; o ruim é `ponto-telas.jsx` respondendo por 17 telas) · **átomo consumido fora do módulo exige prova de guarda** (`Card`/`KpiCard` são usados em Backup, Financeiro/Advisor, Financeiro/Unificado — varredura parcial, logo **piso, não teto**) · **aditivo ou nada** no primitivo.

**Anti-padrões que reprovam o pedido:** *"aplique a classe `.pt-toolbar`"* · *"copie o CSS do protótipo"* · *"deixe igual ao arquivo X"* sem nomear o papel · pedir componente novo sem antes ler `component-registry.json` + `Components/ui/` + `shared/` (§Não reinventar o decidido).

---

## 19 · O QUE O CODE JÁ TEM E EU TENHO DE OBEDECER (adaptação obrigatória · 2026-09-14)

> **Fonte:** `.github/workflows/design-memory-gate.yml` (43.262 B), `aplicar-payload.mjs` (24.437 B), `gerar-payload-partes.mjs` (13.082 B), `cowork-ssot-guard.mjs` (5.403 B), `.gitignore` (4.660 B) — **todos lidos inteiros neste turno**. Nenhum `node` rodado: o que segue é o que as máquinas **declaram fazer**, com as medições que os próprios arquivos registram.

### 19.1 · Seis máquinas que mudam o meu trabalho

| # | Máquina | O que ela faz | **Minha adaptação** |
|---|---|---|---|
| 1 | `gerar-map.mjs` · `consumir-map.mjs` · `design-code-map-check.mjs` | `<tela>.map.json`: ponte design↔código **por RANGE**, STALE por `sha256:contentHash` (pega re-export sem commit), e o `consumir-map` devolve **plano de leitura — a sessão abre só os ranges** | **Parar de pedir contrato por tela e pedir `map.json`.** É a ancoragem por símbolo que a decisão `D-SIMBOLO` estava inventando — **já existe**. Resolve o "1 arquivo para 17 telas" que o charter não resolve |
| 2 | `render-proto-baseline.mjs` | o render do protótipo é **baseline versionado** (`<tela>.proto-baseline.json`); frescor por `prototipo_sha`; `--gerar` recusa sob CI (ADR 0290) | **Re-export invalida baseline sozinho.** Re-exportar **agrupado por tela** e **declarar** a invalidação no pedido. Hoje eu nem menciono |
| 3 | `detect-handoff.mjs --base origin/main` | export do Cowork → chip por tela; report-only. Mordida que o ligou: um export mudou **2 arquivos** e **nenhum** charter tinha `visual_source:` apontando pra eles | Arquivo de build novo **nasce com campo de âncora**, senão o repo o classifica **handoff não-roteável** — que é exatamente o **F1 do Ponto** (5 dos 7 arquivos) |
| 4 | `ds-guard.mjs --all` / por arquivo tocado (§8) | julga `.css`/`.html`: **≥4 tokens de cor bespoke = "paleta inventada"** · limiar 3×4 · **L-21 tela na raiz** · exceção do host · ilegível | Token de cor novo no meu CSS é **dívida medida**, não estilo. `ponto-page.css` e irmãos entram no julgamento quando tocados |
| 5 | `style-fingerprint.mjs` + `fingerprint-harness.mjs` + `alvo.mjs` | vetor de estilo (**bbox, nº de linhas, overflow, cor computada, radius, borda**) **por tema**; matriz **viewport × tema × estado** (hover/focus/active) + mobile 375; `alvo.mjs` exige **2 leituras byte-idênticas** + `--injetar-falha` derrubando o número | **O formato do ALVO já está definido — e eu emito prosa com px.** Medir nessa forma, ou não pareia com o lado dele |
| 6 | `design-gate-bites.jsonl` (DR-2a) | ledger append-only de **mordidas**; **≥2 PRs distintos mordidos ⇒ promoção a required** | O que eu trato como "advisory, dá pra ignorar" (dívida do ds-guard, âncora órfã, `--check-orfaos`) **tem rota de promoção**. Fechar antes de virar bloqueio |

**Duas menores, que mudam contagem e prova:** `page-path.mjs` reconhece **duas raízes de Pages** (`resources/js/Pages/**` e `Modules/**/Resources/js/Pages/**`, nas duas grafias) — denominador que soma uma só repete o "módulo ≠ pasta" (**73 telas + 37 charters** já ficaram fora de um gatilho por isso) · `recibos-ci.mjs` **recusa run com Pest `skipped`** (skip-as-pass não prova, LC-13) — recibo de thread cita run com teste que **rodou**.

### 19.2 · O que JÁ DEU ERRADO (catálogo medido — cada linha é uma regra que nasceu de dano)

**No transporte (o que o zip de hoje reabre):**
- **2026-08-24 · bundle TRUNCADO que se declarava completo.** A reserva do envelope do gerador subestimava ~60 B contra margem de +32 ⇒ `✗ parte 5 excede o cap: 262165 > 262144`, com **rc=2 depois** de já ter escrito o `bundle.manifest.json`. Quem não conferia exit code aplicava **242 de 247 arquivos** — 7 do CRM/impressão fora do espelho, e o manifesto dizendo `missing: []`. Hoje o gerador **apaga o que escreveu** ao falhar. **Regra: sem conferir exit code, "gerei o pacote" não é afirmação.**
- **2026-08-11 · STALE por transcrição.** Conteúdo que chega no contexto do agente e é reescrito à mão. Origem da ADR 0374 e de todo o desenho "bytes como DADO, nunca prosa". **Regra: eu não transcrevo arquivo, em nenhuma direção.**
- **Payload de ~3,5 MB × teto de 256 KiB do `get_file`.** Morria em `SyntaxError: Unterminated string`, que não diz nada sobre a causa. **3º caso**, pior: o **envelope** do `get_file` salvo em disco é JSON válido, escapava das duas guardas e caía no genérico "payload sem `files`" — *"a mensagem que mandou uma sessão concluir errado"*. **Regra: pacote viaja em partes; erro de transporte se declara como transporte.**
- **`bytes` ausente passava calado**, com o **mesmo texto de log** de quem foi verificado. **Regra (família LC-13): "não achei divergência" e "não procurei" nunca compartilham a mesma linha.**
- **`missing` lido só dentro do `--require-complete-shell`** ⇒ lote parcial aplicava **em silêncio** payload que se declarava incompleto.
- **Digest que ninguém reproduz:** o envelope declara FNV-64, os dois lados dizem rodar a mesma função e diverge **118 de 118**, em duas medições independentes (17/08 [CL] · 22/08 [CC]), com controle positivo de 5/5 vetores publicados. **Regra: contradição em aberto se REPORTA; não vira reprovação de transporte nem se esconde.**
- **Mesmo teste, dois vereditos por SO:** `normalize()` devolve o separador da plataforma; comparar com constante escrita em `/` reprovava **118/118 no Windows** e **0/118 no CI**.

**Na aplicação (o que apaga trabalho):**
- **Espelho À FRENTE do vivo, e sync cego apagando.** O `qa-conformance.js` ganhou os gates G14/G15 pelo PR #4597 (20/07) e o vivo seguia em v2.4/G13. O que denunciou foi a **assimetria**: os 6 syncs legítimos tinham remoção da ordem da adição (+24/−8, +84/−43, +19/−19…) e o regressivo era **+2/−171** (787→618 linhas). Daí a guarda de **perda líquida > 20 linhas** — e um teto proporcional (`<75%`) **não discrimina**: 618/787 = 78% passaria.
- **`.md` descartado por regra velha:** do pacote de 11/09, **400 pousavam e 416 eram descartados — 337 `.md`**. O `cowork-inbox/` chegava pela metade. Corrigido pelo **R3** em 13/09.
- **`git add -A` levando artefato:** **90 arquivos / 16,7 MB** de `visreg-art/` entraram no PR #6726.

**Na medição (o que faz número mentir):**
- **Busca com aspas erradas virando fato.** Busquei `from "@/Components/…"` com aspas **duplas**; o repo usa **simples** ⇒ "No matches" virou *"o Ponto não usa o DS"*, **errado em 21 arquivos**. **Regra: toda sonda, inclusive busca de código, roda caso de sanidade de valor conhecido antes do veredito.**
- **Escopo de seletor errado:** contei `role="tab"` na página inteira em vez do `nav` e devolvi "5 de 6" — quase transformei defeito real em "pendência do DS".
- **Sonda que varre a pasta errada e imprime "limpo":** o `ds-guard --all` varria a pasta do próprio script (**0 alvos**) em vez do espelho (**123**) e dizia `-- limpo`. Pego no T7 na **1ª execução**, 13/09.
- **Gate mudo com cara de cobertura:** o `--absent-local` era **medido e incapaz de reprovar** (só imprimia; o exit vinha do `shouldFail()`, que mordia só em STALE).
- **Alarme aceso que ninguém lia:** o `--sla` saía **rc=1 dentro de um step `continue-on-error`** desde 13/08 — e a defasagem do `chat-jana.jsx` (**7 semanas**) foi descoberta por tropeço numa sessão, não pelo alarme.
- **Defesa construída e desarmada:** o anti-drift ALIAS↔charter existia e **nunca rodava em CI**; o `detect-handoff` nasceu **órfão**; o `style-fingerprint` era o **único** selftest do protocolo fora do CI.
- **Predicado absoluto nascendo vermelho permanente:** pele-paralela absoluta acusaria **100% da população**; `--check-orfaos` absoluto deu **26 de 29 falsos-positivos (~90%)** por proveniência herdada. **Regra: predicado de gate é DELTA ("este PR introduz?"), não absoluto ("existe?").**
- **O que os 4 modos não olhavam:** **47 protótipos de tela vivos** no Cowork que **nunca desceram** (fiscal 5 · repair 7 · estoque 5 · configuracoes 4 · patrimonio 4 · governance 4 · venda 8 · compras 3 · produto 3 · +6) — *"nada tinha perguntado, porque os 4 modos olham só o que JÁ desceu"*.
- **Comentário do próprio workflow mentindo:** afirmava que os selftests rodavam **sem** `continue-on-error`; medido: **9 sem e 11 com**. **Regra: retrato datado se RECONTA, não se edita de memória.**
- **Token fora do denominador sem aparecer:** DTCG sem `$extensions.com.oimpresso.source` não tinha endereço canônico e **saía da conta calado** — por isso o eixo FORMA (`--schema`) nasceu antes do VALOR.
- **Migração que apaga cobertura:** o #7224 tirou `ds-guard` e `integrity-check` de `prototipo-ui/**` e **nenhuma linha os repôs** no `paths:` — **0 de 2 cobertos**, medido com controle positivo em 13/09. **O 3º caso é meu:** `memory/reference/prototipo-ui/**` segue fora do gatilho (§17 R7).
- **Âncora que prova proveniência e não correção:** **2 de 9** âncoras estavam podres (shell/fantasma) e **nenhum gate viu** — [W] pegou no instinto. Daí o `anchor-content-check`.

---

## 20 · BATERIA DE COMPORTAMENTO (B1–B8) — a §5 deixou de ser tabela e passou a ser execução

> **Por que nasceu (2026-09-14, com custo medido):** o `D-ESC-DESTROY`, decisão recém-ratificada por [W], saiu daqui marcado "✅ feito" e estava **quebrado nas duas metades** — o `Modal` do DS chamado com 4 props que **não existem** na assinatura (ignoradas em silêncio ⇒ modal **sem botão de ação**, e o handler de confirmar virou código inalcançável), e o "motivo escrito" num `title` de botão `disabled`, que o `Tooltip` do DS **nunca** mostra, porque controle desabilitado não emite hover nem recebe foco.
> **A causa não foi descuido de código — foi ausência de caso:** as 4 escalas do mock tinham colaborador vinculado, então o ramo habilitado **nunca executou**, e "console limpo" passou por prova. Pego pelo verificador, não por mim.
> **O buraco que isso expôs:** a §5 **declarava** o contrato e ninguém o **executava**. As baterias que existiam não cobrem comportamento — **T1–T7** medem forma e diff, **A1–A12** medem a11y estática. Comportamento não tinha bateria. Agora tem.

### As 8 travas

| # | trava | como se prova | o que pega |
|---|---|---|---|
| **B1** | **O caso existe** | o estado do mock em que a regra vale está **presente** e nomeado na coluna `caso que exercita` da §5 | regra aplicada em ramo morto (o defeito de 14/09) |
| **B2** | **O gatilho roda** | disparar o evento **no protótipo servido** e medir o efeito (DOM antes × depois) — nunca ler o código e concluir | "está implementado" por leitura |
| **B3** | **A assinatura foi lida** | copiar a lista de props do componente do DS **no turno**, antes de usá-la | prop inventada, ignorada em silêncio |
| **B4** | **Estado desabilitado comunica** | o motivo é **texto visível**; `title`/tooltip em `disabled` **reprova** | 4 botões mortos sem explicação |
| **B5** | **O reverso roda** | cancelar · `esc` · clicar de novo — e o estado volta | fluxo testado só no caminho feliz |
| **B6** | **Persistência conferida no reload** | recarregar e remedir a chave declarada | filtro/densidade que "persiste" só na sessão |
| **B7** | **Efeito colateral declarado** | o que mais mudou além do alvo (contador, badge de aba, lista irmã) | aprovar numa aba e o badge de outra não cair |
| **B8** | **Evento aninhado** | clicar no filho **não** dispara o pai — `stopPropagation` **provado**, não declarado | checkbox da linha abrindo o drawer |

### Forma do recibo (vai no pacote, junto do placar)

```
B <elemento> · <gatilho> · caso: <estado do mock> · antes: <medida> → depois: <medida> · reverso: <ok|n/a> · [✅|❌]
```

### Regras duras

1. **Linha da §5 sem `executada: ✅` não conta como entregue** — vira "declarada", que é outra coisa.
2. **Se o mock não tem o estado, o mock ganha o estado.** Não se testa regra em ramo inexistente, e não se declara "funciona" sem o ramo.
3. **Prop de componente do DS se LÊ na assinatura**, nunca se supõe. O bundle é compilado: prop desconhecida **não** dá erro — ela **desaparece**.
4. **"Renderizou sem erro no console" não é prova de comportamento.** É prova de que **montou**.
5. **A bateria roda no protótipo servido**, com **T1 estável** e tema declarado — as mesmas condições das outras medições.
6. **Decisão de [W] aplicada no build entra na bateria obrigatoriamente.** Foi uma delas que quebrou.

---


